let allProducts = [];
let filteredProducts = [];
let productToDeleteId = null;

// ─── Fetch products from API ──────────────────────────────────────────────
async function fetchProducts() {
  const data = await apiCall(`${window.env.API_URL}/api/products.php`);
  if (data && data.success !== false) {
    allProducts = data.products || [];
    filteredProducts = [...allProducts];
    renderTable();
    checkRejections(data.rejectedItems || []);
  } else {
    console.error('Products load error:', data?.message);
    const tbody = document.getElementById('prod-tbody');
    tbody.replaceChildren();
    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 5;
    td.className = 'no-data';
    td.textContent = 'Error loading products.';
    tr.appendChild(td);
    tbody.appendChild(tr);
  }
}

// ─── Render table using <template> ────────────────────────────────────────
function renderTable() {
  const tbody = document.getElementById('prod-tbody');
  const tpl = document.getElementById('prod-row-tpl');
  const role = (sessionStorage.getItem('gf_role') || 'admin').trim().toLowerCase();
  const jobRole = (sessionStorage.getItem('gf_job_role') || '').trim().toLowerCase();

  const isAdmin = role !== 'staff' || jobRole === 'supervisor';

  // Everyone can add products (staff additions go to pending)
  const addBtn = document.getElementById('add-product-btn');
  if (addBtn) addBtn.style.display = '';

  tbody.replaceChildren();

  if (filteredProducts.length === 0) {
    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 5;
    td.className = 'no-data';
    td.textContent = 'No products found.';
    tr.appendChild(td);
    tbody.appendChild(tr);
    return;
  }

  filteredProducts.forEach(p => {
    // Clone the row template defined in products.html
    const row = tpl.content.cloneNode(true);

    // Product name & SKU
    row.querySelector('[data-name]').textContent = p.name;
    row.querySelector('[data-sku]').textContent = 'SKU: ' + p.sku;

    // Category badge — set class and text, colour applied via style
    const catCell = row.querySelector('[data-category]');
    const badge = document.createElement('span');
    badge.textContent = p.category;
    const catClass = 'cat-' + (p.category || 'other').toLowerCase();
    badge.className = `cat-badge ${catClass}`;
    catCell.appendChild(badge);

    // Stock — apply low/ok class, no HTML string needed
    const stockCell = row.querySelector('[data-stock]');
    const stockSpan = document.createElement('span');
    stockSpan.textContent = p.stock + ' Units';
    stockSpan.className = p.stock < 10 ? 'stock-low' : 'stock-ok';
    stockCell.appendChild(stockSpan);

    // Price
    const priceTd = row.querySelector('[data-price]');
    priceTd.className = 'prod-price';
    priceTd.textContent = 'Rs. ';
    const priceSpan = document.createElement('span');
    priceSpan.textContent = parseFloat(p.price).toFixed(0);
    priceTd.appendChild(priceSpan);

    // Buttons
    row.querySelector('[data-view-btn]').addEventListener('click', () => openView(p));
    row.querySelector('[data-update-btn]').addEventListener('click', () => openUpdate(p.id, p.name, p.stock));

    const editBtn = row.querySelector('[data-edit-btn]');
    const deleteBtn = row.querySelector('[data-delete-btn]');
    
    // Everyone can edit
    editBtn.addEventListener('click', () => openEdit(p));
    
    // Only Admin/Supervisor can delete directly
    if (isAdmin) {
      deleteBtn.addEventListener('click', () => promptDelete(p.id, p.name));
    } else {
      deleteBtn.style.display = 'none';
    }

    tbody.appendChild(row);
  });
}

// ─── Search filter ────────────────────────────────────────────────────────
document.getElementById('prod-search').addEventListener('input', function () {
  const q = this.value.trim().toLowerCase();
  filteredProducts = q
    ? allProducts.filter(p =>
      p.name.toLowerCase().includes(q) ||
      p.sku.toLowerCase().includes(q) ||
      p.category.toLowerCase().includes(q) ||
      (p.supplier && p.supplier.toLowerCase().includes(q))
    )
    : [...allProducts];
  renderTable();
});

// ─── Modal helpers ────────────────────────────────────────────────────────
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }

document.querySelectorAll('.modal-overlay').forEach(el => {
  el.addEventListener('click', function (e) {
    if (e.target === this) closeModal(this.id);
  });
});

// ─── View modal: clone the view-body template ─────────────────────────────
function openView(p) {
  const tpl = document.getElementById('view-body-tpl');
  const clone = tpl.content.cloneNode(true);

  clone.querySelector('[data-vname]').textContent = p.name;
  clone.querySelector('[data-vcategory]').textContent = p.category;
  clone.querySelector('[data-vstock]').textContent = p.stock + ' Units';
  clone.querySelector('[data-vprice]').textContent = 'Rs ' + parseFloat(p.price).toFixed(2);
  clone.querySelector('[data-vcost]').textContent = 'Rs ' + parseFloat(p.cost).toFixed(2);
  clone.querySelector('[data-vsupplier]').textContent = p.supplier || '—';
  clone.querySelector('[data-vstorage]').textContent = p.storage || '—';

  const container = document.getElementById('view-modal-body');
  container.replaceChildren();
  container.appendChild(clone);

  openModal('view-modal');
}

