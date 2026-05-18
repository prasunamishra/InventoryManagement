/**
 * dashboard.js
 */

let allProducts  = [];
let allLogistics = [];

// Load dashboard data
async function loadDashboard() {
  const data = await apiCall(`${window.env.API_URL}/api/dashboard.php`);
  if (!data || !data.success) return;

  // Save data for search
  allProducts  = data.allProducts      || [];
  allLogistics = data.recentLogistics  || [];

  // Update dashboard cards
  const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
  setVal('stat-products', data.totalProducts  ?? '—');
  setVal('stat-stock',    data.totalStock     ?? '—');
  setVal('stat-lowstock', data.lowStockCount  ?? '—');
  setVal('stat-orders',   data.totalLogistics ?? '—');
  setVal('stat-staff',    data.totalStaff     ?? '—');
  setVal('stat-returns',  data.totalReturns   ?? '—');

  // Show low stock products
  const alertsBox    = document.getElementById('alerts-list');
  const alertsNoData = document.getElementById('alerts-no-data');
  if (alertsBox) {
    alertsBox.replaceChildren();

    // Show message if no low stock items
    if (!data.lowStockItems || data.lowStockItems.length === 0) {
      if (alertsNoData) alertsNoData.classList.remove('hidden');
    } else {
      if (alertsNoData) alertsNoData.classList.add('hidden');

      const tpl = document.getElementById('alert-tpl');

      data.lowStockItems.forEach(item => {
        const clone = tpl.content.cloneNode(true);

        clone.querySelector('[data-alert-name]').textContent  = item.name;
        clone.querySelector('[data-alert-stock]').textContent =
          `Only ${item.stock} unit${item.stock === 1 ? '' : 's'} left`;

        // Restock button link
        const restockBtn = clone.querySelector('[data-alert-restock]');
        if (restockBtn) {
           restockBtn.href = `add_product.html?restock_id=${item.id}`;
        }

        // Open restock page when clicking card
        const card = clone.querySelector('[data-alert-link]');
        if (card) {
          card.addEventListener('click', function(e) {
            if (e.target.tagName === 'A' || e.target.closest('a')) return;

            window.location.href = `add_product.html?restock_id=${item.id}`;
          });
        }

        alertsBox.appendChild(clone);
      });
    }
  }

  // Show recent orders
  const logBox    = document.getElementById('logistics-list');
  const logNoData = document.getElementById('logistics-no-data');

  if (logBox) {
    logBox.replaceChildren();

    // Show message if no orders
    if (!data.recentLogistics || data.recentLogistics.length === 0) {
      if (logNoData) logNoData.classList.remove('hidden');
    } else {
      if (logNoData) logNoData.classList.add('hidden');

      const tpl = document.getElementById('logistics-item-tpl');

      data.recentLogistics.forEach(item => {
        const isDelivered = item.status === 'Delivered';

        const badgeClass  = isDelivered
          ? 'status-delivered'
          : `status-${item.status.toLowerCase().replace(/ /g, '-')}`;

        const clone = tpl.content.cloneNode(true);

        clone.querySelector('[data-log-name]').textContent =
          item.product || item.customer;

        clone.querySelector('[data-log-location]').textContent =
          item.customer;

        const badge = clone.querySelector('[data-log-badge]');

        badge.textContent = item.status;
        badge.classList.add(badgeClass);

        logBox.appendChild(clone);
      });
    }
  }
}

// Load recent purchase returns
async function loadRecentReturns() {
  const data = await apiCall(`${window.env.API_URL}/api/purchase_returns.php`);

  const list   = document.getElementById('pr-recent-list');
  const noData = document.getElementById('pr-no-data');

  if (!list) return;

  list.replaceChildren();

  const returns =
    (data && data.success ? data.returns || [] : []).slice(0, 5);

  // Show message if no returns
  if (returns.length === 0) {
    if (noData) noData.classList.remove('hidden');
    return;
  }

  if (noData) noData.classList.add('hidden');

  const tpl = document.getElementById('pr-recent-tpl');

  returns.forEach(r => {
    const clone = tpl.content.cloneNode(true);

    clone.querySelector('[data-pr-item]').textContent    = r.item_name;
    clone.querySelector('[data-pr-meta]').textContent    =
      `${r.supplier} · ${r.category}`;

    clone.querySelector('[data-pr-invoice]').textContent =
      r.invoice_number;

    clone.querySelector('[data-pr-qty]').textContent =
      `−${r.quantity} units`;

    list.appendChild(clone);
  });
}

