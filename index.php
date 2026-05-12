<?php
require_once 'config/app.php';
requireLogin();

$conn = getDBConnection();

// Today's stats
$today = date('Y-m-d');
$todaySales  = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total FROM sales WHERE DATE(sale_date)='$today' AND status='completed'")->fetch_assoc();
$totalProducts = $conn->query("SELECT COUNT(*) AS c FROM products WHERE status='active'")->fetch_assoc()['c'];
$lowStock    = $conn->query("SELECT COUNT(*) AS c FROM products WHERE stock_qty <= low_stock_alert AND status='active'")->fetch_assoc()['c'];
$totalCustomers = $conn->query("SELECT COUNT(*) AS c FROM customers WHERE is_default=0")->fetch_assoc()['c'];

// Last 7 days sales for chart
$chart = $conn->query("SELECT DATE(sale_date) AS d, COALESCE(SUM(total_amount),0) AS t FROM sales WHERE sale_date >= DATE_SUB(CURDATE(),INTERVAL 6 DAY) AND status='completed' GROUP BY DATE(sale_date) ORDER BY d ASC");
$chartLabels = []; $chartData = [];
while ($row = $chart->fetch_assoc()) { $chartLabels[] = date('M d', strtotime($row['d'])); $chartData[] = $row['t']; }

// Recent sales
$recentSales = $conn->query("SELECT s.invoice_no, s.total_amount, s.payment_method, s.sale_date, c.name AS customer, u.full_name AS cashier FROM sales s JOIN customers c ON c.id=s.customer_id JOIN users u ON u.id=s.user_id ORDER BY s.sale_date DESC LIMIT 8");

// Top products
$topProducts = $conn->query("SELECT p.name, p.sku, SUM(si.qty) AS sold, SUM(si.subtotal) AS revenue FROM sale_items si JOIN products p ON p.id=si.product_id JOIN sales s ON s.id=si.sale_id WHERE s.status='completed' AND DATE(s.sale_date) >= DATE_SUB(CURDATE(),INTERVAL 30 DAY) GROUP BY p.id ORDER BY sold DESC LIMIT 5");
$conn->close();

$pageTitle = 'Dashboard';
require_once 'includes/header.php';
?>

<div class="row g-4 mb-4">
  <!-- Stat Cards -->
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(233,69,96,0.15);color:#e94560;"><i class="bi bi-cash-stack"></i></div>
      <div>
        <div class="stat-value"><?= formatCurrency($todaySales['total']) ?></div>
        <div class="stat-label">Today's Revenue</div>
        <div class="stat-change text-success"><i class="bi bi-arrow-up"></i> <?= $todaySales['cnt'] ?> transactions</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(91,192,222,0.15);color:#5bc0de;"><i class="bi bi-box-seam"></i></div>
      <div>
        <div class="stat-value"><?= number_format($totalProducts) ?></div>
        <div class="stat-label">Active Products</div>
        <div class="stat-change text-warning"><i class="bi bi-exclamation-triangle"></i> <?= $lowStock ?> low stock</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(46,204,113,0.15);color:#2ecc71;"><i class="bi bi-people"></i></div>
      <div>
        <div class="stat-value"><?= number_format($totalCustomers) ?></div>
        <div class="stat-label">Customers</div>
        <div class="stat-change text-muted">Registered</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <a href="modules/sales/pos.php" class="stat-card text-decoration-none" style="background:linear-gradient(135deg,#e94560,#c73652);">
      <div class="stat-icon" style="background:rgba(255,255,255,0.2);color:#fff;"><i class="bi bi-cart-check"></i></div>
      <div>
        <div class="stat-value" style="color:#fff;">New Sale</div>
        <div class="stat-label" style="color:rgba(255,255,255,0.7);">Open POS Terminal</div>
        <div class="stat-change" style="color:rgba(255,255,255,0.6);"><i class="bi bi-arrow-right"></i> Click to start</div>
      </div>
    </a>
  </div>
</div>

<div class="row g-4">
  <!-- Sales Chart -->
  <div class="col-md-8">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-bar-chart me-2 text-accent"></i>Sales — Last 7 Days</span>
      </div>
      <div class="card-body">
        <canvas id="salesChart" height="110"></canvas>
      </div>
    </div>
  </div>
  <!-- Top Products -->
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-trophy me-2 text-warning"></i>Top Products (30 Days)</div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          <?php $rank=1; while($p=$topProducts->fetch_assoc()): ?>
          <li class="list-group-item" style="background:transparent;border-color:var(--border);color:var(--text);padding:12px 20px;">
            <div class="d-flex align-items-center gap-3">
              <span style="width:24px;height:24px;border-radius:50%;background:rgba(233,69,96,0.15);color:#e94560;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:700;"><?= $rank++ ?></span>
              <div class="flex-grow-1">
                <div style="font-size:.875rem;font-weight:600;"><?= htmlspecialchars($p['name']) ?></div>
                <div style="font-size:.75rem;color:#777;"><?= number_format($p['sold']) ?> sold</div>
              </div>
              <div style="font-size:.85rem;font-weight:700;color:#2ecc71;"><?= formatCurrency($p['revenue']) ?></div>
            </div>
          </li>
          <?php endwhile; ?>
        </ul>
      </div>
    </div>
  </div>

  <!-- Recent Sales -->
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-receipt me-2"></i>Recent Transactions</span>
        <a href="modules/sales/index.php" class="btn btn-sm btn-outline-accent">View All</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table mb-0">
            <thead><tr>
              <th>Invoice</th><th>Customer</th><th>Cashier</th><th>Payment</th><th>Total</th><th>Date</th>
            </tr></thead>
            <tbody>
            <?php while($s=$recentSales->fetch_assoc()): ?>
            <tr>
              <td><code style="color:#e94560;"><?= htmlspecialchars($s['invoice_no']) ?></code></td>
              <td><?= htmlspecialchars($s['customer']) ?></td>
              <td><?= htmlspecialchars($s['cashier']) ?></td>
              <td><span class="badge" style="background:rgba(91,192,222,0.15);color:#5bc0de;"><?= ucfirst($s['payment_method']) ?></span></td>
              <td style="font-weight:700;color:#2ecc71;"><?= formatCurrency($s['total_amount']) ?></td>
              <td style="color:#777;font-size:.8rem;"><?= date('M d, H:i', strtotime($s['sale_date'])) ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('salesChart').getContext('2d');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: [{
      label: 'Revenue',
      data: <?= json_encode(array_map('floatval', $chartData)) ?>,
      backgroundColor: 'rgba(233,69,96,0.3)',
      borderColor: '#e94560',
      borderWidth: 2,
      borderRadius: 8,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => '$' + c.raw.toFixed(2) } } },
    scales: {
      x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#777' } },
      y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#777', callback: v => '$' + v } }
    }
  }
});
</script>

<?php require_once 'includes/footer.php'; ?>