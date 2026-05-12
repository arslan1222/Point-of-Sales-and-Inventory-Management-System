<?php
require_once '../../config/app.php';
requireLogin();

$conn = getDBConnection();
$errors = [];

// Handle add customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $name  = sanitize($_POST['name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        if (!$name) $errors[] = 'Customer name is required.';
        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO customers (name, phone, email, address) VALUES (?,?,?,?)");
            $stmt->bind_param("ssss", $name,$phone,$email,$address);
            if ($stmt->execute()) {
                setFlash('success', "Customer '$name' added.");
                redirect(APP_URL.'/modules/customers/index.php');
            } else {
                $errors[] = 'Error saving customer.';
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) { $conn->query("DELETE FROM customers WHERE id=$id AND is_default=0"); }
        setFlash('success', 'Customer deleted.');
        redirect(APP_URL.'/modules/customers/index.php');
    }
}

$search = sanitize($_GET['search'] ?? '');
$where  = $search ? "WHERE (name LIKE '%$search%' OR phone LIKE '%$search%' OR email LIKE '%$search%')" : '';
$customers = $conn->query("SELECT c.*, (SELECT COUNT(*) FROM sales WHERE customer_id=c.id AND status='completed') AS total_orders, (SELECT COALESCE(SUM(total_amount),0) FROM sales WHERE customer_id=c.id AND status='completed') AS total_spent FROM customers c $where ORDER BY c.is_default DESC, c.name ASC");
$conn->close();

$pageTitle = 'Customers';
require_once '../../includes/header.php';
?>

<div class="row g-4">
  <!-- Add Customer -->
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-person-plus me-2"></i>Add Customer</div>
      <div class="card-body">
        <?php if ($errors): ?>
          <div class="alert alert-danger border-0 rounded-3 mb-3 py-2" style="font-size:.85rem;">
            <?php foreach($errors as $e): ?><?= $e ?><br><?php endforeach; ?>
          </div>
        <?php endif; ?>
        <form method="POST">
          <input type="hidden" name="action" value="add">
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" placeholder="Customer name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone" class="form-control" placeholder="+1 234 567 8900">
          </div>
          <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="email@example.com">
          </div>
          <div class="mb-3">
            <label class="form-label">Address</label>
            <textarea name="address" class="form-control" rows="2" placeholder="Street, City..."></textarea>
          </div>
          <button type="submit" class="btn btn-accent w-100"><i class="bi bi-plus-lg me-2"></i>Add Customer</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Customer List -->
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-people me-2"></i>Customer List (<?= $customers->num_rows ?>)</span>
        <form method="GET" class="d-flex gap-2" style="width:280px;">
          <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name, phone..." value="<?= htmlspecialchars($search) ?>">
          <button type="submit" class="btn btn-sm btn-accent px-3">Go</button>
        </form>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table mb-0">
            <thead><tr>
              <th>Name</th><th>Phone</th><th>Orders</th><th>Total Spent</th><th></th>
            </tr></thead>
            <tbody>
            <?php if ($customers->num_rows===0): ?>
              <tr><td colspan="5" class="text-center py-4" style="color:#555;">No customers found.</td></tr>
            <?php endif; ?>
            <?php while($c=$customers->fetch_assoc()): ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span style="width:32px;height:32px;border-radius:50%;background:<?= $c['is_default']?'rgba(91,192,222,0.15)':'rgba(233,69,96,0.15)' ?>;color:<?= $c['is_default']?'#5bc0de':'#e94560' ?>;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;">
                    <?= strtoupper(substr($c['name'],0,1)) ?>
                  </span>
                  <div>
                    <div style="font-weight:600;"><?= htmlspecialchars($c['name']) ?></div>
                    <?php if($c['is_default']): ?><small class="text-muted">Walk-in Default</small><?php elseif($c['email']): ?><small style="color:#666;"><?= htmlspecialchars($c['email']) ?></small><?php endif; ?>
                  </div>
                </div>
              </td>
              <td style="color:#888;"><?= $c['phone'] && $c['phone']!=='0000000000'?htmlspecialchars($c['phone']):'—' ?></td>
              <td>
                <span class="badge" style="background:rgba(91,192,222,0.1);color:#5bc0de;"><?= $c['total_orders'] ?> orders</span>
              </td>
              <td style="font-weight:700;color:#2ecc71;"><?= formatCurrency($c['total_spent']) ?></td>
              <td>
                <?php if(!$c['is_default']): ?>
                <form method="POST" style="display:inline;" id="delc<?= $c['id'] ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button type="button" class="btn btn-sm" style="background:rgba(231,76,60,0.1);color:#e74c3c;border-radius:8px;" onclick="confirmDelete('Delete this customer?','delc<?= $c['id'] ?>')"><i class="bi bi-trash"></i></button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>