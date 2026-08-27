<?php
require_once 'auth.php';
$pageTitle = 'Supplement Sales';

// ── Ensure columns exist ──────────────────────────────────────────────────────
$existingCols = [];
$colRes = $conn->query("SHOW COLUMNS FROM supplements");
if ($colRes) { while($c = $colRes->fetch_assoc()) $existingCols[] = $c['Field']; }
if (!in_array('brand', $existingCols))
    $conn->query("ALTER TABLE supplements ADD COLUMN brand VARCHAR(100) NULL AFTER name");
if (!in_array('category', $existingCols))
    $conn->query("ALTER TABLE supplements ADD COLUMN category ENUM('Protein','Pre-workout','Creatine','Vitamins','BCAAs','Weight Gainer','Fat Burner','Other') DEFAULT 'Other' AFTER brand");
if (!in_array('stock_quantity', $existingCols))
    $conn->query("ALTER TABLE supplements ADD COLUMN stock_quantity INT DEFAULT 0 AFTER category");
if (!in_array('image_data', $existingCols)) {
    $conn->query("ALTER TABLE supplements ADD COLUMN image_data LONGTEXT NULL COMMENT 'base64 encoded image'");
} else {
    $conn->query("ALTER TABLE supplements MODIFY COLUMN image_data LONGTEXT NULL COMMENT 'base64 encoded image'");
}

