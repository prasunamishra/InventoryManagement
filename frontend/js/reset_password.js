document.addEventListener('DOMContentLoaded', async () => {
  const urlParams = new URLSearchParams(window.location.search);
  const token = urlParams.get('token');
  
  if (!token) {
    showInvalidState();
    return;
  }
  
  // Verify token
  try {
    const res = await fetch(`${window.env.API_URL}/api/password_reset.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'verify_token', token: token })
    });
    const data = await res.json();
    
    if (data.success) {
      document.getElementById('loading-state').style.display = 'none';
      document.getElementById('resetForm').style.display = 'block';
      document.getElementById('reset-token').value = token;
      
      const subtitle = document.getElementById('page-subtitle');
      if (data.role === 'staff') {
        subtitle.textContent = 'Set New Staff Password';
      } else {
        subtitle.textContent = 'Set New Admin Password';
      }
    } else {
      showInvalidState();
    }
  } catch (e) {
    showInvalidState();
  }
});

function showInvalidState() {
  document.getElementById('loading-state').style.display = 'none';
  document.getElementById('resetForm').style.display = 'none';
  document.getElementById('invalid-state').style.display = 'block';
}

document.getElementById('resetForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  
  const token = document.getElementById('reset-token').value;
  const pw1 = document.getElementById('new_password').value;
  const pw2 = document.getElementById('confirm_password').value;
  const msgEl = document.getElementById('reset-msg');
  const btn = document.getElementById('reset-submit');
  
  if (pw1 !== pw2) {
    msgEl.textContent = 'Passwords do not match.';
    msgEl.style.display = 'block';
    return;
  }
  
  if (pw1.length < 6) {
    msgEl.textContent = 'Password must be at least 6 characters.';
    msgEl.style.display = 'block';
    return;
  }
  
  btn.disabled = true;
  btn.textContent = 'UPDATING...';
  
  try {
    const res = await fetch(`${window.env.API_URL}/api/password_reset.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'reset', token: token, password: pw1 })
    });
    const data = await res.json();
    
    msgEl.style.display = 'block';
    if (data.success) {
      msgEl.style.backgroundColor = '#d1fae5';
      msgEl.style.color = '#065f46';
      msgEl.style.border = '1px solid #10b981';
      msgEl.textContent = 'Password updated successfully! Redirecting...';
      
      setTimeout(() => {
        window.location.href = 'login.html';
      }, 2000);
    } else {
      msgEl.textContent = data.message || 'Error resetting password.';
      btn.disabled = false;
      btn.textContent = 'UPDATE PASSWORD';
    }
  } catch (err) {
    msgEl.textContent = 'Network error. Please try again.';
    btn.disabled = false;
    btn.textContent = 'UPDATE PASSWORD';
  }
});