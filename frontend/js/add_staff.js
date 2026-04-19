/**
 * add_staff.js – Add new staff member form logic
 */

// Role button selection logic
document.querySelectorAll('.role-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    // Remove active class from all
    document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('role-active'));
    // Add active class to clicked
    btn.classList.add('role-active');
    // Set hidden input value
    document.getElementById('stf-role').value = btn.dataset.value;
  });
});

document.getElementById('staffForm').addEventListener('submit', async function (e) {
  e.preventDefault();

  const msgEl = document.getElementById('stf-message');
  const btn = document.getElementById('stf-submit');
  const phoneInput = document.getElementById('stf-phone');
  const phone = phoneInput.value.trim();

  phoneInput.setCustomValidity("");

  if (!/^\d{10}$/.test(phone)) {
    phoneInput.setCustomValidity("Phone number must be exactly 10 digits.");
    phoneInput.reportValidity();

    // Clear validation instantly as user types
    phoneInput.addEventListener('input', function clearVal() {
      phoneInput.setCustomValidity("");
      phoneInput.removeEventListener('input', clearVal);
    });
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Saving…';

  const payload = {
    name: document.getElementById('stf-name').value.trim(),
    username: document.getElementById('stf-username').value.trim(),
    password: document.getElementById('stf-password').value.trim(),
    role: document.getElementById('stf-role').value,
    phone: phone
  };

  const data = await apiCall(`${window.env.API_URL}/api/staff.php`, {
    method: 'POST',
    body: payload
  });

  if (data.success) {
    showFormMsg(msgEl, 'success', '✓ Staff member added!');
    document.getElementById('staffForm').reset();
    // reset role
    document.querySelectorAll('.role-btn').forEach((b, i) => {
      b.classList.toggle('role-active', i === 0);
    });
    document.getElementById('stf-role').value = document.querySelector('.role-btn').dataset.value;

    setTimeout(() => { window.location.href = 'staff.html'; }, 1000);
  } else {
    showFormMsg(msgEl, 'error', '✗ ' + data.message);
  }

  btn.disabled = false;
  btn.textContent = 'Add Staff';
});

function showFormMsg(el, type, text) {
  el.textContent = text;
  el.className = 'form-message ' + type;
  el.style.display = 'block';
  setTimeout(() => { el.style.display = 'none'; }, 4000);
}
