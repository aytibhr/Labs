<?php
require_once __DIR__ . '/functions.php';
redirect_if_not_logged_in();

$active = $active ?? ''; // set this per-page before including header
$branchName = get_branch_name($_SESSION['branch_id'] ?? null);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($page_title ?? 'Namavi Labs') ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css"
      xintegrity="sha512-vebUliqxrVkNgXF_L3sTgf5cgZqlS_NlKq7gVbCo9/bTRflNxcGepTNn7m2+ozoi+udvLo2sF8jseT85sCqz
      crossorigin="anonymous" referrerpolicy="no-referrer">
</head>
<body>

<!-- Mobile topbar -->
<div class="topbar">
  <button class="mobile-menu-toggle" aria-expanded="false" aria-label="Toggle menu">☰</button>
  <div><img src="assets/img/logo.png" alt="Logo" class="topbar-logo-mobile"></div>
</div>

<!-- App layout -->
<div class="layout">
  <nav class="sidebar" id="sidebar">
    <div class="logo-wrap">
      <img src="assets/img/logo.png" alt="Namavi Labs" style="height:36px">
      <strong>Namavi Labs</strong>
    </div>
    
    <?php
    // Robust current route detection
    $currentPath = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');
    if ($currentPath === '' || $currentPath === 'index.php') {
      $currentPath = 'dashboard.php';
    }
    
    // tiny helper
    function nav_active($file) {
      global $currentPath;
      $file = strtolower($file);
      $cur  = strtolower($currentPath);
      if ($file === 'dashboard.php' && ($cur === 'dashboard' || $cur === 'dashboard.php')) return 'active';
      return $cur === $file ? 'active' : '';
    }
    ?>


    <ul class="sidebar-menu">
      <li class="create-bill-item create-bill-link">
        <?php if (!is_super_admin()): ?>
            <a href="create_bill.php" class="create-bill-btn">✨ Create New Bill</a>
        <?php endif; ?>
      </li>
      <li><a class="<?= nav_active('dashboard.php') ?>" href="dashboard.php">Dashboard</a></li>
      <li><a class="<?= nav_active('patients.php') ?>" href="patients.php">Patients</a></li>
      <li><a class="<?= nav_active('tests.php') ?>" href="tests.php">Lab Tests</a></li>
      <li><a class="<?= nav_active('invoices.php') ?>" href="invoices.php">Invoices</a></li>
      <?php if (is_super_admin()): ?>
        <li><a class="<?= nav_active('branches.php') ?>" href="branches.php">Manage Branches</a></li>
        <li><a class="<?= nav_active('users.php') ?>" href="users.php">Manage Admins</a></li>
        <li><a class="<?= nav_active('settings.php') ?>" href="settings.php">App Settings</a></li>
      <?php endif; ?>

      <li class="menu-divider"></li>
      <li class="sidebar-footer">
        <div class="user-info">
          <strong><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></strong>
          <small><?= htmlspecialchars($_SESSION['role'] ?? '') ?> | <?= htmlspecialchars($branchName) ?></small>
        </div>
        <a class="logout-btn" href="logout.php">Logout</a>
      </li>
    </ul>
  </nav>

  <!-- Page content starts -->
  <main class="main-content">

