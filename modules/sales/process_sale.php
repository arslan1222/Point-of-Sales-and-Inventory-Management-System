<?php
require_once '../../config/app.php';
requireLogin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) { echo json_encode(['success'=>false,'message'=>'Invalid data.']); exit; }

$customer_id   = (int)($data['customer_id'] ?? 1);
$discount_type = in_array($data['discount_type'],['fixed','percent']) ? $data['discount_type'] : 'fixed';
$discount_value = floatval($data['discount_value'] ?? 0);
$amount_paid   = floatval($data['amount_paid'] ?? 0);
$payment_method = in_array($data['payment_method'],['cash','card','mobile']) ? $data['payment_method'] : 'cash';
$items         = $data['items'] ?? [];

if (empty($items)) { echo json_encode(['success'=>false,'message'=>'No items in cart.']); exit; }

$conn = getDBConnection();
$conn->begin_transaction();

try {
    $subtotal = 0;
    $validItems = [];

    foreach ($items as $item) {
        $pid      = (int)$item['product_id'];
        $qty      = (int)$item['qty'];
        $unit_price = floatval($item['unit_price']);

        // Check stock
        $stockRow = $conn->query("SELECT stock_qty, name FROM products WHERE id=$pid AND status='active' FOR UPDATE")->fetch_assoc();
        if (!$stockRow || $stockRow['stock_qty'] < $qty) {
            throw new Exception("Insufficient stock for product ID $pid.");
        }
        $validItems[] = ['product_id'=>$pid,'qty'=>$qty,'unit_price'=>$unit_price,'subtotal'=>$unit_price*$qty,'name'=>$stockRow['name']];
        $subtotal += $unit_price * $qty;
    }

    // Calculate discount
    $discount_amount = $discount_type === 'percent' ? $subtotal * $discount_value / 100 : min($discount_value, $subtotal);
    $taxable   = $subtotal - $discount_amount;
    $tax_pct   = DEFAULT_TAX_PERCENT;
    $tax_amount = $taxable * $tax_pct / 100;
    $total     = $taxable + $tax_amount;
    $change    = max(0, $amount_paid - $total);

    // Generate unique invoice
    do {
        $invoice_no = generateInvoiceNo();
        $exists = $conn->query("SELECT id FROM sales WHERE invoice_no='$invoice_no'")->num_rows;
    } while ($exists);

    $uid = $_SESSION['user_id'];
    $stmt = $conn->prepare("INSERT INTO sales (invoice_no,customer_id,user_id,subtotal,discount_type,discount_value,discount_amount,tax_percent,tax_amount,total_amount,amount_paid,change_amount,payment_method) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param("siidsddddddds", $invoice_no,$customer_id,$uid,$subtotal,$discount_type,$discount_value,$discount_amount,$tax_pct,$tax_amount,$total,$amount_paid,$change,$payment_method);
    $stmt->execute();
    $sale_id = $conn->insert_id;

    // Insert items & deduct stock
    $siStmt = $conn->prepare("INSERT INTO sale_items (sale_id,product_id,qty,unit_price,subtotal) VALUES (?,?,?,?,?)");
    foreach ($validItems as $vi) {
        $siStmt->bind_param("iiddd", $sale_id,$vi['product_id'],$vi['qty'],$vi['unit_price'],$vi['subtotal']);
        $siStmt->execute();
        $conn->query("UPDATE products SET stock_qty=stock_qty-{$vi['qty']} WHERE id={$vi['product_id']}");
    }

    $conn->commit();

    // Build receipt HTML
    $customer = $conn->query("SELECT name FROM customers WHERE id=$customer_id")->fetch_assoc();
    $html  = "<div style='font-family:monospace;font-size:.875rem;'>";
    $html .= "<div class='text-center mb-3'><strong style='font-size:1.1rem;'>".APP_NAME."</strong><br><small class='text-muted'>".date('D, d M Y H:i')."</small></div>";
    $html .= "<div class='d-flex justify-content-between'><span class='text-muted'>Invoice</span><span>$invoice_no</span></div>";
    $html .= "<div class='d-flex justify-content-between mb-3'><span class='text-muted'>Customer</span><span>".htmlspecialchars($customer['name'])."</span></div>";
    $html .= "<hr style='border-color:rgba(255,255,255,0.1)'>";
    foreach ($validItems as $vi) {
        $html .= "<div class='d-flex justify-content-between'><span>".htmlspecialchars($vi['name'])." ×{$vi['qty']}</span><span>$".number_format($vi['subtotal'],2)."</span></div>";
    }
    $html .= "<hr style='border-color:rgba(255,255,255,0.1)'>";
    $html .= "<div class='d-flex justify-content-between'><span class='text-muted'>Subtotal</span><span>$".number_format($subtotal,2)."</span></div>";
    if ($discount_amount>0) $html .= "<div class='d-flex justify-content-between' style='color:#e94560'><span>Discount</span><span>-$".number_format($discount_amount,2)."</span></div>";
    if ($tax_amount>0)      $html .= "<div class='d-flex justify-content-between text-muted'><span>Tax</span><span>$".number_format($tax_amount,2)."</span></div>";
    $html .= "<div class='d-flex justify-content-between mt-2' style='font-size:1.1rem;font-weight:700;'><span>TOTAL</span><span style='color:#2ecc71;'>$".number_format($total,2)."</span></div>";
    $html .= "<div class='d-flex justify-content-between text-muted'><span>Paid (".ucfirst($payment_method).")</span><span>$".number_format($amount_paid,2)."</span></div>";
    if ($change>0) $html .= "<div class='d-flex justify-content-between' style='color:#f39c12;'><span>Change</span><span>$".number_format($change,2)."</span></div>";
    $html .= "<div class='text-center mt-3 text-muted small'>Thank you for your purchase!</div></div>";

    echo json_encode(['success'=>true,'sale_id'=>$sale_id,'invoice_no'=>$invoice_no,'receipt_html'=>$html]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
$conn->close();
?>