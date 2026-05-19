/**
 * chatbot.js – AI Inventory Assistant
 */
var link = document.createElement('link');
link.rel = 'stylesheet';
link.href = '../css/chatbot.css';
document.head.appendChild(link);

// 1. Fetch, parse, and append chatbot HTML wrapper to body
async function initChatbot() {
  try {
    var res = await fetch('../components/chatbot.html');
    var html = await res.text();
    document.body.insertAdjacentHTML('beforeend', html);
    setupChatbot();
    setTimeout(function() {
      if (window.sendChatbotMessage) window.sendChatbotMessage('Hi', true);
    }, 800);
  } catch (err) { console.error('Chatbot init error:', err); }
}

// 2. Set up event listeners, message routing, and form actions
function setupChatbot() {
  var toggle = document.getElementById('chatbot-toggle');
  var closeBtn = document.getElementById('chatbot-close');
  var win = document.getElementById('chatbot-window');
  var form = document.getElementById('chatbot-form');
  var input = document.getElementById('chatbot-input');
  var msgs = document.getElementById('chatbot-messages');

  toggle.onclick = function() {
    win.classList.toggle('hidden');
    if (!win.classList.contains('hidden')) input.focus();
  };
  closeBtn.onclick = function() { win.classList.add('hidden'); };

  document.body.addEventListener('click', function(e) {
    if (e.target.closest('#open-chatbot-nav')) {
      e.preventDefault();
      win.classList.remove('hidden');
      input.focus();
    }
  });

  // Click handler for product links (event delegation)
  msgs.addEventListener('click', function(e) {
    var link = e.target.closest('.cb-product-link');
    if (link) {
      var name = link.getAttribute('data-name');
      if (name) window.sendChatbotMessage(name);
    }
  });

  // Send message function
  window.sendChatbotMessage = function(text, silent) {
    input.value = text;
    form.dispatchEvent(new CustomEvent('submit', { detail: { silent: !!silent } }));
  };

  // Form submit
  form.addEventListener('submit', async function(e) {
    e.preventDefault();
    var text = input.value.trim();
    if (!text) return;
    var silent = e.detail && e.detail.silent;
    if (!silent) addMsg(text, 'user', null);
    input.value = '';
    showTyping();

    try {
      var resp = await fetch(window.env.API_URL + '/api/chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text })
      });
      var data = await resp.json();
      removeTyping();
      if (data.reply) addMsg(data.reply, 'bot', data);
      if (data.action === 'redirect_update_stock') {
        setTimeout(function() {
          window.location.href = 'add_product.html';
        }, 1500);
      }
    } catch (err) {
      removeTyping();
      addMsg('Sorry, an error occurred.', 'bot', null);
      console.error(err);
    }
  });

  function showTyping() {
    var d = document.createElement('div');
    d.className = 'chatbot-message bot typing';
    d.id = 'chatbot-typing';
    d.innerHTML = '<div class="message-content"><div class="typing-indicator"><span></span><span></span><span></span></div></div>';
    msgs.appendChild(d);
    msgs.scrollTop = msgs.scrollHeight;
  }

  function removeTyping() {
    var t = document.getElementById('chatbot-typing');
    if (t) t.remove();
  }

  // ── Core message renderer ───────────────────────────────────────────
  function addMsg(text, sender, data) {
    var d = document.createElement('div');
    d.className = 'chatbot-message ' + sender;
    var html = '<div class="message-content">';

    // Reply text with markdown
    html += '<div class="cb-reply-text">' + md(text) + '</div>';

    // Structured data
    if (sender === 'bot' && data) {
      try {
        html += buildData(data);
      } catch(err) {
        console.error('Table render error:', err);
      }
    }

    // Suggestions
    if (data && data.suggestions && data.suggestions.length > 0) {
      html += '<div class="chatbot-suggestions">';
      for (var i = 0; i < data.suggestions.length; i++) {
        var s = data.suggestions[i];
        var cls = s.toLowerCase() === 'proceed' ? 'suggestion-pill proceed-pill' : 'suggestion-pill';
        html += '<span class="' + cls + '" data-suggest="' + esc(s) + '">' + esc(s) + '</span>';
      }
      html += '</div>';
    }

    html += '</div>';
    d.innerHTML = html;

    // Attach pill click events
    var pills = d.querySelectorAll('.suggestion-pill');
    for (var j = 0; j < pills.length; j++) {
      pills[j].addEventListener('click', function() {
        window.sendChatbotMessage(this.getAttribute('data-suggest'));
      });
    }

    msgs.appendChild(d);
    msgs.scrollTop = msgs.scrollHeight;
  }

  // ── Markdown ────────────────────────────────────────────────────────
  function md(t) {
    if (!t) return '';
    return t
      .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
      .replace(/\n/g, '<br>');
  }

  // ── HTML escape ─────────────────────────────────────────────────────
  function esc(s) {
    if (!s) return '';
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(s));
    return d.innerHTML;
  }

  // ── Build data HTML ─────────────────────────────────────────────────
  function buildData(data) {
    if (!data.type) return '';
    var h = '';

    // Inventory summary
    if (data.type === 'inventory_summary') {
      if (data.summary) {
        var s = data.summary;
        h += '<div class="cb-stats-grid">';
        h += statCard(s.total_products, 'Products', '');
        h += statCard(s.total_stock, 'Total Stock', '');
        h += statCard(s.low_stock_count, 'Low Stock', 'cb-stat-warn');
        h += statCard(s.out_of_stock_count, 'Out of Stock', 'cb-stat-danger');
        h += statCard(s.total_orders, 'Orders', '');
        h += '</div>';
      }
      if (data.low_stock_products && data.low_stock_products.length > 0) {
        h += '<div class="cb-section-title">🔴 Low Stock Items</div>';
        h += lowTable(data.low_stock_products);
      }
      if (data.high_demand_products && data.high_demand_products.length > 0) {
        h += '<div class="cb-section-title">🔥 Top Demand</div>';
        h += demandTable(data.high_demand_products);
      }
      return h;
    }

    // Low stock table
    if (data.type === 'low_stock' && data.products) {
      return lowTable(data.products);
    }

    // High demand table
    if (data.type === 'high_demand' && data.products) {
      return demandTable(data.products);
    }

    // Supplier table
    if (data.type === 'supplier_info' && data.suppliers) {
      return supplierTable(data.suppliers);
    }

    return '';
  }

  function statCard(val, label, cls) {
    return '<div class="cb-stat ' + cls + '"><span class="cb-stat-val">' + val + '</span><span class="cb-stat-label">' + label + '</span></div>';
  }

  function lowTable(items) {
    var h = '<div class="cb-table-wrap"><table class="cb-table">';
    h += '<thead><tr><th>Product</th><th>Stock</th><th>Min</th><th>Status</th></tr></thead><tbody>';
    for (var i = 0; i < items.length; i++) {
      var p = items[i];
      var bc = p.status === 'Critical' ? 'cb-badge-critical' : p.status === 'Out of Stock' ? 'cb-badge-oos' : p.status === 'OK' ? 'cb-badge-ok' : 'cb-badge-low';
      h += '<tr>';
      h += '<td><span class="cb-product-link" data-name="' + esc(p.name) + '">' + esc(p.name) + '</span></td>';
      h += '<td class="cb-center">' + p.stock + '</td>';
      h += '<td class="cb-center">' + p.min_stock + '</td>';
      h += '<td><span class="cb-badge ' + bc + '">' + esc(p.status) + '</span></td>';
      h += '</tr>';
    }
    h += '</tbody></table></div>';
    return h;
  }

  function demandTable(items) {
    var h = '<div class="cb-table-wrap"><table class="cb-table">';
    h += '<thead><tr><th>Product</th><th>Sales</th><th>Demand</th><th>Stock</th></tr></thead><tbody>';
    for (var i = 0; i < items.length; i++) {
      var p = items[i];
      var bc = p.demand_level === 'High' ? 'cb-badge-high' : p.demand_level === 'Medium' ? 'cb-badge-med' : 'cb-badge-low';
      h += '<tr>';
      h += '<td>' + esc(p.name) + '</td>';
      h += '<td class="cb-center">' + p.sales_count + '</td>';
      h += '<td><span class="cb-badge ' + bc + '">' + esc(p.demand_level) + '</span></td>';
      h += '<td class="cb-center">' + p.stock + '</td>';
      h += '</tr>';
    }
    h += '</tbody></table></div>';
    return h;
  }

  function supplierTable(items) {
    var h = '<div class="cb-table-wrap"><table class="cb-table">';
    h += '<thead><tr><th>Supplier</th><th>Contact</th><th>Products</th><th>Stock</th></tr></thead><tbody>';
    for (var i = 0; i < items.length; i++) {
      var s = items[i];
      var contact = (s.phone !== 'N/A' || s.email !== 'N/A') 
        ? esc(s.phone) + '<br><span style="font-size:18px;color:#6b7280;">' + esc(s.email) + '</span>' 
        : '—';
      
      h += '<tr>';
      h += '<td>' + esc(s.name) + '</td>';
      h += '<td>' + contact + '</td>';
      h += '<td class="cb-center">' + s.product_count + '</td>';
      h += '<td class="cb-center">' + s.total_stock + '</td>';
      h += '</tr>';
    }
    h += '</tbody></table></div>';
    return h;
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initChatbot);
} else {
  initChatbot();
}