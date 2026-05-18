/**
 * Product list page functions
 */

let allProducts      = [];
let filteredProducts = [];

// Get all products from API
async function fetchProducts() {
  const data = await apiCall(`${window.env.API_URL}/api/products.php`);
  if (data && data.success !== false) {
    allProducts = data.products || [];
    applyFilters();
  } else {
    const tbody = document.getElementById('prod-tbody');
    tbody.innerHTML = '<tr><td colspan="6" class="no-data">Error loading products.</td></tr>';
  }
}

// Show products in table
function renderTable() {
  const tbody  = document.getElementById('prod-tbody');
  const tpl    = document.getElementById('prod-row-tpl');

  tbody.replaceChildren();

  // Show message if no products found
  if (filteredProducts.length === 0) {
    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 6; td.className = 'no-data'; td.textContent = 'No products found.';
    tr.appendChild(td); tbody.appendChild(tr);
    return;
  }

  filteredProducts.forEach(p => {
    const row = tpl.content.cloneNode(true);

    row.querySelector('[data-name]').textContent = p.name;
    row.querySelector('[data-sku]').textContent  = 'SKU: ' + p.sku;

    // Show latest invoice number
    const invCell = row.querySelector('[data-invoice]');
    if (invCell) invCell.textContent = p.latest_invoice || '—';

    // Show category badge
    const catCell  = row.querySelector('[data-category]');
    const badge    = document.createElement('span');
    badge.textContent = p.category;
    badge.className   = `cat-badge cat-${(p.category || 'other').toLowerCase().replace(/\s+/g, '')}`;
    catCell.appendChild(badge);

    // Show stock status
    const stockCell = row.querySelector('[data-stock]');
    const stockVal  = parseInt(p.stock ?? 0, 10);
    const stockSpan = document.createElement('span');
    stockSpan.textContent = stockVal + ' Units';
    stockSpan.className   = stockVal < 10 ? 'stock-low' : 'stock-ok';
    stockCell.appendChild(stockSpan);

    // Show add stock link if stock is empty
    if (stockVal === 0) {
      const addLink = document.createElement('a');
      addLink.href = 'add_product.html';
      addLink.textContent = ' + Add Stock';
      addLink.style.cssText = 'font-size:18px;color:#2563eb;margin-left:6px;white-space:nowrap;';
      addLink.title = 'Add product and record initial stock';
      stockCell.appendChild(addLink);
    }

    // Show product price
    const priceTd = row.querySelector('[data-price]');
    priceTd.className   = 'prod-price';
    priceTd.textContent = 'Rs ' + parseFloat(p.price || 0).toFixed(0);

    // Open product details modal
    row.querySelector('[data-view-btn]').addEventListener('click', () => openView(p));

    // Open edit modal
    row.querySelector('[data-edit-btn]').addEventListener('click', () => openEdit(p));

    // Go to stock page
    const updateBtn = row.querySelector('[data-update-btn]');
    if (updateBtn) {
      updateBtn.textContent = 'Stock';
      updateBtn.title = 'Manage stock on Stock page';
      updateBtn.addEventListener('click', () => {
        window.location.href = `stock.html`;
      });
    }

    tbody.appendChild(row);
  });
}

// Filter products
function applyFilters() {
  const q        = (document.getElementById('prod-search')?.value || '').toLowerCase();
  const cat      = document.getElementById('prod-filter-category')?.value || '';
  const supplier = document.getElementById('prod-filter-supplier')?.value || '';

  filteredProducts = allProducts.filter(p => {
    if (cat      && p.category !== cat)       return false;
    if (supplier && p.supplier !== supplier)  return false;

    // Search product by name, sku, description or supplier
    if (q) {
      return p.name.toLowerCase().includes(q) ||
        p.sku.toLowerCase().includes(q) ||
        (p.description || '').toLowerCase().includes(q) ||
        (p.supplier || '').toLowerCase().includes(q);
    }

    return true;
  });

  renderTable();
}

// Search input event
document.getElementById('prod-search')?.addEventListener('input', applyFilters);

// Category filter event
document.getElementById('prod-filter-category')?.addEventListener('change', applyFilters);

// Supplier filter event
document.getElementById('prod-filter-supplier')?.addEventListener('change', applyFilters);

// Reset all filters
document.getElementById('prod-filter-reset')?.addEventListener('click', () => {
  ['prod-search', 'prod-filter-category', 'prod-filter-supplier'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });

  applyFilters();
});

// Open modal
function openModal(id)  { document.getElementById(id).style.display = 'flex'; }

// Close modal
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

// Close modal when clicking outside
document.querySelectorAll('.modal-overlay').forEach(el => {
  el.addEventListener('click', function (e) {
    if (e.target === this) closeModal(this.id);
  });
});

