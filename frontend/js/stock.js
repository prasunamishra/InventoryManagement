/**
 * stock.js – Stock page
 *
 * RULE: Stock is NEVER stored directly.
 * Current Stock = Total Stock IN - Total Stock OUT  (from stock_ledger)
 *
 * Tabs:
 *   1. Current Stock  → one row per product, aggregated totals
 *   2. Movement Log   → every individual ledger entry (IN/OUT)
 */

let _stockData  = [];   // aggregated per-product
let _ledgerData = [];   // raw movements
let _activeTab  = 'current';
let showInactive     = false;
let statusTargetIds  = [];
let statusTargetStatus = null;
let statusTargetName = '';

// Aggregated Current Stock table
function renderCurrentStock() {
  const tbody     = document.getElementById('current-stock-tbody');
  const search    = document.getElementById('stock-search').value.toLowerCase();
  const catFilter = document.getElementById('stock-cat-filter').value;

  const rows = _stockData.filter(r => {
    if (!showInactive && r.status === 'inactive') return false;
    const matchSearch = !search ||
      (r.name || '').toLowerCase().includes(search) ||
      (r.sku  || '').toLowerCase().includes(search);
    const matchCat = !catFilter || r.category === catFilter || (r.category === 'Multiple Categories' && catFilter === '');
    return matchSearch && matchCat;
  });

  if (!rows.length) {
    tbody.innerHTML = `
      <tr><td colspan="6" style="padding:40px;text-align:center;">
        <div style="font-size:40px;margin-bottom:10px;">📦</div>
        <div style="font-size:18px;font-weight:700;color:#374151;margin-bottom:6px;">No stock data found</div>
        <div style="font-size:18px;color:#6b7280;margin-bottom:16px;">
          Stock is calculated from purchases. Record a purchase to see stock levels.
        </div>
        <a href="add_product.html" class="confirm-btn" style="text-decoration:none;display:inline-block;padding:10px 24px;">
          + Add New Product & Stock
        </a>
      </td></tr>`;
    return;
  }

  tbody.innerHTML = '';
  rows.forEach(r => {
    const stockIn   = parseInt(r.stock_in    ?? 0, 10);
    const stockOut  = parseInt(r.stock_out   ?? 0, 10);
    const current   = parseInt(r.current_stock ?? 0, 10);

    let levelClass = 'ok';
    if (current <= 0)   levelClass = 'low';
    else if (current < 10) levelClass = 'warn';

    const tr = document.createElement('tr');
    const mergedBadge = r.merged_count > 1 
      ? `<span style="display:inline-block;background:#fef3c7;color:#92400e;font-size:18px;font-weight:700;padding:1px 6px;border-radius:4px;margin-left:6px;vertical-align:middle;border:1px solid #fcd34d;">${r.merged_count} MERGED</span>`
      : '';

    tr.innerHTML = `
      <td>
        <strong>${r.name}</strong>${mergedBadge}<br>
        <span style="font-size:18px;color:#6b7280;font-family:monospace;">${r.sku || ''}</span>
      </td>
      <td>
        <span class="cat-badge cat-${(r.category || 'other').toLowerCase().replace(/\s+/g, '')}">${r.category}</span>
      </td>
      <td style="color:#16a34a;font-weight:700;font-size:18px;">${stockIn}</td>
      <td style="color:#ef4444;font-weight:700;font-size:18px;">${stockOut}</td>
      <td>
        <span class="stock-level ${levelClass}" style="font-size:18px;font-weight:800;">
          ${current} units
        </span>
      </td>
      <td>Rs ${parseFloat(r.price || 0).toFixed(2)}</td>
      <td class="text-center">
        <button class="delete-btn btn-sm status-toggle-btn" 
          data-ids="${r.aggregated_ids}" 
          data-status="${r.status}" 
          data-name="${r.name}"
          style="background: ${r.status === 'inactive' ? '#10b981' : '#fee2e2'}; color: ${r.status === 'inactive' ? 'white' : '#dc2626'};">
          ${r.status === 'inactive' ? 'Activate' : 'Deactivate'}
        </button>
      </td>
    `;

    // Dim inactive rows
    if (r.status === 'inactive') {
      tr.style.opacity    = '0.6';
      tr.style.background = '#f3f4f6';
    }

    tr.querySelector('.status-toggle-btn').addEventListener('click', (e) => {
      const p = {
        ids: e.target.dataset.ids.split(','),
        status: e.target.dataset.status,
        name: e.target.dataset.name
      };
      openStatusModal(p);
    });

    tbody.appendChild(tr);
  });
}

