<?php
require_once '../../config/app.php';
requireLogin();

$conn = getDBConnection();

// Stock adjustment POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasRole('admin','manager')) {
    $pid    = (int)($_POST['product_id'] ?? 0);
    $type   = in_array($_POST['type'],['in','out','adjustment']) ? $_POST['type'] : 'adjustment';
    $qty    = (int)($_POST['qty'] ?? 0);
    $reason = sanitize($_POST['reason'] ?? '');
    $uid    = $_SESSION['user_id'];

    if ($pid && $qty > 0) {
        $prod = $conn->query("SELECT stock_qty FROM products WHERE id=$pid")->fetch_assoc();
        if ($type === 'out' && $prod['stock_qty'] < $qty) {
            setFlash('error', 'Cannot remove more stock than available.');
        } else {
            $sign = ($type === 'in') ? '+' : '-';
            if ($type === 'adjustment') {
                $conn->query("UPDATE products SET stock_qty=$qty WHERE id=$pid");
            } else {
                $conn->query("UPDATE products SET stock_qty=stock_qty$sign$qty WHERE id=$pid");
            }
            $conn->query("INSERT INTO stock_adjustments (product_id,user_id,type,qty,reason) VALUES ($pid,$uid,'$type',$qty,'".addslashes($reason)."')");
            setFlash('success','Stock updated successfully.');
        }
    }
    redirect(APP_URL . '/modules/inventory/index.php');
}

// Filters
$search     = sanitize($_GET['search'] ?? '');
$catFilter  = (int)($_GET['category'] ?? 0);
$stockFilter = sanitize($_GET['stock'] ?? '');

$where = ["p.status='active'"];
if ($search)      $where[] = "(p.name LIKE '%$search%' OR p.sku LIKE '%$search%')";
if ($catFilter)   $where[] = "p.category_id=$catFilter";
if ($stockFilter === 'low')  $where[] = "p.stock_qty <= p.low_stock_alert AND p.stock_qty > 0";
if ($stockFilter === 'out')  $where[] = "p.stock_qty = 0";

$products   = $conn->query("SELECT p.*, c.name AS cat FROM products p JOIN categories c ON c.id=p.category_id WHERE ".implode(' AND ',$where)." ORDER BY p.stock_qty ASC");
$categories = $conn->query("SELECT * FROM categories WHERE status='active' ORDER BY name");

// Recent adjustments
$adjustments = $conn->query("SELECT sa.*, p.name AS pname, u.full_name AS uname FROM stock_adjustments sa JOIN products p ON p.id=sa.product_id JOIN users u ON u.id=sa.user_id ORDER BY sa.created_at DESC LIMIT 15");
$conn->close();

$pageTitle = 'Inventory Management';
require_once '../../includes/header.php';
?>

