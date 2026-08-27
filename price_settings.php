<?php
require_once 'auth.php';
$pageTitle = 'Price Settings';

if ($_SESSION['admin_role'] !== 'superadmin') {
    header('Location: dashboard.php');
    exit;
}

$successMsg = $errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_price') {
        $type = $_POST['type'] ?? '';
        $price = floatval($_POST['price'] ?? 0);
        $label = trim($_POST['label'] ?? '');
        if (!in_array($type, ['walkin', 'subscription'])) {
            $errorMsg = 'Invalid price type.';
        } elseif ($price > 0 && $label) {
            $safeLabel = $conn->real_escape_string($label);
            $conn->query("INSERT INTO price_settings (type, price, label) VALUES ('$type', $price, '$safeLabel')");
            $successMsg = "Price option added.";
        } else { $errorMsg = 'Please fill all fields.'; }
    }
    
    if ($action === 'toggle_price') {
        $id = intval($_POST['price_id'] ?? 0);
        $conn->query("UPDATE price_settings SET is_active = NOT is_active WHERE id = $id");
        $successMsg = "Price option updated.";
    }
    
    if ($action === 'delete_price') {
        $id = intval($_POST['price_id'] ?? 0);
        $conn->query("DELETE FROM price_settings WHERE id = $id");
        $successMsg = "Price option deleted.";
    }

    // ── Supplement actions ───────────────────────────────────────────────────
    if ($action === 'add_supplement') {
        $name     = trim($_POST['supp_name'] ?? '');
        $price    = floatval($_POST['supp_price'] ?? 0);
        $brand    = $conn->real_escape_string(trim($_POST['supp_brand'] ?? ''));
        $category = $conn->real_escape_string(trim($_POST['supp_category'] ?? 'Other'));
        $stock    = intval($_POST['supp_stock'] ?? 0);
        $desc     = $conn->real_escape_string(trim($_POST['supp_desc'] ?? ''));
        $safeName = $conn->real_escape_string($name);
        if ($name && $price > 0) {
            $conn->query("INSERT INTO supplements (name, brand, category, stock_quantity, price, description, is_active) VALUES ('$safeName','$brand','$category',$stock,$price,'$desc',1)");
            $successMsg = "Supplement added.";
        } else { $errorMsg = 'Please enter supplement name and price.'; }
    }
    if ($action === 'toggle_supplement') {
        $id = intval($_POST['supp_id'] ?? 0);
        $conn->query("UPDATE supplements SET is_active = NOT is_active WHERE id = $id");
        $successMsg = "Supplement updated.";
    }
    if ($action === 'delete_supplement') {
        $id = intval($_POST['supp_id'] ?? 0);
        $conn->query("DELETE FROM supplements WHERE id = $id");
        $successMsg = "Supplement deleted.";
    }
}

// Ensure supplements table exists
$conn->query("CREATE TABLE IF NOT EXISTS supplements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");

$prices = $conn->query("SELECT * FROM price_settings ORDER BY type, price ASC");
$supplements = $conn->query("SELECT * FROM supplements ORDER BY name ASC");
include 'header.php';
?>

<div class="page-header mb-4">
  <div>
    <h4 class="fw-800 mb-1" style="font-family:'Barlow Condensed',sans-serif;font-size:26px;font-weight:800;">Price Settings</h4>
    <p class="text-muted mb-0" style="font-size:13px;">Configure walk-in and subscription pricing options</p>
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

<!-- Add Price Form -->
<div class="section-card mb-4">
  <div class="section-card-header">
    <span class="section-card-title"><i class="fas fa-plus-circle me-2" style="color:var(--primary)"></i>Add Price Option</span>
  </div>
  <div class="section-card-body">
    <form method="POST" action="">
      <input type="hidden" name="action" value="add_price">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Type</label>
          <select name="type" class="form-select">
            <option value="walkin">Walk-in</option>
            <option value="subscription">Subscription</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Price (₱)</label>
          <input type="number" name="price" class="form-control" placeholder="e.g. 150" min="1" step="0.01" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Label</label>
          <input type="text" name="label" class="form-control" placeholder="e.g. ₱150 - Premium" required>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn-primary-custom w-100" style="justify-content:center;"><i class="fas fa-plus"></i> Add</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Prices List -->
