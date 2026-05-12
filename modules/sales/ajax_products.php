<?php
require_once '../../config/app.php';
requireLogin();

$conn = getDBConnection();
$result = $conn->query("SELECT p.id, p.name, p.sku, p.selling_price, p.stock_qty, p.unit, p.category_id, c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.status='active' ORDER BY p.name ASC");
$products = [];
while ($row = $result->fetch_assoc()) $products[] = $row;
$conn->close();
header('Content-Type: application/json');
echo json_encode($products);
?>