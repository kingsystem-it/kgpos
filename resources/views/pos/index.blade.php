<!doctype html>
<html lang="it">

<head>
    <meta charset="utf-8">
    <title>POS – KG POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        :root {
            --bg: #0b0f12;
            --panel: #0f1419;
            --card: #12181d;
            --muted: #94a3b8;
            --ok: #16a34a;
            --warn: #f59e0b;
            --text: #e2e8f0;
            --accent: #38bdf8;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid #1f2937;
            gap: 12px;
        }

        header h1 {
            font-size: 18px;
            margin: 0;
        }

        .muted {
            color: var(--muted);
        }

        .pill {
            padding: 2px 8px;
            border-radius: 999px;
            border: 1px solid #334155;
            font-size: 12px;
            color: var(--muted);
        }

        .btn {
            border: 1px solid #1f2937;
            background: #0e141a;
            color: var(--text);
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn:hover {
            border-color: #334155;
        }

        .btn.ok {
            border-color: #14532d;
            background: #0b1912;
        }

        .btn[disabled] {
            opacity: .6;
            cursor: not-allowed;
        }

        .layout {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 12px;
            padding: 12px;
        }

        .panel {
            background: var(--panel);
            border: 1px solid #1f2937;
            border-radius: 12px;
            padding: 12px;
        }

        .row {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .row.wrap {
            flex-wrap: wrap;
        }

        input,
        select {
            background: #0e141a;
            color: var(--text);
            border: 1px solid #1f2937;
            border-radius: 8px;
            padding: 8px;
        }

        /* GRID DE PRODUTOS */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 10px;
        }

        .card {
            background: var(--card);
            border: 1px solid #1f2937;
            border-radius: 12px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            overflow: hidden;
            position: relative;
        }

        .name {
            font-weight: 600;
        }

        .price {
            color: var(--muted);
            font-size: 13px;
        }

        /* controles internos do card */
        .card .controls {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: nowrap;
        }

        .card .controls input {
            width: 64px;
        }

        .card .controls .btn.add {
            margin-left: auto;
        }

        /* nova linha p/ o botão Adicionar, centralizado */
        .card .add-row {
            display: flex;
            justify-content: center;
            margin-top: 8px;
        }

        .card .add-row .btn.add {
            min-width: 140px;
        }

        /* quando faltar espaço, o botão "Adicionar" quebra para linha inteira */
        @media (max-width: 1400px) {
            .card .add-row .btn.add {
                width: 100%;
            }
        }

        /* Tabela da comanda */
        .order-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 6px 4px;
            border-bottom: 1px solid #1f2937;
            font-size: 14px;
        }

        tfoot td {
            font-weight: 700;
        }

        .right {
            text-align: right;
        }

        /* Toast */
        .toast {
            position: fixed;
            right: 16px;
            bottom: 16px;
            background: #111827;
            color: #e5e7eb;
            border: 1px solid #374151;
            padding: 10px 14px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .35);
            opacity: 0;
            transform: translateY(10px);
            transition: all .25s;
            z-index: 9999;
        }

        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        mark {
            background: #fde68a33;
            color: inherit;
            border-bottom: 1px dashed #f59e0b55;
        }
    </style>
</head>

