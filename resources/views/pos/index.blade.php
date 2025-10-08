<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>POS – KG POS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  @vite(['resources/css/pos.css','resources/js/pos.js'])
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
      <button class="btn" onclick="suspendOrder()">Suspender</button>
      <button class="btn" onclick="toggleOpenOrders()">
        Comandas abertas <span id="open-count" class="pill">0</span>
      </button>
    </div>
  </header>

  <div id="openPanel" class="open-panel"></div>

  <div class="layout">
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
          <tr><th>Item</th><th class="right">Qtde</th><th class="right">Preço</th><th class="right">Total</th></tr>
        </thead>
        <tbody id="items"></tbody>
        <tfoot>
          <tr><td colspan="3" class="right">Subtotal</td><td class="right" id="subtotal">€ 0,00</td></tr>
          <tr><td colspan="3" class="right">Total</td><td class="right" id="total">€ 0,00</td></tr>
        </tfoot>
      </table>
    </aside>
  </div>

  
</body>
</html>
