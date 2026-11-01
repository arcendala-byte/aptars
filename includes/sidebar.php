<?php
$u = currentUser();
$role = $u['role'] ?? '';
$nav  = $activeNav ?? '';

$sectionMap = [
    'academic'   => ['students','lecturers','departments','programmes','subjects','academic_years','semesters'],
    'alloc'      => ['allocations','registrations'],
    'tracking'   => ['assessments','results','attendance','factors'],
    'analysis'   => ['analysis_perf','analysis_factors','analysis_alerts'],
    'reports'    => ['reports'],
    'payments_group' => ['payments','search'],
    'system'     => ['users','logs','settings'],
    'teaching'   => ['results','attendance'],
    'myacademics'=> ['results','attendance','performance'],
    'fees'       => ['register_units','pay'],
];

$activeSection = '';
foreach ($sectionMap as $sid => $keys) {
    if (in_array($nav, $keys, true)) { $activeSection = $sid; break; }
}

$group = function (string $id, string $title, callable $body) use ($activeSection) {
    $open = $id === $activeSection;
    $cls  = 'nav-section-toggle' . ($open ? '' : ' collapsed');
    $exp  = $open ? 'true' : 'false';
    echo '<a class="' . $cls . '" data-bs-toggle="collapse" href="#sec-' . e($id) . '" role="button" aria-expanded="' . $exp . '">';
    echo '<span>' . e($title) . '</span><i class="bi bi-chevron-down"></i></a>';
    echo '<div class="collapse' . ($open ? ' show' : '') . '" id="sec-' . e($id) . '"><div class="nav-section-body">';
    $body();
    echo '</div></div>';
};

$item = function (string $key, string $href, string $icon, string $label, string $navCurrent) {
    $cls = $key === $navCurrent ? 'nav-link active' : 'nav-link';
    echo '<a class="' . $cls . '" href="' . e(baseUrl($href)) . '"><i class="bi ' . e($icon) . '"></i> ' . e($label) . '</a>';
};
?>

<header class="mobile-topbar" id="mobileTopbar">
  <button type="button" class="hamburger" id="sidebarToggle" aria-label="Open navigation">
    <i class="bi bi-list"></i>
  </button>
  <div class="mobile-brand">
    <i class="bi bi-mortarboard-fill"></i>
    <span><?= e(setting('system_title', 'APTARS')) ?></span>
  </div>
</header>

<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

