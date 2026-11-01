<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

// Logged-in users go straight to their dashboard
if (isLoggedIn()) {
    switch ($_SESSION['role']) {
        case 'admin':    redirect(baseUrl('admin/dashboard.php'));    break;
        case 'lecturer': redirect(baseUrl('lecturer/dashboard.php')); break;
        case 'student':  redirect(baseUrl('student/dashboard.php'));  break;
    }
}

$inst = setting('institution_name', 'Academic Institution');
$sys  = setting('system_title', 'APTARS');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($sys) ?> &middot; <?= e($inst) ?></title>
<link href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>" rel="stylesheet">
<link href="<?= e(asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>" rel="stylesheet">
<link href="<?= e(asset('assets/css/style.css')) ?>" rel="stylesheet">
<style>
/* ---------- Landing page ---------- */
.landing-nav {
  background: #0f3460; color: #fff;
  padding: .85rem 0;
  position: sticky; top: 0; z-index: 100;
}
.landing-nav .brand { display: flex; align-items: center; gap: .6rem; font-weight: 600; font-size: 1.1rem; }
.landing-nav .brand i { font-size: 1.4rem; color: #7ad0b3; }
.landing-nav a { color: #dbe5f2; text-decoration: none; margin-left: 1.2rem; font-size: .92rem; }
.landing-nav a:hover { color: #fff; }
.landing-nav .btn-light { margin-left: 1.2rem; }

.landing-hero {
  background: linear-gradient(135deg, #0f3460 0%, #164e8a 60%, #16a085 140%);
  color: #fff;
  padding: 5rem 0 5.5rem;
}
.landing-hero h1 { font-size: 3rem; font-weight: 800; margin: 0 0 .3rem; letter-spacing: -.02em; }
.landing-hero .lead { font-size: 1.2rem; opacity: .95; margin-bottom: 1rem; }
.landing-hero p.desc { opacity: .85; font-size: 1rem; max-width: 520px; }
.landing-badge {
  display: inline-block; background: rgba(255,255,255,.15); color: #fff;
  padding: .25rem .85rem; border-radius: 20px; font-size: .78rem;
  text-transform: uppercase; letter-spacing: .1em; margin-bottom: 1rem;
}
.landing-cta { margin-top: 1.6rem; display: flex; gap: .75rem; flex-wrap: wrap; }
.landing-cta .btn { padding: .7rem 1.6rem; font-weight: 500; }

.landing-hero-visual {
  display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;
  padding: 1.5rem; background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.15); border-radius: 16px;
}
.landing-hero-visual .tile { background: rgba(255,255,255,.1); border-radius: 10px; padding: 1rem; text-align: center; }
.landing-hero-visual .tile i { font-size: 1.6rem; color: #7ad0b3; display: block; margin-bottom: .4rem; }
.landing-hero-visual .tile span { font-size: .82rem; opacity: .9; }

.landing-section { padding: 4rem 0; }
.landing-section h2 { font-size: 1.7rem; color: #0f3460; font-weight: 700; margin-bottom: .5rem; }
.landing-section .subtitle { color: #6b7280; margin-bottom: 2.2rem; }

.feature-card {
  background: #fff; border: 1px solid var(--aptars-border);
  border-radius: 14px; padding: 1.6rem 1.4rem; height: 100%;
  transition: transform .15s ease, box-shadow .15s ease;
}
.feature-card:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(15,52,96,.08); }
.feature-card .icon {
  width: 44px; height: 44px; border-radius: 10px;
  background: var(--aptars-primary-soft); color: var(--aptars-primary);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.3rem; margin-bottom: .9rem;
}
.feature-card h5 { font-size: 1rem; font-weight: 600; margin: 0 0 .4rem; }
.feature-card p { font-size: .9rem; color: #4b5563; margin: 0; }

.landing-footer {
  background: #0f3460; color: #9fb3cc; padding: 2rem 0; font-size: .85rem; margin-top: 3rem;
}
.landing-footer strong { color: #fff; }

@media (max-width: 768px) {
  .landing-hero { padding: 3rem 0; }
  .landing-hero h1 { font-size: 2.2rem; }
  .landing-nav a.nav-link-item { display: none; }
  .landing-hero-visual { margin-top: 2rem; }
}
</style>
</head>
<body>

<nav class="landing-nav">
  <div class="container d-flex justify-content-between align-items-center">
    <div class="brand"><i class="bi bi-mortarboard-fill"></i> <?= e($sys) ?></div>
    <div>
      <a class="nav-link-item d-none d-md-inline" href="#features">Features</a>
      <a class="nav-link-item d-none d-md-inline" href="#about">About</a>
      <a href="login.php" class="btn btn-sm btn-outline-light">Sign in</a>
      <a href="register.php" class="btn btn-sm btn-light text-primary fw-semibold">Register</a>
    </div>
  </div>
</nav>

<section class="landing-hero">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <div class="landing-badge">Academic Information System</div>
        <h1>APTARS</h1>
        <p class="lead">Academic Performance Tracking, Analysis &amp; Reporting System</p>
        <p class="desc">
          Track student performance, record assessments and attendance, analyse learning-environment
          factors, and generate professional academic reports — all in one integrated platform.
        </p>
        <div class="landing-cta">
          <a href="login.php" class="btn btn-light text-primary fw-semibold">
            <i class="bi bi-box-arrow-in-right"></i> Sign in
          </a>
          <a href="register.php" class="btn btn-outline-light">
            <i class="bi bi-person-plus"></i> Create account
          </a>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="landing-hero-visual">
          <div class="tile"><i class="bi bi-people-fill"></i><span>Student Records</span></div>
          <div class="tile"><i class="bi bi-bar-chart-fill"></i><span>Marks &amp; Grades</span></div>
          <div class="tile"><i class="bi bi-calendar-check-fill"></i><span>Attendance</span></div>
          <div class="tile"><i class="bi bi-graph-up-arrow"></i><span>Performance Analysis</span></div>
          <div class="tile"><i class="bi bi-sliders"></i><span>Influencing Factors</span></div>
          <div class="tile"><i class="bi bi-file-earmark-text-fill"></i><span>Reports</span></div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="landing-section" id="features">
  <div class="container">
    <h2 class="text-center">Built for continuous academic monitoring</h2>
    <p class="subtitle text-center">
      Every feature maps directly to the institution's need for timely, evidence-based academic decisions.
    </p>

    <div class="row g-3">
      <div class="col-md-6 col-lg-4">
        <div class="feature-card">
          <div class="icon"><i class="bi bi-journal-check"></i></div>
          <h5>Assessment &amp; Grade Tracking</h5>
          <p>Record CATs, assignments, practicals and exams. Grades and grade points compute automatically using a configurable scale.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature-card">
          <div class="icon"><i class="bi bi-calendar-check"></i></div>
          <h5>Attendance Monitoring</h5>
          <p>Record session attendance per subject. Percentages update in real time and feed directly into attention indicators.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature-card">
          <div class="icon"><i class="bi bi-sliders"></i></div>
          <h5>Influencing-Factor Analysis</h5>
          <p>Capture learning-environment factors such as facilities, teacher effectiveness, and resource access on configurable scales.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature-card">
          <div class="icon"><i class="bi bi-graph-up"></i></div>
          <h5>Graphical Dashboards</h5>
          <p>Real-data charts — trends, distributions, comparisons, and scatter plots — rendered live from the database.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature-card">
          <div class="icon"><i class="bi bi-exclamation-triangle"></i></div>
          <h5>Attention Indicators</h5>
          <p>Configurable thresholds flag students who may require support — without ever labelling them negatively.</p>
        </div>
      </div>
      <div class="col-md-6 col-lg-4">
        <div class="feature-card">
          <div class="icon"><i class="bi bi-file-earmark-text"></i></div>
          <h5>Printable Reports</h5>
          <p>Student, subject, class, and institution-wide performance reports. Print or save as PDF in one click.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="landing-section pt-0" id="about">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-9 text-center">
        <h2>About the system</h2>
        <p class="subtitle">
          APTARS provides three roles, each with the appropriate view of the academic record.
        </p>
        <div class="row g-3 text-start">
          <div class="col-md-4">
            <div class="feature-card">
              <div class="icon"><i class="bi bi-gear"></i></div>
              <h5>Administrator</h5>
              <p>Manages students, lecturers, departments, programmes, subjects, assessments, and system settings. Views institution-wide analytics.</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="feature-card">
              <div class="icon"><i class="bi bi-person-badge"></i></div>
              <h5>Lecturer</h5>
              <p>Records marks and attendance for assigned subjects only. Views class performance and student attention indicators.</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="feature-card">
              <div class="icon"><i class="bi bi-mortarboard"></i></div>
              <h5>Student</h5>
              <p>Views their own results, grades, attendance, performance trend, and printable academic report.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<footer class="landing-footer">
  <div class="container text-center">
    <p class="mb-1"><strong><?= e($sys) ?></strong> &middot; <?= e($inst) ?></p>
    <p class="mb-0">Academic Performance Tracking, Analysis &amp; Reporting System</p>
  </div>
</footer>

</body>
</html>