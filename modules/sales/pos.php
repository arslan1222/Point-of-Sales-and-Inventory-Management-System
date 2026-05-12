<?php
require_once '../../config/app.php';
requireLogin();

$conn = getDBConnection();
$categories = $conn->query("SELECT * FROM categories WHERE status='active' ORDER BY name");
$customers  = $conn->query("SELECT * FROM customers ORDER BY is_default DESC, name ASC");
$conn->close();

$pageTitle = 'Point of Sale';
require_once '../../includes/header.php';
?>

<style>
  .pos-wrapper { display:grid; grid-template-columns:1fr 400px; gap:20px; height:calc(100vh - 130px); }
  .qty-btn { width:28px;height:28px;border-radius:6px;border:1px solid var(--border);background:rgba(255,255,255,0.05);color:#fff;cursor:pointer;font-size:.9rem;display:flex;align-items:center;justify-content:center; }
  .qty-btn:hover { background:var(--accent);border-color:var(--accent); }
  .numpad-btn { border:1px solid var(--border);background:rgba(255,255,255,0.05);color:#fff;border-radius:10px;padding:14px;font-size:1.1rem;cursor:pointer;transition:all .15s; }
  .numpad-btn:hover { background:rgba(255,255,255,0.1); }
  .category-pill { cursor:pointer;border-radius:20px;padding:4px 14px;border:1px solid var(--border);background:transparent;color:#888;font-size:.8rem;transition:all .2s;white-space:nowrap; }
  .category-pill:hover,.category-pill.active { background:var(--accent);border-color:var(--accent);color:#fff; }
</style>

<div class="pos-wrapper">
  <!-- LEFT: Product Grid -->
  <div class="pos-products d-flex flex-column gap-3">
    <!-- Search & Category filter -->
    <div class="d-flex gap-3">
      <input type="text" id="productSearch" class="form-control" placeholder="&#128269; Search products..." oninput="filterProducts()">
    </div>
    <div class="d-flex gap-2 overflow-auto pb-1" id="categoryPills">
      <button class="category-pill active" onclick="filterCategory(0, this)">All</button>
      <?php while($c=$categories->fetch_assoc()): ?>
      <button class="category-pill" onclick="filterCategory(<?= $c['id'] ?>, this)"><?= htmlspecialchars($c['name']) ?></button>
      <?php endwhile; ?>
    </div>
    <!-- Product Grid -->
    <div class="row g-3" id="productGrid"></div>
  </div>

  <!-- RIGHT: Cart -->
  <div class="pos-cart">
    <div class="cart-header">
      <div class="d-flex justify-content-between align-items-center">
        <span><i class="bi bi-cart3 me-2 text-accent"></i>Current Order</span>
        <button class="btn btn-sm" style="background:rgba(231,76,60,0.15);color:#e74c3c;border-radius:8px;" onclick="clearCart()"><i class="bi bi-trash"></i></button>
      </div>
      <!-- Customer select -->
      <select id="customerSelect" class="form-select form-select-sm mt-2" style="background:rgba(255,255,255,0.05);">
        <?php while($cu=$customers->fetch_assoc()): ?>
        <option value="<?= $cu['id'] ?>" <?= $cu['is_default']?'selected':'' ?>><?= htmlspecialchars($cu['name']) ?> <?= $cu['is_default']?'(Walk-in)':'' ?></option>
        <?php endwhile; ?>
      </select>
    </div>

    <div class="cart-items" id="cartItems">
      <div class="text-center py-5" id="emptyCart" style="color:#555;">
        <i class="bi bi-cart-x" style="font-size:3rem;"></i>
        <p class="mt-2 mb-0">Cart is empty</p>
        <small>Click a product to add</small>
      </div>
    </div>

    <div class="cart-footer">
      <!-- Totals -->
      <div class="d-flex justify-content-between mb-1" style="font-size:.85rem;color:#888;">
        <span>Subtotal</span><span id="subtotal">$0.00</span>
      </div>

      <!-- Discount -->
      <div class="d-flex align-items-center gap-2 mb-1">
        <span style="font-size:.85rem;color:#888;white-space:nowrap;">Discount</span>
        <select id="discountType" class="form-select form-select-sm" onchange="recalc()" style="width:80px;">
          <option value="fixed">$</option>
          <option value="percent">%</option>
        </select>
        <input type="number" id="discountValue" class="form-control form-control-sm" value="0" min="0" step="0.01" oninput="recalc()" style="width:80px;">
        <span style="font-size:.85rem;color:#e74c3c;margin-left:auto;" id="discountAmount">-$0.00</span>
      </div>

      <div class="d-flex justify-content-between mb-2" style="font-size:.85rem;color:#888;">
        <span>Tax (<?= DEFAULT_TAX_PERCENT ?>%)</span><span id="taxAmount">$0.00</span>
      </div>
      <div class="d-flex justify-content-between mb-3" style="font-size:1.3rem;font-family:'Syne',sans-serif;font-weight:800;color:#fff;">
        <span>TOTAL</span><span id="totalAmount" style="color:#2ecc71;">$0.00</span>
      </div>

      <!-- Payment method -->
      <div class="d-flex gap-2 mb-3">
        <?php foreach(['cash'=>'bi-cash','card'=>'bi-credit-card','mobile'=>'bi-phone'] as $m=>$i): ?>
        <button class="btn btn-sm flex-fill pay-method <?= $m==='cash'?'btn-accent':'' ?>" data-method="<?= $m ?>" onclick="setPayMethod(this)" style="<?= $m!=='cash'?'background:rgba(255,255,255,0.05);border:1px solid var(--border);color:#888;border-radius:10px;':'' ?>">
          <i class="bi <?= $i ?> me-1"></i><?= ucfirst($m) ?>
        </button>
        <?php endforeach; ?>
      </div>

      <!-- Cash tendered (for cash payment) -->
      <div id="cashSection">
        <label class="form-label mb-1" style="font-size:.8rem;">Amount Tendered</label>
        <input type="number" id="amountPaid" class="form-control mb-1" placeholder="0.00" step="0.01" oninput="calcChange()">
        <div class="d-flex justify-content-between" style="font-size:.85rem;">
          <span style="color:#888;">Change</span>
          <span id="changeAmount" style="color:#f39c12;font-weight:700;">$0.00</span>
        </div>
      </div>

      <button class="btn btn-accent w-100 mt-3 py-3" style="font-size:1rem;font-family:'Syne',sans-serif;font-weight:700;letter-spacing:.5px;" onclick="processSale()">
        <i class="bi bi-check-circle me-2"></i>PROCESS SALE
      </button>
    </div>
  </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Sale Complete</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body" id="receiptBody"></div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" onclick="newSale()">New Sale</button>
        <button class="btn btn-accent" onclick="printReceipt()"><i class="bi bi-printer me-2"></i>Print Receipt</button>
      </div>
    </div>
  </div>
</div>

<script>
const TAX_RATE = <?= DEFAULT_TAX_PERCENT ?> / 100;
let cart = {};
let allProducts = [];
let activeCategory = 0;
let payMethod = 'cash';
let lastSaleId = null;

// Load products
function loadProducts() {
    fetch('ajax_products.php')
        .then(res => res.json())
        .then(data => {
            allProducts = data.map(p => ({
                ...p,
                selling_price: parseFloat(p.selling_price),
                stock_qty: parseInt(p.stock_qty)
            }));
            renderProducts();
        });
}

// Render products
function renderProducts(filter = '', catId = 0) {
    const grid = document.getElementById('productGrid');

    let prods = allProducts;

    if (catId) {
        prods = prods.filter(p => p.category_id == catId);
    }

    if (filter) {
        filter = filter.toLowerCase();
        prods = prods.filter(p =>
            p.name.toLowerCase().includes(filter) ||
            p.sku.toLowerCase().includes(filter)
        );
    }

    grid.innerHTML = prods.map(p => `
        <div class="col-6 col-md-4 col-xl-3">
            <div class="pos-product-card ${p.stock_qty <= 0 ? 'out-of-stock' : ''}"
                 onclick="${p.stock_qty > 0 ? `addToCart(${p.id})` : ''}">
                <div style="font-size:.65rem;color:#555;margin-bottom:4px;">${p.sku}</div>
                <div style="font-weight:600;font-size:.875rem;margin-bottom:6px;">${p.name}</div>
                <div class="product-price">$${p.selling_price.toFixed(2)}</div>
                <div class="product-stock">
                    ${p.stock_qty <= 0
                        ? '<span style="color:#e74c3c">Out of stock</span>'
                        : p.stock_qty + ' ' + p.unit + ' left'}
                </div>
            </div>
        </div>
    `).join('');
}

function filterProducts() {
    renderProducts(
        document.getElementById('productSearch').value,
        activeCategory
    );
}

function filterCategory(catId, btn) {
    activeCategory = catId;
    document.querySelectorAll('.category-pill').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    filterProducts();
}

// Add cart
function addToCart(productId) {
    const p = allProducts.find(x => x.id == productId);
    if (!p) return;

    if (cart[productId]) {
        if (cart[productId].qty >= p.stock_qty) {
            alert('Not enough stock!');
            return;
        }
        cart[productId].qty++;
    } else {
        cart[productId] = { ...p, qty: 1 };
    }

    renderCart();
}

// Change qty
function changeQty(productId, delta) {
    if (!cart[productId]) return;

    cart[productId].qty += delta;

    if (cart[productId].qty <= 0) {
        delete cart[productId];
    }

    renderCart();
}

// Remove item
function removeFromCart(productId) {
    delete cart[productId];
    renderCart();
}

// Clear cart
function clearCart() {
    cart = {};
    renderCart();
}

// Render cart
function renderCart() {
    const items = Object.values(cart);
    const cartDiv = document.getElementById('cartItems');

    if (!items.length) {
        cartDiv.innerHTML = `
            <div class="text-center py-5" style="color:#555;">
                <i class="bi bi-cart-x" style="font-size:3rem;"></i>
                <p class="mt-2 mb-0">Cart is empty</p>
                <small>Click a product to add</small>
            </div>
        `;
        recalc();
        return;
    }

    cartDiv.innerHTML = items.map(item => `
        <div class="cart-item">
            <div class="d-flex justify-content-between align-items-start mb-1">
                <div style="font-size:.875rem;font-weight:600;flex:1;">
                    ${item.name}
                </div>
                <button class="qty-btn ms-2"
                        onclick="removeFromCart(${item.id})"
                        style="color:#e74c3c;">
                    <i class="bi bi-x"></i>
                </button>
            </div>

            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <button class="qty-btn" onclick="changeQty(${item.id},-1)">−</button>
                    <span style="min-width:24px;text-align:center;font-weight:700;">
                        ${item.qty}
                    </span>
                    <button class="qty-btn" onclick="changeQty(${item.id},1)">+</button>
                </div>

                <div>
                    <span style="color:#777;">
                        $${item.selling_price.toFixed(2)} × ${item.qty}
                    </span>
                    <span style="color:#2ecc71;font-weight:700;margin-left:8px;">
                        $${(item.selling_price * item.qty).toFixed(2)}
                    </span>
                </div>
            </div>
        </div>
    `).join('');

    recalc();
}

// Calculate totals
function recalc() {
    const items = Object.values(cart);

    let subtotal = items.reduce((sum, item) => {
        return sum + (parseFloat(item.selling_price) * parseInt(item.qty));
    }, 0);

    const discountType = document.getElementById('discountType').value;
    const discountValue = parseFloat(document.getElementById('discountValue').value) || 0;

    let discountAmount = discountType === 'percent'
        ? subtotal * discountValue / 100
        : Math.min(discountValue, subtotal);

    let taxable = subtotal - discountAmount;
    let taxAmount = taxable * TAX_RATE;
    let total = taxable + taxAmount;

    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('discountAmount').textContent = '-$' + discountAmount.toFixed(2);
    document.getElementById('taxAmount').textContent = '$' + taxAmount.toFixed(2);
    document.getElementById('totalAmount').textContent = '$' + total.toFixed(2);

    calcChange();
}

// Change calc
function calcChange() {
    const total = parseFloat(
        document.getElementById('totalAmount').textContent.replace('$', '')
    ) || 0;

    const paid = parseFloat(
        document.getElementById('amountPaid').value
    ) || 0;

    const change = Math.max(0, paid - total);

    document.getElementById('changeAmount').textContent =
        '$' + change.toFixed(2);
}

// Payment method
function setPayMethod(btn) {
    payMethod = btn.dataset.method;

    document.querySelectorAll('.pay-method').forEach(b => {
        b.className = 'btn btn-sm flex-fill pay-method';
        b.style.cssText =
            'background:rgba(255,255,255,0.05);border:1px solid var(--border);color:#888;border-radius:10px;';
    });

    btn.classList.add('btn-accent');
    btn.style.cssText = '';

    document.getElementById('cashSection').style.display =
        payMethod === 'cash' ? 'block' : 'none';
}

// Process sale
function processSale() {
    const items = Object.values(cart);

    if (!items.length) {
        alert('Please add items.');
        return;
    }

    recalc();

    const total = parseFloat(
        document.getElementById('totalAmount').textContent.replace('$', '')
    ) || 0;

    const paid = parseFloat(
        document.getElementById('amountPaid').value
    ) || total;

    if (payMethod === 'cash' && paid < total) {
        alert('Amount paid is less than total.');
        return;
    }

    const payload = {
        customer_id: document.getElementById('customerSelect').value,
        discount_type: document.getElementById('discountType').value,
        discount_value: document.getElementById('discountValue').value,
        amount_paid: paid,
        payment_method: payMethod,
        items: items.map(i => ({
            product_id: i.id,
            qty: i.qty,
            unit_price: i.selling_price
        }))
    };

    fetch('process_sale.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            lastSaleId = data.sale_id;
            document.getElementById('receiptBody').innerHTML = data.receipt_html;
            new bootstrap.Modal(document.getElementById('receiptModal')).show();

            cart = {};
            renderCart();
            loadProducts();
        } else {
            alert(data.message);
        }
    });
}

function printReceipt() {
    window.open('receipt.php?id=' + lastSaleId, '_blank');
}

function newSale() {
    bootstrap.Modal.getInstance(
        document.getElementById('receiptModal')
    ).hide();

    document.getElementById('amountPaid').value = '';
}

// Init
loadProducts();
document.getElementById('cashSection').style.display = 'block';
</script>

<?php require_once '../../includes/footer.php'; ?>