<body>
    <header>
        <div class="row wrap">
            <h1>POS – KG POS</h1>
            <span class="pill">Logado: {{ auth()->user()->email }}</span>
            <span class="pill">Use /kds para a cozinha</span>
        </div>
        <div class="row">
            <input id="anchor" placeholder="Mesa/Cliente (opcional)" />
            <button class="btn" onclick="newOrder()">Nova comanda</button>
            <button class="btn" onclick="toggleFullscreen()">Tela cheia</button>
        </div>
    </header>

    <div class="layout">
        <!-- Produtos -->
        <section class="panel">
            <div class="row wrap" style="justify-content:space-between;">
                <div class="row">
                    <input id="search" placeholder="Buscar produto..." oninput="debouncedSearch()" style="min-width:240px">
                    <select id="category" onchange="loadProducts()">
                        <option value="">Todas categorias</option>
                    </select>
                </div>
                <div class="row">
                    <span class="muted">Exibindo:</span>
                    <span id="count" class="pill">0</span>
                </div>
            </div>

            <div id="product-grid" class="grid"></div>
        </section>

        <!-- Comanda -->
        <aside class="panel">
            <div class="order-head">
                <div>
                    <div class="muted">Comanda atual</div>
                    <div id="order-label" style="font-weight:700;">—</div>
                </div>
                <div class="row">
                    <button class="btn ok" onclick="sendToKds()">Enviar ao KDS</button>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="right">Qtde</th>
                        <th class="right">Preço</th>
                        <th class="right">Total</th>
                    </tr>
                </thead>
                <tbody id="items"></tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="right">Subtotal</td>
                        <td class="right" id="subtotal">€ 0,00</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="right">Total</td>
                        <td class="right" id="total">€ 0,00</td>
                    </tr>
                </tfoot>
            </table>
        </aside>
    </div>

    <script>
        const fmt = (n) => new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR'
        }).format(Number(n || 0));

        let state = {
            orderId: null,
            products: [],
            categories: [],
            search: '',
            categoryId: '',
            hasDraft: false,
            loading: {
                add: false,
                send: false
            },
            selected: {
                id: null,
                qty: 1
            } // seleção do card
        };

        // ---------- Toast ----------
        function toast(msg) {
            let el = document.getElementById('toast');
            if (!el) {
                el = document.createElement('div');
                el.id = 'toast';
                el.className = 'toast';
                document.body.appendChild(el);
            }
            el.textContent = msg;
            el.classList.add('show');
            clearTimeout(el._t);
            el._t = setTimeout(() => el.classList.remove('show'), 1800);
        }

        function toggleFullscreen() {
            if (!document.fullscreenElement) document.documentElement.requestFullscreen().catch(() => {});
            else document.exitFullscreen();
        }

        // ---------- API helper ----------
        async function api(path, opts = {}) {
            const res = await fetch(path, {
                credentials: 'same-origin',
                ...opts
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        }

        // ---------- Order ----------
        async function newOrder() {
            try {
                const anchor = document.getElementById('anchor').value || null;
                const data = await api('/api/orders', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        anchor
                    })
                });
                state.orderId = data.id;
                localStorage.setItem('pos_order_id', String(state.orderId));
                renderOrderLabel(data);
                await refreshOrder();
                toast('Comanda criada');
            } catch (e) {
                toast('Erro ao criar comanda');
                console.error(e);
            }
        }

        async function refreshOrder() {
            if (!state.orderId) return;
            try {
                const o = await api(`/api/orders/${state.orderId}`);
                renderOrder(o);
                state.hasDraft = (o.items || []).some(it => it.status === 'draft');
                renderSendButton();
            } catch (e) {
                console.error(e);
            }
        }

        function renderSendButton() {
            const btn = document.querySelector('button.btn.ok[onclick="sendToKds()"]');
            if (!btn) return;
            btn.disabled = !state.hasDraft || state.loading.send;
            btn.textContent = state.loading.send ? 'Enviando...' : 'Enviar ao KDS';
        }

        function renderOrderLabel(order) {
            const label = `#${order.id}` + (order.anchor ? ` · ${order.anchor}` : '');
            document.getElementById('order-label').textContent = label;
        }

        function renderOrder(order) {
            const all = order.items || [];
            const drafts = all.filter(i => i.status === 'draft');
            const sents = all.filter(i => i.status !== 'draft');

            const tbody = document.getElementById('items');
            tbody.innerHTML = '';

            let sub = 0;

            if (drafts.length) {
                const trH = document.createElement('tr');
                trH.innerHTML = `<td colspan="4"><strong>Pendentes</strong></td>`;
                tbody.appendChild(trH);
                drafts.forEach(it => {
                    const line = it.price_snapshot * it.quantity;
                    sub += line;
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
          <td>${escapeHtml(it.name_snapshot)} <span class="pill">draft</span></td>
          <td class="right">${Number(it.quantity)}</td>
          <td class="right">${fmt(it.price_snapshot)}</td>
          <td class="right">${fmt(line)}</td>
        `;
                    tbody.appendChild(tr);
                });
            }

            if (sents.length) {
                const trH = document.createElement('tr');
                trH.innerHTML = `<td colspan="4"><strong>Enviados</strong></td>`;
                tbody.appendChild(trH);
                sents.forEach(it => {
                    const line = it.price_snapshot * it.quantity;
                    sub += line;
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
          <td>${escapeHtml(it.name_snapshot)} <span class="pill">${it.status}</span></td>
          <td class="right">${Number(it.quantity)}</td>
          <td class="right">${fmt(it.price_snapshot)}</td>
          <td class="right">${fmt(line)}</td>
        `;
                    tbody.appendChild(tr);
                });
            }

            document.getElementById('subtotal').textContent = fmt(order.subtotal ?? sub);
            document.getElementById('total').textContent = fmt(order.total ?? sub);
            renderOrderLabel(order);
        }

        // ---------- Adição com consolidação no servidor ----------
        let addThrottle = false;
        async function addItem(productId, qty = 1) {
            if (addThrottle) return;
            addThrottle = true;
            setTimeout(() => addThrottle = false, 300);

            if (!state.orderId) {
                await newOrder();
            }
            try {
                await api(`/api/orders/${state.orderId}/items`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        quantity: qty
                    })
                });
                await refreshOrder();
                toast('Item adicionado');

                // reset qty -> 1 mantendo card selecionado
                if (state.selected.id === productId) {
                    state.selected.qty = 1;
                    updateSelectedUI();
                }
            } catch (e) {
                toast('Erro ao adicionar item');
                console.error(e);
            }
        }

        // ---------- Categorias / Produtos ----------
        async function loadCategories() {
            try {
                const data = await api('/api/categories?per_page=100');
                state.categories = (data.data || []).filter(c => c.visible_pos);
                const sel = document.getElementById('category');
                sel.innerHTML = '<option value="">Todas categorias</option>';
                state.categories.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.name;
                    sel.appendChild(opt);
                });

                const savedCat = localStorage.getItem('pos_cat_id');
                if (savedCat) {
                    sel.value = savedCat;
                    state.categoryId = savedCat;
                }
            } catch (e) {
                console.error(e);
            }
        }

        async function loadProducts() {
            try {
                state.search = document.getElementById('search').value.trim();
                state.categoryId = document.getElementById('category').value || '';
                localStorage.setItem('pos_search', state.search);
                localStorage.setItem('pos_cat_id', state.categoryId);

                const qs = new URLSearchParams({
                    per_page: 100
                });
                if (state.search) qs.set('search', state.search);
                if (state.categoryId) qs.set('category_id', state.categoryId);

                const data = await api('/api/products?' + qs.toString());
                state.products = data.data || [];

                // se o selecionado não existir mais na lista filtrada, limpa seleção
                if (!state.products.some(p => p.id === state.selected.id)) state.selected = {
                    id: null,
                    qty: 1
                };

                renderProducts();
            } catch (e) {
                console.error(e);
            }
        }

        function highlight(text, term) {
            if (!term) return escapeHtml(text);
            const re = new RegExp('(' + escapeReg(term) + ')', 'ig');
            return escapeHtml(text).replace(re, '<mark>$1</mark>');
        }

        function escapeReg(s) {
            return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        function renderProducts() {
            const grid = document.getElementById('product-grid');
            grid.innerHTML = '';
            document.getElementById('count').textContent = state.products.length;

            state.products.forEach(p => {
                const selected = state.selected.id === p.id;
                const qty = selected ? state.selected.qty : 1;

                const card = document.createElement('div');
                card.className = 'card';
                card.style.borderColor = selected ? '#38bdf8' : '#1f2937';
                card.style.boxShadow = selected ? '0 0 0 1px #38bdf8 inset' : 'none';

                // 1º toque seleciona; toques seguintes no card incrementam qty
                card.addEventListener('click', () => {
                    if (state.selected.id !== p.id) {
                        state.selected = {
                            id: p.id,
                            qty: 1
                        };
                    } else {
                        state.selected.qty = Math.max(1, state.selected.qty + 1);
                    }
                    renderProducts();
                });

                card.innerHTML = `
  <div class="name">${highlight(p.name, state.search)}</div>
  <div class="price">${fmt(p.price)} ${p.type==='composed' ? '· composto' : ''}</div>
  ${selected ? `
    <div class="controls" style="margin-top:6px" onclick="event.stopPropagation()">
      <button class="btn" onclick="decQty(${p.id})">−</button>
      <input id="qty-${p.id}" type="number" min="1" step="1" value="${qty}"
             onfocus="this.select()"
             onkeydown="if(event.key==='Enter'){ addItem(${p.id}, Number(this.value||1)); event.preventDefault(); }">
      <button class="btn" onclick="incQty(${p.id})">+</button>
    </div>
    <div class="add-row" onclick="event.stopPropagation()">
      <button class="btn ok add"
              onclick="addItem(${p.id}, Number(document.getElementById('qty-${p.id}').value||1))">
        Adicionar
      </button>
    </div>
  ` : ''}
`;


                grid.appendChild(card);
            });
        }

        function decQty(pid) {
            if (state.selected.id !== pid) return;
            state.selected.qty = Math.max(1, state.selected.qty - 1);
            updateSelectedUI();
        }

        function incQty(pid) {
            if (state.selected.id !== pid) return;
            state.selected.qty = Math.max(1, state.selected.qty + 1);
            updateSelectedUI();
        }

        function updateSelectedUI() {
            const pid = state.selected.id;
            const el = document.getElementById('qty-' + pid);
            if (el) el.value = state.selected.qty;
        }

        // ---------- Utils ----------
        function escapeHtml(s) {
            return String(s ?? '').replace(/[&<>"']/g, c => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            } [c]));
        }

        function debounce(fn, ms) {
            let t;
            return (...a) => {
                clearTimeout(t);
                t = setTimeout(() => fn(...a), ms);
            };
        }
        const debouncedSearch = debounce(loadProducts, 200);

        // Atalhos: "/" foca busca, Ctrl+K nova comanda
        window.addEventListener('keydown', (e) => {
            if (e.key === '/' && !e.metaKey && !e.ctrlKey) {
                e.preventDefault();
                document.getElementById('search').focus();
            }
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                newOrder();
            }
        });

        // ---------- Boot ----------
        (async function init() {
            const savedOrder = localStorage.getItem('pos_order_id');
            if (savedOrder) {
                state.orderId = Number(savedOrder);
                refreshOrder().catch(() => {});
            }
            const savedSearch = localStorage.getItem('pos_search');
            if (savedSearch) {
                document.getElementById('search').value = savedSearch;
            }
            await loadCategories();
            await loadProducts();
        })();
    </script>
</body>

</html>