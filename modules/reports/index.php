<?php
require_once '../../config/app.php';
requireRole('admin', 'manager');

$conn = getDBConnection();
$dateFrom = sanitize($_GET['date_from'] ?? date('Y-m-01'));
$dateTo   = sanitize($_GET['date_to']   ?? date('Y-m-d'));

// Summary totals
$summary = $conn->query("SELECT COUNT(*) AS transactions, COALESCE(SUM(subtotal),0) AS gross, COALESCE(SUM(discount_amount),0) AS discounts, COALESCE(SUM(tax_amount),0) AS taxes, COALESCE(SUM(total_amount),0) AS net FROM sales WHERE DATE(sale_date) BETWEEN '$dateFrom' AND '$dateTo' AND status='completed'")->fetch_assoc();

// Daily breakdown
$daily = $conn->query("SELECT DATE(sale_date) AS d, COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS total FROM sales WHERE DATE(sale_date) BETWEEN '$dateFrom' AND '$dateTo' AND status='completed' GROUP BY DATE(sale_date) ORDER BY d ASC");
$dailyLabels=[]; $dailyData=[];
while($r=$daily->fetch_assoc()) { $dailyLabels[]=date('M d',strtotime($r['d'])); $dailyData[]=$r['total']; }

// Top products
$topProducts = $conn->query("SELECT p.name, p.sku, SUM(si.qty) AS units_sold, SUM(si.subtotal) AS revenue, SUM(si.qty*(si.unit_price-p.cost_price)) AS profit FROM sale_items si JOIN products p ON p.id=si.product_id JOIN sales s ON s.id=si.sale_id WHERE DATE(s.sale_date) BETWEEN '$dateFrom' AND '$dateTo' AND s.status='completed' GROUP BY p.id ORDER BY revenue DESC LIMIT 10");

// Payment methods
$payMethods = $conn->query("SELECT payment_method, COUNT(*) AS cnt, SUM(total_amount) AS total FROM sales WHERE DATE(sale_date) BETWEEN '$dateFrom' AND '$dateTo' AND status='completed' GROUP BY payment_method");
$pmLabels=[]; $pmData=[];
while($r=$payMethods->fetch_assoc()) { $pmLabels[]=ucfirst($r['payment_method']); $pmData[]=$r['total']; }

// Category revenue
$catRevenue = $conn->query("SELECT c.name, SUM(si.subtotal) AS revenue FROM sale_items si JOIN products p ON p.id=si.product_id JOIN categories c ON c.id=p.category_id JOIN sales s ON s.id=si.sale_id WHERE DATE(s.sale_date) BETWEEN '$dateFrom' AND '$dateTo' AND s.status='completed' GROUP BY c.id ORDER BY revenue DESC");
$conn->close();

$pageTitle = 'Reports & Analytics';
require_once '../../includes/header.php';
?>

<!-- Filter -->
<div class="card mb-4">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Date From</label>
        <input type="date" name="date_from" class="form-control" value="<?= $dateFrom ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Date To</label>
        <input type="date" name="date_to" class="form-control" value="<?= $dateTo ?>">
      </div>
      <div class="col-md-4 d-flex gap-2">
        <button type="submit" class="btn btn-accent flex-fill">Generate Report</button>
        <a href="?date_from=<?= date('Y-m-01') ?>&date_to=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary">This Month</a>
      </div>
    </form>
  </div>
</div>

<!-- Quick presets -->
<div class="d-flex gap-2 mb-4 flex-wrap">
  <?php
  $presets = [
    'Today'          => [date('Y-m-d'), date('Y-m-d')],
    'This Week'      => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
    'This Month'     => [date('Y-m-01'), date('Y-m-d')],
    'Last 30 Days'   => [date('Y-m-d', strtotime('-30 days')), date('Y-m-d')],
    'This Year'      => [date('Y-01-01'), date('Y-m-d')],
  ];
  foreach($presets as $label=>[$f,$t]):
  ?>
  <a href="?date_from=<?= $f ?>&date_to=<?= $t ?>" class="btn btn-sm" style="background:rgba(255,255,255,0.05);border:1px solid var(--border);color:#aaa;border-radius:20px;<?= ($f===$dateFrom&&$t===$dateTo)?'background:rgba(233,69,96,0.15);color:#e94560;border-color:rgba(233,69,96,0.3);':'' ?>">
    <?= $label ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card"><div class="stat-icon" style="background:rgba(233,69,96,0.15);color:#e94560;"><i class="bi bi-cart"></i></div>
      <div><div class="stat-value"><?= number_format($summary['transactions']) ?></div><div class="stat-label">Transactions</div></div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card"><div class="stat-icon" style="background:rgba(46,204,113,0.15);color:#2ecc71;"><i class="bi bi-cash-stack"></i></div>
      <div><div class="stat-value"><?= formatCurrency($summary['net']) ?></div><div class="stat-label">Net Revenue</div></div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card"><div class="stat-icon" style="background:rgba(243,156,18,0.15);color:#f39c12;"><i class="bi bi-tag"></i></div>
      <div><div class="stat-value"><?= formatCurrency($summary['discounts']) ?></div><div class="stat-label">Total Discounts</div></div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card"><div class="stat-icon" style="background:rgba(91,192,222,0.15);color:#5bc0de;"><i class="bi bi-graph-up"></i></div>
      <div><div class="stat-value"><?= $summary['transactions']>0?formatCurrency($summary['net']/$summary['transactions']):'$0.00' ?></div><div class="stat-label">Avg. Transaction</div></div></div>
  </div>
