/**
 * dashboard.js – Dashboard page logic
 * Renders recent orders, low-stock alerts, and global search (HTML templated).
 */

let allProducts  = [];
let allLogistics = [];

// ── Load dashboard data ───────────────────────────────────────────────────────
async function loadDashboard() {
  const data = await apiCall(`${window.env.API_URL}/api/dashboard.php`);
  if (!data || !data.success) return;

    // Cache for search
    allProducts  = data.allProducts      || [];
    allLogistics = data.recentLogistics  || [];

    // ── Low stock alerts ──────────────────────────────────────────────────
    const alertsBox = document.getElementById('alerts-list');
    const alertsNoData = document.getElementById('alerts-no-data');
    if (alertsBox) {
      alertsBox.replaceChildren();
      if (!data.lowStockItems || data.lowStockItems.length === 0) {
        if (alertsNoData) alertsNoData.classList.remove('hidden');
      } else {
        if (alertsNoData) alertsNoData.classList.add('hidden');
        const tpl = document.getElementById('alert-tpl');
        data.lowStockItems.forEach(item => {
          const params = new URLSearchParams({
            name: item.name, category: item.category || '',
            price: item.price || '', cost: item.cost || '',
            supplier: item.supplier || '', storage: item.storage || '',
          });
          const clone = tpl.content.cloneNode(true);
          clone.querySelector('[data-alert-name]').textContent = item.name;
          clone.querySelector('[data-alert-stock]').textContent = `Only ${item.stock} unit${item.stock === 1 ? '' : 's'} left`;
          clone.querySelector('[data-alert-link]').href = `add_product.html?${params}`;
          alertsBox.appendChild(clone);
        });
      }
    }

    // ── Recent logistics ──────────────────────────────────────────────────
    const logBox = document.getElementById('logistics-list');
    const logNoData = document.getElementById('logistics-no-data');
    if (logBox) {
      logBox.replaceChildren();
      if (!data.recentLogistics || data.recentLogistics.length === 0) {
        if (logNoData) logNoData.classList.remove('hidden');
      } else {
        if (logNoData) logNoData.classList.add('hidden');
        const tpl = document.getElementById('logistics-item-tpl');
        data.recentLogistics.forEach(item => {
          const isDelivered = item.status === 'Delivered';
          const badgeClass  = isDelivered ? 'status-delivered' : `status-${item.status.toLowerCase().replace(/ /g, '-')}`;
          
          const clone = tpl.content.cloneNode(true);
          
          const cat = item.category || item.customer;
          
          clone.querySelector('[data-log-name]').textContent = item.product;
          clone.querySelector('[data-log-location]').textContent = item.customer;
          const badge = clone.querySelector('[data-log-badge]');
          badge.textContent = item.status;
          badge.classList.add(badgeClass);
          logBox.appendChild(clone);
        });
      }
    }

}

// ── Pending Permissions ───────────────────────────────────────────────────────
async function loadPermissions() {
  const role = (sessionStorage.getItem('gf_role') || '').trim().toLowerCase();
  const jobRole = (sessionStorage.getItem('gf_job_role') || '').trim().toLowerCase();
  if (role !== 'admin' && jobRole !== 'supervisor') return;

  const section = document.getElementById('permissions-section');
  if (!section) return;

  const data = await apiCall(`${window.env.API_URL}/api/permissions.php?action=list`);
  if (!data || !data.success) return;

  section.style.display = 'block';

  const list = document.getElementById('permissions-list');
  const noData = document.getElementById('permissions-no-data');
  const reqs = data.requests || [];

  list.replaceChildren();

  if (reqs.length === 0) {
    if(noData) noData.classList.remove('hidden');
  } else {
    if(noData) noData.classList.add('hidden');
    const tpl = document.getElementById('permission-item-tpl');
    reqs.forEach(req => {
      const clone = tpl.content.cloneNode(true);
      clone.querySelector('[data-perm-name]').textContent = req.product_name || 'New Product';
      clone.querySelector('[data-perm-username]').textContent = 'by @' + (req.username || 'staff');

      const approveBtn = clone.querySelector('[data-perm-approve]');
      const rejectBtn = clone.querySelector('[data-perm-reject]');

      approveBtn.onclick = () => updatePermission(req.product_id, 'approved');
      rejectBtn.onclick = () => updatePermission(req.product_id, 'rejected');

      list.appendChild(clone);
    });
  }
}

async function updatePermission(productId, status) {
  const data = await apiCall(`${window.env.API_URL}/api/permissions.php?action=update`, {
     method: 'POST',
     body: { product_id: productId, status }
  });
  if (data.success) {
     loadPermissions();
  } else {
     alert(data.message || 'Error updating permission');
  }
}

// ── Global search ─────────────────────────────────────────────────────────────
async function loadAllForSearch() {
  const [pData, lData] = await Promise.all([
    apiCall(`${window.env.API_URL}/api/products.php`),
    apiCall(`${window.env.API_URL}/api/logistics.php`)
  ]);
  allProducts  = pData?.products  || [];
  allLogistics = lData?.logistics || [];
}