// Raw Movement Log table
function renderLedger() {
  const tbody      = document.getElementById('ledger-tbody');
  const search     = document.getElementById('stock-search').value.toLowerCase();
  const typeFilter = document.getElementById('stock-type-filter').value;

  const rows = _ledgerData.filter(r => {
    const matchSearch = !search || (r.product_name || '').toLowerCase().includes(search);
    const matchType   = !typeFilter || r.type === typeFilter;
    return matchSearch && matchType;
  });

  if (!rows.length) {
    tbody.innerHTML = `
      <tr><td colspan="6" style="padding:40px;text-align:center;">
        <div style="font-size:40px;margin-bottom:10px;">📋</div>
        <div style="font-size:18px;font-weight:700;color:#374151;margin-bottom:6px;">No movements recorded yet</div>
        <div style="font-size:18px;color:#6b7280;margin-bottom:16px;">
          Every purchase, sale, and adjustment will appear here.
        </div>
        <a href="add_product.html" class="confirm-btn" style="text-decoration:none;display:inline-block;padding:10px 24px;">
          + Add New Product & Stock
        </a>
      </td></tr>`;
    return;
  }

  tbody.innerHTML = '';
  rows.forEach(r => {
    const d = r.created_at
      ? new Date(r.created_at).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' })
      : '—';
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td style="color:#6b7280;font-size:18px;">${d}</td>
      <td><strong>${r.product_name}</strong></td>
      <td><span class="type-badge ${r.type}">${r.type}</span></td>
      <td style="font-weight:700;font-size:18px;">${r.quantity}</td>
      <td style="font-size:18px;color:#6b7280;text-transform:capitalize;">
        ${r.reference_type || '—'} ${r.reference_id ? '#' + r.reference_id : ''}
      </td>
      <td style="font-size:18px;color:#374151;">${r.note || '—'}</td>
    `;
    tbody.appendChild(tr);
  });
}

// Stats cards
function updateStats() {
  const totalIn  = _ledgerData.filter(r => r.type === 'IN').reduce((s, r) => s + parseInt(r.quantity, 10), 0);
  const totalOut = _ledgerData.filter(r => r.type === 'OUT').reduce((s, r) => s + parseInt(r.quantity, 10), 0);
  const lowCount = _stockData.filter(r => parseInt(r.current_stock ?? 0, 10) < 10).length;

  document.getElementById('stat-total-in').textContent  = totalIn;
  document.getElementById('stat-total-out').textContent = totalOut;
  document.getElementById('stat-products').textContent  = _stockData.length;
  document.getElementById('stat-low').textContent       = lowCount;
}

// Tab switching
function switchTab(tab) {
  _activeTab = tab;
  document.querySelectorAll('.stock-tab').forEach(t => t.classList.remove('active'));
  document.getElementById(`tab-${tab}`)?.classList.add('active');

  document.getElementById('panel-current').style.display = tab === 'current' ? '' : 'none';
  document.getElementById('panel-ledger').style.display  = tab === 'ledger'  ? '' : 'none';

  const typeFilter = document.getElementById('stock-type-filter');
  if (typeFilter) typeFilter.style.display = tab === 'ledger' ? '' : 'none';

  if (tab === 'current') renderCurrentStock();
  else renderLedger();
}

// Reload all stock data
async function reloadStockData() {
  const [stockRes, ledgerRes] = await Promise.all([
    apiCall(`${window.env.API_URL}/api/stock.php?action=stock`),
    apiCall(`${window.env.API_URL}/api/stock.php?action=ledger`)
  ]);
  if (stockRes?.success)  _stockData  = stockRes.stock   || [];
  if (ledgerRes?.success) _ledgerData = ledgerRes.ledger  || [];
}

// Status modal
function openStatusModal(p) {
  statusTargetIds    = p.ids;
  statusTargetStatus = p.status === 'inactive' ? 'active' : 'inactive';
  statusTargetName   = p.name;

  const msgEl = document.getElementById('status-confirm-msg');
  const btnEl = document.getElementById('confirm-status-btn');

  if (statusTargetStatus === 'inactive') {
    msgEl.textContent   = `Deactivate "${p.name}"? It will be hidden from orders.`;
    btnEl.textContent   = 'Yes, Deactivate';
    btnEl.style.background = '#ef4444';
  } else {
    msgEl.textContent   = `Activate "${p.name}"?`;
    btnEl.textContent   = 'Yes, Activate';
    btnEl.style.background = '#10b981';
  }
  openModal('status-modal');
}

// DOMContentLoaded
document.addEventListener('DOMContentLoaded', async function () {

  await reloadStockData();
  updateStats();
  renderCurrentStock();

  document.querySelectorAll('.stock-tab').forEach(tab => {
    tab.addEventListener('click', () => switchTab(tab.dataset.tab));
  });

  document.getElementById('stock-search').addEventListener('input', () => {
    if (_activeTab === 'current') renderCurrentStock();
    else renderLedger();
  });
  document.getElementById('stock-cat-filter').addEventListener('change', renderCurrentStock);
  document.getElementById('stock-type-filter').addEventListener('change', renderLedger);
  document.getElementById('stock-filter-reset').addEventListener('click', () => {
    document.getElementById('stock-search').value = '';
    document.getElementById('stock-cat-filter').value = '';
    document.getElementById('stock-type-filter').value = '';
    showInactive = false;
    const tog = document.getElementById('show-inactive-toggle');
    if (tog) tog.checked = false;

    if (_activeTab === 'current') renderCurrentStock();
    else renderLedger();
  });

  document.getElementById('show-inactive-toggle')?.addEventListener('change', function (e) {
    showInactive = e.target.checked;
    if (_activeTab === 'current') renderCurrentStock();
  });

  document.getElementById('confirm-status-btn')?.addEventListener('click', async function () {
    if (!statusTargetIds.length || !statusTargetStatus) return;
    this.disabled = true;
    const data = await apiCall(`${window.env.API_URL}/api/products.php`, {
      method: 'POST',
      body: { action: 'update_status', ids: statusTargetIds, status: statusTargetStatus }
    });
    if (data.success) {
      if (data.pending) {
        closeModal('status-modal');
        showToast(data.message || 'Status change submitted for approval.', 'info');
      } else {
        await reloadStockData();
        renderCurrentStock();
        updateStats();
        closeModal('status-modal');
        showToast(`${statusTargetName} ${statusTargetStatus === 'active' ? 'activated' : 'deactivated'} successfully!`);
      }
    } else {
      closeModal('status-modal');
      showAlertPopup('Cannot Change Status', data.message || 'Error.');
    }
    this.disabled = false;
  });

});