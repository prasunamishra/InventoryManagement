/**
 * app.js – Login page logic
 */

document.addEventListener('DOMContentLoaded', () => {

  const roleRadios = document.querySelectorAll('input[name="login_role"]'); // role inputs

  roleRadios.forEach(radio => {
    radio.addEventListener('change', (e) => {

      // reset styles
      document.querySelectorAll('.role-tab').forEach(label => {
        label.style.background = '#e5e7eb';
        label.style.color = '#4b5563';
      });

      // highlight selected
      const activeLabel = document.querySelector(`label[for="${e.target.id}"]`);
      if (activeLabel) {
        activeLabel.style.background = '#0d9488';
        activeLabel.style.color = 'white';
      }
      
      // update forgot password link
      const fpLink = document.getElementById('forgot-password-link');
      if (fpLink) fpLink.href = `forgot_password.html?role=${e.target.value}`;
    });
  });
});

document.getElementById('loginForm').addEventListener('submit', async function (e) {
  e.preventDefault();

  const username = document.getElementById('username').value.trim(); // username
  const password = document.getElementById('password').value.trim(); // password
  const loginRole = document.querySelector('input[name="login_role"]:checked').value; // role
  const remember = document.getElementById('remember') ? document.getElementById('remember').checked : false; // remember

  const btn = document.querySelector('.login-btn');
  const errorEl = document.getElementById('login-error');

  btn.textContent = 'Logging in…'; // loading
  btn.disabled = true;
  errorEl.style.display = 'none';

  const data = await apiCall(`${window.env.API_URL}/api/login.php`, {
    method: 'POST',
    body: { username, password, login_role: loginRole, remember }
  });

  if (data.success) {
    // store session
    sessionStorage.setItem('gf_name', data.name);
    sessionStorage.setItem('gf_username', data.username);
    sessionStorage.setItem('gf_role', data.role);
    sessionStorage.setItem('gf_job_role', data.job_role || '');
    sessionStorage.setItem('gf_product_permission', data.product_permission || 'None');

    window.location.href = 'dashboard.html'; // redirect
  } else {
    errorEl.textContent = data.message || 'Invalid credentials.'; // error
    errorEl.style.display = 'block';
  }

  btn.textContent = 'LOG IN'; // reset
  btn.disabled = false;
});