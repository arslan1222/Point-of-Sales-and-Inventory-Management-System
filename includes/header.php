<?php
// includes/header.php
// Call requireLogin() before including this file
$flash = getFlash();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ?? APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <i class="bi bi-bag-check-fill"></i>
    <span>POS<strong>Pro</strong></span>
  </div>

  <div class="sidebar-section">MAIN</div>
  <a href="<?= APP_URL ?>/index.php" class="sidebar-link <?= ($currentPage === 'index') ? 'active' : '' ?>">
    <i class="bi bi-speedometer2"></i> Dashboard
  </a>
  <a href="<?= APP_URL ?>/modules/sales/pos.php" class="sidebar-link <?= ($currentDir === 'sales' && $currentPage === 'pos') ? 'active' : '' ?>">
    <i class="bi bi-cart-check"></i> Point of Sale
  </a>

  <div class="sidebar-section">INVENTORY</div>
  <a href="<?= APP_URL ?>/modules/products/index.php" class="sidebar-link <?= ($currentDir === 'products') ? 'active' : '' ?>">
    <i class="bi bi-box-seam"></i> Products
  </a>
  <a href="<?= APP_URL ?>/modules/inventory/index.php" class="sidebar-link <?= ($currentDir === 'inventory') ? 'active' : '' ?>">
    <i class="bi bi-archive"></i> Inventory
  </a>

  <div class="sidebar-section">RECORDS</div>
  <a href="<?= APP_URL ?>/modules/sales/index.php" class="sidebar-link <?= ($currentDir === 'sales' && $currentPage === 'index') ? 'active' : '' ?>">
    <i class="bi bi-receipt"></i> Sales History
  </a>
  <a href="<?= APP_URL ?>/modules/customers/index.php" class="sidebar-link <?= ($currentDir === 'customers') ? 'active' : '' ?>">
    <i class="bi bi-people"></i> Customers
  </a>

  <div class="sidebar-section">ANALYTICS</div>
  <a href="<?= APP_URL ?>/modules/reports/index.php" class="sidebar-link <?= ($currentDir === 'reports') ? 'active' : '' ?>">
    <i class="bi bi-bar-chart-line"></i> Reports
  </a>

  <?php if (hasRole('admin')): ?>
  <div class="sidebar-section">ADMIN</div>
  <a href="<?= APP_URL ?>/modules/users/index.php" class="sidebar-link <?= ($currentDir === 'users') ? 'active' : '' ?>">
    <i class="bi bi-person-gear"></i> Users
  </a>
  <?php endif; ?>
</div>

<!-- Main Wrapper -->
<div class="main-wrapper" id="mainWrapper">
  <!-- Topbar -->
  <nav class="topbar">
    <div class="d-flex align-items-center gap-3">
      <button class="btn btn-icon" id="sidebarToggle" onclick="toggleSidebar()">
        <i class="bi bi-list fs-5"></i>
      </button>
      <div class="topbar-title"><?= $pageTitle ?? 'Dashboard' ?></div>
    </div>
    <div class="d-flex align-items-center gap-3">
      <!-- Low Stock Badge -->
      <?php
        $conn2 = getDBConnection();
        $lowStockCount = $conn2->query("SELECT COUNT(*) AS c FROM products WHERE stock_qty <= low_stock_alert AND status='active'")->fetch_assoc()['c'];
        $conn2->close();
      ?>
      <?php if ($lowStockCount > 0): ?>
      <a href="<?= APP_URL ?>/modules/inventory/index.php" class="badge-alert">
        <i class="bi bi-exclamation-triangle-fill"></i> <?= $lowStockCount ?> Low Stock
      </a>
      <?php endif; ?>
      <!-- User Dropdown -->
      <div class="dropdown">
        <button class="btn btn-user dropdown-toggle" data-bs-toggle="dropdown">
          <span class="user-avatar"><?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?></span>
          <span class="d-none d-md-inline"><?= htmlspecialchars($_SESSION['full_name']) ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
          <li><span class="dropdown-item-text text-muted small"><?= ucfirst($_SESSION['role']) ?></span></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="<?= APP_URL ?>/modules/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Flash Message -->
  <?php if ($flash): ?>
  <div class="container-fluid pt-3 px-4">
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'danger' : $flash['type']) ?> alert-dismissible fade show border-0 rounded-3">
      <?= htmlspecialchars($flash['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  </div>
  <?php endif; ?>

  <!-- Page Content -->
  <div class="page-content">