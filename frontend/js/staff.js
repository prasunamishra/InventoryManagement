let allStaff = []; // store all staff data

// fetch staff from API
async function fetchStaff() {
  const data = await apiCall(`${window.env.API_URL}/api/staff.php`);
  if (data && data.success) {
    allStaff = data.staff || []; // save data
    renderTable(allStaff); // render table
  } else {
    document.getElementById('staff-tbody').innerHTML = '<tr><td colspan="4" class="no-data">Error loading staff.</td></tr>'; // error
  }
}

// render table rows
function renderTable(list) {
  const tbody = document.getElementById('staff-tbody');
  const tpl = document.getElementById('staff-row-tpl');
  tbody.replaceChildren(); // clear table

  if (list.length === 0) {
    tbody.innerHTML = '<tr><td colspan="4" class="no-data">No staff found.</td></tr>'; // no data
    return;
  }

  list.forEach(s => {
    const row = tpl.content.cloneNode(true); // clone template

    row.querySelector('[data-name]').textContent = s.name; // set name
    row.querySelector('[data-username]').textContent = s.username; // set username
    
    const roleBadge = row.querySelector('[data-role]');
    roleBadge.textContent = s.job_role || 'Staff'; // set role

    // role colors
    if (s.job_role === 'Inventory Manager') roleBadge.className = 'cat-badge cat-dairy';
    if (s.job_role === 'Logistics Coordinator') roleBadge.className = 'cat-badge cat-beverages';
    
    // edit button
    const editBtn = row.querySelector('[data-edit]');
    editBtn.onclick = () => window.location.href = `add_staff.html?id=${s.id}`;
    
    // delete button
    const deleteBtn = row.querySelector('[data-delete]');
    deleteBtn.onclick = () => openDeleteModal(s.id, s.name);
    
    tbody.appendChild(row); // add row
  });
}

// open delete modal
function openDeleteModal(id, name) {
  document.getElementById('delete-id').value = id; // set id
  document.getElementById('delete-name').textContent = name; // set name
  document.getElementById('delete-msg').style.display = 'none'; // hide error
  openModal('delete-modal'); // show modal
}

// delete confirm
document.getElementById('confirm-delete-btn')?.addEventListener('click', async function() {
  const id = document.getElementById('delete-id').value;

  this.disabled = true; 
  this.textContent = 'Deleting...'; // loading
  
  const data = await apiCall(`${window.env.API_URL}/api/staff.php`, {
    method: 'DELETE', body: { id }
  });
  
  if (data.success) {
    showToast('Staff member deleted successfully.', 'success'); // success
    closeModal('delete-modal');
    fetchStaff(); // reload data
  } else {
    const msg = document.getElementById('delete-msg');
    msg.textContent = data.message || 'Delete failed.'; // error
    msg.style.display = 'block';
  }
  
  this.disabled = false; 
  this.textContent = 'Delete Account'; // reset button
});

// search filter
document.getElementById('staff-search')?.addEventListener('input', function() {
  const q = this.value.toLowerCase();

  const filtered = allStaff.filter(s => 
    s.name.toLowerCase().includes(q) || 
    s.username.toLowerCase().includes(q) || 
    (s.job_role || '').toLowerCase().includes(q)
  );

  renderTable(filtered); // update table
});

// load on page ready
document.addEventListener('DOMContentLoaded', fetchStaff);