// Current approval tab
let currentApprovalTab = 'Pending';

// Load approval requests
async function loadPermissions() {
  const role    =
    (sessionStorage.getItem('gf_role') || '').trim().toLowerCase();

  const jobRole =
    (sessionStorage.getItem('gf_job_role') || '').trim().toLowerCase();

  const isApprover =
    role === 'admin' || jobRole === 'supervisor';

  const section = document.getElementById('permissions-section');

  if (!section) return;

  section.style.display = 'block';

  // Change title for normal users
  const titleEl = document.querySelector('#permissions-section h2');

  if (titleEl) {
      titleEl.textContent =
        isApprover ? 'Approval Panel' : 'My Requests';
  }

  await renderApprovalRequests(currentApprovalTab);
  await updateApprovalBadge();
}

// Show approval requests
async function renderApprovalRequests(status) {
  const list   = document.getElementById('permissions-list');
  const noData = document.getElementById('permissions-no-data');

  if (!list) return;

  list.replaceChildren();

  if (noData) noData.classList.add('hidden');

  const data = await apiCall(
    `${window.env.API_URL}/api/requests.php?action=list&status=${status}`
  );

  const reqs =
    (data && data.success ? data.requests : null) || [];

  // Show message if no requests
  if (reqs.length === 0) {
    if (noData) noData.classList.remove('hidden');
    return;
  }

  // Request labels
  const actionLabels = {
    create_order:           '📦 Create Order',
    update_order:           '🔄 Update Order',
    delete_order:           '🗑️ Delete Order',
    create_product:         '➕ Add Product(s)',
    update_product:         '✏️ Update Product',
    delete_product:         '🗑️ Delete Product',
    update_product_status:  '⚡ Update Product Status',
    stock_adjustment:       '📊 Stock Adjustment',
    create_purchase_return: '🔙 Purchase Return',
  };

  reqs.forEach(req => {
    const isPending  = req.status === 'Pending';

    const card = document.createElement('div');
    card.className = 'approval-card';

    const ts = new Date(req.created_at.replace(' ', 'T'));

    const timeStr =
      ts.toLocaleDateString('en-GB', {
        day:'2-digit',
        month:'short',
        year:'numeric'
      })
      + ' ' +
      ts.toLocaleTimeString('en-GB', {
        hour:'2-digit',
        minute:'2-digit'
      });

    // Status color
    const statusColor = req.status === 'Approved'
      ? '#16a34a'
      : req.status === 'Rejected'
      ? '#dc2626'
      : '#d97706';

    // Check user role
    const isApprover =
      (sessionStorage.getItem('gf_role') || '').toLowerCase() === 'admin' ||
      (sessionStorage.getItem('gf_job_role') || '').toLowerCase() === 'supervisor';

    card.innerHTML = `
      <div class="approval-card-top">
        <div class="approval-card-left">

          <div class="approval-action-label">
            ${actionLabels[req.action_type] || req.action_type}
          </div>

          <div class="approval-description">
            ${req.description}
          </div>

          <div class="approval-meta">
            <span class="approval-requester">
              👤 ${req.requester_name}
            </span>

            <span class="approval-role-tag">
              ${req.requester_role}
            </span>

            <span class="approval-time">
              🕐 ${timeStr}
            </span>
          </div>
        </div>

        <div class="approval-card-right">

          <span class="approval-status-badge"
            style="color:${statusColor}; border-color:${statusColor};">
            ${req.status}
          </span>

          ${isPending && isApprover ? `
            <div class="approval-actions">
              <button class="confirm-btn approval-approve-btn"
                onclick="reviewRequest(${req.id},'Approved')">
                ✓ Approve
              </button>

              <button class="delete-btn approval-reject-btn"
                onclick="reviewRequest(${req.id},'Rejected')">
                ✗ Reject
              </button>
            </div>
          ` : (req.reviewer_name
              ? `<div class="approval-reviewer">by ${req.reviewer_name}</div>`
              : '')}
        </div>
      </div>
    `;

    list.appendChild(card);
  });
}

