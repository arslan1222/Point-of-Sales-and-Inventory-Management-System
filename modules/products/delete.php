<?php
require_once '../../config/app.php';
requireRole('admin', 'manager');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $conn = getDBConnection();
        // Soft delete
        $conn->query("UPDATE products SET status='inactive' WHERE id=$id");
        $conn->close();
        setFlash('success', 'Product removed successfully.');
    }
}
redirect(APP_URL . '/modules/products/index.php');
?>