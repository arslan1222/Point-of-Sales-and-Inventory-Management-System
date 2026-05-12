<?php
require_once '../../config/app.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
$conn = getDBConnection();
$sale = $conn->query("SELECT s.*, c.name AS customer, c.phone AS cphone, u.full_name AS cashier FROM sales s JOIN customers c ON c.id=s.customer_id JOIN users u ON u.id=s.user_id WHERE s.id=$id LIMIT 1")->fetch_assoc();
if (!$sale) { echo "Receipt not found."; exit; }

$items = $conn->query("SELECT si.*, p.name, p.sku, p.unit FROM sale_items si JOIN products p ON p.id=si.product_id WHERE si.sale_id=$id");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt — <?= htmlspecialchars($sale['invoice_no']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=DM+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: #f5f5f5; font-family: 'DM Sans', sans-serif; font-size: 13px; color: #222; }
  .receipt {
    background: #fff; width: 320px; margin: 30px auto;
    border-radius: 8px; overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
  }
  .receipt-header { background: #1a1a2e; color: #fff; text-align: center; padding: 24px 20px; }
  .receipt-header h2 { font-size: 1.2rem; font-weight: 700; color: #e94560; margin-bottom: 4px; }
  .receipt-header p { color: #888; font-size: .75rem; }
  .receipt-body { padding: 20px; }
  .receipt-row { display: flex; justify-content: space-between; margin-bottom: 4px; }
  .receipt-row .label { color: #888; }
  .divider { border: none; border-top: 1px dashed #ddd; margin: 12px 0; }
  .item-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
  .item-name { flex: 1; }
  .item-qty { color: #888; font-size: .8rem; }
  .totals { background: #f9f9f9; border-radius: 6px; padding: 12px; margin-top: 12px; }
  .total-row { display: flex; justify-content: space-between; margin-bottom: 4px; font-size: .85rem; }
  .grand-total { font-size: 1.1rem; font-weight: 700; color: #1a1a2e; border-top: 1px solid #ddd; padding-top: 8px; margin-top: 8px; }
  .receipt-footer { text-align: center; padding: 16px 20px; color: #888; font-size: .75rem; border-top: 1px dashed #ddd; }
  .print-btn { display: block; width: 320px; margin: 0 auto 20px; padding: 12px; background: #e94560; color: #fff; border: none; border-radius: 8px; font-size: .9rem; font-weight: 600; cursor: pointer; }
  @media print {
    .print-btn { display: none; }
    body { background: #fff; }
    .receipt { box-shadow: none; margin: 0; border-radius: 0; }
  }
</style>
</head>
<body>
<button class="print-btn" onclick="window.print()">🖨 Print Receipt</button>
<div class="receipt">
  <div class="receipt-header">
    <h2><?= APP_NAME ?></h2>
    <p><?= date('D, d M Y H:i', strtotime($sale['sale_date'])) ?></p>
  </div>
  <div class="receipt-body">
    <div class="receipt-row"><span class="label">Invoice No.</span><span style="font-weight:600;"><?= htmlspecialchars($sale['invoice_no']) ?></span></div>
    <div class="receipt-row"><span class="label">Customer</span><span><?= htmlspecialchars($sale['customer']) ?></span></div>
    <?php if ($sale['cphone'] && $sale['cphone'] !== '0000000000'): ?>
    <div class="receipt-row"><span class="label">Phone</span><span><?= htmlspecialchars($sale['cphone']) ?></span></div>
    <?php endif; ?>
    <div class="receipt-row"><span class="label">Cashier</span><span><?= htmlspecialchars($sale['cashier']) ?></span></div>
    <div class="receipt-row"><span class="label">Payment</span><span><?= ucfirst($sale['payment_method']) ?></span></div>

    <hr class="divider">
    <div style="font-weight:600;margin-bottom:8px;">ITEMS</div>
    <?php while($item=$items->fetch_assoc()): ?>
    <div class="item-row">
      <div class="item-name">
        <div><?= htmlspecialchars($item['name']) ?></div>
        <div class="item-qty"><?= $item['qty'] ?> <?= $item['unit'] ?> × $<?= number_format($item['unit_price'],2) ?></div>
      </div>
      <div style="font-weight:600;">$<?= number_format($item['subtotal'],2) ?></div>
    </div>
    <?php endwhile; ?>

    <div class="totals">
      <div class="total-row"><span>Subtotal</span><span>$<?= number_format($sale['subtotal'],2) ?></span></div>
      <?php if ($sale['discount_amount'] > 0): ?>
      <div class="total-row" style="color:#e94560;"><span>Discount (<?= $sale['discount_type']==='percent'?$sale['discount_value'].'%':'$'.$sale['discount_value'] ?>)</span><span>-$<?= number_format($sale['discount_amount'],2) ?></span></div>
      <?php endif; ?>
      <?php if ($sale['tax_amount'] > 0): ?>
      <div class="total-row" style="color:#888;"><span>Tax (<?= $sale['tax_percent'] ?>%)</span><span>$<?= number_format($sale['tax_amount'],2) ?></span></div>
      <?php endif; ?>
      <div class="total-row grand-total"><span>TOTAL</span><span>$<?= number_format($sale['total_amount'],2) ?></span></div>
      <div class="total-row" style="color:#888;"><span>Paid (<?= ucfirst($sale['payment_method']) ?>)</span><span>$<?= number_format($sale['amount_paid'],2) ?></span></div>
      <?php if ($sale['change_amount'] > 0): ?>
      <div class="total-row" style="color:#f39c12;font-weight:600;"><span>Change</span><span>$<?= number_format($sale['change_amount'],2) ?></span></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="receipt-footer">
    <div style="font-size:1.5rem;margin-bottom:6px;">🙏</div>
    <strong>Thank you for your purchase!</strong><br>
    Keep this receipt for your records.
  </div>
</div>
</body>
</html>