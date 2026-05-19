document.addEventListener('DOMContentLoaded', async () => {

  const urlParams = new URLSearchParams(window.location.search); // get query params
  const staffId = urlParams.get('id'); // get id

  if (staffId) {
    // switch to edit mode
    document.getElementById('page-heading').textContent = 'Edit Staff Member';
    document.getElementById('submit-btn').textContent = 'Update Staff';

    document.getElementById('lbl-password').innerHTML =
      'PASSWORD <span style="font-size:18px; font-weight:normal; color:#6b7280;">(Leave blank to keep current)</span>';

    document.getElementById('staff-password').required = false; // not required
    document.getElementById('staff-password').placeholder = 'Leave blank to keep current';

    // fetch staff
    const data = await apiCall(`${window.env.API_URL}/api/staff.php?id=${staffId}`);

    if (data.success) {
      const s = data.staff;

      document.getElementById('staff-id').value = s.id; // set id
      document.getElementById('staff-name').value = s.name; // set name
      document.getElementById('staff-username').value = s.username; // set username
      document.getElementById('staff-email').value = s.email || ''; // set email

      // set role
      const roleInputs = document.querySelectorAll('input[name="job_role"]');
      roleInputs.forEach(r => {
        if (r.value === s.job_role) r.checked = true;
      });
    }
  }
});

// form submit
document.getElementById('staff-form')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  
  const id = document.getElementById('staff-id').value; // id
  const name = document.getElementById('staff-name').value.trim(); // name
  const username = document.getElementById('staff-username').value.trim(); // username
  const email = document.getElementById('staff-email').value.trim(); // email

  const jobRoleInput = document.querySelector('input[name="job_role"]:checked');
  const job_role = jobRoleInput ? jobRoleInput.value : 'Staff'; // role

  const password = document.getElementById('staff-password').value; // password

  const msg = document.getElementById('form-msg');
  msg.style.display = 'none'; // hide msg

  // validation
  if (!name || !username || !email) {
    showMsg(msg, 'error', 'Name, username, and email are required.');
    return;
  }
  
  if (!id && !password) {
    showMsg(msg, 'error', 'Password is required for new staff members.');
    return;
  }
  
  if (password) {
    const pwError = validateStrongPassword(password); // check password
    if (pwError) {
      showMsg(msg, 'error', pwError);
      return;
    }
  }

  const btn = document.getElementById('submit-btn');
  const originalText = btn.textContent;

  btn.disabled = true; 
  btn.textContent = 'Saving...'; // loading

  const payload = { id, name, username, email, job_role, password };
  const method = id ? 'PUT' : 'POST'; // update or create

  const data = await apiCall(`${window.env.API_URL}/api/staff.php`, {
    method, body: payload
  });

  if (data.success) {
    showMsg(msg, 'success', data.message); // success
    setTimeout(() => window.location.href = 'staff.html', 1500); // redirect
  } else {
    showMsg(msg, 'error', data.message); // error
    btn.disabled = false; 
    btn.textContent = originalText;
  }
});

// show message
function showMsg(el, type, text) {
  el.textContent = text;
  el.className = `form-message ${type}`;
  el.style.display = 'block';
}