// ─── Edit modal ───────────────────────────────────────────────────────────
function openEdit(p) {
  document.getElementById('edit-id').value = p.id;
  document.getElementById('edit-name').value = p.name;
  document.getElementById('edit-category').value = p.category;
  document.getElementById('edit-stock').value = p.stock;
  document.getElementById('edit-price').value = p.price;
  document.getElementById('edit-cost').value = p.cost;
  document.getElementById('edit-supplier').value = p.supplier || '';
  document.getElementById('edit-storage').value = p.storage || '';
  document.getElementById('edit-msg').style.display = 'none';
  openModal('edit-modal');
}

document.getElementById('edit-form').addEventListener('submit', async function (e) {
  e.preventDefault();
  const btn = document.getElementById('edit-submit');
  btn.disabled = true; btn.textContent = 'Saving...';

  const payload = {
    id: parseInt(document.getElementById('edit-id').value),
    name: document.getElementById('edit-name').value.trim(),
    category: document.getElementById('edit-category').value,
    stock: parseInt(document.getElementById('edit-stock').value),
    price: parseFloat(document.getElementById('edit-price').value),
    cost: parseFloat(document.getElementById('edit-cost').value),
    supplier: document.getElementById('edit-supplier').value.trim(),
    storage: document.getElementById('edit-storage').value.trim()
  };

  const msg = document.getElementById('edit-msg');
  const data = await apiCall(`${window.env.API_URL}/api/products.php`, {
    method: 'PUT',
    body: payload
  });
  if (data.success) {
    showMsg(msg, 'success', 'Product updated!');
    await fetchProducts();
    setTimeout(() => closeModal('edit-modal'), 1200);
  } else {
    showMsg(msg, 'error', ' ' + data.message);
  }

  btn.disabled = false; btn.textContent = 'Save Changes';
});

// ─── Update stock modal ───────────────────────────────────────────────────
function openUpdate(id, name, stock) {
  document.getElementById('update-id').value = id;
  document.getElementById('update-product-name').textContent = name;
  document.getElementById('update-stock').value = stock;
  document.getElementById('update-msg').style.display = 'none';
  openModal('update-modal');
}

document.getElementById('update-form').addEventListener('submit', async function (e) {
  e.preventDefault();
  const id = parseInt(document.getElementById('update-id').value);
  const stock = parseInt(document.getElementById('update-stock').value);
  const cached = allProducts.find(x => x.id === id) || {};

  const msg = document.getElementById('update-msg');
  const data = await apiCall(`${window.env.API_URL}/api/products.php`, {
    method: 'PUT',
    body: {
      id, stock,
      name: cached.name, category: cached.category,
      price: cached.price, cost: cached.cost,
      supplier: cached.supplier, storage: cached.storage
    }
  });

  if (data.success) {
    showMsg(msg, 'success', 'Stock updated!');
    await fetchProducts();
    setTimeout(() => closeModal('update-modal'), 1000);
  } else {
    showMsg(msg, 'error', ' ' + data.message);
  }
});

// ─── Delete ───────────────────────────────────────────────────────────────
function promptDelete(id, name) {
  productToDeleteId = id;
  openModal('delete-modal');
}

document.getElementById('confirm-delete-btn').addEventListener('click', async function () {
  if (!productToDeleteId) return;
  const btn = this;
  btn.disabled = true; btn.textContent = 'DELETING...';

  const data = await apiCall(`${window.env.API_URL}/api/products.php`, {
    method: 'DELETE',
    body: { id: productToDeleteId }
  });

  if (data.success) {
    await fetchProducts();
    closeModal('delete-modal');
  } else {
    alert(data.message || 'Error deleting product.');
  }

  btn.disabled = false; btn.textContent = 'DELETE';
  productToDeleteId = null;
});

// ─── Utility ──────────────────────────────────────────────────────────────
function showMsg(el, type, text) {
  el.textContent = text;
  el.className = 'form-message ' + type;
  el.style.display = 'block';
}

// ─── Rejections ───────────────────────────────────────────────────────────
let currentRejections = [];
function checkRejections(items) {
  if (items && items.length > 0) {
    currentRejections = items;
    showNextRejection();
  }
}

function showNextRejection() {
  if (currentRejections.length === 0) return;
  const item = currentRejections[0];
  document.getElementById('rejection-message').textContent = `Admin declined your request to add product: ${item.name}`;
  openModal('rejection-modal');
}

document.getElementById('ack-rejection-btn')?.addEventListener('click', async function() {
  if (currentRejections.length === 0) return;
  const item = currentRejections[0];
  const btn = this;
  btn.disabled = true;

  const data = await apiCall(`${window.env.API_URL}/api/permissions.php?action=acknowledge`, {
    method: 'POST',
    body: { product_id: item.id }
  });

  btn.disabled = false;
  closeModal('rejection-modal');
  
  if (data.success) {
    currentRejections.shift(); // remove first
    if (currentRejections.length > 0) {
      setTimeout(showNextRejection, 500);
    }
  }
});


// ─── Init ─────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', fetchProducts);