// ── Ensure supplement_sales table ─────────────────────────────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS supplement_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_no VARCHAR(30) UNIQUE NOT NULL,
    member_name VARCHAR(100) NOT NULL,
    user_id VARCHAR(30) NULL,
    supplement_id INT NULL,
    supplement_name VARCHAR(100) NOT NULL,
    brand VARCHAR(100) NULL,
    category VARCHAR(50) NULL,
    price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash','Card','GCash','Maya','Others') DEFAULT 'Cash',
    payment_status ENUM('Paid','Pending') DEFAULT 'Paid',
    staff_name VARCHAR(100) NULL,
    notes TEXT NULL,
    sale_date DATE NOT NULL,
    sale_time TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$successMsg = $errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Add Supplement ─────────────────────────────────────────────────────────
    if ($action === 'add_supplement') {
        $suppName  = trim($_POST['supp_name']        ?? '');
        $suppBrand = trim($_POST['supp_brand']        ?? '');
        $suppCat   = $_POST['supp_category']          ?? 'Other';
        $suppPrice = floatval($_POST['supp_price']    ?? 0);
        $suppDesc  = trim($_POST['supp_description']  ?? '');
        $suppStock = max(0, intval($_POST['supp_stock'] ?? 0));
        $imageData = null;

        if (!$suppName || $suppPrice <= 0) {
            $errorMsg = 'Supplement name and a valid price are required.';
        } else {
            if (!empty($_FILES['supp_image']['tmp_name'])) {
                $file    = $_FILES['supp_image'];
                $maxB    = 2 * 1024 * 1024;
                $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
                if ($file['size'] > $maxB) {
                    $errorMsg = 'Image must be under 2 MB.';
                } elseif (!in_array(mime_content_type($file['tmp_name']), $allowed)) {
                    $errorMsg = 'Only JPG, PNG, GIF or WebP images are allowed.';
                } else {
                    $mime      = mime_content_type($file['tmp_name']);
                    $raw       = file_get_contents($file['tmp_name']);
                    $imageData = 'data:' . $mime . ';base64,' . base64_encode($raw);
                }
            }
            if (!$errorMsg) {
                $stmt = $conn->prepare("INSERT INTO supplements (name, brand, category, stock_quantity, price, description, image_data, is_active) VALUES (?,?,?,?,?,?,?,1)");
                $stmt->bind_param("sssisss", $suppName, $suppBrand, $suppCat, $suppStock, $suppPrice, $suppDesc, $imageData);
                if ($stmt->execute()) {
                    $successMsg = "Supplement <strong>" . htmlspecialchars($suppName) . "</strong> added successfully.";
                } else {
                    $errorMsg = 'Failed to add supplement: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }

    // ── Record Sale (standard form POST fallback) ───────────────────────────────
    if ($action === 'record_sale') {
        $memberName    = trim($_POST['member_name']   ?? '');
        $suppId        = intval($_POST['supplement_id'] ?? 0);
        $price         = floatval($_POST['price']     ?? 0);
        $qty           = max(1, intval($_POST['quantity'] ?? 1));
        $paymentMethod = $_POST['payment_method']     ?? 'Cash';
        $saleDate      = date('Y-m-d');
        $saleTime      = date('H:i:s');
        $staffName     = $_SESSION['admin_name'] ?? '';

        if (!$memberName || !$suppId || $price <= 0) {
            $errorMsg = 'Please fill all required fields.';
        } else {
            $sr = $conn->query("SELECT * FROM supplements WHERE id = $suppId")->fetch_assoc();
            if (!$sr) {
                $errorMsg = 'Supplement not found.';
            } elseif ((int)$sr['stock_quantity'] < $qty) {
                $errorMsg = "Insufficient stock. Only " . (int)$sr['stock_quantity'] . " unit(s) available.";
            } else {
                $total     = $price * $qty;
                $receiptNo = 'RCP-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 5));
                $stmt = $conn->prepare("INSERT INTO supplement_sales (receipt_no, member_name, supplement_id, supplement_name, brand, category, price, quantity, total_amount, payment_method, payment_status, staff_name, sale_date, sale_time) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                $paid = 'Paid';
                $stmt->bind_param("ssisssdidsssss",
                    $receiptNo, $memberName, $suppId, $sr['name'],
                    $sr['brand'], $sr['category'], $price, $qty, $total,
                    $paymentMethod, $paid, $staffName, $saleDate, $saleTime
                );
                if ($stmt->execute()) {
                    $conn->query("UPDATE supplements SET stock_quantity = GREATEST(0, stock_quantity - $qty) WHERE id = $suppId");
                    $successMsg = "Sale recorded! Receipt: <strong>$receiptNo</strong> — <strong>" . htmlspecialchars($sr['name']) . "</strong> x{$qty} for <strong>₱" . number_format($total, 2) . "</strong>.";
                } else {
                    $errorMsg = 'Failed: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }

    // ── Delete Sale ──────────────────────────────────────────────────────────────
    if ($action === 'delete_sale' && in_array($_SESSION['admin_role'], ['superadmin','admin'])) {
        $saleId = intval($_POST['sale_id'] ?? 0);
        if ($saleId) {
            $sale = $conn->query("SELECT supplement_id, quantity FROM supplement_sales WHERE id = $saleId")->fetch_assoc();
            if ($sale && $sale['supplement_id'])
                $conn->query("UPDATE supplements SET stock_quantity = stock_quantity + {$sale['quantity']} WHERE id = {$sale['supplement_id']}");
            $conn->query("DELETE FROM supplement_sales WHERE id = $saleId");
            $successMsg = "Sale deleted and stock restored.";
        }
    }

    // ── Restock ──────────────────────────────────────────────────────────────────
    if ($action === 'restock') {
        $suppId = intval($_POST['restock_id'] ?? 0);
        $addQty = intval($_POST['restock_qty'] ?? 0);
        if ($suppId && $addQty > 0) {
            $conn->query("UPDATE supplements SET stock_quantity = stock_quantity + $addQty WHERE id = $suppId");
            $successMsg = "Stock updated successfully.";
        }
    }
}

// ── Queries ────────────────────────────────────────────────────────────────────
$fromDate     = (isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'])) ? $_GET['from'] : date('Y-m-d');
$toDate       = (isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']))   ? $_GET['to']   : date('Y-m-d');
$sales        = $conn->query("SELECT * FROM supplement_sales WHERE sale_date BETWEEN '$fromDate' AND '$toDate' ORDER BY created_at DESC");
$totalRevenue = $conn->query("SELECT COALESCE(SUM(total_amount),0) as rev FROM supplement_sales WHERE sale_date BETWEEN '$fromDate' AND '$toDate' AND payment_status='Paid'")->fetch_assoc()['rev'] ?? 0;
$totalTx      = $conn->query("SELECT COUNT(*) as c FROM supplement_sales WHERE sale_date BETWEEN '$fromDate' AND '$toDate'")->fetch_assoc()['c'] ?? 0;
$todaySuppRev = $conn->query("SELECT COALESCE(SUM(total_amount),0) as rev FROM supplement_sales WHERE sale_date = CURDATE() AND payment_status='Paid'")->fetch_assoc()['rev'] ?? 0;
$supplements  = $conn->query("SELECT * FROM supplements WHERE is_active = 1 ORDER BY name ASC");

// Build supplement catalog array for JS
$suppCatalog = [];
$catRes = $conn->query("SELECT id, name, brand, category, price, stock_quantity, description, image_data FROM supplements WHERE is_active=1 ORDER BY name ASC");
if ($catRes) {
    while ($sc = $catRes->fetch_assoc()) {
        $suppCatalog[(int)$sc['id']] = [
            'id'          => (int)$sc['id'],
            'name'        => $sc['name'],
            'brand'       => $sc['brand'] ?? '',
            'category'    => $sc['category'] ?? 'Other',
            'price'       => (float)$sc['price'],
            'stock'       => (int)($sc['stock_quantity'] ?? 0),
            'description' => $sc['description'] ?? '',
            'image'       => $sc['image_data'] ?? '',
        ];
    }
}

// Build sales export data
$exportRows = [];
if ($sales && $sales->num_rows > 0) {
    $sales->data_seek(0);
    while ($s = $sales->fetch_assoc()) {
        $exportRows[] = [
            'receipt'     => $s['receipt_no'],
            'customer'    => $s['member_name'],
            'supplement'  => $s['supplement_name'],
            'category'    => $s['category'] ?? '',
            'qty'         => (int)$s['quantity'],
            'price'       => '₱' . number_format($s['price'], 2),
            'total'       => '₱' . number_format($s['total_amount'], 2),
            'payment'     => $s['payment_method'],
            'date'        => date('F d, Y', strtotime($s['sale_date'])),
            'time'        => date('h:i A', strtotime($s['sale_time'])),
        ];
    }
}

$jsPDFSrc     = file_exists(__DIR__.'/assets/js/jspdf.umd.min.js')              ? 'assets/js/jspdf.umd.min.js'              : 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
$autoTableSrc = file_exists(__DIR__.'/assets/js/jspdf.plugin.autotable.min.js') ? 'assets/js/jspdf.plugin.autotable.min.js' : 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.6.0/jspdf.plugin.autotable.min.js';

include 'header.php';
?>

<style>
/* ── Category badges ─────────────────────────────────────────── */
.supp-badge { display:inline-block;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700; }
.supp-badge.Protein       { background:rgba(30,120,255,.12);color:#1e78ff; }
.supp-badge.Pre-workout   { background:rgba(239,68,68,.12);color:#ef4444; }
.supp-badge.Creatine      { background:rgba(139,92,246,.12);color:#8b5cf6; }
.supp-badge.Vitamins      { background:rgba(16,185,129,.12);color:#10b981; }
.supp-badge.BCAAs         { background:rgba(20,184,166,.12);color:#14b8a6; }
.supp-badge.Weight-Gainer { background:rgba(245,158,11,.12);color:#f59e0b; }
.supp-badge.Fat-Burner    { background:rgba(249,115,22,.12);color:#f97316; }
.supp-badge.Other         { background:rgba(107,122,153,.12);color:#6b7a99; }

/* ── Product Grid ─────────────────────────────────────────────── */
.product-grid {
  display:grid;
  grid-template-columns:repeat(auto-fill, minmax(155px, 1fr));
  gap:16px; padding:20px;
}
.product-card {
  background:linear-gradient(160deg,#1a1a2e 0%,#16213e 60%,#0f3460 100%);
  border:2px solid rgba(255,255,255,.08);
  border-radius:16px; overflow:hidden; cursor:pointer;
  transition:all .25s; position:relative;
  box-shadow:0 4px 15px rgba(0,0,0,.3);
}
.product-card:hover:not(.out-of-stock) {
  transform:translateY(-5px) scale(1.02);
  border-color:#f59e0b;
  box-shadow:0 12px 35px rgba(245,158,11,.4);
}
.product-card.out-of-stock { opacity:.5; cursor:not-allowed; filter:grayscale(.5); }

/* number badge */
.product-num {
  position:absolute;top:9px;left:9px;
  width:24px;height:24px;border-radius:50%;
  background:linear-gradient(135deg,#f59e0b,#d97706);
  color:#fff;font-size:11px;font-weight:800;
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 2px 8px rgba(245,158,11,.5);z-index:2;
}
/* image area */
.product-img-wrap {
  width:100%;height:130px;overflow:hidden;
  display:flex;align-items:center;justify-content:center;
  background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.01));
  border-bottom:1px solid rgba(255,255,255,.06);
}
.product-img-wrap img {
  width:100%;height:100%;object-fit:contain;padding:8px;
  filter:drop-shadow(0 4px 8px rgba(0,0,0,.4));
  transition:transform .3s;
}
.product-card:hover:not(.out-of-stock) .product-img-wrap img { transform:scale(1.08); }
.product-img-wrap .no-img { font-size:42px;color:rgba(255,255,255,.12); }
/* info area */
.product-info { padding:10px 10px 13px; }
.product-name {
  font-size:12px;font-weight:800;color:#fff;line-height:1.3;
  margin-bottom:2px;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
}
.product-brand { font-size:10px;color:rgba(255,255,255,.4);margin-bottom:4px; }
.product-price { font-size:15px;font-weight:800;color:#f59e0b; }
.product-stock-pill {
  display:inline-block;margin-top:5px;
  font-size:9px;font-weight:700;padding:2px 8px;border-radius:20px;
  text-transform:uppercase;letter-spacing:.5px;
}
.psp-ok   { background:rgba(16,185,129,.2);color:#34d399;border:1px solid rgba(16,185,129,.3); }
.psp-warn { background:rgba(245,158,11,.2);color:#fbbf24;border:1px solid rgba(245,158,11,.3); }
.psp-out  { background:rgba(239,68,68,.2);color:#f87171;border:1px solid rgba(239,68,68,.3); }
.product-hint { font-size:9px;color:rgba(255,255,255,.25);margin-top:4px; }

/* ── Purchase Modal ─────────────────────────────────────────── */
#purchaseModal .modal-content {
  background:linear-gradient(160deg,#1a1a2e 0%,#16213e 55%,#0f3460 100%);
  border:1.5px solid rgba(245,158,11,.35); color:#fff; border-radius:18px;
}
#purchaseModal .modal-header {
  border-bottom:1px solid rgba(255,255,255,.1);
  background:rgba(245,158,11,.07); border-radius:16px 16px 0 0;
}
#purchaseModal .modal-title { color:#f59e0b; font-family:'Barlow Condensed',sans-serif; font-size:20px; font-weight:800; }
#purchaseModal .btn-close { filter:invert(1) opacity(.6); }
#purchaseModal .modal-footer { border-top:1px solid rgba(255,255,255,.1); }

/* modal image */
.pm-img-wrap {
  width:100%;height:200px;border-radius:14px;overflow:hidden;
  background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);
  display:flex;align-items:center;justify-content:center;margin-bottom:16px;
}
.pm-img-wrap img { max-width:100%;max-height:100%;object-fit:contain;padding:14px; }
.pm-img-wrap .pm-no-img { font-size:56px;color:rgba(255,255,255,.08); }

/* info row */
.pm-row { display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.07);font-size:13px; }
.pm-row:last-of-type { border-bottom:none; }
.pm-label { color:rgba(255,255,255,.45);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px; }
.pm-val   { color:#fff;font-weight:600;text-align:right; }
.pm-price-big { font-size:26px;font-weight:800;color:#f59e0b;text-align:center;margin-bottom:14px;text-shadow:0 2px 8px rgba(245,158,11,.35); }
.pm-total-wrap {
  background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);
  border-radius:12px;padding:12px 16px;text-align:center;margin:14px 0;
}
.pm-total-label { font-size:11px;color:rgba(255,255,255,.4);font-weight:700;text-transform:uppercase;letter-spacing:.8px; }
.pm-total-amt   { font-size:28px;font-weight:800;color:#34d399;letter-spacing:-0.5px; }

/* qty selector */
.qty-wrap { display:flex;align-items:center;justify-content:center;gap:0;border:1.5px solid rgba(255,255,255,.15);border-radius:12px;overflow:hidden;width:160px;margin:0 auto; }
.qty-btn {
  width:44px;height:44px;border:none;cursor:pointer;font-size:20px;font-weight:700;
  background:rgba(255,255,255,.07);color:#fff;
  transition:background .15s;display:flex;align-items:center;justify-content:center;
  flex-shrink:0;
}
.qty-btn:hover:not(:disabled) { background:rgba(245,158,11,.25);color:#f59e0b; }
.qty-btn:disabled { opacity:.3;cursor:not-allowed; }
.qty-display {
  flex:1;text-align:center;font-size:20px;font-weight:800;color:#fff;
  padding:0 8px;background:rgba(255,255,255,.04);min-width:60px;line-height:44px;
}

/* customer name input */
.pm-input {
  width:100%;background:rgba(255,255,255,.07);border:1.5px solid rgba(255,255,255,.15);
  border-radius:10px;padding:11px 14px;color:#fff;font-size:14px;font-family:inherit;
  outline:none;transition:border-color .2s;
}
.pm-input::placeholder { color:rgba(255,255,255,.3); }
.pm-input:focus { border-color:#f59e0b;background:rgba(255,255,255,.09); }

/* checkout button */
.btn-checkout {
  width:100%;background:linear-gradient(135deg,#f59e0b,#d97706);
  border:none;color:#fff;font-weight:800;font-size:15px;
  padding:13px;border-radius:12px;cursor:pointer;
  transition:all .2s;box-shadow:0 4px 16px rgba(245,158,11,.4);
  display:flex;align-items:center;justify-content:center;gap:8px;
}
.btn-checkout:hover:not(:disabled) { transform:translateY(-2px);box-shadow:0 8px 24px rgba(245,158,11,.5); }
.btn-checkout:disabled { background:rgba(245,158,11,.3);cursor:not-allowed;transform:none;box-shadow:none; }

/* payment method — cash only badge */
.cash-badge { display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.06);border:1.5px solid rgba(245,158,11,.3);border-radius:10px;padding:10px 14px; }

/* success flash on card after purchase */
@keyframes cardSuccess { 0%{border-color:#34d399;box-shadow:0 0 0 4px rgba(52,211,153,.3);} 100%{border-color:rgba(255,255,255,.08);box-shadow:0 4px 15px rgba(0,0,0,.3);} }
.card-purchased { animation:cardSuccess 1.5s ease forwards; }


/* image upload */
.img-upload-wrap { border:2px dashed var(--border);border-radius:12px;padding:24px;text-align:center;cursor:pointer;transition:border-color .2s;background:#fafbff; }
.img-upload-wrap:hover { border-color:var(--primary); }
.img-preview { max-width:100%;max-height:160px;border-radius:10px;display:none;margin:10px auto 0; }

.receipt-code { background:#f8faff;border:1px solid #e8ecf4;padding:2px 8px;border-radius:6px;font-size:11px;font-family:monospace; }

@media(max-width:576px) { .product-grid { grid-template-columns:repeat(2,1fr);gap:10px;padding:12px; } }
@media(max-width:640px) { #purchaseModal .modal-body > div[style*='grid'] { grid-template-columns:1fr !important; } #purchaseModal .modal-dialog { max-width:95% !important; } }
@media print {
  .sidebar,.top-navbar,.page-footer,.btn-outline-custom,.section-card-header .d-flex,
  .section-card:not(:last-child) { display:none!important; }
  .main-wrapper { margin-left:0!important; }
  body { background:white; }
  .section-card { box-shadow:none;border:1px solid #ddd; }
  .table { font-size:10px; }
  #printHeader { display:block!important; }
  .page-content { padding:0; }
}
#printHeader { display:none;margin-bottom:20px; }
</style>

<!-- ── Page Header ────────────────────────────────────────────── -->
<div class="page-header mb-4">
  <div>
    <h4 class="fw-800 mb-1" style="font-family:'Barlow Condensed',sans-serif;font-size:26px;font-weight:800;">Supplement Sales</h4>
    <p class="text-muted mb-0" style="font-size:13px;">Click any product to purchase</p>
  </div>
  <div class="d-flex gap-2 flex-wrap align-items-center">
    <div class="stat-card py-2 px-3" style="min-width:0;text-align:center;">
      <div class="card-label" style="font-size:11px;">Today's Revenue</div>
      <div class="card-value" style="font-size:18px;color:var(--success);">₱<?= number_format($todaySuppRev,2) ?></div>
    </div>
    <div class="stat-card py-2 px-3" style="min-width:0;text-align:center;">
      <div class="card-label" style="font-size:11px;">Period Revenue</div>
      <div class="card-value" style="font-size:18px;color:#f59e0b;">₱<?= number_format($totalRevenue,2) ?></div>
    </div>
    <div class="stat-card py-2 px-3" style="min-width:0;text-align:center;">
      <div class="card-label" style="font-size:11px;">Transactions</div>
      <div class="card-value" style="font-size:18px;"><?= $totalTx ?></div>
    </div>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addSuppModal">
      <i class="fas fa-plus me-1"></i> Add Supplement
    </button>
  </div>
</div>

<?php if ($successMsg): ?>
<div class="alert" style="background:#f0fff8;border:1px solid #a7f3d0;border-radius:12px;padding:14px 18px;color:#065f46;font-size:14px;margin-bottom:20px;">
  <i class="fas fa-check-circle me-2"></i><?= $successMsg ?>
</div>
<?php endif; ?>
<?php if ($errorMsg): ?>
<div class="alert" style="background:#fff0f0;border:1px solid #fca5a5;border-radius:12px;padding:14px 18px;color:#991b1b;font-size:14px;margin-bottom:20px;">
  <i class="fas fa-exclamation-circle me-2"></i><?= $errorMsg ?>
</div>
<?php endif; ?>

<!-- ── PRODUCT CATALOG ─────────────────────────────────────────── -->
<div class="section-card mb-4" style="background:linear-gradient(160deg,#1a1a2e,#16213e,#0f3460);border-color:rgba(245,158,11,.25);">
  <div class="section-card-header" style="border-bottom:1px solid rgba(255,255,255,.08);background:rgba(245,158,11,.06);">
    <span class="section-card-title" style="color:#f59e0b;"><i class="fas fa-store me-2"></i>Supplement Catalog</span>
    <span style="font-size:12px;color:rgba(255,255,255,.4);"><i class="fas fa-hand-pointer me-1"></i>Click a product to purchase</span>
  </div>

  <?php $supplements->data_seek(0); ?>
  <?php if ($supplements->num_rows > 0): ?>
  <div class="product-grid" id="productGrid">
    <?php $idx = 1; $supplements->data_seek(0); while($s = $supplements->fetch_assoc()):
      $stock      = (int)($s['stock_quantity'] ?? 0);
      $outOfStock = ($stock === 0);
      $stockPill  = $stock === 0 ? 'psp-out' : ($stock <= 5 ? 'psp-warn' : 'psp-ok');
      $stockLabel = $stock === 0 ? 'Out of stock' : ($stock <= 5 ? $stock.' left' : $stock.' in stock');
    ?>
    <div class="product-card<?= $outOfStock ? ' out-of-stock' : '' ?>"
         id="pc_<?= $s['id'] ?>"
         <?= !$outOfStock ? "onclick=\"openPurchase({$s['id']})\"" : '' ?>>
      <div class="product-num"><?= $idx++ ?></div>
      <div class="product-img-wrap">
        <?php if (!empty($s['image_data'])): ?>
        <img src="<?= $s['image_data'] ?>" alt="<?= htmlspecialchars($s['name']) ?>" loading="lazy" id="pimg_<?= $s['id'] ?>">
        <?php else: ?>
        <span class="no-img"><i class="fas fa-flask"></i></span>
        <?php endif; ?>
      </div>
      <div class="product-info">
        <div class="product-name"><?= htmlspecialchars($s['name']) ?></div>
        <?php if (!empty($s['brand'])): ?>
        <div class="product-brand"><?= htmlspecialchars($s['brand']) ?></div>
        <?php endif; ?>
        <div class="product-price">₱<?= number_format($s['price'],2) ?></div>
        <div><span class="product-stock-pill <?= $stockPill ?>" id="stockpill_<?= $s['id'] ?>"><?= $stockLabel ?></span></div>
        <?php if (!$outOfStock): ?>
        <div class="product-hint"><i class="fas fa-shopping-cart"></i> tap to buy</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endwhile; ?>
  </div>
  <?php else: ?>
  <div style="padding:40px;text-align:center;color:rgba(255,255,255,.3);">
    <i class="fas fa-box-open" style="font-size:36px;margin-bottom:12px;display:block;"></i>
    <div style="font-size:14px;">No supplements yet.</div>
    <div style="font-size:12px;margin-top:4px;">Click <strong style="color:#f59e0b;">"Add Supplement"</strong> to get started.</div>
  </div>
  <?php endif; ?>
</div>


<!-- ── SALES HISTORY ───────────────────────────────────────────── -->
<div class="section-card">
  <div class="section-card-header">
    <span class="section-card-title"><i class="fas fa-history me-2" style="color:var(--text-muted)"></i>Sales History</span>
    <div class="d-flex gap-2 flex-wrap">
      <button class="btn-outline-custom" onclick="exportSalesPDF()" style="padding:7px 14px;font-size:12px;"><i class="fas fa-file-pdf me-1"></i>PDF</button>
      <button class="btn-outline-custom" onclick="exportSalesCSV()" style="padding:7px 14px;font-size:12px;"><i class="fas fa-file-csv me-1"></i>CSV</button>
      <button class="btn-outline-custom" onclick="window.print()" style="padding:7px 14px;font-size:12px;"><i class="fas fa-print me-1"></i>Print</button>
    </div>
  </div>
  <div style="padding:14px 18px;border-bottom:1px solid var(--border);background:#fafbff;">
    <form method="GET" class="d-flex flex-wrap gap-3 align-items-end">
      <div><label class="form-label" style="font-size:12px;">From</label>
        <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($fromDate) ?>" style="width:150px;"></div>
      <div><label class="form-label" style="font-size:12px;">To</label>
        <input type="date" name="to"   class="form-control form-control-sm" value="<?= htmlspecialchars($toDate) ?>"   style="width:150px;"></div>
      <button type="submit" class="btn-primary-custom" style="padding:8px 16px;font-size:13px;"><i class="fas fa-filter me-1"></i>Filter</button>
      <a href="supplement_sales.php" class="btn-outline-custom" style="padding:8px 16px;font-size:13px;">Reset</a>
      <div style="margin-left:auto;font-size:13px;color:var(--text-muted);align-self:center;">
        Total: <strong style="color:#10b981;">₱<?= number_format($totalRevenue,2) ?></strong>
        &nbsp;·&nbsp; <?= $totalTx ?> transaction<?= $totalTx!=1?'s':'' ?>
      </div>
    </form>
  </div>
  <div class="section-card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0" id="salesTable">
        <thead>
          <tr><th>Receipt</th><th>Customer</th><th>Supplement</th><th>Qty</th><th>Price</th><th>Total</th><th>Payment</th><th>Date &amp; Time</th><th>Action</th></tr>
        </thead>
        <tbody id="salesTbody">
          <?php if ($sales && $sales->num_rows > 0): $sales->data_seek(0); while($s = $sales->fetch_assoc()): ?>
          <tr>
            <td><span class="receipt-code"><?= htmlspecialchars($s['receipt_no']) ?></span></td>
            <td><strong style="font-size:13px;"><?= htmlspecialchars($s['member_name']) ?></strong></td>
            <td><strong><?= htmlspecialchars($s['supplement_name']) ?></strong>
              <?php $cat = str_replace(' ','-',$s['category']??'Other'); ?>
              <br><span class="supp-badge <?= $cat ?>" style="font-size:9px;"><?= htmlspecialchars($s['category']??'') ?></span>
            </td>
            <td style="font-weight:600;"><?= (int)$s['quantity'] ?></td>
            <td>₱<?= number_format($s['price'],2) ?></td>
            <td><strong style="color:#10b981;">₱<?= number_format($s['total_amount'],2) ?></strong></td>
            <td style="font-size:12px;font-weight:600;"><?= htmlspecialchars($s['payment_method']) ?></td>
            <td style="font-size:12px;white-space:nowrap;"><?= date('M d, Y', strtotime($s['sale_date'])) ?><br><span style="color:var(--text-muted);"><?= date('h:i A', strtotime($s['sale_time'])) ?></span></td>
            <td>
              <?php if (in_array($_SESSION['admin_role'],['superadmin','admin'])): ?>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this sale? Stock will be restored.')">
                <input type="hidden" name="action" value="delete_sale">
                <input type="hidden" name="sale_id" value="<?= $s['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" style="font-size:11px;"><i class="fas fa-trash"></i></button>
              </form>
              <?php else: ?><span style="font-size:12px;color:var(--text-muted);">—</span><?php endif; ?>
            </td>
          </tr>
          <?php endwhile; else: ?>
          <tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>No sales records for this period</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── PURCHASE MODAL ─────────────────────────────────────────── -->
<div class="modal fade" id="purchaseModal" tabindex="-1" data-bs-backdrop="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:700px;width:95%;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-shopping-cart me-2"></i><span id="pmTitle">Purchase</span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:24px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">

          <!-- LEFT: Product image + info ─────────────────────── -->
          <div>
            <div class="pm-img-wrap" id="pmImgWrap" style="height:240px;margin-bottom:16px;">
              <span class="pm-no-img"><i class="fas fa-flask"></i></span>
            </div>
            <!-- Info rows -->
            <div id="pmInfoRows"></div>
          </div>

          <!-- RIGHT: Purchase controls ──────────────────────── -->
          <div style="display:flex;flex-direction:column;gap:16px;">
            <!-- Unit price -->
            <div style="text-align:center;">
              <div style="font-size:11px;font-weight:700;color:rgba(255,255,255,.4);text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px;">Unit Price</div>
              <div class="pm-price-big" id="pmUnitPrice" style="margin-bottom:0;">₱0.00</div>
            </div>
            <!-- Customer name -->
            <div>
              <label style="font-size:11px;font-weight:700;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:6px;">Customer Name *</label>
              <input type="text" id="pmCustomerName" class="pm-input" placeholder="Enter customer name…" autocomplete="off">
            </div>
            <!-- Payment method — Cash only -->
            <div>
              <label style="font-size:11px;font-weight:700;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:6px;">Payment Method</label>
              <div style="display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.06);border:1.5px solid rgba(245,158,11,.3);border-radius:10px;padding:10px 14px;">
                <div style="width:34px;height:34px;background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                  <i class="fas fa-money-bill-wave" style="color:#fff;font-size:15px;"></i>
                </div>
                <div>
                  <div style="font-size:14px;font-weight:800;color:#f59e0b;">Cash</div>
                  <div style="font-size:10px;color:rgba(255,255,255,.35);">Payment upon purchase</div>
                </div>
                <i class="fas fa-check-circle" style="color:#34d399;font-size:16px;margin-left:auto;"></i>
              </div>
            </div>
            <!-- Quantity selector -->
            <div>
              <label style="font-size:11px;font-weight:700;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.8px;display:block;margin-bottom:8px;text-align:center;">Quantity</label>
              <div class="qty-wrap" style="width:100%;">
                <button class="qty-btn" id="btnQtyMinus" onclick="changeQty(-1)">−</button>
                <div class="qty-display" id="pmQtyDisplay">1</div>
                <button class="qty-btn" id="btnQtyPlus"  onclick="changeQty(1)">+</button>
              </div>
              <div id="pmStockNote" style="text-align:center;font-size:11px;color:rgba(255,255,255,.35);margin-top:6px;"></div>
            </div>
            <!-- Total -->
            <div class="pm-total-wrap" style="margin:0;">
              <div class="pm-total-label">Total Amount</div>
              <div class="pm-total-amt" id="pmTotalAmt">₱0.00</div>
            </div>
          </div>

        </div><!-- end grid -->
      </div>
      <div class="modal-footer" style="padding:14px 20px;gap:10px;">
        <button type="button" class="btn-outline-custom" data-bs-dismiss="modal" style="color:rgba(255,255,255,.5);border-color:rgba(255,255,255,.2);">Cancel</button>
        <button type="button" class="btn-checkout" id="btnCheckout" onclick="doCheckout()">
          <i class="fas fa-check-circle"></i> <span id="checkoutBtnText">Confirm Purchase</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── ADD SUPPLEMENT MODAL ───────────────────────────────────── -->
<div class="modal fade" id="addSuppModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-flask me-2 text-primary"></i>Add New Supplement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" enctype="multipart/form-data" id="addSuppForm">
        <input type="hidden" name="action" value="add_supplement">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Name <span class="text-danger">*</span></label>
              <input type="text" name="supp_name" class="form-control" placeholder="e.g. Whey Protein Gold" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Brand</label>
              <input type="text" name="supp_brand" class="form-control" placeholder="e.g. Optimum Nutrition">
            </div>
            <div class="col-md-4">
              <label class="form-label">Category</label>
              <select name="supp_category" class="form-select">
                <option value="Protein">Protein</option>
                <option value="Pre-workout">Pre-workout</option>
                <option value="Creatine">Creatine</option>
                <option value="Vitamins">Vitamins</option>
                <option value="BCAAs">BCAAs</option>
                <option value="Weight Gainer">Weight Gainer</option>
                <option value="Fat Burner">Fat Burner</option>
                <option value="Other" selected>Other</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Price (₱) <span class="text-danger">*</span></label>
              <input type="number" name="supp_price" class="form-control" placeholder="0.00" min="0.01" step="0.01" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Initial Stock</label>
              <input type="number" name="supp_stock" class="form-control" value="0" min="0">
            </div>
            <div class="col-12">
              <label class="form-label">Description <small class="text-muted">(optional)</small></label>
              <textarea name="supp_description" class="form-control" rows="2" placeholder="e.g. 24g protein per serving…"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Product Image <small class="text-muted">(optional — max 2 MB)</small></label>
              <div class="img-upload-wrap" onclick="document.getElementById('suppImageInput').click()">
                <i class="fas fa-cloud-upload-alt" style="font-size:24px;color:#c0c9d9;margin-bottom:8px;display:block;"></i>
                <div style="font-size:13px;color:var(--text-muted);">Click to upload</div>
                <div style="font-size:11px;color:#adb5bd;margin-top:4px;">JPG, PNG, GIF or WebP</div>
                <img id="imgPreview" class="img-preview" alt="Preview">
              </div>
              <input type="file" name="supp_image" id="suppImageInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;" onchange="previewImage(this)">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-outline-custom" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" id="addSuppBtn" class="btn-primary-custom">
            <i class="fas fa-plus me-1"></i><span id="addSuppBtnText">Add Supplement</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Print header -->
<div id="printHeader">
  <h3 style="font-family:'Barlow Condensed',sans-serif;font-weight:800;">Diozabeth Fitness — Supplement Sales</h3>
  <p>Period: <?= date('M d, Y',strtotime($fromDate)) ?> – <?= date('M d, Y',strtotime($toDate)) ?> | Generated: <?= date('M d, Y h:i A') ?></p><hr>
</div>

<?php
// JSON encode catalog and export rows for JS — no single-quote issues
$suppCatalogJson = json_encode($suppCatalog, JSON_HEX_APOS | JSON_HEX_QUOT);
$salesDataJson   = json_encode($exportRows,  JSON_HEX_APOS | JSON_HEX_QUOT);
$jsPDF_tag       = '<script src="' . $jsPDFSrc     . '"></script>';
$autoTable_tag   = '<script src="' . $autoTableSrc . '"></script>';
?>

<?= $jsPDF_tag ?>
<?= $autoTable_tag ?>
<script>
// ── Catalog & state ───────────────────────────────────────────
var CATALOG     = <?= $suppCatalogJson ?>;
var salesData   = <?= $salesDataJson ?>;
var pmSuppId    = null;
var pmQty       = 1;
var pmMaxStock  = 0;
var pmUnitPrice = 0;
var checkoutBusy = false;

// ── DataTables init ───────────────────────────────────────────
$(document).ready(function() {
  $('#salesTable').DataTable({
    responsive:true, order:[[7,'desc']], pageLength:25,
    lengthMenu:[[10,25,50,100,-1],['10','25','50','100','All']],
    language:{emptyTable:'No sales records found'},
    columnDefs:[{orderable:false,targets:8}]
  });

});

// ── Open purchase modal ───────────────────────────────────────
function openPurchase(id) {
  var s = CATALOG[id];
  if (!s || s.stock <= 0) return;

  pmSuppId    = id;
  pmQty       = 1;
  pmMaxStock  = s.stock;
  pmUnitPrice = s.price;
  pmPayMethod = 'Cash';
  checkoutBusy = false;

  // Title
  document.getElementById('pmTitle').textContent = s.name;

  // Image — pull from the rendered card img tag
  var imgWrap = document.getElementById('pmImgWrap');
  var cardImg = document.getElementById('pimg_' + id);
  if (cardImg && cardImg.src && cardImg.src.length > 50) {
    imgWrap.innerHTML = '<img src="' + cardImg.src + '" alt="' + escHtml(s.name) + '">';
  } else {
    imgWrap.innerHTML = '<span class="pm-no-img"><i class="fas fa-flask"></i></span>';
  }

  // Unit price
  document.getElementById('pmUnitPrice').textContent = '₱' + fmtMoney(s.price);

  // Info rows
  var catColors = {
    'Protein':'#60a5fa','Pre-workout':'#f87171','Creatine':'#c084fc',
    'Vitamins':'#34d399','BCAAs':'#2dd4bf','Weight Gainer':'#fbbf24',
    'Fat Burner':'#fb923c','Other':'#9ca3af'
  };
  var cc = catColors[s.category] || '#9ca3af';
  var rows = '';
  if (s.brand) rows += makeRow('Brand', escHtml(s.brand));
  rows += makeRow('Category', '<span style="color:'+cc+';font-weight:700;">'+escHtml(s.category)+'</span>');
  rows += makeRow('Stock', '<span style="color:'+stockColor(s.stock)+';font-weight:700;">'+s.stock+' unit'+( s.stock!=1?'s':'')+' available</span>');
  if (s.description) rows += '<div class="pm-row" style="flex-direction:column;align-items:flex-start;gap:4px;"><span class="pm-label">Description</span><span style="color:rgba(255,255,255,.65);font-size:12px;line-height:1.5;">'+escHtml(s.description)+'</span></div>';
  document.getElementById('pmInfoRows').innerHTML = rows;

  // Reset qty display
  document.getElementById('pmQtyDisplay').textContent = '1';
  document.getElementById('pmStockNote').textContent  = 'Max: ' + s.stock + ' unit' + (s.stock!=1?'s':'');
  updateQtyBtns();
  updateTotal();

  // Reset customer name
  document.getElementById('pmCustomerName').value = '';

  // Reset checkout button
  var btn = document.getElementById('btnCheckout');
  btn.disabled = false;
  document.getElementById('checkoutBtnText').textContent = 'Confirm Purchase';
  btn.style.opacity = '1';

  new bootstrap.Modal(document.getElementById('purchaseModal')).show();
  setTimeout(function(){ document.getElementById('pmCustomerName').focus(); }, 400);
}

// ── Payment method — always Cash ─────────────────────────────
var pmPayMethod = 'Cash'; // fixed — Cash only

// ── Quantity controls ─────────────────────────────────────────
function changeQty(delta) {
  var newQty = pmQty + delta;
  if (newQty < 1 || newQty > pmMaxStock) return;
  pmQty = newQty;
  document.getElementById('pmQtyDisplay').textContent = pmQty;
  updateQtyBtns();
  updateTotal();
}

function updateQtyBtns() {
  document.getElementById('btnQtyMinus').disabled = (pmQty <= 1);
  document.getElementById('btnQtyPlus').disabled  = (pmQty >= pmMaxStock);
}

function updateTotal() {
  var total = pmUnitPrice * pmQty;
  document.getElementById('pmTotalAmt').textContent = '₱' + fmtMoney(total);
}

// ── Checkout ──────────────────────────────────────────────────
function doCheckout() {
  if (checkoutBusy) return;

  var customerName = document.getElementById('pmCustomerName').value.trim();
  if (!customerName) {
    document.getElementById('pmCustomerName').focus();
    document.getElementById('pmCustomerName').style.borderColor = '#f87171';
    setTimeout(function(){ document.getElementById('pmCustomerName').style.borderColor = ''; }, 2000);
    return;
  }

  checkoutBusy = true;
  var btn  = document.getElementById('btnCheckout');
  var btxt = document.getElementById('checkoutBtnText');
  btn.disabled    = true;
  btn.style.opacity = '0.7';
  btxt.textContent  = 'Processing…';

  var fd = new FormData();
  fd.append('action',        'record_sale');
  fd.append('member_name',   customerName);
  fd.append('supplement_id', pmSuppId);
  fd.append('price',         pmUnitPrice);
  fd.append('quantity',      pmQty);
  fd.append('payment_method',pmPayMethod);
  fd.append('sale_date',     new Date().toISOString().split('T')[0]);

  fetch(window.location.href, { method:'POST', body:fd, credentials:'same-origin' })
    .then(function(r){ return r.text(); })
    .then(function(html) {
      // Parse the response page for success/error alert
      var parser  = new DOMParser();
      var doc     = parser.parseFromString(html, 'text/html');
      var success = doc.querySelector('.alert[style*="f0fff8"]');
      var error   = doc.querySelector('.alert[style*="fff0f0"]');

      if (success) {
        // Close modal
        bootstrap.Modal.getInstance(document.getElementById('purchaseModal')).hide();

        // Update catalog stock in memory
        var s = CATALOG[pmSuppId];
        if (s) {
          s.stock -= pmQty;
          if (s.stock < 0) s.stock = 0;
        }

        // Update product card
        updateCardStock(pmSuppId, s ? s.stock : 0);


        // Add new row to top of sales table
        addSaleRow(success.textContent.trim(), customerName, pmSuppId, pmUnitPrice, pmQty, pmPayMethod);

        // Flash the card
        var card = document.getElementById('pc_' + pmSuppId);
        if (card) {
          card.classList.add('card-purchased');
          setTimeout(function(){ card.classList.remove('card-purchased'); }, 1600);
        }

        showToast(success.innerHTML, 'success');

      } else if (error) {
        checkoutBusy = false;
        btn.disabled      = false;
        btn.style.opacity = '1';
        btxt.textContent  = 'Confirm Purchase';
        showToast(error.textContent.trim(), 'error');
      } else {
        // Fallback — reload page
        bootstrap.Modal.getInstance(document.getElementById('purchaseModal')).hide();
        setTimeout(function(){ window.location.reload(); }, 800);
      }
    })
    .catch(function(err) {
      checkoutBusy    = false;
      btn.disabled    = false;
      btn.style.opacity = '1';
      btxt.textContent  = 'Confirm Purchase';
      showToast('Network error. Please try again.', 'error');
    });
}

// ── Update card after purchase ────────────────────────────────
function updateCardStock(id, newStock) {
  var pill = document.getElementById('stockpill_' + id);
  var card = document.getElementById('pc_' + id);
  if (!pill || !card) return;

  if (newStock <= 0) {
    pill.textContent = 'Out of stock';
    pill.className   = 'product-stock-pill psp-out';
    // Disable card
    card.classList.add('out-of-stock');
    card.removeAttribute('onclick');
    var hint = card.querySelector('.product-hint');
    if (hint) hint.remove();
  } else if (newStock <= 5) {
    pill.textContent = newStock + ' left';
    pill.className   = 'product-stock-pill psp-warn';
  } else {
    pill.textContent = newStock + ' in stock';
    pill.className   = 'product-stock-pill psp-ok';
  }
}

// ── Dynamically add sale row to history table ─────────────────
function addSaleRow(alertText, customer, suppId, price, qty, payMethod) {
  var s       = CATALOG[suppId] || {};
  var total   = price * qty;
  var now     = new Date();
  var dateStr = now.toLocaleDateString('en-PH',{month:'short',day:'2-digit',year:'numeric'});
  var timeStr = now.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',hour12:true});
  var receipt = 'RCP-' + now.toISOString().slice(0,10).replace(/-/g,'') + '-NEW';

  // Extract receipt from alert text if possible
  var rcpMatch = alertText.match(/RCP-[\w-]+/);
  if (rcpMatch) receipt = rcpMatch[0];

  var tbody = document.getElementById('salesTbody');
  // Remove "no records" row if present
  var emptyRow = tbody.querySelector('td[colspan]');
  if (emptyRow) emptyRow.closest('tr').remove();

  var tr = document.createElement('tr');
  tr.style.animation = 'rowHighlight 2s ease';
  tr.innerHTML =
    '<td><span class="receipt-code">'+escHtml(receipt)+'</span></td>' +
    '<td><strong style="font-size:13px;">'+escHtml(customer)+'</strong></td>' +
    '<td><strong>'+escHtml(s.name||'')+'</strong></td>' +
    '<td style="font-weight:600;">'+qty+'</td>' +
    '<td>₱'+fmtMoney(price)+'</td>' +
    '<td><strong style="color:#10b981;">₱'+fmtMoney(total)+'</strong></td>' +
    '<td style="font-size:12px;font-weight:600;">'+escHtml(payMethod)+'</td>' +
    '<td style="font-size:12px;white-space:nowrap;">'+dateStr+'<br><span style="color:var(--text-muted);">'+timeStr+'</span></td>' +
    '<td><span style="font-size:12px;color:var(--text-muted);">—</span></td>';
  tbody.insertBefore(tr, tbody.firstChild);

  // Add to salesData for export
  salesData.unshift({
    receipt:receipt, customer:customer,
    supplement:s.name||'', category:s.category||'',
    qty:qty, price:'₱'+fmtMoney(price), total:'₱'+fmtMoney(total),
    payment:payMethod, date:dateStr, time:timeStr
  });
}

// ── Toast ─────────────────────────────────────────────────────
var toastWrap = (function() {
  var el = document.createElement('div');
  el.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none;';
  document.body.appendChild(el);
  return el;
})();

function showToast(msg, type) {
  var el = document.createElement('div');
  el.style.cssText = 'padding:13px 18px;border-radius:12px;font-size:13px;font-weight:600;max-width:380px;display:flex;align-items:flex-start;gap:10px;pointer-events:auto;box-shadow:0 8px 24px rgba(0,0,0,.15);animation:tIn .35s cubic-bezier(.34,1.56,.64,1) both;' +
    (type==='success' ? 'background:#f0fff8;border:1px solid #a7f3d0;color:#065f46;' : 'background:#fff0f0;border:1px solid #fca5a5;color:#991b1b;');
  el.innerHTML = '<i class="fas fa-'+(type==='success'?'check-circle':'exclamation-circle')+'" style="margin-top:1px;flex-shrink:0;"></i><span>'+msg+'</span>';
  toastWrap.appendChild(el);
  setTimeout(function(){
    el.style.animation = 'tOut .35s ease forwards';
    setTimeout(function(){ el.remove(); }, 380);
  }, 4500);
}

// ── Helpers ───────────────────────────────────────────────────
function fmtMoney(n) { return parseFloat(n).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function escHtml(s)  { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function makeRow(label, valHtml) {
  return '<div class="pm-row"><span class="pm-label">'+label+'</span><span class="pm-val">'+valHtml+'</span></div>';
}
function stockColor(n) { return n===0 ? '#f87171' : (n<=5 ? '#fbbf24' : '#34d399'); }

// ── Image preview (add supplement) ────────────────────────────
function previewImage(input) {
  var preview = document.getElementById('imgPreview');
  if (input.files && input.files[0]) {
    var reader = new FileReader();
    reader.onload = function(e){ preview.src = e.target.result; preview.style.display = 'block'; };
    reader.readAsDataURL(input.files[0]);
  }
}

// ── Prevent double-submit on add supplement form ───────────────
(function() {
  var form = document.getElementById('addSuppForm');
  var btn  = document.getElementById('addSuppBtn');
  var txt  = document.getElementById('addSuppBtnText');
  var busy = false;
  if (!form) return;
  form.addEventListener('submit', function(e) {
    if (busy) { e.preventDefault(); return; }
    busy = true; btn.disabled = true; btn.style.opacity = '0.7';
    txt.textContent = 'Saving…';
  });
})();

// ── Row highlight keyframes (injected via JS) ─────────────────
(function() {
  var style = document.createElement('style');
  style.textContent =
    '@keyframes rowHighlight{0%{background:rgba(245,158,11,.18)}100%{background:transparent}}' +
    '@keyframes tIn{from{transform:translateX(40px);opacity:0}to{transform:translateX(0);opacity:1}}' +
    '@keyframes tOut{from{transform:translateX(0);opacity:1}to{transform:translateX(40px);opacity:0}}';
  document.head.appendChild(style);
})();

// ── Export functions ──────────────────────────────────────────
function exportSalesPDF() {
  if (!window.jspdf || !window.jspdf.jsPDF) { alert('PDF library not loaded.'); return; }
  var doc = new window.jspdf.jsPDF('l','mm','a4');
  doc.setFontSize(14); doc.setFont('helvetica','bold');
  doc.text('Diozabeth Fitness — Supplement Sales Report', 14, 16);
  doc.setFontSize(9); doc.setFont('helvetica','normal');
  doc.text('Generated: ' + new Date().toLocaleString('en-PH'), 14, 22);
  var rows = salesData.map(function(s){
    return [s.receipt, s.customer, s.supplement, s.category, String(s.qty), s.price, s.total, s.payment, s.date+' '+s.time];
  });
  doc.autoTable({
    head:[['Receipt','Customer','Supplement','Category','Qty','Price','Total','Payment','Date & Time']],
    body:rows, startY:26,
    styles:{fontSize:8,cellPadding:2},
    headStyles:{fillColor:[245,158,11],textColor:255,fontStyle:'bold'},
    alternateRowStyles:{fillColor:[255,253,245]}
  });
  doc.save('supplement_sales_' + new Date().toISOString().split('T')[0] + '.pdf');
}

function exportSalesCSV() {
  if (!salesData.length) { alert('No data to export.'); return; }
  var rows = [['Receipt','Customer','Supplement','Category','Qty','Price','Total','Payment','Date','Time']];
  salesData.forEach(function(s){
    rows.push([s.receipt,s.customer,s.supplement,s.category,s.qty,s.price,s.total,s.payment,s.date,s.time]);
  });
  var csv = rows.map(function(r){ return r.map(function(c){ return '"'+String(c).replace(/"/g,'""')+'"'; }).join(','); }).join('\n');
  var a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob(['\uFEFF'+csv],{type:'text/csv;charset=utf-8'}));
  a.download = 'supplement_sales.csv';
  document.body.appendChild(a); a.click(); document.body.removeChild(a);
}
</script>
<?php include 'footer.php'; ?>