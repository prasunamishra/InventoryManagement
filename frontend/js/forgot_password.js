document.addEventListener('DOMContentLoaded', () => {

  const urlParams = new URLSearchParams(window.location.search); // get params
  const role = urlParams.get('role') || 'admin'; // get role

  document.getElementById('reset-role').value = role; // set role
  
  const subtitle = document.getElementById('page-subtitle');

  // change subtitle
  if (role === 'staff') {
    subtitle.textContent = 'Reset Staff Password';
  } else {
    subtitle.textContent = 'Reset Admin Password';
  }
});

document.getElementById('forgotForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  
  const email = document.getElementById('email').value.trim(); // email
  const msgEl = document.getElementById('forgot-msg');
  const btn = document.getElementById('forgot-submit');
  
  // validation
  if (!email) {
    msgEl.textContent = 'Please enter your email address.';
    msgEl.className = 'login-error';
    msgEl.style.display = 'block';
    return;
  }
  
  btn.disabled = true;
  btn.textContent = 'SENDING...'; // loading
  
  try {
    const res = await fetch(`${window.env.API_URL}/api/password_reset.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'forgot',
        email: email,
        role: document.getElementById('reset-role').value
      })
    });

    const data = await res.json();
    
    msgEl.style.display = 'block';

    if (data.success) {
      msgEl.className = 'login-error'; // success style
      msgEl.style.backgroundColor = '#d1fae5';
      msgEl.style.color = '#065f46';
      msgEl.style.border = '1px solid #10b981';
      msgEl.textContent = data.message;

      document.getElementById('forgotForm').reset(); // reset form
    } else {
      msgEl.className = 'login-error';
      msgEl.style.backgroundColor = '#fee2e2';
      msgEl.style.color = '#b91c1c';
      msgEl.style.border = 'none';
      msgEl.textContent = data.message || 'Error sending reset link.';
    }

  } catch (err) {
    msgEl.className = 'login-error';
    msgEl.style.display = 'block';
    msgEl.textContent = 'Network error. Please try again later.'; // error
  }
  
  btn.disabled = false;
  btn.textContent = 'SEND RESET LINK'; // reset
});