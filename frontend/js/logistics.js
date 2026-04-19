/**
 * logistics.js – Logistics management page logic
 */

async function fetchLogistics() {
  const data = await apiCall(`${window.env.API_URL}/api/logistics.php`);
  if (data && data.success !== false) {
    const tbody = document.getElementById('logistics-tbody');
    const tpl   = document.getElementById('logistics-row-tpl');

    tbody.replaceChildren();

    if (!data.logistics || data.logistics.length === 0) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 4;
      td.className = 'no-data';
      td.textContent = 'No orders found.';
      tr.appendChild(td);
      tbody.appendChild(tr);
      
      const countEl = document.getElementById('active-deliv-count');
      if (countEl) countEl.textContent = '0';
      return;
    }

    let activeCount = 0;

    data.logistics.forEach(item => {
      if (item.status !== 'Delivered') {
        activeCount++;
      }

      const clone = tpl.content.cloneNode(true);

      clone.querySelector('[data-logi-customer]').textContent = item.customer;
      clone.querySelector('[data-logi-address]').textContent  = item.address;
      clone.querySelector('[data-logi-product]').textContent  = item.product;

      // Status pill
      const wrap = clone.querySelector('[data-logi-status-wrap]');
      const select = clone.querySelector('[data-logi-status-select]');
      
      select.value = item.status;
      const statusLower = item.status.toLowerCase().replace(/ /g, '-');
      wrap.classList.add(`pill-${statusLower}`);

      select.addEventListener('change', async function() {
        const newStatus = this.value;
        const ogStatus = item.status;
        this.disabled = true;

        const res = await apiCall(`${window.env.API_URL}/api/logistics.php`, {
          method: 'PUT',
          body: { id: item.id, status: newStatus }
        });

        this.disabled = false;

        if (res.success) {
          if (typeof window.showToast === 'function') window.showToast('Status updated!', 'success');
          wrap.className = 'status-pill-wrap';
          wrap.classList.add(`pill-${newStatus.toLowerCase().replace(/ /g, '-')}`);
          item.status = newStatus;
          
          fetchLogistics();
        } else {
          if (typeof window.showToast === 'function') window.showToast(res.message || 'Failed to update status', 'error');
          this.value = ogStatus;
        }
      });

      tbody.appendChild(clone);
    });

    const countEl = document.getElementById('active-deliv-count');
    if (countEl) countEl.textContent = activeCount.toString();

  } else {
    console.error('Logistics load error:', data?.message);
    const tbody = document.getElementById('logistics-tbody');
    tbody.replaceChildren();
    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 4;
    td.className = 'no-data';
    td.textContent = 'Error loading orders.';
    tr.appendChild(td);
    tbody.appendChild(tr);
  }
}

document.getElementById('logisticsForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  
  const customer = document.getElementById('log-customer').value;
  const address  = document.getElementById('log-address').value;
  const product  = document.getElementById('log-product').value;
  const status   = document.getElementById('log-status').value;

  const btn = this.querySelector('button[type="submit"]');
  const ogText = btn.textContent;
  btn.textContent = 'ADDING...';
  btn.disabled = true;

  const data = await apiCall(`${window.env.API_URL}/api/logistics.php`, {
    method: 'POST',
    body: { customer, address, product, status }
  });

  if (data.success) {
    if (typeof window.showToast === 'function') window.showToast('Order added!', 'success');
    this.reset();
    await fetchLogistics();
    if (typeof window.closeModal === 'function') window.closeModal('add-order-modal');
  } else {
    if (typeof window.showToast === 'function') window.showToast(data.message || 'Failed to add order.', 'error');
  }

  btn.textContent = ogText;
  btn.disabled = false;
});

document.addEventListener('DOMContentLoaded', fetchLogistics);