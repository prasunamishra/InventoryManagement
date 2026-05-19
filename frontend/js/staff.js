let allStaff = [];
let showInactive = true;
let statusTargetId = null;
let statusTargetStatus = null;
let statusTargetName = '';
async function fetchStaff() {
  const data = await apiCall(`${window.env.API_URL}/api/staff.php`);
  if (data && data.success) {
    allStaff = data.staff || [];
    applyFilters();
  } else {
    document.getElementById('staff-tbody').innerHTML = '<tr><td colspan="5" class="no-data">Error loading staff.</td></tr>';
  }
}

function renderTable(list) {
  const tbody = document.getElementById('staff-tbody');
  const tpl = document.getElementById('staff-row-tpl');
  tbody.replaceChildren();

  if (list.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5" class="no-data">No staff found.</td></tr>';
    return;
  }

  list.forEach(s => {
    const row = tpl.content.cloneNode(true);
    row.querySelector('[data-name]').textContent = s.name;
    row.querySelector('[data-username]').textContent = s.username;
    row.querySelector('[data-phone]').textContent = s.phone || 'N/A';
    
    const roleBadge = row.querySelector('[data-role]');
    roleBadge.textContent = s.job_role || 'Staff';
    if (s.job_role === 'Inventory Manager') roleBadge.className = 'cat-badge cat-dairy'; // green-ish
    if (s.job_role === 'Logistics Coordinator') roleBadge.className = 'cat-badge cat-beverages'; // blue-ish
    
    const editBtn = row.querySelector('[data-edit]');
    editBtn.onclick = () => window.location.href = `add_staff.html?id=${s.id}`;
    
    const statusBtn = row.querySelector('[data-status-btn]');
    const isInactive = s.status === 'inactive';
    statusBtn.textContent = isInactive ? 'Activate' : 'Deactivate';
    statusBtn.style.background = isInactive ? '#10b981' : '#fee2e2';
    statusBtn.style.color = isInactive ? 'white' : '#dc2626';
    
    if (isInactive) {
      row.children[0].style.opacity = '0.6';
      row.children[0].style.background = '#f3f4f6';
    }
    
    statusBtn.onclick = () => openStatusModal(s.id, s.name, s.status);
    
    tbody.appendChild(row);
  });
}

function openStatusModal(id, name, currentStatus) {
  statusTargetId = id;
  statusTargetName = name;
  statusTargetStatus = currentStatus === 'inactive' ? 'active' : 'inactive';
  
  const msgEl = document.getElementById('status-confirm-msg');
  const btnEl = document.getElementById('confirm-status-btn');
  
  if (statusTargetStatus === 'inactive') {
    msgEl.textContent = `Deactivate "${name}"? They will not be able to log in.`;
    btnEl.textContent = 'Yes, Deactivate';
    btnEl.style.background = '#ef4444';
  } else {
    msgEl.textContent = `Activate "${name}"?`;
    btnEl.textContent = 'Yes, Activate';
    btnEl.style.background = '#10b981';
  }
  
  openModal('status-modal');
}

document.getElementById('confirm-status-btn')?.addEventListener('click', async function() {
  if (!statusTargetId || !statusTargetStatus) return;
  this.disabled = true; this.textContent = 'Updating...';
  
  const data = await apiCall(`${window.env.API_URL}/api/staff.php`, {
    method: 'POST', body: { action: 'update_status', id: statusTargetId, status: statusTargetStatus }
  });
  
  if (data.success) {
    showToast(data.message || 'Status updated successfully.', 'success');
    closeModal('status-modal');
    fetchStaff();
  } else {
    showToast(data.message || 'Update failed.', 'error');
  }
  
  this.disabled = false;
});

document.getElementById('staff-search')?.addEventListener('input', applyFilters);
document.getElementById('show-inactive-toggle')?.addEventListener('change', function(e) {
  showInactive = e.target.checked;
  applyFilters();
});

function applyFilters() {
  const q = (document.getElementById('staff-search')?.value || '').toLowerCase();
  const filtered = allStaff.filter(s => {
    if (!showInactive && s.status === 'inactive') return false;
    return s.name.toLowerCase().includes(q) || 
           s.username.toLowerCase().includes(q) || 
           (s.job_role || '').toLowerCase().includes(q);
  });
  renderTable(filtered);
}

document.addEventListener('DOMContentLoaded', fetchStaff);
