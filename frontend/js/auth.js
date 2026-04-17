/**
 * app.js – Login page logic
 */

document.getElementById('loginForm').addEventListener('submit', async function (e) {
  e.preventDefault();

  const username = document.getElementById('username').value.trim();
  const password = document.getElementById('password').value.trim();
  const remember = document.getElementById('remember') ? document.getElementById('remember').checked : false;
  const btn = document.querySelector('.login-btn');
  const errorEl = document.getElementById('login-error');

  btn.textContent = 'Logging in…';
  btn.disabled = true;
  errorEl.style.display = 'none';

  const data = await apiCall(`${window.env.API_URL}/api/login.php`, {
    method: 'POST',
    body: { username, password, remember }
  });

  if (data.success) {
    sessionStorage.setItem('gf_name', data.name);
    sessionStorage.setItem('gf_username', data.username);
    sessionStorage.setItem('gf_role', data.role);
    sessionStorage.setItem('gf_job_role', data.job_role || '');
    sessionStorage.setItem('gf_product_permission', data.product_permission || 'None');
    window.location.href = 'dashboard.html';
  } else {
    errorEl.textContent = data.message || 'Invalid credentials.';
    errorEl.style.display = 'block';
  }

  btn.textContent = 'LOG IN';
  btn.disabled = false;
});