// Applies highlighting by splitting the text node, purely using DOM API.
function highlightNode(el, text, query) {
  el.replaceChildren();
  const lowerText = text.toLowerCase();
  const lowerQuery = query.toLowerCase();
  let lastIndex = 0;
  let idx = 0;
  
  while ((idx = lowerText.indexOf(lowerQuery, lastIndex)) !== -1) {
    if (idx > lastIndex) {
      el.appendChild(document.createTextNode(text.substring(lastIndex, idx)));
    }
    const mark = document.createElement('mark');
    mark.textContent = text.substring(idx, idx + query.length);
    el.appendChild(mark);
    lastIndex = idx + query.length;
  }
  if (lastIndex < text.length) {
    el.appendChild(document.createTextNode(text.substring(lastIndex)));
  }
}

function renderSearchResults(query) {
  const resultsBox = document.getElementById('search-results');
  if (!resultsBox) return;
  const q = query.trim().toLowerCase();

  if (!q) { resultsBox.classList.add('hidden'); return; }
  resultsBox.replaceChildren();

  const matchedProducts = allProducts.filter(p =>
    p.name.toLowerCase().includes(q) ||
    p.sku.toLowerCase().includes(q)  ||
    p.category.toLowerCase().includes(q) ||
    (p.supplier && p.supplier.toLowerCase().includes(q))
  );

  const groupTpl = document.getElementById('search-group-tpl');
  const itemTpl = document.getElementById('search-item-tpl');

  if (matchedProducts.length) {
    const groupClone = groupTpl.content.cloneNode(true);
    groupClone.querySelector('[data-group-name]').textContent = 'Products';
    resultsBox.appendChild(groupClone);

    matchedProducts.slice(0, 5).forEach(p => {
      const cls = p.stock < 10 ? 'search-stock-low' : 'search-stock-ok';
      const itemClone = itemTpl.content.cloneNode(true);
      const row = itemClone.querySelector('[data-search-link]');
      row.onclick = () => window.location.href = 'products.html';
      
      highlightNode(itemClone.querySelector('[data-search-name]'), p.name, query);
      itemClone.querySelector('[data-search-meta]').textContent = `${p.sku} · ${p.category}`;
      const badge = itemClone.querySelector('[data-search-badge]');
      badge.textContent = `${p.stock} units`;
      badge.classList.add(cls);
      resultsBox.appendChild(itemClone);
    });
  }

  const matchedLogistics = allLogistics.filter(l =>
    l.customer.toLowerCase().includes(q) ||
    l.product.toLowerCase().includes(q)  ||
    l.address.toLowerCase().includes(q)  ||
    l.status.toLowerCase().includes(q)
  );

  if (matchedLogistics.length) {
    const groupClone = groupTpl.content.cloneNode(true);
    groupClone.querySelector('[data-group-name]').textContent = 'Orders';
    resultsBox.appendChild(groupClone);

    matchedLogistics.slice(0, 5).forEach(l => {
      const cls = `status-${l.status.toLowerCase().replace(/ /g, '-')}`;
      const itemClone = itemTpl.content.cloneNode(true);
      const row = itemClone.querySelector('[data-search-link]');
      row.onclick = () => window.location.href = 'logistics.html';
      
      highlightNode(itemClone.querySelector('[data-search-name]'), l.customer, query);
      itemClone.querySelector('[data-search-meta]').textContent = l.product;
      const badge = itemClone.querySelector('[data-search-badge]');
      badge.textContent = l.status;
      badge.classList.add('status-badge', cls);
      resultsBox.appendChild(itemClone);
    });
  }

  if (matchedProducts.length === 0 && matchedLogistics.length === 0) {
    const noResTpl = document.getElementById('search-no-results-tpl');
    const clone = noResTpl.content.cloneNode(true);
    const container = clone.querySelector('[data-no-results]');
    container.textContent = 'No results for "';
    const strong = document.createElement('strong');
    strong.textContent = query;
    container.appendChild(strong);
    container.appendChild(document.createTextNode('"'));
    resultsBox.appendChild(clone);
  }

  resultsBox.classList.remove('hidden');
}

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async function () {
  await loadDashboard();
  await loadPermissions();
  await loadAllForSearch();

  const input    = document.getElementById('dashboard-search');
  const clearBtn = document.getElementById('search-clear');
  const results  = document.getElementById('search-results');

  if (!input) return;

  input.addEventListener('input', function () {
    if (clearBtn) clearBtn.classList.toggle('hidden', !this.value);
    renderSearchResults(this.value);
  });

  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      input.value = '';
      this.classList.add('hidden');
      if (results) results.classList.add('hidden');
      input.focus();
    });
  }

  document.addEventListener('click', function (e) {
    if (results && !e.target.closest('.dashboard-banner')) {
      results.classList.add('hidden');
    }
  });

  input.addEventListener('focus', function () {
    if (this.value) renderSearchResults(this.value);
  });
});
