/**
 * suppliers.js – Suppliers list page logic
 */

let allSuppliers = [];
let showInactive = true;
let statusTargetId = null;
let statusTargetStatus = null;
let statusTargetName = '';

async function fetchSuppliers() {
  const data = await apiCall(`${window.env.API_URL}/api/suppliers.php`);
  if (data && data.success !== false) {
    allSuppliers = data.suppliers || [];
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
  
  const displayList = allSuppliers.filter(s => {
    if (!showInactive && s.status === 'inactive') return false;
    const q = (document.getElementById('sup-search')?.value || '').toLowerCase();
    return s.name.toLowerCase().includes(q) ||
           (s.phone && s.phone.toLowerCase().includes(q)) ||
           (s.email && s.email.toLowerCase().includes(q));
  });

  if (displayList.length === 0) {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td colspan="5" class="no-data">No suppliers found.</td>`;
    tbody.appendChild(tr);
    return;
  }

  const role = (sessionStorage.getItem('gf_role') || 'staff').toLowerCase();
  const jobRole = sessionStorage.getItem('gf_job_role') || '';
  const hasAccess = role === 'admin' || jobRole === 'Supervisor';
  
  const addBtn = document.getElementById('add-sup-btn');
  if (addBtn) {
      addBtn.style.display = hasAccess ? 'inline-block' : 'none';
  }

  displayList.forEach(s => {
    const clone = tpl.content.cloneNode(true);

    clone.querySelector('[data-sup-name]').textContent = s.name;
    clone.querySelector('[data-sup-phone]').textContent = s.phone || '—';
    clone.querySelector('[data-sup-email]').textContent = s.email || '—';
    clone.querySelector('[data-sup-date]').textContent = s.created_at
      ? new Date(s.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
      : '—';

    const editBtn = clone.querySelector('[data-edit-btn]');
    const statusBtn = clone.querySelector('[data-status-btn]');
    const isInactive = s.status === 'inactive';
    
    if (isInactive) {
      clone.children[0].style.opacity = '0.6';
      clone.children[0].style.background = '#f3f4f6';
    }

    if (hasAccess) {
      statusBtn.textContent = isInactive ? 'Activate' : 'Deactivate';
      statusBtn.style.background = isInactive ? '#10b981' : '#fee2e2';
      statusBtn.style.color = isInactive ? 'white' : '#dc2626';
      
      statusBtn.addEventListener('click', () => {
        openStatusModal(s.id, s.name, s.status);
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
      statusBtn.style.display = 'none';
      if (editBtn) editBtn.style.display = 'none';
    }

    tbody.appendChild(clone);
  });
}

function openStatusModal(id, name, currentStatus) {
  statusTargetId = id;
  statusTargetName = name;
  statusTargetStatus = currentStatus === 'inactive' ? 'active' : 'inactive';
  
  const msgEl = document.getElementById('status-confirm-msg');
  const btnEl = document.getElementById('confirm-status-btn');
  
  if (statusTargetStatus === 'inactive') {
    msgEl.textContent = `Deactivate "${name}"? They will be hidden from new purchase orders.`;
    btnEl.textContent = 'Yes, Deactivate';
    btnEl.style.background = '#ef4444';
  } else {
    msgEl.textContent = `Activate "${name}"?`;
    btnEl.textContent = 'Yes, Activate';
    btnEl.style.background = '#10b981';
  }
  
  openModal('status-modal');
}

document.getElementById('confirm-status-btn')?.addEventListener('click', async function () {
  if (!statusTargetId) return;
  const btn = this;
  btn.disabled = true; btn.textContent = 'UPDATING...';

  const data = await apiCall(`${window.env.API_URL}/api/suppliers.php`, {
    method: 'POST',
    body: { action: 'update_status', id: statusTargetId, status: statusTargetStatus }
  });

  if (data.success) {
    await fetchSuppliers();
    closeModal('status-modal');
    showToast('Supplier status updated.');
  } else {
    alert(data.message || 'Error updating status.');
  }

  btn.disabled = false;
  statusTargetId = null;
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
  } else if (res.message && res.message.toLowerCase().includes('already exists')) {
    // Duplicate — show styled popup
    showAlertPopup('Duplicate Supplier', 'Supplier already exists. Duplicate entries are not allowed.');
  } else {
    msg.textContent = res.message || 'Error updating supplier.';
    msg.className = 'form-message error';
    msg.style.display = 'block';
  }

  btn.disabled = false; btn.textContent = 'Save Changes';
});

document.getElementById('sup-search').addEventListener('input', renderTable);
document.getElementById('show-inactive-toggle')?.addEventListener('change', function(e) {
  showInactive = e.target.checked;
  renderTable();
});

document.addEventListener('DOMContentLoaded', fetchSuppliers);
