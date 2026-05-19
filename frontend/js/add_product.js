/**
 * add_product.js – Multi-Product Bulk Entry (Unified Product & Purchase Flow)
 */

let _allProducts = []; // store all products

// load suppliers + products
async function loadInitialData() {
  const [supData, prodData] = await Promise.all([
    apiCall(`${window.env.API_URL}/api/suppliers.php`),
    apiCall(`${window.env.API_URL}/api/products.php`)
  ]);

  // fill supplier dropdown
  const supplierSelect = document.getElementById('ap-supplier');
  if (supplierSelect && supData?.success !== false) {
    const suppliers = supData.suppliers || [];
    supplierSelect.replaceChildren();

    const def = document.createElement('option');
    def.value = ''; 
    def.textContent = 'Select a Supplier';
    def.disabled = true; 
    def.selected = true;

    supplierSelect.appendChild(def);

    suppliers.forEach(s => {
      const o = document.createElement('option');
      o.value = s.name; 
      o.textContent = s.name;
      supplierSelect.appendChild(o);
    });
  }

  // save products
  if (prodData?.success) _allProducts = prodData.products || [];

  // set today date
  const dateEl = document.getElementById('ap-pur-date');
  if (dateEl) dateEl.value = new Date().toISOString().split('T')[0];
}

// create new row
function createRow() {
  const container = document.getElementById('product-rows-container');
  const template = container.querySelector('.product-row').cloneNode(true);
  
  // reset inputs
  template.querySelectorAll('input').forEach(i => {
    if (i.type === 'number') i.value = i.classList.contains('row-qty') ? '1' : '';
    else i.value = '';
  });
  
  // show remove button
  const removeBtn = template.querySelector('.remove-row-btn');
  removeBtn.style.display = 'block';
  removeBtn.onclick = () => template.remove();

  container.appendChild(template);
  setupRowListeners(template); // add listeners
}

// setup row events
function setupRowListeners(row) {
  const nameInput = row.querySelector('.row-name');

  // auto-fill if product exists
  nameInput.addEventListener('blur', function() {
    const val = this.value.trim().toLowerCase();
    const existing = _allProducts.find(p => p.name.toLowerCase() === val);

    if (existing) {
      row.querySelector('.row-category').value = existing.category;
      row.querySelector('.row-cost').value = existing.cost;
      row.querySelector('.row-price').value = existing.price;
      this.style.borderColor = '#10b981'; // highlight
    } else {
      this.style.borderColor = '';
    }
  });
}

// generate invoice
function generateAutoInvoice() {
  const prefix = 'PUR';
  const date = new Date().toISOString().slice(2, 10).replace(/-/g, '');
  const rand = Math.floor(1000 + Math.random() * 9000);

  document.getElementById('ap-invoice').value = `${prefix}-${date}-${rand}`;
}

// init on load
document.addEventListener('DOMContentLoaded', async () => {
  await loadInitialData();

  const firstRow = document.querySelector('.product-row');
  setupRowListeners(firstRow);

  document.getElementById('add-row-btn').onclick = createRow;
  document.getElementById('ap-auto-invoice').onclick = generateAutoInvoice;

  // restock autofill
  const params = new URLSearchParams(window.location.search);
  const restockId = params.get('restock_id');

  if (restockId) {
    const prod = _allProducts.find(p => p.id == restockId);

    if (prod) {
      if (prod.supplier) {
        const supOpt = Array.from(document.getElementById('ap-supplier').options)
          .find(o => o.value === prod.supplier);
        if (supOpt) supOpt.selected = true;
      }

      generateAutoInvoice();
      
      firstRow.querySelector('.row-name').value = prod.name;
      firstRow.querySelector('.row-category').value = prod.category || 'Other';
      firstRow.querySelector('.row-cost').value = prod.cost || '';
      firstRow.querySelector('.row-price').value = prod.price || '';
      firstRow.querySelector('.row-name').style.borderColor = '#10b981';
      
      firstRow.querySelector('.row-qty').focus(); // focus qty
    }
  }
});

// form submit
document.getElementById('add-product-form').addEventListener('submit', async function(e) {
  e.preventDefault();

  const msgEl = document.getElementById('ap-message');
  const btn = document.getElementById('ap-submit');

  const supplier = document.getElementById('ap-supplier').value;
  const purDate = document.getElementById('ap-pur-date').value;
  const invoice = document.getElementById('ap-invoice').value.trim();

  // validation
  if (!supplier || !purDate || !invoice) {
    showAlertPopup('Missing Info', 'Supplier, Purchase Date, and Invoice Reference are required.');
    return;
  }

  const rows = document.querySelectorAll('.product-row');
  const products = [];

  for (const row of rows) {
    const name = row.querySelector('.row-name').value.trim();
    const cost = parseFloat(row.querySelector('.row-cost').value) || 0;
    const price = parseFloat(row.querySelector('.row-price').value) || 0;
    const qty = parseInt(row.querySelector('.row-qty').value, 10) || 0;

    if (!name) continue;

    if (cost <= 0 || price <= 0 || qty <= 0) {
      showAlertPopup('Invalid Row Data', `Please ensure Name, Cost, Price, and Qty are all provided and greater than zero for "${name}".`);
      return;
    }

    products.push({
      name,
      category: row.querySelector('.row-category').value,
      cost,
      price,
      qty
    });
  }

  if (products.length === 0) {
    showAlertPopup('No Products', 'Add at least one product name.');
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Processing Bulk Entry…'; // loading

  const payload = {
    action: 'bulk_create',
    supplier,
    purchase_date: purDate,
    invoice_number: invoice,
    products
  };

  const data = await apiCall(`${window.env.API_URL}/api/products.php`, {
    method: 'POST',
    body: payload
  });

  if (data.success) {
    showFormMsg(msgEl, 'success', `✓ ${data.message || 'Bulk entry successful!'}`);
    setTimeout(() => { window.location.href = 'stock.html'; }, 1800); // redirect
  } else {
    showFormMsg(msgEl, 'error', ' ' + (data.message || 'Failed to process bulk entry.'));
  }

  btn.disabled = false;
  btn.textContent = 'Save All Products & Update Stock'; // reset
});

// show message
function showFormMsg(el, type, text) {
  el.textContent = text;
  el.className = 'form-message ' + type;
  el.hidden = false;

  setTimeout(() => { el.hidden = true; }, 4000); // auto hide
}