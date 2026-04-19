/**
 * add_supplier.js – Add New Supplier page logic
 */

document.getElementById('add-supplier-form').addEventListener('submit', async function (e) {
  e.preventDefault();

  const name = document.getElementById('sup-name').value.trim();
  const phone = document.getElementById('sup-phone').value.trim();
  const email = document.getElementById('sup-email').value.trim();
  const msg = document.getElementById('sup-msg');
  const btn = document.getElementById('sup-submit');

  if (!name) {
    msg.textContent = 'Supplier name is required.';
    msg.className = 'form-message error';
    msg.hidden = false;
    return;
  }

  if (!/^\d{10}$/.test(phone)) {
    msg.textContent = 'Phone number must be exactly 10 digits.';
    msg.className = 'form-message error';
    msg.hidden = false;
    return;
  }

  const emailRegex = /^[^\s@]+@[^\s@]+\.com$/i;
  if (!emailRegex.test(email)) {
    msg.textContent = 'Please enter a valid .com email address.';
    msg.className = 'form-message error';
    msg.hidden = false;
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Adding...';

  const data = await apiCall(`${window.env.API_URL}/api/suppliers.php`, {
    method: 'POST',
    body: { name, phone, email }
  });

  if (data.success) {
    msg.textContent = 'Supplier added successfully!';
    msg.className = 'form-message success';
    msg.hidden = false;
    this.reset();
    setTimeout(() => { window.location.href = 'suppliers.html'; }, 1200);
  } else {
    msg.textContent = ' ' + (data.message || 'Failed to add supplier.');
    msg.className = 'form-message error';
    msg.hidden = false;
  }

  btn.disabled = false;
  btn.textContent = 'Add Supplier';
});