// Open product view modal
function openView(p) {
  const tpl   = document.getElementById('view-body-tpl');
  const clone = tpl.content.cloneNode(true);

  clone.querySelector('[data-vname]').textContent     = p.name;
  clone.querySelector('[data-vcategory]').textContent = p.category;
  clone.querySelector('[data-vstock]').textContent    = (parseInt(p.stock ?? 0, 10)) + ' Units (from ledger)';
  clone.querySelector('[data-vprice]').textContent    = 'Rs ' + parseFloat(p.price || 0).toFixed(2);
  clone.querySelector('[data-vcost]').textContent     = 'Rs ' + parseFloat(p.cost  || 0).toFixed(2);
  clone.querySelector('[data-vsupplier]').textContent = p.supplier || '—';
  clone.querySelector('[data-vstorage]').textContent  = p.storage  || '—';

  // Show product description
  const descEl = clone.querySelector('[data-vdescription]');
  if (descEl) descEl.textContent = p.description || '—';

  const container = document.getElementById('view-modal-body');
  container.replaceChildren();
  container.appendChild(clone);

  openModal('view-modal');
}

// Open edit modal and fill product data
function openEdit(p) {
  document.getElementById('edit-id').value          = p.id;
  document.getElementById('edit-name').value        = p.name;
  document.getElementById('edit-category').value    = p.category;
  document.getElementById('edit-price').value       = p.price;
  document.getElementById('edit-cost').value        = p.cost;
  document.getElementById('edit-storage').value     = p.storage || '';

  const descEl = document.getElementById('edit-description');
  if (descEl) descEl.value = p.description || '';

  const suppEl = document.getElementById('edit-supplier');
  if (suppEl) suppEl.value = p.supplier || '';

  const msgEl = document.getElementById('edit-msg');
  if (msgEl) msgEl.style.display = 'none';

  const btn = document.getElementById('edit-submit');
  if (btn) {
    btn.disabled = false;
    btn.textContent = 'Save Changes';
  }

  openModal('edit-modal');
}

// Prevent form submit on Enter key
document.getElementById('edit-form')?.addEventListener('keydown', function (e) {
  if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') e.preventDefault();
});

// Save edited product
document.getElementById('edit-form')?.addEventListener('submit', async function (e) {
  e.preventDefault();

  const btn = document.getElementById('edit-submit');
  btn.disabled = true;
  btn.textContent = 'Saving…';

  // Create update payload
  const payload = {
    id:          parseInt(document.getElementById('edit-id').value),
    name:        document.getElementById('edit-name').value.trim(),
    category:    document.getElementById('edit-category').value,
    price:       parseFloat(document.getElementById('edit-price').value)  || 0,
    cost:        parseFloat(document.getElementById('edit-cost').value)   || 0,
    supplier:    (document.getElementById('edit-supplier')?.value || '').trim(),
    storage:     document.getElementById('edit-storage').value.trim(),
    description: (document.getElementById('edit-description')?.value || '').trim(),
  };

  const msg  = document.getElementById('edit-msg');

  // Send update request
  const data = await apiCall(`${window.env.API_URL}/api/products.php`, {
    method: 'PUT',
    body: payload
  });

  if (data.success) {

    // Request goes for approval
    if (data.request_id) {
      showMsg(msg, 'success', '⏳ Edit request submitted for Admin/Supervisor approval.');
      setTimeout(() => closeModal('edit-modal'), 2000);

    } else {

      // Product updated directly
      showMsg(msg, 'success', 'Product updated!');
      await fetchProducts();
      setTimeout(() => closeModal('edit-modal'), 1200);
    }

  } else {
    showMsg(msg, 'error', ' ' + data.message);
  }

  btn.disabled = false;
  btn.textContent = 'Save Changes';
});

// Load supplier list
async function loadSuppliers() {
  const editSel   = document.getElementById('edit-supplier');
  const filterSel = document.getElementById('prod-filter-supplier');

  const data = await apiCall(`${window.env.API_URL}/api/suppliers.php`);

  if (data && data.success !== false) {
    const suppliers = data.suppliers || [];

    // Add suppliers to edit dropdown
    if (editSel) {
      editSel.replaceChildren();

      const def = document.createElement('option');
      def.value = '';
      def.textContent = 'Select a Supplier';
      def.disabled = true;
      def.selected = true;

      editSel.appendChild(def);

      suppliers.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.name;
        opt.textContent = s.name;
        editSel.appendChild(opt);
      });
    }

    // Add suppliers to filter dropdown
    if (filterSel) {
      suppliers.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.name;
        opt.textContent = s.name;
        filterSel.appendChild(opt);
      });
    }
  }
}

// Show message in form
function showMsg(el, type, text) {
  if (!el) return;

  el.textContent = text;
  el.className   = 'form-message ' + type;
  el.style.display = 'block';
}

// Run page functions after page load
document.addEventListener('DOMContentLoaded', async () => {
  await loadSuppliers();
  await fetchProducts();
});