  </div><!-- end page-content -->

  <div class="page-footer">
    <span>© 2026 Diozabeth Fitness Gym Management System</span>
    <span>Admin Web Portal</span>
  </div>
</div><!-- end main-wrapper -->

<script src="<?= file_exists(__DIR__.'/assets/js/bootstrap.bundle.min.js') ? 'assets/js/bootstrap.bundle.min.js' : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js' ?>"></script>
<script src="<?= file_exists(__DIR__.'/assets/js/jquery.min.js') ? 'assets/js/jquery.min.js' : 'https://code.jquery.com/jquery-3.7.1.min.js' ?>"></script>
<script src="<?= file_exists(__DIR__.'/assets/js/jquery.dataTables.min.js') ? 'assets/js/jquery.dataTables.min.js' : 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js' ?>"></script>
<script src="<?= file_exists(__DIR__.'/assets/js/dataTables.bootstrap5.min.js') ? 'assets/js/dataTables.bootstrap5.min.js' : 'https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js' ?>"></script>
<script src="<?= file_exists(__DIR__.'/assets/js/sweetalert2.all.min.js') ? 'assets/js/sweetalert2.all.min.js' : 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js' ?>"></script>
<link href="<?= file_exists(__DIR__.'/assets/css/sweetalert2.min.css') ? 'assets/css/sweetalert2.min.css' : 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css' ?>" rel="stylesheet">

<script>
/* ── Sidebar Toggle ─────────────────────────────────── */
function toggleSidebar() {
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  const isOpen   = sidebar.classList.contains('open');
  if (isOpen) {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
  } else {
    sidebar.classList.add('open');
    overlay.classList.add('active');
  }
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('active');
}

/* Close sidebar on resize to desktop */
window.addEventListener('resize', function() {
  if (window.innerWidth > 576) {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('active');
  }
});

/* ── Theme Switcher ─────────────────────────────────── */
(function() {
  var themes = {
    blue:   { p:'#1e78ff', d:'#0d5ad4', sb:'#0d1b35', sh:'#1a2f55' },
    green:  { p:'#10b981', d:'#059669', sb:'#0a2018', sh:'#14302a' },
    purple: { p:'#8b5cf6', d:'#7c3aed', sb:'#16103a', sh:'#251852' },
    red:    { p:'#ef4444', d:'#dc2626', sb:'#2a0a0a', sh:'#3d1010' },
    orange: { p:'#f97316', d:'#ea6c0b', sb:'#281508', sh:'#3d2010' },
    pink:   { p:'#ec4899', d:'#db2777', sb:'#2a0a1a', sh:'#3d1030' },
    teal:   { p:'#14b8a6', d:'#0d9488', sb:'#081e1e', sh:'#0d2e2e' },
    gold:   { p:'#f59e0b', d:'#d97706', sb:'#1e1608', sh:'#2d2010' }
  };

  function applyTheme(name) {
    var t = themes[name];
    if (!t) return;
    var r = document.documentElement.style;
    r.setProperty('--primary',      t.p);
    r.setProperty('--primary-dark', t.d);
    r.setProperty('--sidebar-bg',   t.sb);
    r.setProperty('--sidebar-hover',t.sh);
    r.setProperty('--sidebar-active',t.p);
    // Update active swatch
    document.querySelectorAll('.swatch').forEach(function(s) {
      s.classList.toggle('active', s.dataset.theme === name);
    });
    localStorage.setItem('gymTheme', name);
  }

  // Apply saved theme on load
  var saved = localStorage.getItem('gymTheme') || 'blue';
  applyTheme(saved);

  // Bind swatch clicks
  document.addEventListener('click', function(e) {
    if (e.target.classList.contains('swatch')) {
      applyTheme(e.target.dataset.theme);
    }
  });
})();
function updateClock() {
  const now = new Date();
  const dateStr = now.toLocaleDateString('en-PH', { weekday:'short', month:'short', day:'2-digit', year:'numeric' });
  const timeStr = now.toLocaleTimeString('en-PH', { hour:'2-digit', minute:'2-digit', hour12:true });
  const el = document.getElementById('liveClock');
  if (el) el.innerHTML = '<i class="fas fa-clock me-1"></i>' + dateStr + ' • ' + timeStr;
}
setInterval(updateClock, 1000);
updateClock();

/* ── Touch swipe to close sidebar on mobile ─────────── */
(function() {
  let startX = 0;
  document.addEventListener('touchstart', function(e) { startX = e.touches[0].clientX; }, { passive: true });
  document.addEventListener('touchend', function(e) {
    const dx = e.changedTouches[0].clientX - startX;
    if (dx < -60 && document.getElementById('sidebar').classList.contains('open')) closeSidebar();
  }, { passive: true });
})();
</script>
<?= $extraScripts ?? '' ?>
</body>
</html>