<aside class="sidebar" id="sidebar">

  <div class="brand">
    <i class="bi bi-mortarboard-fill"></i>
    <span><?= e(setting('system_title', 'APTARS')) ?></span>
    <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Close navigation">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <nav class="nav flex-column">
    <?php $item('dashboard', "$role/dashboard.php", 'bi-speedometer2', 'Dashboard', $nav); ?>

    <?php if ($role === 'admin'): ?>
      <?php $group('academic', 'Academic Management', function () use ($item, $nav) { ?>
        <?php $item('students',       'admin/students/index.php',       'bi-people',          'Students',            $nav); ?>
        <?php $item('lecturers',      'admin/lecturers/index.php',      'bi-person-badge',    'Lecturers',           $nav); ?>
        <?php $item('departments',    'admin/departments/index.php',    'bi-building',        'Departments',         $nav); ?>
        <?php $item('programmes',     'admin/programmes/index.php',     'bi-journal-bookmark','Programmes',          $nav); ?>
        <?php $item('subjects',       'admin/subjects/index.php',       'bi-book',            'Subjects',            $nav); ?>
        <?php $item('academic_years', 'admin/academic_years/index.php', 'bi-calendar-event',  'Academic Years',      $nav); ?>
        <?php $item('semesters',      'admin/semesters/index.php',      'bi-calendar3',       'Semesters',           $nav); ?>
      <?php }); ?>

      <?php $group('alloc', 'Allocations & Registration', function () use ($item, $nav) { ?>
        <?php $item('allocations',   'admin/allocations/index.php',   'bi-diagram-3',       'Subject Allocation',   $nav); ?>
        <?php $item('registrations', 'admin/registrations/index.php', 'bi-clipboard-check', 'Student Registration', $nav); ?>
      <?php }); ?>

      <?php $group('tracking', 'Academic Tracking', function () use ($item, $nav) { ?>
        <?php $item('assessments', 'admin/assessments/index.php', 'bi-ui-checks',       'Assessments',          $nav); ?>
        <?php $item('results',     'admin/results/index.php',     'bi-bar-chart',       'Results',              $nav); ?>
        <?php $item('attendance',  'admin/attendance/index.php',  'bi-calendar-check',  'Attendance',           $nav); ?>
        <?php $item('factors',     'admin/factors/index.php',     'bi-sliders',         'Influencing Factors',  $nav); ?>
      <?php }); ?>

      <?php $group('analysis', 'Analysis', function () use ($item, $nav) { ?>
        <?php $item('analysis_perf',    'admin/analysis/performance.php', 'bi-graph-up',              'Performance Analysis', $nav); ?>
        <?php $item('analysis_factors', 'admin/analysis/factors.php',     'bi-diagram-2',             'Factor Analysis',      $nav); ?>
        <?php $item('analysis_alerts',  'admin/analysis/alerts.php',      'bi-exclamation-triangle',  'Attention Alerts',     $nav); ?>
      <?php }); ?>

      <?php $group('reports', 'Reports', function () use ($item, $nav) { ?>
        <?php $item('reports', 'reports/index.php', 'bi-file-earmark-text', 'All Reports', $nav); ?>
      <?php }); ?>

      <?php $group('payments_group', 'Payments & Search', function () use ($item, $nav) { ?>
        <?php $item('payments', 'admin/payments/index.php', 'bi-credit-card', 'Payments', $nav); ?>
        <?php $item('search',   'admin/search.php',         'bi-search',      'Search',   $nav); ?>
      <?php }); ?>

      <?php $group('system', 'System', function () use ($item, $nav) { ?>
        <?php $item('users',    'admin/users/index.php',    'bi-person-gear',  'User Accounts', $nav); ?>
        <?php $item('logs',     'admin/logs/index.php',     'bi-clock-history','Activity Log',  $nav); ?>
        <?php $item('settings', 'admin/settings/index.php', 'bi-gear',         'Settings',      $nav); ?>
      <?php }); ?>
    <?php endif; ?>

    <?php if ($role === 'lecturer'): ?>
      <?php $group('teaching', 'Teaching', function () use ($item, $nav) { ?>
        <?php $item('results',    'lecturer/results/index.php',    'bi-pencil-square',  'Enter Marks',        $nav); ?>
        <?php $item('attendance', 'lecturer/attendance/index.php', 'bi-calendar-check', 'Record Attendance',  $nav); ?>
      <?php }); ?>
      <?php $group('analysis', 'Analysis', function () use ($item, $nav) { ?>
        <?php $item('analysis_perf',     'lecturer/analysis/performance.php', 'bi-graph-up', 'Class Performance', $nav); ?>
        <?php $item('analysis_students', 'lecturer/analysis/students.php',    'bi-people',   'My Students',       $nav); ?>
      <?php }); ?>
      <?php $group('reports', 'Reports', function () use ($item, $nav) { ?>
        <?php $item('reports', 'reports/index.php', 'bi-file-earmark-text', 'Reports', $nav); ?>
      <?php }); ?>
    <?php endif; ?>

    <?php if ($role === 'student'): ?>
      <?php $group('myacademics', 'My Academics', function () use ($item, $nav) { ?>
        <?php $item('results',    'student/results.php',     'bi-journal-text',    'My Results',      $nav); ?>
        <?php $item('attendance', 'student/attendance.php',  'bi-calendar-check',  'My Attendance',   $nav); ?>
        <?php $item('performance','student/performance.php', 'bi-graph-up',        'My Performance',  $nav); ?>
      <?php }); ?>
      <?php $group('fees', 'Fees & Payments', function () use ($item, $nav) { ?>
        <?php $item('register_units', 'student/register_units.php', 'bi-journal-plus', 'Register Units',     $nav); ?>
        <?php $item('pay',            'student/pay.php',            'bi-credit-card',  'Pay Fees (M-Pesa)',  $nav); ?>
      <?php }); ?>
      <?php $group('reports', 'Reports', function () use ($item, $nav) { ?>
        <?php $item('reports', 'reports/index.php', 'bi-file-earmark-text', 'My Report', $nav); ?>
      <?php }); ?>
    <?php endif; ?>

    <div class="nav-section">Account</div>
    <?php $item('notifications', 'notifications.php', 'bi-bell', 'Notifications', $nav); ?>
    <a class="nav-link text-danger" href="<?= e(baseUrl('logout.php')) ?>"><i class="bi bi-box-arrow-right"></i> Logout</a>
  </nav>

  <div class="sidebar-footer">
    <?= e(setting('institution_name', '')) ?>
  </div>
</aside>

<script>
(function () {
  const KEY = 'aptars.sidebar.sections';
  let saved = {};
  try { saved = JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) { saved = {}; }

  document.querySelectorAll('.nav-section-toggle').forEach(function (t) {
    const id = (t.getAttribute('href') || '').replace('#sec-', '');
    if (!id) return;
    const isActive = t.getAttribute('aria-expanded') === 'true';
    if (!isActive && saved[id] === true) {
      const panel = document.getElementById('sec-' + id);
      if (panel) {
        panel.classList.add('show');
        t.classList.remove('collapsed');
        t.setAttribute('aria-expanded', 'true');
      }
    }
    t.addEventListener('click', function () {
      const panel = document.getElementById('sec-' + id);
      setTimeout(function () {
        saved[id] = panel && panel.classList.contains('show');
        try { localStorage.setItem(KEY, JSON.stringify(saved)); } catch (e) {}
      }, 350);
    });
  });

  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  const openBtn  = document.getElementById('sidebarToggle');
  const closeBtn = document.getElementById('sidebarClose');
  if (!sidebar) return;

  const isMobile = () => window.matchMedia('(max-width: 900px)').matches;

  if (sidebar) {
    const active = sidebar.querySelector('.nav-link.active');
    if (active) {
      setTimeout(function () {
        try { active.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
        catch (e) { active.scrollIntoView(false); }
      }, 120);
    }
  }

  function openDrawer() {
    sidebar.classList.add('open');
    if (overlay) overlay.classList.add('show');
    document.body.classList.add('drawer-open');
    const active = sidebar.querySelector('.nav-link.active');
    if (active) {
      setTimeout(function () {
        try { active.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
        catch (e) { active.scrollIntoView(false); }
      }, 80);
    }
  }
  function closeDrawer() {
    sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('show');
    document.body.classList.remove('drawer-open');
  }

  if (openBtn)  openBtn.addEventListener('click', openDrawer);
  if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
  if (overlay)  overlay.addEventListener('click', closeDrawer);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });

  sidebar.querySelectorAll('a.nav-link').forEach(a => {
    a.addEventListener('click', () => { if (isMobile()) closeDrawer(); });
  });

  window.addEventListener('resize', () => { if (!isMobile()) closeDrawer(); });
})();
</script>