<!-- Filters -->
<div class="card mb-4">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Search</label>
        <input type="text" name="search" class="form-control" placeholder="Product name or SKU..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Category</label>
        <select name="category" class="form-select">
          <option value="">All</option>
          <?php $categories->data_seek(0); while($c=$categories->fetch_assoc()): ?>
          <option value="<?= $c['id'] ?>" <?= $catFilter==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Stock Filter</label>
        <select name="stock" class="form-select">
          <option value="">All</option>
          <option value="low" <?= $stockFilter==='low'?'selected':'' ?>>Low Stock</option>
          <option value="out" <?= $stockFilter==='out'?'selected':'' ?>>Out of Stock</option>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-accent flex-fill">Filter</button>
        <a href="index.php" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="row g-4">
  <!-- Stock Table -->
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><i class="bi bi-archive me-2"></i>Stock Levels (<?= $products->num_rows ?>)</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table mb-0">
            <thead><tr>
              <th>SKU</th><th>Product</th><th>Category</th><th>Stock</th><th>Alert</th><th>Status</th>
              <?php if(hasRole('admin','manager')): ?><th></th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php if($products->num_rows===0): ?>
              <tr><td colspan="7" class="text-center py-4" style="color:#555;">No products found.</td></tr>
            <?php endif; ?>
            <?php while($p=$products->fetch_assoc()):
              $pct = $p['low_stock_alert'] > 0 ? min(100, round($p['stock_qty'] / $p['low_stock_alert'] * 50)) : 100;
              $barColor = $p['stock_qty']==0?'#e74c3c':($p['stock_qty']<=$p['low_stock_alert']?'#f39c12':'#2ecc71');
            ?>
            <tr>
              <td><code style="color:#777;font-size:.75rem;"><?= htmlspecialchars($p['sku']) ?></code></td>
              <td style="font-weight:600;"><?= htmlspecialchars($p['name']) ?></td>
              <td><span class="badge" style="background:rgba(91,192,222,0.1);color:#5bc0de;font-size:.7rem;"><?= htmlspecialchars($p['cat']) ?></span></td>
              <td>
                <div style="font-weight:700;font-size:.95rem;"><?= $p['stock_qty'] ?> <span style="color:#555;font-size:.75rem;"><?= $p['unit'] ?></span></div>
                <div style="height:4px;background:rgba(255,255,255,0.08);border-radius:2px;margin-top:4px;width:80px;">
                  <div style="height:100%;width:<?= min(100,$pct) ?>%;background:<?= $barColor ?>;border-radius:2px;transition:width .3s;"></div>
                </div>
              </td>
              <td style="color:#777;"><?= $p['low_stock_alert'] ?></td>
              <td>
                <?php if($p['stock_qty']==0): ?>
                  <span class="badge badge-stock-low">Out of Stock</span>
                <?php elseif($p['stock_qty']<=$p['low_stock_alert']): ?>
                  <span class="badge badge-stock-warn">Low Stock</span>
                <?php else: ?>
                  <span class="badge badge-stock-ok">In Stock</span>
                <?php endif; ?>
              </td>
              <?php if(hasRole('admin','manager')): ?>
              <td>
                <button class="btn btn-sm" style="background:rgba(91,192,222,0.1);color:#5bc0de;border-radius:8px;" onclick="openAdjust(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>', <?= $p['stock_qty'] ?>)">
                  <i class="bi bi-plus-slash-minus"></i>
                </button>
              </td>
              <?php endif; ?>
            </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Adjustments -->
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-clock-history me-2"></i>Recent Adjustments</div>
      <div class="card-body p-0" style="overflow-y:auto;max-height:480px;">
        <?php while($adj=$adjustments->fetch_assoc()): ?>
        <div style="padding:12px 18px;border-bottom:1px solid var(--border);">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div style="font-size:.85rem;font-weight:600;"><?= htmlspecialchars($adj['pname']) ?></div>
              <div style="font-size:.75rem;color:#777;"><?= htmlspecialchars($adj['uname']) ?></div>
            </div>
            <span class="badge" style="<?= $adj['type']==='in'?'background:rgba(46,204,113,0.1);color:#2ecc71;':($adj['type']==='out'?'background:rgba(231,76,60,0.1);color:#e74c3c;':'background:rgba(91,192,222,0.1);color:#5bc0de;') ?>">
              <?= $adj['type']==='in'?'+':($adj['type']==='out'?'-':'=') ?><?= $adj['qty'] ?> <?= $adj['type'] ?>
            </span>
          </div>
          <?php if($adj['reason']): ?>
          <div style="font-size:.75rem;color:#666;margin-top:3px;"><?= htmlspecialchars($adj['reason']) ?></div>
          <?php endif; ?>
          <div style="font-size:.7rem;color:#555;margin-top:2px;"><?= date('d M Y H:i',strtotime($adj['created_at'])) ?></div>
        </div>
        <?php endwhile; ?>
      </div>
    </div>
  </div>
</div>

<!-- Stock Adjustment Modal -->
<?php if(hasRole('admin','manager')): ?>
<div class="modal fade" id="adjustModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-slash-minus me-2"></i>Adjust Stock — <span id="adjProductName"></span></h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="product_id" id="adjProductId">
          <div class="mb-3">
            <label class="form-label">Current Stock</label>
            <input type="text" id="adjCurrentStock" class="form-control" readonly style="opacity:.6;">
          </div>
          <div class="mb-3">
            <label class="form-label">Adjustment Type</label>
            <select name="type" class="form-select" id="adjType" onchange="updateAdjLabel()">
              <option value="in">Stock In (Add)</option>
              <option value="out">Stock Out (Remove)</option>
              <option value="adjustment">Set Exact Quantity</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" id="adjQtyLabel">Quantity to Add</label>
            <input type="number" name="qty" class="form-control" min="1" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Reason</label>
            <input type="text" name="reason" class="form-control" placeholder="e.g. Restock, Damage, Correction...">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-accent"><i class="bi bi-check-lg me-2"></i>Save Adjustment</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function openAdjust(id, name, current) {
  document.getElementById('adjProductId').value = id;
  document.getElementById('adjProductName').textContent = name;
  document.getElementById('adjCurrentStock').value = current;
  updateAdjLabel();
  new bootstrap.Modal(document.getElementById('adjustModal')).show();
}
function updateAdjLabel() {
  const t = document.getElementById('adjType').value;
  const labels = { in:'Quantity to Add', out:'Quantity to Remove', adjustment:'Set New Quantity' };
  document.getElementById('adjQtyLabel').textContent = labels[t];
}
</script>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>