/**
 * api.js – Global API helper wrapper
 * Centralizes fetch logic across the frontend.
 */

async function apiCall(endpoint, options = {}) {
  try {
    const config = {
      credentials: 'include',
      method: options.method || 'GET',
      headers: {
        'Content-Type': 'application/json',
        ...(options.headers || {})
      }
    };

    if (options.body) {
      config.body = typeof options.body === 'string' 
        ? options.body 
        : JSON.stringify(options.body);
    }

    const res = await fetch(endpoint, config);
    
    // Attempt to parse JSON response
    try {
      const data = await res.json();
      return data;
    } catch (parseError) {
      console.error(`Failed to parse JSON from ${endpoint}:`, parseError);
      return { success: false, message: 'Invalid response from server.' };
    }
    
  } catch (err) {
    console.error(`API Call failed for ${endpoint}:`, err);
    return { success: false, message: 'Unable to connect to server. Please try again.' };
  }
}

/**
 * Shared strong password validation (mirrors backend rules).
 * Returns null if valid, or an error message string.
 */
function validateStrongPassword(password) {
  if (!password || password.length < 8)
    return 'Password must be at least 8 characters long.';
  if (!/[A-Z]/.test(password))
    return 'Password must contain at least one uppercase letter (A–Z).';
  if (!/[a-z]/.test(password))
    return 'Password must contain at least one lowercase letter (a–z).';
  if (!/[0-9]/.test(password))
    return 'Password must contain at least one number (0–9).';
  if (!/[^A-Za-z0-9]/.test(password))
    return 'Password must contain at least one special character (e.g., @, #, $, !).';
  return null;
}
