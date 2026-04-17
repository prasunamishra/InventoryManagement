// ─── Avatar color helper ───────────────────────────────────────────────────
function getAvatarClassIndex(str) {
  let hash = 0;
  for (let i = 0; i < str.length; i++) hash = str.charCodeAt(i) + ((hash << 5) - hash);
  return Math.abs(hash) % 10;
}

// ─── Role helper ──────────────────────────────────────────────────────────
const isAdmin = () => (sessionStorage.getItem('gf_role') || '').toLowerCase() === 'admin';

// ─── Load & render staff table ────────────────────────────────────────────
async function loadStaff() {
  const data = await apiCall(`${window.env.API_URL}/api/staff.php`);
  if (data && data.success !== false) {
    const tbody = document.getElementById('staff-tbody');
    const tpl = document.getElementById('staff-row-tpl');

    tbody.replaceChildren();

    if (!data.staff || data.staff.length === 0) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 4;
      td.className = 'no-data';
      td.textContent = 'No staff found.';
      tr.appendChild(td);
      tbody.appendChild(tr);
      return;
    }

    data.staff.forEach(s => {
      // Clone the HTML template defined in staff.html
      const row = tpl.content.cloneNode(true);

      // Avatar: set background color and initials text
      const avatar = row.querySelector('[data-avatar]');
      const idx = getAvatarClassIndex(s.name);
      avatar.className = `staff-avatar avatar-c${idx}`;
      avatar.textContent = (s.name.charAt(0) || '?').toUpperCase();

      // Fill in text data
      row.querySelector('[data-name]').textContent = s.name;
      row.querySelector('[data-stf-id]').textContent = 'ID: ' + s.stf_id;
      row.querySelector('[data-role]').textContent = s.role;
      row.querySelector('[data-phone]').textContent = s.phone || '—';

      // Password cell — admin sees it, staff role sees a hidden cell
      const pwCell = row.querySelector('.staff-password-cell');
      if (isAdmin()) {
        const pwText = row.querySelector('[data-pw]');
        const plainPw = s.plain_password || '—';
        const masked  = plainPw !== '—' ? '•'.repeat(plainPw.length) : '—';
        pwText.textContent = masked;
        pwText.dataset.plain  = plainPw;
        pwText.dataset.masked = masked;

        const eyeBtn = row.querySelector('[data-eye]');
        eyeBtn.addEventListener('click', () => {
          const showing = pwText.dataset.showing === 'true';
          pwText.textContent = showing ? pwText.dataset.masked : pwText.dataset.plain;
          pwText.dataset.showing = !showing;
          eyeBtn.querySelector('.eye-open').style.display  = showing ? 'none'  : 'inline';
          eyeBtn.querySelector('.eye-closed').style.display = showing ? 'inline': 'none';
        });
      } else {
        pwCell.style.display = 'none';
      }

      // Wire up buttons
      row.querySelector('[data-edit-btn]').addEventListener('click', () => openEdit(s));
      row.querySelector('[data-delete-btn]').addEventListener('click', () => deleteStaff(s.id, s.name));

      tbody.appendChild(row);
    });

  } else {
    console.error('Staff load error:', data?.message);
  }
}

// ─── Apply admin-only visibility ──────────────────────────────────────────
function applyRoleVisibility() {
  if (!isAdmin()) {
    document.querySelectorAll('.admin-only').forEach(el => el.style.display = 'none');
  }
}

// ─── Delete staff ─────────────────────────────────────────────────────────
async function deleteStaff(id, name) {
  if (!confirm(`Delete staff member "${name}"?`)) return;
  const data = await apiCall(`${window.env.API_URL}/api/staff.php`, {
    method: 'DELETE',
    body: { id }
  });
  if (data.success) {
    loadStaff();
  } else {
    alert(data.message || 'Error deleting staff.');
  }
}

// ─── Modal helpers ────────────────────────────────────────────────────────
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

document.querySelectorAll('.modal-overlay').forEach(el => {
  el.addEventListener('click', function (e) {
    if (e.target === this) closeModal(this.id);
  });
});

// ─── Open edit modal ──────────────────────────────────────────────
function openEdit(staff) {
  document.getElementById('edit-id').value = staff.id;
  document.getElementById('edit-name').value = staff.name;
  document.getElementById('edit-username').value = staff.username || '';
  document.getElementById('edit-phone').value = staff.phone || '';
  document.getElementById('edit-role').value = staff.role;
  document.getElementById('edit-password').value = '';
  document.getElementById('edit-msg').style.display = 'none';

  // Wire up modal password eye toggle
  const pwInput  = document.getElementById('edit-password');
  const pwToggle = document.getElementById('edit-pw-toggle');
  // Remove old listener to avoid stacking
  const newToggle = pwToggle.cloneNode(true);
  pwToggle.parentNode.replaceChild(newToggle, pwToggle);
  newToggle.addEventListener('click', () => {
    const showing = pwInput.type === 'text';
    pwInput.type = showing ? 'password' : 'text';
    newToggle.querySelector('.eye-open').style.display  = showing ? 'none'  : 'inline';
    newToggle.querySelector('.eye-closed').style.display = showing ? 'inline': 'none';
  });

  openModal('edit-modal');
}

// ─── Edit form submit ─────────────────────────────────────────────────────
const editForm = document.getElementById('edit-form');
if (editForm) {
  editForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = document.getElementById('edit-submit');
    const msgEl = document.getElementById('edit-msg');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    const phone = document.getElementById('edit-phone').value.trim();
    const username = document.getElementById('edit-username').value.trim();

    if (!/^\d{10}$/.test(phone)) {
      showMsg(msgEl, 'error', 'Phone number must be exactly 10 digits.');
      btn.disabled = false; btn.textContent = 'Save Changes';
      return;
    }
    if (!username) {
      showMsg(msgEl, 'error', 'Username cannot be empty.');
      btn.disabled = false; btn.textContent = 'Save Changes';
      return;
    }

    const payload = {
      id: parseInt(document.getElementById('edit-id').value),
      name: document.getElementById('edit-name').value.trim(),
      username,
      phone,
      role: document.getElementById('edit-role').value,
      // Only include password in payload for admin users
      password: isAdmin() ? document.getElementById('edit-password').value : ''
    };

    const data = await apiCall(`${window.env.API_URL}/api/staff.php`, {
      method: 'PUT',
      body: payload
    });
    if (data.success) {
      showMsg(msgEl, 'success', 'Staff updated!');
      loadStaff();
      setTimeout(() => closeModal('edit-modal'), 1000);
    } else {
      showMsg(msgEl, 'error', ' ' + (data.message || 'Error updating staff.'));
    }

    btn.disabled = false;
    btn.textContent = 'Save Changes';
  });
}

// ─── Utility: show a form message ────────────────────────────────────────
function showMsg(el, type, text) {
  el.textContent = text;
  el.className = 'form-message ' + type;
  el.style.display = 'block';
}

// ─── Init ─────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  applyRoleVisibility();
  loadStaff();
});
