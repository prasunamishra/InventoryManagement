/**
 * create_order.js – Create Logistics Order page logic
 */

document.getElementById('create-order-form').addEventListener('submit', async function (e) {
  e.preventDefault();

  const customer = document.getElementById('co-customer').value.trim();
  const address = document.getElementById('co-address').value.trim();
  const product = document.getElementById('co-product').value;
  const qty = document.getElementById('co-qty').value;
  const status = document.getElementById('co-status').value;
  const msg = document.getElementById('co-msg');
  const btn = document.getElementById('co-submit');

  if (!customer || !address || !product) {
    msg.textContent = 'Please fill in all required fields.';
    msg.className = 'form-message error';
    msg.hidden = false;
    return;
  }

  const productStr = qty > 0 ? `${product} x${qty}` : product;

  btn.disabled = true;
  btn.innerHTML = `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg> Creating...`;

  const data = await apiCall(`${window.env.API_URL}/api/logistics.php`, {
    method: 'POST',
    body: { customer, address, product: productStr, status }
  });

  if (data.success) {
    msg.textContent = 'Order created successfully!';
    msg.className = 'form-message success';
    msg.hidden = false;
    this.reset();
    setTimeout(() => { window.location.href = 'logistics.html'; }, 1200);
  } else {
    msg.textContent = ' ' + (data.message || 'Failed to create order.');
    msg.className = 'form-message error';
    msg.hidden = false;
  }

  btn.disabled = false;
  btn.innerHTML = `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg> Create Logistics Order`;
});