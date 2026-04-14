/**
 * add_product.js – Add new product form logic
 * BUG FIX: was listening to '#addProductForm' but HTML id is 'add-product-form'
 */

// Pre-fill fields from URL query params (used by "Order More" link on dashboard)
(function prefillFromParams() {
  const params = new URLSearchParams(window.location.search);
  const map = {
    name: 'ap-name',
    category: 'ap-category',
    price: 'ap-price',
    cost: 'ap-cost',
    supplier: 'ap-supplier',
    storage: 'ap-storage',
  };
  let anyFilled = false;
  for (const [param, id] of Object.entries(map)) {
    const val = params.get(param);
    if (val) {
      const el = document.getElementById(id);
      if (el) { el.value = val; anyFilled = true; }
    }
  }
  // Focus stock field when coming from low-stock alert
  if (anyFilled) {
    const stockEl = document.getElementById('ap-stock');
    if (stockEl) stockEl.focus();
  }
})();

// ── Form submission ─────────────────────────────────────────────────────────
document.getElementById('add-product-form').addEventListener('submit', async function (e) {
  e.preventDefault();

  const msgEl = document.getElementById('ap-message');
  const btn = document.getElementById('ap-submit');

  btn.disabled = true;
  btn.textContent = 'Saving…';

  const payload = {
    name: document.getElementById('ap-name').value.trim(),
    category: document.getElementById('ap-category').value,
    stock: parseInt(document.getElementById('ap-stock').value) || 0,
    price: parseFloat(document.getElementById('ap-price').value) || 0,
    cost: parseFloat(document.getElementById('ap-cost').value) || 0,
    supplier: document.getElementById('ap-supplier').value.trim(),
    storage: document.getElementById('ap-storage').value.trim(),
  };

  const data = await apiCall(`${window.env.API_URL}/api/products.php`, {
    method: 'POST',
    body: payload
  });

  if (data.success) {
    showFormMsg(msgEl, 'success', 'Product added successfully!');
    document.getElementById('add-product-form').reset();
    setTimeout(() => { window.location.href = 'products.html'; }, 1200);
  } else {
    showFormMsg(msgEl, 'error', ' ' + data.message);
  }

  btn.disabled = false;
  btn.textContent = 'Save Product';
});

function showFormMsg(el, type, text) {
  el.textContent = text;
  el.className = 'form-message ' + type;
  el.style.display = 'block';
  setTimeout(() => { el.style.display = 'none'; }, 4000);
}
