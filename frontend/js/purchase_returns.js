/**
 * purchase_returns.js – Purchase Returns page logic
 */

let _allReturns      = [];
let _filteredReturns = [];
let _productsByCategory = {};
let _stockMap = {};

// ── Helpers ───────────────────────────────────────────────────────────────────
function showPRMsg(type, text) {
  const el = document.getElementById('pr-form-msg');
  if (!el) return;
  el.textContent = text;
  el.className = 'form-message ' + type;
  el.style.display = 'block';
}
function hidePRMsg() {
  const el = document.getElementById('pr-form-msg');
  if (el) el.style.display = 'none';
}

// ── Auto-generate invoice from backend (sequential PR-XXXX) ───────────────────
async function fetchNextInvoice() {
  const data = await apiCall(`${window.env.API_URL}/api/purchase_returns.php?action=next_invoice`);
  if (data && data.success) {
    document.getElementById('pr-invoice').value = data.invoice_number;
  }
}

// ── Filter + render ───────────────────────────────────────────────────────────
function applyPRFilters() {
  const q        = (document.getElementById('pr-search')?.value || '').trim().toLowerCase();
  const supplier = document.getElementById('pr-filter-supplier')?.value || '';
  const from     = document.getElementById('pr-filter-from')?.value || '';
  const to       = document.getElementById('pr-filter-to')?.value   || '';

  _filteredReturns = _allReturns.filter(r => {
    if (supplier && r.supplier !== supplier) return false;
    if (from || to) {
      const d = r.created_at ? r.created_at.substring(0, 10) : '';
      if (from && d < from) return false;
      if (to   && d > to)   return false;
    }
    if (q) {
      const hay = [r.invoice_number, r.supplier, r.item_name, r.category].join(' ').toLowerCase();
      if (!hay.includes(q)) return false;
    }
    return true;
  });
  renderPRTable();
}

