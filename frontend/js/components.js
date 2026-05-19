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

// ── Stock alert popup ────────────────────────────────────────────────────────
function showStockAlert(available) {
  // Remove any stale alert
  const existing = document.getElementById('stock-alert-overlay');
  if (existing) existing.remove();

  const overlay = document.createElement('div');
  overlay.id = 'stock-alert-overlay';
  overlay.className = 'stock-alert-overlay';
  overlay.innerHTML = `
    <div class="stock-alert-box">
      <div class="stock-alert-icon">
        <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
          <line x1="12" y1="9" x2="12" y2="13"></line>
          <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
      </div>
      <h3 class="stock-alert-title">Order Cannot Be Created</h3>
      <p class="stock-alert-msg">
        The requested quantity exceeds available stock.<br>
        Only <strong>${available}</strong> item${available !== 1 ? 's are' : ' is'} currently in stock.
      </p>
      <button class="stock-alert-btn" id="stock-alert-close">Understood</button>
    </div>
  `;
  document.body.appendChild(overlay);
  requestAnimationFrame(() => overlay.classList.add('visible'));

  document.getElementById('stock-alert-close').addEventListener('click', () => {
    overlay.classList.remove('visible');
    setTimeout(() => overlay.remove(), 300);
  });

  // Also close on backdrop click
  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) {
      overlay.classList.remove('visible');
      setTimeout(() => overlay.remove(), 300);
    }
  });
}

// ── Generic alert popup (reuses stock-alert styles) ──────────────────────────
function showAlertPopup(title, message) {
  const existing = document.getElementById('alert-popup-overlay');
  if (existing) existing.remove();

  const overlay = document.createElement('div');
  overlay.id = 'alert-popup-overlay';
  overlay.className = 'stock-alert-overlay';
  overlay.innerHTML = `
    <div class="stock-alert-box">
      <div class="stock-alert-icon">
        <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
          <line x1="12" y1="9" x2="12" y2="13"></line>
          <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
      </div>
      <h3 class="stock-alert-title">${title}</h3>
      <p class="stock-alert-msg">${message}</p>
      <button class="stock-alert-btn" id="alert-popup-close">OK</button>
    </div>
  `;
  document.body.appendChild(overlay);
  requestAnimationFrame(() => overlay.classList.add('visible'));

  document.getElementById('alert-popup-close').addEventListener('click', () => {
    overlay.classList.remove('visible');
    setTimeout(() => overlay.remove(), 300);
  });

  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) {
      overlay.classList.remove('visible');
      setTimeout(() => overlay.remove(), 300);
    }
  });
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
    const role    = sessionStorage.getItem('gf_role')     || '';
    const jobRole = sessionStorage.getItem('gf_job_role') || '';

    const isAdmin      = role.toLowerCase() === 'admin';
    const isSupervisor = jobRole === 'Supervisor';

    // Pages accessible to Staff
    const STAFF_ALLOWED = [
      'dashboard', 'products', 'stock', 'add_product', 
      'logistics', 'create_order', 'suppliers', 'purchase_returns'
    ];

    // Helper: hide a nav link by data-page value
    const hideNav = (pageName) => {
      const link = document.querySelector(`.nav-links a[data-page="${pageName}"]`);
      if (link) link.closest('li').style.display = 'none';
    };

    if (!isAdmin && !isSupervisor) {
      // Staff cannot manage or view Staff page
      hideNav('staff');

      // Restrict access to strictly allowed pages
      if (!STAFF_ALLOWED.includes(page)) {
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

    // Profile Edit
    const profileBtn = document.getElementById('profile-btn');
    if (profileBtn) {
      profileBtn.addEventListener('click', async function() {
        const res = await fetch(`${API_BASE}/profile.php`);
        const data = await res.json();
        if (data.success) {
          document.getElementById('profile-name').value = data.profile.name || '';
          document.getElementById('profile-username').value = data.profile.username || '';
          document.getElementById('profile-email').value = data.profile.email || '';
          document.getElementById('profile-msg').style.display = 'none';
          openModal('profile-modal');
        } else {
          showToast('Failed to load profile.', 'error');
        }
      });
    }

    const profileForm = document.getElementById('profile-form');
    if (profileForm) {
      profileForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('profile-submit');
        const msgEl = document.getElementById('profile-msg');
        
        const payload = {
          name: document.getElementById('profile-name').value.trim(),
          username: document.getElementById('profile-username').value.trim(),
          email: document.getElementById('profile-email').value.trim()
        };

        if (!payload.name || !payload.username) {
          msgEl.textContent = 'Name and username are required.';
          msgEl.className = 'form-message error';
          msgEl.style.display = 'block';
          return;
        }

        btn.disabled = true;
        btn.textContent = 'Saving...';

        try {
          const res = await fetch(`${API_BASE}/profile.php`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });
          const data = await res.json();

          if (data.success) {
            msgEl.textContent = 'Profile updated successfully!';
            msgEl.className = 'form-message success';
            msgEl.style.display = 'block';
            
            // Update session storage and UI
            sessionStorage.setItem('gf_name', data.name);
            document.getElementById('nav-username').textContent = data.name;
            document.getElementById('nav-avatar').textContent = data.name.charAt(0).toUpperCase();

            setTimeout(() => {
              closeModal('profile-modal');
            }, 1000);
          } else {
            msgEl.textContent = data.message || 'Error updating profile.';
            msgEl.className = 'form-message error';
            msgEl.style.display = 'block';
          }
        } catch (err) {
          msgEl.textContent = 'Network error. Please try again.';
          msgEl.className = 'form-message error';
          msgEl.style.display = 'block';
        }

        btn.disabled = false;
        btn.textContent = 'Save Changes';
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
    
    // Load Chatbot
    const chatbotScript = document.createElement('script');
    chatbotScript.src = '../js/chatbot.js';
    document.body.appendChild(chatbotScript);
  }
});
