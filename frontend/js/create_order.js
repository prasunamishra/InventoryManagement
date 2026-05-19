/**
 * create_order.js – Multi-product logistics order creation
 */

// Product catalogue loaded from API: { id, name, category, price, stock }
let _coProducts = [];
let _itemCount  = 0;

// ── Invoice auto-generate ────────────────────────────────────────────────────
function generateLogisticsInvoice() {
  const num = 'LOG-' + String(Math.floor(Math.random() * 9000) + 1000);
  const el = document.getElementById('co-invoice');
  if (el) el.value = num;
}

// ── Compute & display order total ────────────────────────────────────────────
function refreshOrderTotal() {
  const rows = document.querySelectorAll('.order-item-row');
  let total = 0;
  rows.forEach(row => {
    const qty   = parseFloat(row.querySelector('.item-qty').value)   || 0;
    const price = parseFloat(row.querySelector('.item-price').value) || 0;
    total += qty * price;
  });
  const bar = document.getElementById('order-summary-bar');
  if (bar) {
    bar.style.display = rows.length ? 'flex' : 'none';
    document.getElementById('order-total-display').textContent = `Rs ${total.toFixed(2)}`;
  }
}

// ── Build one product row ─────────────────────────────────────────────────────
function buildItemRow(rowIndex) {
  const row = document.createElement('div');
  row.className = 'order-item-row';
  row.dataset.rowIndex = rowIndex;

  // Category select
  const catWrap = document.createElement('div');
  catWrap.innerHTML = `<label class="ap-label">CATEGORY</label>`;
  const catSel = document.createElement('select');
  catSel.className = 'ap-input ap-select item-category';
  catSel.innerHTML = '<option value="" disabled selected>Select category</option>';
  const categories = [...new Set(_coProducts.map(p => p.category))].sort();
  categories.forEach(cat => {
    const o = document.createElement('option');
    o.value = cat; o.textContent = cat;
    catSel.appendChild(o);
  });
  catWrap.appendChild(catSel);

  // Product select
  const prodWrap = document.createElement('div');
  prodWrap.innerHTML = `<label class="ap-label">PRODUCT</label>`;
  const prodSel = document.createElement('select');
  prodSel.className = 'ap-input ap-select item-product';
  prodSel.disabled = true;
  prodSel.innerHTML = '<option value="" disabled selected>First select category</option>';
  const stockBadge = document.createElement('div');
  stockBadge.className = 'stock-badge';
  prodWrap.appendChild(prodSel);
  prodWrap.appendChild(stockBadge);

  // Quantity
  const qtyWrap = document.createElement('div');
  qtyWrap.innerHTML = `<label class="ap-label">QTY</label>`;
  const qtyInput = document.createElement('input');
  qtyInput.type = 'number'; qtyInput.min = '1'; qtyInput.value = '1';
  qtyInput.className = 'ap-input item-qty';
  qtyWrap.appendChild(qtyInput);

  // Unit price
  const priceWrap = document.createElement('div');
  priceWrap.innerHTML = `<label class="ap-label">PRICE (RS)</label>`;
  const priceInput = document.createElement('input');
  priceInput.type = 'number'; priceInput.step = '0.01'; priceInput.min = '0'; priceInput.value = '0';
  priceInput.className = 'ap-input item-price';
  priceWrap.appendChild(priceInput);

  // Remove button
  const removeBtn = document.createElement('button');
  removeBtn.type = 'button';
  removeBtn.className = 'remove-item-btn';
  removeBtn.innerHTML = '&times;';
  removeBtn.title = 'Remove this item';

  // ── Wiring ────────────────────────────────────────────────────────────────
  catSel.addEventListener('change', function () {
    const cat = this.value;
    prodSel.replaceChildren();
    const defaultOpt = document.createElement('option');
    defaultOpt.value = ''; defaultOpt.disabled = true; defaultOpt.selected = true;
    defaultOpt.textContent = 'Select a product';
    prodSel.appendChild(defaultOpt);

    const filtered = _coProducts.filter(p => p.category === cat && p.status !== 'inactive');
    filtered.forEach(p => {
      const o = document.createElement('option');
      o.value = p.id;
      o.textContent = p.name;
      o.dataset.price = p.price;
      o.dataset.stock = p.stock;
      prodSel.appendChild(o);
    });
    prodSel.disabled = filtered.length === 0;
    stockBadge.textContent = '';
    priceInput.value = '0';
  });

  prodSel.addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    const stock = parseInt(opt.dataset.stock, 10) || 0;
    const price = parseFloat(opt.dataset.price)  || 0;
    priceInput.value = price.toFixed(2);
    stockBadge.textContent = `Available: ${stock} units`;
    stockBadge.className = 'stock-badge ' + (stock < 5 ? 'low' : 'ok');
    refreshOrderTotal();
  });

  qtyInput.addEventListener('input', refreshOrderTotal);
  priceInput.addEventListener('input', refreshOrderTotal);

  removeBtn.addEventListener('click', function () {
    const list = document.getElementById('order-items-list');
    if (list.children.length === 1) {
      showAlertPopup('Minimum Item', 'An order must have at least one product.');
      return;
    }
    row.remove();
    refreshOrderTotal();
  });

  row.appendChild(catWrap);
  row.appendChild(prodWrap);
  row.appendChild(qtyWrap);
  row.appendChild(priceWrap);
  row.appendChild(removeBtn);

  return row;
}

