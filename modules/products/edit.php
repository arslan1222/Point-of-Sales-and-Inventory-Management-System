<?php
require_once '../../config/app.php';
requireRole('admin', 'manager');

$conn = getDBConnection();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('error','Invalid product.'); redirect(APP_URL.'/modules/products/index.php'); }

$p = $conn->query("SELECT * FROM products WHERE id=$id LIMIT 1")->fetch_assoc();
if (!$p) { setFlash('error','Product not found.'); redirect(APP_URL.'/modules/products/index.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = sanitize($_POST['name'] ?? '');
    $sku          = sanitize($_POST['sku'] ?? '');
    $category_id  = (int)($_POST['category_id'] ?? 0);
    $cost_price   = floatval($_POST['cost_price'] ?? 0);
    $selling_price = floatval($_POST['selling_price'] ?? 0);
    $low_stock_alert = (int)($_POST['low_stock_alert'] ?? 10);
    $unit         = sanitize($_POST['unit'] ?? 'pcs');
    $description  = sanitize($_POST['description'] ?? '');

    if (!$name) $errors[] = 'Product name is required.';
    if (!$sku)  $errors[] = 'SKU is required.';

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE products SET category_id=?,name=?,sku=?,description=?,unit=?,cost_price=?,selling_price=?,low_stock_alert=?,updated_at=NOW() WHERE id=?");
        $stmt->bind_param("issssdiii", $category_id,$name,$sku,$description,$unit,$cost_price,$selling_price,$low_stock_alert,$id);
        if ($stmt->execute()) {
            setFlash('success',"Product '$name' updated.");
            redirect(APP_URL.'/modules/products/index.php');
        } else {
            $errors[] = 'Database error: '.$conn->error;
        }
    }
    $p = array_merge($p, $_POST);
}

$categories = $conn->query("SELECT * FROM categories WHERE status='active' ORDER BY name");
$conn->close();
$pageTitle = 'Edit Product';
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
    <div class="card-header"><i class="bi bi-pencil-square me-2"></i>Edit Product — <?= htmlspecialchars($p['name']) ?></div>
    <div class="card-body p-4">
      <form method="POST">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Product Name</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($p['name']) ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">SKU</label>
            <input type="text" name="sku" class="form-control" value="<?= htmlspecialchars($p['sku']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-select" required>
              <?php while($cat=$categories->fetch_assoc()): ?>
              <option value="<?= $cat['id'] ?>" <?= $p['category_id']==$cat['id']?'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Unit</label>
            <select name="unit" class="form-select">
              <?php foreach(['pcs','kg','g','litre','ml','box','pack','set','dozen'] as $u): ?>
              <option value="<?= $u ?>" <?= $p['unit']===$u?'selected':'' ?>><?= $u ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Low Stock Alert</label>
            <input type="number" name="low_stock_alert" class="form-control" value="<?= $p['low_stock_alert'] ?>" min="0">
          </div>
          <div class="col-md-4">
            <label class="form-label">Cost Price</label>
            <input type="number" name="cost_price" class="form-control" step="0.01" min="0" value="<?= $p['cost_price'] ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Selling Price</label>
            <input type="number" name="selling_price" class="form-control" step="0.01" min="0" value="<?= $p['selling_price'] ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Current Stock</label>
            <input type="text" class="form-control" value="<?= $p['stock_qty'] ?> <?= $p['unit'] ?>" readonly style="opacity:.6;">
            <small class="text-muted">Adjust via Inventory module.</small>
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($p['description']??'') ?></textarea>
          </div>
        </div>
        <div class="d-flex gap-3 mt-4">
          <button type="submit" class="btn btn-accent px-5"><i class="bi bi-check-lg me-2"></i>Update Product</button>
          <a href="index.php" class="btn btn-outline-secondary px-4">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
</div>
<?php require_once '../../includes/footer.php'; ?>