<?php
require_once '../../config/app.php';
requireRole('admin', 'manager');

$conn = getDBConnection();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = sanitize($_POST['name'] ?? '');
    $sku          = sanitize($_POST['sku'] ?? '');
    $category_id  = (int)($_POST['category_id'] ?? 0);
    $cost_price   = floatval($_POST['cost_price'] ?? 0);
    $selling_price = floatval($_POST['selling_price'] ?? 0);
    $stock_qty    = (int)($_POST['stock_qty'] ?? 0);
    $low_stock_alert = (int)($_POST['low_stock_alert'] ?? 10);
    $unit         = sanitize($_POST['unit'] ?? 'pcs');
    $description  = sanitize($_POST['description'] ?? '');

    if (!$name) $errors[] = 'Product name is required.';
    if (!$sku)  $errors[] = 'SKU is required.';
    if (!$category_id) $errors[] = 'Category is required.';
    if ($selling_price <= 0) $errors[] = 'Selling price must be greater than 0.';

    if (empty($errors)) {
        $stmt = $conn->prepare("
    INSERT INTO products
    (category_id, name, sku, description, unit, cost_price, selling_price, stock_qty, low_stock_alert)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "issssddii",
    $category_id,
    $name,
    $sku,
    $description,
    $unit,
    $cost_price,
    $selling_price,
    $stock_qty,
    $low_stock_alert
);
        if ($stmt->execute()) {
            // Log stock adjustment
            $pid = $conn->insert_id;
            if ($stock_qty > 0) {
                $uid = $_SESSION['user_id'];
                $conn->query("INSERT INTO stock_adjustments (product_id,user_id,type,qty,reason) VALUES ($pid,$uid,'in',$stock_qty,'Initial stock on product creation')");
            }
            setFlash('success', "Product '$name' added successfully.");
            redirect(APP_URL . '/modules/products/index.php');
        } else {
            $errors[] = 'SKU already exists or database error.';
        }
    }
}

$categories = $conn->query("SELECT * FROM categories WHERE status='active' ORDER BY name");
$conn->close();
$pageTitle = 'Add Product';
require_once '../../includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-lg-8">
  <?php if ($errors): ?>
    <div class="alert alert-danger border-0 rounded-3 mb-4">
      <ul class="mb-0"><?php foreach($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>
  <div class="card">
    <div class="card-header"><i class="bi bi-plus-circle me-2"></i>Add New Product</div>
    <div class="card-body p-4">
      <form method="POST">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Product Name</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name']??'') ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">SKU</label>
            <input type="text" name="sku" class="form-control" value="<?= htmlspecialchars($_POST['sku']??'') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-select" required>
              <option value="">Select category</option>
              <?php while($cat=$categories->fetch_assoc()): ?>
              <option value="<?= $cat['id'] ?>" <?= ($_POST['category_id']??'')==$cat['id']?'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Unit</label>
            <select name="unit" class="form-select">
              <?php foreach(['pcs','kg','g','litre','ml','box','pack','set','dozen'] as $u): ?>
              <option value="<?= $u ?>" <?= ($_POST['unit']??'pcs')===$u?'selected':'' ?>><?= $u ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Low Stock Alert</label>
            <input type="number" name="low_stock_alert" class="form-control" value="<?= $_POST['low_stock_alert']??10 ?>" min="0">
          </div>
          <div class="col-md-4">
            <label class="form-label">Cost Price</label>
            <div class="input-group">
              <span class="input-group-text" style="background:rgba(255,255,255,0.05);border-color:var(--border);color:#888;">$</span>
              <input type="number" name="cost_price" class="form-control" step="0.01" min="0" value="<?= $_POST['cost_price']??0 ?>">
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Selling Price</label>
            <div class="input-group">
              <span class="input-group-text" style="background:rgba(255,255,255,0.05);border-color:var(--border);color:#888;">$</span>
              <input type="number" name="selling_price" class="form-control" step="0.01" min="0" value="<?= $_POST['selling_price']??0 ?>" required>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Initial Stock</label>
            <input type="number" name="stock_qty" class="form-control" min="0" value="<?= $_POST['stock_qty']??0 ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($_POST['description']??'') ?></textarea>
          </div>
        </div>
        <div class="d-flex gap-3 mt-4">
          <button type="submit" class="btn btn-accent px-5"><i class="bi bi-check-lg me-2"></i>Save Product</button>
          <a href="index.php" class="btn btn-outline-secondary px-4">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
</div>
<?php require_once '../../includes/footer.php'; ?>