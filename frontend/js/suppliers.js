/**
 * suppliers.js – Suppliers list page logic
 */

let allSuppliers = [];
let filteredSuppliers = [];
let supplierToDeleteId = null;

async function fetchSuppliers() {
  const data = await apiCall(`${window.env.API_URL}/api/suppliers.php`);
  if (data && data.success !== false) {
    allSuppliers = data.suppliers || [];
    filteredSuppliers = [...allSuppliers];
    renderTable();
  } else {
    const tbody = document.getElementById('supplier-tbody');
    tbody.replaceChildren();
    const tr = document.createElement('tr');
    tr.innerHTML = `<td colspan="5" class="no-data">${data?.message || 'Error loading suppliers.'}</td>`;
    tbody.appendChild(tr);
  }
}

function renderTable() {
  const tbody = document.getElementById('supplier-tbody');
  const tpl = document.getElementById('supplier-row-tpl');

  tbody.replaceChildren();

  if (filteredSuppliers.length === 0) {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td colspan="5" class="no-data">No suppliers found.</td>`;
    tbody.appendChild(tr);
    return;
  }

  const role = (sessionStorage.getItem('gf_role') || 'staff').toLowerCase();
  const jobRole = sessionStorage.getItem('gf_job_role') || '';
  const hasAccess = role === 'admin' || jobRole === 'Supervisor';

  filteredSuppliers.forEach(s => {
    const clone = tpl.content.cloneNode(true);

    clone.querySelector('[data-sup-name]').textContent = s.name;
    clone.querySelector('[data-sup-phone]').textContent = s.phone || '—';
    clone.querySelector('[data-sup-email]').textContent = s.email || '—';
    clone.querySelector('[data-sup-date]').textContent = s.created_at
      ? new Date(s.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
      : '—';

    const editBtn = clone.querySelector('[data-edit-btn]');
    const delBtn = clone.querySelector('[data-del-btn]');

    if (hasAccess) {
      delBtn.addEventListener('click', () => {
        supplierToDeleteId = s.id;
        openModal('sup-delete-modal');
      });
      editBtn.addEventListener('click', () => {
        document.getElementById('edit-sup-id').value = s.id;
        document.getElementById('edit-sup-name').value = s.name;
        document.getElementById('edit-sup-phone').value = s.phone || '';
        document.getElementById('edit-sup-email').value = s.email || '';
        document.getElementById('edit-sup-msg').style.display = 'none';
        openModal('edit-modal');
      });
    } else {
      delBtn.style.display = 'none';
      if (editBtn) editBtn.style.display = 'none';
    }

    tbody.appendChild(clone);
  });
}

document.getElementById('sup-confirm-delete-btn').addEventListener('click', async function () {
  if (!supplierToDeleteId) return;
  const btn = this;
  btn.disabled = true; btn.textContent = 'DELETING...';

  const data = await apiCall(`${window.env.API_URL}/api/suppliers.php`, {
    method: 'DELETE',
    body: { id: supplierToDeleteId }
  });

  if (data.success) {
    await fetchSuppliers();
    closeModal('sup-delete-modal');
    showToast('Supplier deleted successfully.');
  } else {
    alert(data.message || 'Error deleting supplier.');
  }

  btn.disabled = false; btn.textContent = 'DELETE';
  supplierToDeleteId = null;
});

document.getElementById('edit-supplier-form').addEventListener('submit', async function (e) {
  e.preventDefault();
  const id = document.getElementById('edit-sup-id').value;
  const name = document.getElementById('edit-sup-name').value.trim();
  const phone = document.getElementById('edit-sup-phone').value.trim();
  const email = document.getElementById('edit-sup-email').value.trim();
  const btn = document.getElementById('edit-sup-submit');
  const msg = document.getElementById('edit-sup-msg');

  if (!name) {
    msg.textContent = 'Supplier name is required.';
    msg.className = 'form-message error';
    msg.style.display = 'block';
    return;
  }

  if (!/^\d{10}$/.test(phone)) {
    msg.textContent = 'Phone number must be exactly 10 digits.';
    msg.className = 'form-message error';
    msg.style.display = 'block';
    return;
  }

  const emailRegex = /^[^\s@]+@[^\s@]+\.com$/i;
  if (!emailRegex.test(email)) {
    msg.textContent = 'Please enter a valid .com email address.';
    msg.className = 'form-message error';
    msg.style.display = 'block';
    return;
  }

  btn.disabled = true; btn.textContent = 'Saving...';

  const res = await apiCall(`${window.env.API_URL}/api/suppliers.php`, {
    method: 'PUT',
    body: { id, name, phone, email }
  });

  if (res.success) {
    msg.textContent = 'Supplier updated!';
    msg.className = 'form-message success';
    msg.style.display = 'block';
    await fetchSuppliers();
    setTimeout(() => {
      closeModal('edit-modal');
      msg.style.display = 'none';
    }, 1200);
  } else {
    msg.textContent = res.message || 'Error updating supplier.';
    msg.className = 'form-message error';
    msg.style.display = 'block';
  }

  btn.disabled = false; btn.textContent = 'Save Changes';
});

document.getElementById('sup-search').addEventListener('input', function () {
  const q = this.value.trim().toLowerCase();
  filteredSuppliers = q
    ? allSuppliers.filter(s =>
      s.name.toLowerCase().includes(q) ||
      (s.phone && s.phone.toLowerCase().includes(q)) ||
      (s.email && s.email.toLowerCase().includes(q))
    )
    : [...allSuppliers];
  renderTable();
});

document.addEventListener('DOMContentLoaded', fetchSuppliers);