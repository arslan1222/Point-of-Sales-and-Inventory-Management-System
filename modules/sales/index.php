<?php
require_once '../../config/app.php';
requireLogin();

$conn = getDBConnection();
$dateFrom = sanitize($_GET['date_from'] ?? date('Y-m-01'));
$dateTo   = sanitize($_GET['date_to']   ?? date('Y-m-d'));
$search   = sanitize($_GET['search']    ?? '');

$where = ["DATE(s.sale_date) BETWEEN '$dateFrom' AND '$dateTo'"];
if ($search) $where[] = "(s.invoice_no LIKE '%$search%' OR c.name LIKE '%$search%')";

$sales = $conn->query("SELECT s.*, c.name AS customer, u.full_name AS cashier FROM sales s JOIN customers c ON c.id=s.customer_id JOIN users u ON u.id=s.user_id WHERE ".implode(' AND ',$where)." ORDER BY s.sale_date DESC");
$summary = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total, COALESCE(SUM(discount_amount),0) AS disc FROM sales s JOIN customers c ON c.id=s.customer_id WHERE ".implode(' AND ',$where)." AND s.status='completed'")->fetch_assoc();
$conn->close();

$pageTitle = 'Sales History';
require_once '../../includes/header.php';
?>

<!-- Summary cards -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(233,69,96,0.15);color:#e94560;"><i class="bi bi-receipt"></i></div>
      <div><div class="stat-value"><?= number_format($summary['cnt']) ?></div><div class="stat-label">Transactions</div></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(46,204,113,0.15);color:#2ecc71;"><i class="bi bi-cash-stack"></i></div>
      <div><div class="stat-value"><?= formatCurrency($summary['total']) ?></div><div class="stat-label">Net Revenue</div></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(243,156,18,0.15);color:#f39c12;"><i class="bi bi-tag"></i></div>
      <div><div class="stat-value"><?= formatCurrency($summary['disc']) ?></div><div class="stat-label">Discounts Given</div></div>
    </div>
  </div>
</div>

<!-- Filters -->
<div class="card mb-4">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label">Search</label>
        <input type="text" name="search" class="form-control" placeholder="Invoice or customer..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Date From</label>
        <input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Date To</label>
        <input type="date" name="date_to" class="form-control" value="<?= $dateTo ?>">
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-accent flex-fill">Filter</button>
        <a href="index.php" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Sales Table -->
<div class="card">
  <div class="card-header d-flex justify-content-between">
    <span><i class="bi bi-receipt me-2"></i>Transactions (<?= $sales->num_rows ?>)</span>
    <a href="../reports/index.php" class="btn btn-sm btn-outline-accent">Full Reports</a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0">
        <thead><tr>
          <th>Invoice</th><th>Customer</th><th>Cashier</th><th>Subtotal</th><th>Discount</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th></th>
        </tr></thead>
        <tbody>
        <?php if ($sales->num_rows===0): ?>
          <tr><td colspan="10" class="text-center py-4" style="color:#555;">No sales found.</td></tr>
        <?php endif; ?>
        <?php while($s=$sales->fetch_assoc()): ?>
        <tr>
          <td><code style="color:#e94560;"><?= htmlspecialchars($s['invoice_no']) ?></code></td>
          <td><?= htmlspecialchars($s['customer']) ?></td>
          <td style="color:#777;font-size:.8rem;"><?= htmlspecialchars($s['cashier']) ?></td>
          <td><?= formatCurrency($s['subtotal']) ?></td>
          <td style="color:#e74c3c;"><?= $s['discount_amount']>0?'-'.formatCurrency($s['discount_amount']):'-' ?></td>
          <td style="font-weight:700;color:#2ecc71;"><?= formatCurrency($s['total_amount']) ?></td>
          <td><span class="badge" style="background:rgba(91,192,222,0.1);color:#5bc0de;"><?= ucfirst($s['payment_method']) ?></span></td>
          <td>
            <?php $sc=['completed'=>['#2ecc71','rgba(46,204,113,0.1)'],'refunded'=>['#f39c12','rgba(243,156,18,0.1)'],'voided'=>['#e74c3c','rgba(231,76,60,0.1)']]; $c=$sc[$s['status']]??$sc['completed']; ?>
            <span class="badge" style="color:<?=$c[0]?>;background:<?=$c[1]?>;"><?= ucfirst($s['status']) ?></span>
          </td>
          <td style="color:#777;font-size:.8rem;"><?= date('d M Y H:i',strtotime($s['sale_date'])) ?></td>
          <td><a href="receipt.php?id=<?= $s['id'] ?>" target="_blank" class="btn btn-sm" style="background:rgba(255,255,255,0.05);border:1px solid var(--border);color:#aaa;border-radius:8px;" title="View Receipt"><i class="bi bi-receipt"></i></a></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>