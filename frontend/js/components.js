/**
 * components.js
 * Shared utilities: header/footer injection, auth guard, global modal helpers.
 */

// ── Base API URL ────────────────────────────────────────────────────────────
const API_BASE = `${window.env.API_URL}/api`;

// ── Global modal helpers (called from HTML onclick attributes) ──────────────
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

// ── Toast notification system ───────────────────────────────────────────────
function showToast(message, type = 'success') {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  container.appendChild(toast);

  // Animate in
  requestAnimationFrame(() => toast.classList.add('toast-visible'));

  // Auto-remove after 3 s
  setTimeout(() => {
    toast.classList.remove('toast-visible');
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// ── Load header component ───────────────────────────────────────────────────
async function loadHeader() {
  try {
    const res = await fetch('../components/header.html');
    const html = await res.text();
    document.getElementById('header-placeholder').innerHTML = html;

    // Set active nav link
    const page = location.pathname.split('/').pop().replace('.html', '');
    const activeLink = document.querySelector(`.nav-links a[data-page="${page}"]`);
    if (activeLink) activeLink.classList.add('nav-active');

    // Role-based nav & route guard
    const role = sessionStorage.getItem('gf_role') || '';
    const jobRole = sessionStorage.getItem('gf_job_role') || '';
    if (role.toLowerCase() === 'staff') {
      const staffLink = document.querySelector('.nav-links a[data-page="staff"]');
      if (staffLink) staffLink.closest('li').style.display = 'none';

      if (jobRole !== 'Supervisor') {
        const suppliersLink = document.querySelector('.nav-links a[data-page="suppliers"]');
        if (suppliersLink) suppliersLink.closest('li').style.display = 'none';

        if (page === 'suppliers' || page === 'add_supplier') {
          window.location.href = 'dashboard.html';
          return;
        }
      }

      if (page === 'staff' || page === 'add_staff') {
        window.location.href = 'dashboard.html';
        return;
      }
    }

    // Populate user display
    const name = sessionStorage.getItem('gf_name') || 'Admin';
    const navUsername = document.getElementById('nav-username');
    const navAvatar = document.getElementById('nav-avatar');
    if (navUsername) navUsername.textContent = name;
    if (navAvatar) navAvatar.textContent = name.charAt(0).toUpperCase();

    // Logout
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
      logoutBtn.addEventListener('click', function (e) {
        e.preventDefault();
        sessionStorage.clear();
        window.location.href = 'login.html';
      });
    }
  } catch (err) {
    console.error('Header load error:', err);
  }
}

// ── Load footer component ───────────────────────────────────────────────────
async function loadFooter() {
  try {
    const res = await fetch('../components/footer.html');
    const html = await res.text();
    const el = document.getElementById('footer-placeholder');
    if (el) el.innerHTML = html;
  } catch (err) {
    console.error('Footer load error:', err);
  }
}

// ── Auth guard + init ───────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  const isLoginPage = location.pathname.includes('login.html');

  if (!isLoginPage && !sessionStorage.getItem('gf_name')) {
    window.location.href = 'login.html';
    return;
  }

  if (!isLoginPage) {
    loadHeader();
    loadFooter();
  }
});