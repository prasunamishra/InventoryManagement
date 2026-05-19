document.addEventListener('DOMContentLoaded', async () => {
  const urlParams = new URLSearchParams(window.location.search);
  const staffId = urlParams.get('id');

  if (staffId) {
    document.getElementById('page-heading').textContent = 'Edit Staff Member';
    document.getElementById('submit-btn').textContent = 'Update Staff';
    document.getElementById('lbl-password').innerHTML = 'PASSWORD <span style="font-size:18px; font-weight:normal; color:#6b7280;">(Leave blank to keep current)</span>';
    document.getElementById('staff-password').required = false;
    document.getElementById('staff-password').placeholder = 'Leave blank to keep current';

    // Fetch staff data
    const data = await apiCall(`${window.env.API_URL}/api/staff.php?id=${staffId}`);
    if (data.success) {
      const s = data.staff;
      document.getElementById('staff-id').value = s.id;
      document.getElementById('staff-name').value = s.name;
      document.getElementById('staff-username').value = s.username;
      document.getElementById('staff-email').value = s.email || '';
      document.getElementById('staff-phone').value = s.phone || '';
      const roleInputs = document.querySelectorAll('input[name="job_role"]');
      roleInputs.forEach(r => {
        if (r.value === s.job_role) r.checked = true;
      });
    }
  }
});

document.getElementById('staff-form')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  
  const id = document.getElementById('staff-id').value;
  const name = document.getElementById('staff-name').value.trim();
  const username = document.getElementById('staff-username').value.trim();
  const email = document.getElementById('staff-email').value.trim();
  const phone = document.getElementById('staff-phone').value.trim();
  const jobRoleInput = document.querySelector('input[name="job_role"]:checked');
  const job_role = jobRoleInput ? jobRoleInput.value : 'Staff';
  const password = document.getElementById('staff-password').value;

  const msg = document.getElementById('form-msg');
  msg.style.display = 'none';

  if (!name || !username || !email || !phone) {
    showMsg(msg, 'error', 'Name, username, email, and phone are required.');
    return;
  }
  
  if (!/^\d{10}$/.test(phone)) {
    showMsg(msg, 'error', 'Phone number must be a valid 10-digit number.');
    return;
  }
  
  if (!id && !password) {
    showMsg(msg, 'error', 'Password is required for new staff members.');
    return;
  }
  
  if (password) {
    const pwError = validateStrongPassword(password);
    if (pwError) {
      showMsg(msg, 'error', pwError);
      return;
    }
  }

  const btn = document.getElementById('submit-btn');
  const originalText = btn.textContent;
  btn.disabled = true; btn.textContent = 'Saving...';

  const payload = { id, name, username, email, phone, job_role, password };
  const method = id ? 'PUT' : 'POST';

  const data = await apiCall(`${window.env.API_URL}/api/staff.php`, {
    method, body: payload
  });

  if (data.success) {
    showMsg(msg, 'success', data.message);
    setTimeout(() => window.location.href = 'staff.html', 1500);
  } else {
    showMsg(msg, 'error', data.message);
    btn.disabled = false; btn.textContent = originalText;
  }
});

function showMsg(el, type, text) {
  el.textContent = text;
  el.className = `form-message ${type}`;
  el.style.display = 'block';
}