<div class="row g-3">
  <!-- Walk-in Prices -->
  <div class="col-12">
    <div class="section-card">
      <div class="section-card-header" style="background:rgba(30,120,255,0.04);">
        <span class="section-card-title"><i class="fas fa-person-walking me-2" style="color:var(--primary)"></i>Walk-in Prices</span>
      </div>
      <div class="section-card-body p-0">
        <div class="table-responsive">
          <table class="table mb-0">
            <thead><tr>
              <th style="width:140px;">Price</th>
              <th>Label</th>
              <th style="width:120px;">Status</th>
              <th style="width:80px;">Action</th>
            </tr></thead>
            <tbody>
              <?php
              $prices->data_seek(0);
              $hasWalkin = false;
              while($p = $prices->fetch_assoc()):
                if ($p['type'] !== 'walkin') continue;
                $hasWalkin = true;
              ?>
              <tr>
                <td><strong style="color:var(--success);">₱<?= number_format($p['price'], 2) ?></strong></td>
                <td style="font-size:13px;"><?= htmlspecialchars($p['label']) ?></td>
                <td>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="toggle_price">
                    <input type="hidden" name="price_id" value="<?= $p['id'] ?>">
                    <button type="submit" class="badge-<?= $p['is_active'] ? 'active' : 'inactive' ?>" style="border:none;cursor:pointer;">
                      <?= $p['is_active'] ? 'Active' : 'Inactive' ?>
                    </button>
                  </form>
                </td>
                <td>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this price?')">
                    <input type="hidden" name="action" value="delete_price">
                    <input type="hidden" name="price_id" value="<?= $p['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" style="font-size:11px;"><i class="fas fa-trash"></i></button>
                  </form>
                </td>
              </tr>
              <?php endwhile;
              if (!$hasWalkin): ?>
              <tr><td colspan="4" class="text-center text-muted py-3" style="font-size:13px;">No walk-in prices set</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Subscription Prices -->
  <div class="col-12">
    <div class="section-card">
      <div class="section-card-header" style="background:rgba(139,92,246,0.04);">
        <span class="section-card-title"><i class="fas fa-id-card me-2" style="color:#8b5cf6"></i>Subscription Prices</span>
      </div>
      <div class="section-card-body p-0">
        <div class="table-responsive">
          <table class="table mb-0">
            <thead><tr>
              <th style="width:140px;">Price</th>
              <th>Label</th>
              <th style="width:120px;">Status</th>
              <th style="width:80px;">Action</th>
            </tr></thead>
            <tbody>
              <?php
              $prices->data_seek(0);
              $hasSub = false;
              while($p = $prices->fetch_assoc()):
                if ($p['type'] !== 'subscription') continue;
                $hasSub = true;
              ?>
              <tr>
                <td><strong style="color:#8b5cf6;">₱<?= number_format($p['price'], 2) ?></strong></td>
                <td style="font-size:13px;"><?= htmlspecialchars($p['label']) ?></td>
                <td>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="toggle_price">
                    <input type="hidden" name="price_id" value="<?= $p['id'] ?>">
                    <button type="submit" class="badge-<?= $p['is_active'] ? 'active' : 'inactive' ?>" style="border:none;cursor:pointer;">
                      <?= $p['is_active'] ? 'Active' : 'Inactive' ?>
                    </button>
                  </form>
                </td>
                <td>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this price?')">
                    <input type="hidden" name="action" value="delete_price">
                    <input type="hidden" name="price_id" value="<?= $p['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" style="font-size:11px;"><i class="fas fa-trash"></i></button>
                  </form>
                </td>
              </tr>
              <?php endwhile;
              if (!$hasSub): ?>
              <tr><td colspan="4" class="text-center text-muted py-3" style="font-size:13px;">No subscription prices set</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── SUPPLEMENTS SECTION ─────────────────────────────────────────────── -->