// ── Main DOMContentLoaded ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async function () {
  document.getElementById('co-gen-invoice')?.addEventListener('click', generateLogisticsInvoice);

  // Load products from API
  const data = await apiCall(`${window.env.API_URL}/api/products.php`);
  if (data && data.success && data.products) {
    _coProducts = data.products.filter(p => p.status !== 'inactive');
  }

  // Add first row
  const list = document.getElementById('order-items-list');
  list.appendChild(buildItemRow(_itemCount++));
  refreshOrderTotal();

  document.getElementById('add-item-btn').addEventListener('click', () => {
    list.appendChild(buildItemRow(_itemCount++));
    refreshOrderTotal();
  });

  // ── Form submit ───────────────────────────────────────────────────────────
  document.getElementById('create-order-form').addEventListener('submit', async function (e) {
    e.preventDefault();

    const invoice  = document.getElementById('co-invoice').value.trim().toUpperCase();
    const customer = document.getElementById('co-customer').value.trim();
    const address  = document.getElementById('co-address').value.trim();
    const status   = document.getElementById('co-status').value;
    const notes    = document.getElementById('co-notes').value.trim();
    const msg      = document.getElementById('co-msg');
    const btn      = document.getElementById('co-submit');

    if (!invoice) {
      showAlertPopup('Invoice Required', 'Please enter or generate an invoice number.');
      return;
    }
    if (!customer || !address) {
      msg.textContent = 'Customer name and delivery address are required.';
      msg.className = 'form-message error';
      msg.hidden = false;
      return;
    }

    // Collect items
    const rows  = document.querySelectorAll('.order-item-row');
    const items = [];
    let valid = true;

    rows.forEach((row, idx) => {
      const prodSel  = row.querySelector('.item-product');
      const qtyInput = row.querySelector('.item-qty');
      const priceInp = row.querySelector('.item-price');

      const productId = parseInt(prodSel.value, 10);
      const qty       = parseInt(qtyInput.value, 10)   || 0;
      const price     = parseFloat(priceInp.value)      || 0;

      if (!productId || qty <= 0) {
        showAlertPopup('Incomplete Item', `Item #${idx + 1}: Please select a product and enter a valid quantity.`);
        valid = false;
        return;
      }

      // Frontend stock check
      const opt = prodSel.options[prodSel.selectedIndex];
      const available = parseInt(opt.dataset.stock, 10) || 0;
      if (qty > available) {
        showStockAlert(available);
        valid = false;
        return;
      }

      items.push({ product_id: productId, quantity: qty, unit_price: price });
    });

    if (!valid || items.length === 0) return;

    btn.disabled = true;
    btn.textContent = 'Creating…';

    const result = await apiCall(`${window.env.API_URL}/api/logistics.php`, {
      method: 'POST',
      body: { invoice_number: invoice, customer, address, status, notes, items }
    });

    if (result.success) {
      // Check if this was queued as a pending request (Logistics Coordinator)
      if (result.request_id) {
        msg.textContent = '⏳ Your order request has been submitted for Admin/Supervisor approval.';
        msg.className = 'form-message success';
        msg.hidden = false;
        btn.disabled = false;
        btn.textContent = 'Create Logistics Order';
        setTimeout(() => { window.location.href = 'logistics.html'; }, 2200);
      } else {
        msg.textContent = '✓ Order created successfully!';
        msg.className = 'form-message success';
        msg.hidden = false;
        this.reset();
        setTimeout(() => { window.location.href = 'logistics.html'; }, 1200);
      }
    } else if (result.message?.toLowerCase().includes('invoice')) {
      showAlertPopup('Invoice Error', result.message);
    } else if (result.message?.toLowerCase().includes('stock') || result.message?.toLowerCase().includes('insufficient')) {
      showAlertPopup('Stock Error', result.message);
    } else {
      msg.textContent = result.message || 'Failed to create order.';
      msg.className = 'form-message error';
      msg.hidden = false;
    }

    btn.disabled = false;
    btn.textContent = 'Create Logistics Order';
  });
});