function renderPRTable() {
  const tbody = document.getElementById('pr-tbody');
  const tpl   = document.getElementById('pr-row-tpl');
  tbody.replaceChildren();

  if (_filteredReturns.length === 0) {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td colspan="6" class="no-data">No purchase returns found.</td>`;
    tbody.appendChild(tr);
    return;
  }

  _filteredReturns.forEach(r => {
    const clone = tpl.content.cloneNode(true);
    clone.querySelector('[data-pr-invoice]').textContent  = r.invoice_number || '—';
    clone.querySelector('[data-pr-supplier]').textContent = r.supplier;
    clone.querySelector('[data-pr-item]').textContent     = r.item_name;
    clone.querySelector('[data-pr-category]').textContent = r.category;
    clone.querySelector('[data-pr-qty]').textContent      = '−' + r.quantity + ' units';
    clone.querySelector('[data-pr-date]').textContent     = r.created_at
      ? new Date(r.created_at).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' })
      : '—';
    tbody.appendChild(clone);
  });
}

// ── Fetch all returns ─────────────────────────────────────────────────────────
async function fetchReturns() {
  const data = await apiCall(`${window.env.API_URL}/api/purchase_returns.php`);
  if (data && data.success) {
    _allReturns = data.returns || [];
    applyPRFilters();
  } else {
    const tbody = document.getElementById('pr-tbody');
    tbody.innerHTML = `<tr><td colspan="6" class="no-data">Error loading returns.</td></tr>`;
  }
}

// ── Load suppliers into form + filter dropdowns ───────────────────────────────
async function loadSuppliers() {
  const data = await apiCall(`${window.env.API_URL}/api/suppliers.php`);
  const suppliers = (data && data.success !== false) ? (data.suppliers || []) : [];

  const formSel   = document.getElementById('pr-supplier');
  const filterSel = document.getElementById('pr-filter-supplier');

  suppliers.forEach(s => {
    [formSel, filterSel].forEach(sel => {
      if (!sel) return;
      const opt = document.createElement('option');
      opt.value = s.name; opt.textContent = s.name;
      sel.appendChild(opt);
    });
  });
}

// ── Load products, build category → items map ─────────────────────────────────
async function loadProducts() {
  const data = await apiCall(`${window.env.API_URL}/api/products.php`);
  if (!data || !data.success) return;

  (data.products || []).forEach(p => {
    if (p.status === 'inactive') return;
    const cat = p.category || 'Other';
    if (!_productsByCategory[cat]) _productsByCategory[cat] = [];
    _productsByCategory[cat].push(p);
    _stockMap[p.name] = parseInt(p.stock, 10) || 0;
  });

  // ── Populate category dropdown from real product categories ──────────────
  const catSel = document.getElementById('pr-category-sel');
  if (catSel) {
    catSel.replaceChildren();
    const def = document.createElement('option');
    def.value = ''; def.disabled = true; def.selected = true;
    def.textContent = Object.keys(_productsByCategory).length
      ? 'Select category'
      : 'No categories available';
    catSel.appendChild(def);

    Object.keys(_productsByCategory).sort().forEach(cat => {
      const opt = document.createElement('option');
      opt.value = cat; opt.textContent = cat;
      catSel.appendChild(opt);
    });
  }

  // Reset item dropdown to default state
  const itemSel = document.getElementById('pr-item');
  if (itemSel) {
    itemSel.replaceChildren();
    const def2 = document.createElement('option');
    def2.value = ''; def2.disabled = true; def2.selected = true;
    def2.textContent = 'Select category first';
    itemSel.appendChild(def2);
    itemSel.disabled = true;
  }
}

// ── Category → Item cascade ───────────────────────────────────────────────────
function onCategoryChange() {
  const cat      = document.getElementById('pr-category-sel').value;
  const itemSel  = document.getElementById('pr-item');
  const stockInfo = document.getElementById('pr-stock-info');

  itemSel.replaceChildren();
  const def = document.createElement('option');
  def.value = ''; def.disabled = true; def.selected = true;
  def.textContent = cat ? 'Select an item' : 'Select category first';
  itemSel.appendChild(def);
  if (stockInfo) stockInfo.textContent = '';

  if (cat && _productsByCategory[cat]) {
    _productsByCategory[cat].forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.name; opt.textContent = p.name;
      itemSel.appendChild(opt);
    });
    itemSel.disabled = false;
  } else {
    itemSel.disabled = true;
  }
}

function onItemChange() {
  const item      = document.getElementById('pr-item').value;
  const stockInfo = document.getElementById('pr-stock-info');
  if (!stockInfo) return;
  if (item && item in _stockMap) {
    const s = _stockMap[item];
    stockInfo.textContent = `Available stock: ${s} unit${s !== 1 ? 's' : ''}`;
    stockInfo.style.color = s < 5 ? '#dc2626' : '#6b7280';
  } else {
    stockInfo.textContent = '';
  }
}

// ── Form submit ───────────────────────────────────────────────────────────────
document.getElementById('pr-form').addEventListener('submit', async function (e) {
  e.preventDefault();
  hidePRMsg();

  const invoice  = (document.getElementById('pr-invoice').value || '').trim().toUpperCase();
  const supplier = document.getElementById('pr-supplier').value;
  const category = document.getElementById('pr-category-sel').value;
  const item     = document.getElementById('pr-item').value;
  const qty      = parseInt(document.getElementById('pr-qty').value, 10) || 0;
  const btn      = document.getElementById('pr-submit');

  // Frontend validations
  if (!invoice || !supplier || !category || !item || qty <= 0) {
    showPRMsg('error', 'Please fill in all required fields.');
    return;
  }

  // Frontend stock check
  if (item in _stockMap && qty > _stockMap[item]) {
    showPRMsg('error', `Return quantity exceeds available stock. Only ${_stockMap[item]} unit${_stockMap[item] !== 1 ? 's' : ''} in stock.`);
    return;
  }

  btn.disabled = true; btn.textContent = 'Submitting…';

  const data = await apiCall(`${window.env.API_URL}/api/purchase_returns.php`, {
    method: 'POST',
    body: { invoice_number: invoice, supplier, category, item_name: item, quantity: qty }
  });

  btn.disabled = false; btn.textContent = 'Submit Return';

  if (data.success) {
    closeModal('pr-modal');
    showToast('Purchase return recorded successfully. Stock has been updated.', 'success');
    // Update local stock map
    if (item in _stockMap) _stockMap[item] = Math.max(0, _stockMap[item] - qty);
    this.reset();
    document.getElementById('pr-item').replaceChildren();
    document.getElementById('pr-stock-info').textContent = '';
    await fetchReturns();
    await fetchNextInvoice(); // pre-load next invoice for next time
  } else if (data.message && data.message.toLowerCase().includes('invoice')) {
    showAlertPopup('Invoice Error', data.message);
  } else if (data.message && data.message.toLowerCase().includes('quantity') || (data.message || '').toLowerCase().includes('stock')) {
    showAlertPopup('Stock Error', data.message || 'Return quantity exceeds available stock.');
  } else {
    showPRMsg('error', data.message || 'Failed to record return.');
  }
});

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
  await Promise.all([loadSuppliers(), loadProducts(), fetchReturns()]);
  await fetchNextInvoice();

  // Open form button
  document.getElementById('pr-open-form-btn')?.addEventListener('click', async () => {
    hidePRMsg();
    await fetchNextInvoice();
    openModal('pr-modal');
  });

  // Auto-generate button inside modal
  document.getElementById('pr-gen-invoice')?.addEventListener('click', fetchNextInvoice);

  // Category → item cascade
  document.getElementById('pr-category-sel')?.addEventListener('change', onCategoryChange);
  document.getElementById('pr-item')?.addEventListener('change', onItemChange);

  // Filter wiring
  document.getElementById('pr-search')?.addEventListener('input', applyPRFilters);
  document.getElementById('pr-filter-supplier')?.addEventListener('change', applyPRFilters);
  document.getElementById('pr-filter-from')?.addEventListener('change', applyPRFilters);
  document.getElementById('pr-filter-to')?.addEventListener('change', applyPRFilters);
  document.getElementById('pr-filter-reset')?.addEventListener('click', () => {
    ['pr-search','pr-filter-supplier','pr-filter-from','pr-filter-to']
      .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    applyPRFilters();
  });
});