<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="section-card">
      <div class="section-card-header" style="background:rgba(245,158,11,0.05);">
        <span class="section-card-title"><i class="fas fa-flask me-2" style="color:#f59e0b"></i>Supplement Prices</span>
        <span style="font-size:12px;color:var(--text-muted);">Gym supplements available for purchase</span>
      </div>
      <div class="section-card-body">
        <!-- Ensure columns exist -->
        <?php
        $existingCols = [];
        $colRes = $conn->query("SHOW COLUMNS FROM supplements");
        if ($colRes) { while($c = $colRes->fetch_assoc()) $existingCols[] = $c['Field']; }
        if (!in_array('brand', $existingCols))
            $conn->query("ALTER TABLE supplements ADD COLUMN brand VARCHAR(100) NULL AFTER name");
        if (!in_array('category', $existingCols))
            $conn->query("ALTER TABLE supplements ADD COLUMN category ENUM('Protein','Pre-workout','Creatine','Vitamins','BCAAs','Weight Gainer','Fat Burner','Other') DEFAULT 'Other' AFTER brand");
        if (!in_array('stock_quantity', $existingCols))
            $conn->query("ALTER TABLE supplements ADD COLUMN stock_quantity INT DEFAULT 0 AFTER category");
        ?>
        <form method="POST" action="">
          <input type="hidden" name="action" value="add_supplement">
          <div class="row g-3 align-items-end">
            <div class="col-md-3">
              <label class="form-label">Supplement Name <span class="text-danger">*</span></label>
              <input type="text" name="supp_name" class="form-control" placeholder="e.g. Whey Protein 1kg" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Brand</label>
              <input type="text" name="supp_brand" class="form-control" placeholder="e.g. Optimum Nutrition">
            </div>
            <div class="col-md-2">
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
            <div class="col-md-1">
              <label class="form-label">Price (₱) <span class="text-danger">*</span></label>
              <input type="number" name="supp_price" class="form-control" placeholder="1500" min="1" step="0.01" required>
            </div>
            <div class="col-md-1">
              <label class="form-label">Stock</label>
              <input type="number" name="supp_stock" class="form-control" placeholder="0" min="0" value="0">
            </div>
            <div class="col-md-2">
              <label class="form-label">Description</label>
              <input type="text" name="supp_desc" class="form-control" placeholder="e.g. Choco, 30 servings">
            </div>
            <div class="col-md-1">
              <button type="submit" class="btn-primary-custom w-100" style="justify-content:center;background:linear-gradient(135deg,#f59e0b,#d97706);box-shadow:0 4px 12px rgba(245,158,11,.35);">
                <i class="fas fa-plus"></i> Add
              </button>
            </div>
          </div>
        </form>
      </div>
      <div class="section-card-body p-0">
        <div class="table-responsive">
          <table class="table mb-0">
            <thead>
              <tr>
                <th>Name</th>
                <th>Brand</th>
                <th>Category</th>
                <th style="width:110px;">Price</th>
                <th style="width:80px;">Stock</th>
                <th>Description</th>
                <th style="width:120px;">Status</th>
                <th style="width:80px;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($supplements && $supplements->num_rows > 0):
              while($s = $supplements->fetch_assoc()):
                $stock = (int)($s['stock_quantity'] ?? 0);
                $stockColor = $stock === 0 ? '#ef4444' : ($stock <= 5 ? '#f59e0b' : '#10b981');
              ?>
              <tr>
                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                <td style="font-size:13px;color:var(--text-muted);"><?= htmlspecialchars($s['brand'] ?? '—') ?></td>
                <td><span style="background:rgba(245,158,11,.12);color:#d97706;font-size:11px;font-weight:700;padding:2px 10px;border-radius:20px;"><?= htmlspecialchars($s['category'] ?? 'Other') ?></span></td>
                <td><strong style="color:#f59e0b;">₱<?= number_format($s['price'], 2) ?></strong></td>
                <td><strong style="color:<?= $stockColor ?>;"><?= $stock ?></strong></td>
                <td style="font-size:13px;color:var(--text-muted);"><?= htmlspecialchars($s['description'] ?? '—') ?></td>
                <td>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="toggle_supplement">
                    <input type="hidden" name="supp_id" value="<?= $s['id'] ?>">
                    <?php if ($stock === 0): ?>
                    <span class="badge-inactive" style="display:inline-block;">Not Available</span>
                    <?php else: ?>
                    <button type="submit" class="badge-<?= $s['is_active'] ? 'active' : 'inactive' ?>" style="border:none;cursor:pointer;">
                      <?= $s['is_active'] ? 'Available' : 'Unavailable' ?>
                    </button>
                    <?php endif; ?>
                  </form>
                </td>
                <td>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this supplement?')">
                    <input type="hidden" name="action" value="delete_supplement">
                    <input type="hidden" name="supp_id" value="<?= $s['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" style="font-size:11px;"><i class="fas fa-trash"></i></button>
                  </form>
                </td>
              </tr>
              <?php endwhile; else: ?>
              <tr><td colspan="8" class="text-center text-muted py-4" style="font-size:13px;"><i class="fas fa-flask me-2"></i>No supplements added yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