// Approve or reject request
async function reviewRequest(requestId, decision) {
  const data = await apiCall(
    `${window.env.API_URL}/api/requests.php?action=review`,
    {
      method: 'POST',
      body: {
        action: 'review',
        request_id: requestId,
        decision
      }
    }
  );

  if (data && data.success) {
    showToast(data.message || `Request ${decision}.`, 'success');

    await renderApprovalRequests(currentApprovalTab);
    await updateApprovalBadge();
  } else {
    showToast(
      (data && data.message) || 'Error processing request.',
      'error'
    );
  }
}

// Update approval count badge
async function updateApprovalBadge() {
  const badge = document.getElementById('approval-badge');

  if (!badge) return;

  const data = await apiCall(
    `${window.env.API_URL}/api/requests.php?action=count`
  );

  const count =
    data && data.success ? (data.count || 0) : 0;

  if (count > 0) {
    badge.textContent = count;
    badge.classList.remove('hidden');
  } else {
    badge.classList.add('hidden');
  }
}

// Change approval tab
function switchApprovalTab(btn, status) {
  currentApprovalTab = status;

  document.querySelectorAll('.approval-tab').forEach(t =>
    t.classList.remove('active')
  );

  btn.classList.add('active');

  renderApprovalRequests(status);
}

// Load products and orders for search
async function loadAllForSearch() {
  const [pData, lData] = await Promise.all([
    apiCall(`${window.env.API_URL}/api/products.php`),
    apiCall(`${window.env.API_URL}/api/logistics.php`)
  ]);

  allProducts  = pData?.products  || [];
  allLogistics = lData?.logistics || [];
}

// Highlight matching search text
function highlightNode(el, text, query) {
  el.replaceChildren();

  const lowerText = text.toLowerCase();
  const lowerQuery = query.toLowerCase();

  let lastIndex = 0;
  let idx = 0;

  while ((idx = lowerText.indexOf(lowerQuery, lastIndex)) !== -1) {

    if (idx > lastIndex) {
      el.appendChild(
        document.createTextNode(text.substring(lastIndex, idx))
      );
    }

    const mark = document.createElement('mark');

    mark.textContent =
      text.substring(idx, idx + query.length);

    el.appendChild(mark);

    lastIndex = idx + query.length;
  }

  if (lastIndex < text.length) {
    el.appendChild(
      document.createTextNode(text.substring(lastIndex))
    );
  }
}

