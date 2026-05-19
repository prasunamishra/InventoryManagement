/**
 * logistics.js – Orders management (multi-product display + status update)
 */

let _allLogistics      = [];
let _filteredLogistics = [];

function applyLogiFilters() {
  const q      = (document.getElementById('logi-search')?.value || '').toLowerCase();
  const status = document.getElementById('logi-filter-status')?.value || '';
  const from   = document.getElementById('logi-filter-from')?.value   || '';
  const to     = document.getElementById('logi-filter-to')?.value     || '';

  _filteredLogistics = _allLogistics.filter(item => {
    if (q) {
      // Search customer, address, invoice, and all item names
      const items = (item.items || []).map(i => i.product_name).join(' ');
      const hay = [item.customer, item.address, item.invoice_number || '', items].join(' ').toLowerCase();
      if (!hay.includes(q)) return false;
    }
    if (status && item.status !== status) return false;
    if (from || to) {
      const d = item.created_at ? item.created_at.substring(0, 10) : '';
      if (from && d < from) return false;
      if (to   && d > to)   return false;
    }
    return true;
  });
  renderLogistics();
}

function renderLogistics() {
  const tbody = document.getElementById('logistics-tbody');
  const tpl   = document.getElementById('logistics-row-tpl');
  tbody.replaceChildren();

  if (_filteredLogistics.length === 0) {
    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 5; td.className = 'no-data'; td.textContent = 'No orders found.';
    tr.appendChild(td); tbody.appendChild(tr);
    const c = document.getElementById('active-deliv-count');
    if (c) c.textContent = '0';
    return;
  }

  let activeCount = 0;
  _filteredLogistics.forEach(item => {
    if (item.status !== 'Delivered') activeCount++;

    const clone = tpl.content.cloneNode(true);
    clone.querySelector('[data-logi-invoice]').textContent  = item.invoice_number || '—';
    clone.querySelector('[data-logi-customer]').textContent = item.customer;
    clone.querySelector('[data-logi-address]').textContent  = item.address;

    // ── Multi-product display ──────────────────────────────────────────────
    const productCell = clone.querySelector('[data-logi-product]');
    if (item.items && item.items.length > 0) {
      productCell.innerHTML = item.items.map(i =>
        `<span style="display:inline-block;background:#eff6ff;color:#1d4ed8;border-radius:6px;padding:2px 8px;font-size:18px;margin:2px 2px 2px 0;">
          ${i.product_name} <strong>×${i.quantity}</strong>
        </span>`
      ).join('');
    } else {
      productCell.textContent = item.product || '—';
    }

    const wrap   = clone.querySelector('[data-logi-status-wrap]');
    const select = clone.querySelector('[data-logi-status-select]');
    if (select) select.value = item.status;
    if (wrap) wrap.classList.add(`pill-${item.status.toLowerCase().replace(/ /g, '-')}`);

    if (item.status === 'Delivered') {
      if (select) { select.disabled = true; select.title = 'Delivered orders cannot be changed'; }
      if (wrap)   wrap.classList.add('pill-locked');
    } else if (select) {
      select.addEventListener('change', async function () {
        const newStatus = this.value;
        const ogStatus  = item.status;
        this.disabled   = true;

        const res = await apiCall(`${window.env.API_URL}/api/logistics.php`, {
          method: 'PUT', body: { id: item.id, status: newStatus }
        });

        this.disabled = false;
        if (res.success) {
          // Logistics Coordinator → queued as pending request
          if (res.request_id) {
            showToast('⏳ Status change submitted for approval.', 'success');
            this.value = ogStatus; // revert UI until approved
          } else {
            showToast('Status updated!', 'success');
            if (wrap) {
              wrap.className = 'status-pill-wrap';
              wrap.classList.add(`pill-${newStatus.toLowerCase().replace(/ /g, '-')}`);
            }
            item.status = newStatus;
            if (newStatus === 'Delivered') fetchLogistics();
          }
        } else {
          showToast(res.message || 'Failed to update', 'error');
          this.value = ogStatus;
        }
      });
    }

    tbody.appendChild(clone);
  });

  const c = document.getElementById('active-deliv-count');
  if (c) c.textContent = activeCount.toString();
}

async function fetchLogistics() {
  const data = await apiCall(`${window.env.API_URL}/api/logistics.php`);
  if (data && data.success !== false) {
    _allLogistics = data.logistics || [];
    applyLogiFilters();
  } else {
    document.getElementById('logistics-tbody').innerHTML =
      '<tr><td colspan="5" class="no-data">Error loading orders.</td></tr>';
  }
}

document.addEventListener('DOMContentLoaded', async function () {
  await fetchLogistics();

  // Wire up filters
  document.getElementById('logi-search')?.addEventListener('input', applyLogiFilters);
  document.getElementById('logi-filter-status')?.addEventListener('change', applyLogiFilters);
  document.getElementById('logi-filter-from')?.addEventListener('change', applyLogiFilters);
  document.getElementById('logi-filter-to')?.addEventListener('change', applyLogiFilters);
  document.getElementById('logi-filter-reset')?.addEventListener('click', () => {
    ['logi-search', 'logi-filter-status', 'logi-filter-from', 'logi-filter-to']
      .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    applyLogiFilters();
  });

  // "Add Order" button → redirect to create_order.html
  document.getElementById('add-order-btn')?.addEventListener('click', () => {
    window.location.href = 'create_order.html';
  });
});