</div>

<div class="row g-4 mb-4">
  <!-- Daily Sales Chart -->
  <div class="col-md-8">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-bar-chart me-2"></i>Daily Revenue</div>
      <div class="card-body"><canvas id="dailyChart" height="120"></canvas></div>
    </div>
  </div>
  <!-- Payment Methods -->
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-pie-chart me-2"></i>Payment Methods</div>
      <div class="card-body d-flex align-items-center justify-content-center">
        <canvas id="payChart" height="200" style="max-height:200px;"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Top Products & Category Revenue -->
<div class="row g-4">
  <div class="col-md-8">
    <div class="card">
      <div class="card-header"><i class="bi bi-trophy me-2"></i>Top Products by Revenue</div>
      <div class="card-body p-0">
        <table class="table mb-0">
          <thead><tr><th>#</th><th>Product</th><th>Units Sold</th><th>Revenue</th><th>Est. Profit</th></tr></thead>
          <tbody>
          <?php $rank=1; while($p=$topProducts->fetch_assoc()): ?>
          <tr>
            <td><span style="width:26px;height:26px;border-radius:50%;background:rgba(233,69,96,0.1);color:#e94560;display:inline-flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;"><?= $rank++ ?></span></td>
            <td><div style="font-weight:600;"><?= htmlspecialchars($p['name']) ?></div><code style="font-size:.7rem;color:#555;"><?= $p['sku'] ?></code></td>
            <td><?= number_format($p['units_sold']) ?></td>
            <td style="font-weight:700;color:#2ecc71;"><?= formatCurrency($p['revenue']) ?></td>
            <td style="color:#f39c12;"><?= formatCurrency($p['profit']) ?></td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-grid me-2"></i>Revenue by Category</div>
      <div class="card-body p-0">
        <?php while($cat=$catRevenue->fetch_assoc()): ?>
        <div style="padding:12px 18px;border-bottom:1px solid var(--border);">
          <div class="d-flex justify-content-between mb-1">
            <span style="font-size:.875rem;font-weight:600;"><?= htmlspecialchars($cat['name']) ?></span>
            <span style="font-weight:700;color:#2ecc71;"><?= formatCurrency($cat['revenue']) ?></span>
          </div>
          <div style="height:4px;background:rgba(255,255,255,0.07);border-radius:2px;">
            <div style="height:100%;width:<?= min(100, round($cat['revenue']/$summary['net']*100)) ?>%;background:linear-gradient(90deg,#e94560,#5bc0de);border-radius:2px;"></div>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Daily Chart
new Chart(document.getElementById('dailyChart').getContext('2d'), {
  type: 'line',
  data: {
    labels: <?= json_encode($dailyLabels) ?>,
    datasets: [{ label:'Revenue', data: <?= json_encode(array_map('floatval',$dailyData)) ?>,
      borderColor:'#e94560', backgroundColor:'rgba(233,69,96,0.1)', fill:true,
      tension:.4, borderWidth:2, pointBackgroundColor:'#e94560', pointRadius:4 }]
  },
  options: {
    responsive:true, plugins:{ legend:{display:false}, tooltip:{ callbacks:{ label:c=>'$'+c.raw.toFixed(2) } } },
    scales: {
      x:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#777'}},
      y:{grid:{color:'rgba(255,255,255,0.05)'},ticks:{color:'#777',callback:v=>'$'+v}}
    }
  }
});
// Payment Doughnut
new Chart(document.getElementById('payChart').getContext('2d'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($pmLabels) ?>,
    datasets:[{ data: <?= json_encode(array_map('floatval',$pmData)) ?>,
      backgroundColor:['#e94560','#5bc0de','#2ecc71','#f39c12'],
      borderWidth:0, hoverOffset:8 }]
  },
  options: {
    responsive:true, plugins:{ legend:{ position:'bottom', labels:{ color:'#888', boxWidth:12, padding:16 } } },
    cutout:'68%'
  }
});
</script>

<?php require_once '../../includes/footer.php'; ?>