// Show search results
function renderSearchResults(query) {
  const resultsBox = document.getElementById('search-results');

  if (!resultsBox) return;

  const q = query.trim().toLowerCase();

  // Hide result box if search is empty
  if (!q) {
    resultsBox.classList.add('hidden');
    return;
  }

  resultsBox.replaceChildren();

  // Search matching products
  const matchedProducts = allProducts.filter(p =>
    p.name.toLowerCase().includes(q) ||
    p.sku.toLowerCase().includes(q)  ||
    p.category.toLowerCase().includes(q) ||
    (p.supplier && p.supplier.toLowerCase().includes(q))
  );

  const groupTpl = document.getElementById('search-group-tpl');
  const itemTpl = document.getElementById('search-item-tpl');

  // Show matching products
  if (matchedProducts.length) {

    const groupClone = groupTpl.content.cloneNode(true);

    groupClone.querySelector('[data-group-name]').textContent =
      'Products';

    resultsBox.appendChild(groupClone);

    matchedProducts.slice(0, 5).forEach(p => {

      const cls =
        p.stock < 10 ? 'search-stock-low' : 'search-stock-ok';

      const itemClone = itemTpl.content.cloneNode(true);

      const row =
        itemClone.querySelector('[data-search-link]');

      row.onclick = () =>
        window.location.href = 'products.html';

      highlightNode(
        itemClone.querySelector('[data-search-name]'),
        p.name,
        query
      );

      itemClone.querySelector('[data-search-meta]').textContent =
        `${p.sku} · ${p.category}`;

      const badge =
        itemClone.querySelector('[data-search-badge]');

      badge.textContent = `${p.stock} units`;

      badge.classList.add(cls);

      resultsBox.appendChild(itemClone);
    });
  }

  // Search matching orders
  const matchedLogistics = allLogistics.filter(l => {

    const productStr =
      l.product ||
      (l.items || []).map(i => i.product_name).join(' ');

    return l.customer.toLowerCase().includes(q) ||
      productStr.toLowerCase().includes(q) ||
      l.address.toLowerCase().includes(q)  ||
      l.status.toLowerCase().includes(q);
  });

  // Show matching orders
  if (matchedLogistics.length) {

    const groupClone = groupTpl.content.cloneNode(true);

    groupClone.querySelector('[data-group-name]').textContent =
      'Orders';

    resultsBox.appendChild(groupClone);

    matchedLogistics.slice(0, 5).forEach(l => {

      const cls =
        `status-${l.status.toLowerCase().replace(/ /g, '-')}`;

      const itemClone = itemTpl.content.cloneNode(true);

      const row =
        itemClone.querySelector('[data-search-link]');

      row.onclick = () =>
        window.location.href = 'logistics.html';

      highlightNode(
        itemClone.querySelector('[data-search-name]'),
        l.customer,
        query
      );

      const productStr =
        l.product ||
        (l.items || []).map(i => i.product_name).join(', ') ||
        '—';

      itemClone.querySelector('[data-search-meta]').textContent =
        productStr;

      const badge =
        itemClone.querySelector('[data-search-badge]');

      badge.textContent = l.status;

      badge.classList.add('status-badge', cls);

      resultsBox.appendChild(itemClone);
    });
  }

  // Show message if nothing found
  if (matchedProducts.length === 0 &&
      matchedLogistics.length === 0) {

    const noResTpl =
      document.getElementById('search-no-results-tpl');

    const clone = noResTpl.content.cloneNode(true);

    const container =
      clone.querySelector('[data-no-results]');

    container.textContent = 'No results for "';

    const strong = document.createElement('strong');

    strong.textContent = query;

    container.appendChild(strong);

    container.appendChild(document.createTextNode('"'));

    resultsBox.appendChild(clone);
  }

  resultsBox.classList.remove('hidden');
}

// Run dashboard when page loads
document.addEventListener('DOMContentLoaded', async function () {

  await loadDashboard();
  await loadPermissions();
  await loadAllForSearch();
  await loadRecentReturns();

  const input    = document.getElementById('dashboard-search');
  const clearBtn = document.getElementById('search-clear');
  const results  = document.getElementById('search-results');

  if (!input) return;

  // Search input event
  input.addEventListener('input', function () {

    if (clearBtn) {
      clearBtn.classList.toggle('hidden', !this.value);
    }

    renderSearchResults(this.value);
  });

  // Clear search input
  if (clearBtn) {
    clearBtn.addEventListener('click', function () {

      input.value = '';

      this.classList.add('hidden');

      if (results) {
        results.classList.add('hidden');
      }

      input.focus();
    });
  }

  // Hide search results when clicking outside
  document.addEventListener('click', function (e) {

    if (results && !e.target.closest('.dashboard-banner')) {
      results.classList.add('hidden');
    }
  });

  // Show search results on input focus
  input.addEventListener('focus', function () {

    if (this.value) {
      renderSearchResults(this.value);
    }
  });
});