<?php
require_once '../../config/app.php';
requireLogin();

$conn = getDBConnection();

// Filters
$search   = sanitize($_GET['search'] ?? '');
$catFilter = (int)($_GET['category'] ?? 0);
$stockFilter = sanitize($_GET['stock'] ?? '');

$where = ["p.status='active'"];
$params = []; $types = '';
if ($search) { $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)"; $like="%$search%"; $params[]=$like;$params[]=$like;$params[]=$like; $types.='sss'; }
if ($catFilter) { $where[] = "p.category_id=?"; $params[]=$catFilter; $types.='i'; }
if ($stockFilter === 'low')  $where[] = "p.stock_qty <= p.low_stock_alert";
if ($stockFilter === 'out')  $where[] = "p.stock_qty = 0";

$sql = "SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE " . implode(' AND ', $where) . " ORDER BY p.name ASC";
$stmt = $conn->prepare($sql);
if ($types) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$products = $stmt->get_result();

$categories = $conn->query("SELECT * FROM categories WHERE status='active' ORDER BY name");
$conn->close();

$pageTitle = 'Products';
require_once '../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div></div>
  <?php if (hasRole('admin','manager')): ?>
  <a href="create.php" class="btn btn-accent px-4"><i class="bi bi-plus-lg me-2"></i>Add Product</a>
  <?php endif; ?>
</div>

<!-- Filters -->
<div class="card mb-4">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Search</label>
        <input type="text" name="search" class="form-control" placeholder="Name, SKU, Barcode..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Category</label>
        <select name="category" class="form-select">
          <option value="">All Categories</option>
          <?php $categories->data_seek(0); while($cat=$categories->fetch_assoc()): ?>
          <option value="<?= $cat['id'] ?>" <?= $catFilter==$cat['id']?'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Stock Status</label>
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

<!-- Products Table -->
<div class="card">
  <div class="card-header"><i class="bi bi-box-seam me-2"></i>Product List (<?= $products->num_rows ?> items)</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0">
        <thead><tr>
          <th>SKU</th><th>Product</th><th>Category</th><th>Cost</th><th>Price</th><th>Stock</th><th>Status</th>
          <?php if (hasRole('admin','manager')): ?><th class="text-end">Actions</th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php if ($products->num_rows === 0): ?>
        <tr><td colspan="8" class="text-center py-4" style="color:#555;">No products found.</td></tr>
        <?php endif; ?>
        <?php while($p=$products->fetch_assoc()): ?>
        <?php
          $stockClass = 'badge-stock-ok';
          $stockLabel = 'In Stock';
          if ($p['stock_qty'] == 0) { $stockClass='badge-stock-low'; $stockLabel='Out of Stock'; }
          elseif ($p['stock_qty'] <= $p['low_stock_alert']) { $stockClass='badge-stock-warn'; $stockLabel='Low Stock'; }
        ?>
        <tr>
          <td><code style="color:#777;"><?= htmlspecialchars($p['sku']) ?></code></td>
          <td>
            <div style="font-weight:600;"><?= htmlspecialchars($p['name']) ?></div>
            <div style="font-size:.75rem;color:#555;"><?= htmlspecialchars($p['unit']) ?></div>
          </td>
          <td><span class="badge" style="background:rgba(91,192,222,0.1);color:#5bc0de;"><?= htmlspecialchars($p['category_name']) ?></span></td>
          <td><?= formatCurrency($p['cost_price']) ?></td>
          <td style="font-weight:700;color:#2ecc71;"><?= formatCurrency($p['selling_price']) ?></td>
          <td>
            <span class="badge <?= $stockClass ?>"><?= $p['stock_qty'] ?> - <?= $stockLabel ?></span>
          </td>
          <td><span class="badge" style="background:rgba(46,204,113,0.1);color:#2ecc71;">Active</span></td>
          <?php if (hasRole('admin','manager')): ?>
          <td class="text-end">
            <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-accent me-1" data-bs-toggle="tooltip" title="Edit"><i class="bi bi-pencil"></i></a>
            <form method="POST" action="delete.php" style="display:inline;" id="del<?= $p['id'] ?>">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <button type="button" class="btn btn-sm" style="background:rgba(231,76,60,0.15);color:#e74c3c;border-radius:8px;" onclick="confirmDelete('Delete this product?','del<?= $p['id'] ?>')" data-bs-toggle="tooltip" title="Delete"><i class="bi bi-trash"></i></button>
            </form>
          </td>
          <?php endif; ?>
        </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>