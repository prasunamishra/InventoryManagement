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