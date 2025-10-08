
    const fmt = n => new Intl.NumberFormat('it-IT',{style:'currency',currency:'EUR'}).format(Number(n||0));

    let state = {
        orderId:null, products:[], categories:[], openOrders:[],
    search:'', categoryId:'', hasDraft:false,
    loading:{add:false, send:false},
    selected:{id:null, qty:1}
    };

    function toast(msg){
        let el=document.getElementById('toast');
    if(!el){el = document.createElement('div'); el.id='toast'; el.className='toast'; document.body.appendChild(el); }
      el.textContent=msg; el.classList.add('show'); clearTimeout(el._t); el._t=setTimeout(()=>el.classList.remove('show'),1800);
    }
    function toggleFullscreen(){ if(!document.fullscreenElement) document.documentElement.requestFullscreen().catch(()=>{ }); else document.exitFullscreen(); }

    async function api(path, opts = { }) {
  const res = await fetch(path, {credentials:'same-origin', ...opts });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}


    async function newOrder(){
      try{
        const anchor=document.getElementById('anchor').value||null;
    const data=await api('/api/orders',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({anchor})});
    state.orderId=data.id; localStorage.setItem('pos_order_id', String(state.orderId));
    renderOrderLabel(data); await refreshOrder(); toast('Comanda criada'); await loadOpenOrders();
      }catch(e){toast('Erro ao criar comanda'); console.error(e); }
    }
    async function refreshOrder(){
      if(!state.orderId) return;
    try{
        const o=await api(`/api/orders/${state.orderId}`);
    renderOrder(o);
        state.hasDraft=(o.items||[]).some(it=>it.status==='draft');
    renderSendButton();
      }catch(e){console.error(e); }
    }
    function renderSendButton(){
      const btn=document.querySelector('button.btn.ok[onclick="sendToKds()"]');
    if(!btn) return; btn.disabled=!state.hasDraft || state.loading.send; btn.textContent=state.loading.send?'Enviando...':'Enviar ao KDS';
    }

    // ----- Comandas abertas -----
    async function loadOpenOrders(){
      try{
        const res=await api('/api/orders/open');
        state.openOrders=(res.data||[]).filter(o => Number(o.total)>0 || Number(o.drafts)>0 || Number(o.sents)>0);
    const badge=document.getElementById('open-count'); if(badge) badge.textContent=state.openOrders.length;
    renderOpenOrders();
      }catch(e){console.error(e); }
    }
    function toggleOpenOrders(){ const el=document.getElementById('openPanel'); el.classList.toggle('show'); renderOpenOrders(); }
    function renderOpenOrders(){
      const el=document.getElementById('openPanel'); if(!el || !el.classList.contains('show')) return;
    const arr=state.openOrders||[]; if(!arr.length){el.innerHTML = '<div class="muted">Nenhuma comanda aberta.</div>'; return; }
      el.innerHTML=arr.map(o=>`
    <div class="open-item" onclick="pickOpen(${o.id})">
        <div class="row" style="justify-content:space-between">
            <div><strong>#${o.id}</strong> ${o.anchor ? '• ' + escapeHtml(o.anchor) : ''}</div>
            <div class="muted">${new Date(o.created_at).toLocaleTimeString()}</div>
        </div>
        <div class="row" style="justify-content:space-between">
            <div class="muted">draft: ${o.drafts || 0} · enviados: ${o.sents || 0}</div>
            <div><strong>${fmt(o.total || 0)}</strong></div>
        </div>
    </div>`).join('');
    }
    async function pickOpen(id){
        document.getElementById('openPanel')?.classList.remove('show');
    state.orderId=id; localStorage.setItem('pos_order_id', String(id));
    await refreshOrder();
    try{ const o=await api(`/api/orders/${id}`); document.getElementById('anchor').value=o.anchor??''; }catch{ }
    toast('Comanda retomada');
    }
    async function suspendOrder(){await loadOpenOrders(); toast('Comanda suspensa'); await newOrder(); }

    function renderOrderLabel(order){ const label=`#${order.id}`+(order.anchor?` • ${order.anchor}`:''); document.getElementById('order-label').textContent=label; }
    function renderOrder(order){
      const all=order.items||[]; const drafts=all.filter(i=>i.status==='draft'); const sents=all.filter(i=>i.status!=='draft');
    const tbody=document.getElementById('items'); tbody.innerHTML=''; let sub=0;
    if(drafts.length){ const trH=document.createElement('tr'); trH.innerHTML=`<td colspan="4"><strong>Pendentes</strong></td>`; tbody.appendChild(trH);
        drafts.forEach(it=>{ const line=it.price_snapshot*it.quantity; sub+=line;
    const tr=document.createElement('tr'); tr.innerHTML=`
    <td>${escapeHtml(it.name_snapshot)} <span class="pill">draft</span></td>
    <td class="right">${Number(it.quantity)}</td>
    <td class="right">${fmt(it.price_snapshot)}</td>
    <td class="right">${fmt(line)}</td>`; tbody.appendChild(tr); });
      }
    if(sents.length){ const trH=document.createElement('tr'); trH.innerHTML=`<td colspan="4"><strong>Enviados</strong></td>`; tbody.appendChild(trH);
        sents.forEach(it=>{ const line=it.price_snapshot*it.quantity; sub+=line;
    const tr=document.createElement('tr'); tr.innerHTML=`
    <td>${escapeHtml(it.name_snapshot)} <span class="pill">${it.status}</span></td>
    <td class="right">${Number(it.quantity)}</td>
    <td class="right">${fmt(it.price_snapshot)}</td>
    <td class="right">${fmt(line)}</td>`; tbody.appendChild(tr); });
      }
    document.getElementById('subtotal').textContent=fmt(order.subtotal??sub);
    document.getElementById('total').textContent=fmt(order.total??sub);
    document.getElementById('anchor').value=order.anchor??'';
    renderOrderLabel(order);
    }

    // ----- Adição -----
    let addThrottle=false;
    async function addItem(productId, qty=1){
      if(addThrottle) return; addThrottle=true; setTimeout(()=>addThrottle=false,300);
    if(!state.orderId) await newOrder();
    try{
        await api(`/api/orders/${state.orderId}/items`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ product_id: productId, quantity: qty }) });
    await refreshOrder(); await loadOpenOrders(); toast('Item adicionado');
    if(state.selected.id===productId){state.selected.qty = 1; updateSelectedUI(); }
      }catch(e){toast('Erro ao adicionar item'); console.error(e); }
    }

    // ----- Enviar ao KDS (sem criar nova comanda) -----
    async function sendToKds(){
      if(!state.orderId) return toast('Crie uma comanda primeiro');
    if(!state.hasDraft) return toast('Nada para enviar');
    state.loading.send=true; renderSendButton();
    try{
        const res=await api(`/api/orders/${state.orderId}/send`, {method:'POST'});
    const n=Number(res.sent_items||0);
    await refreshOrder();
    toast(`Enviado ao KDS: ${n} item${n === 1 ? '' : 's'}`);
    state.selected={id:null, qty:1};
    await loadOpenOrders();
    await loadProducts();
      }catch(e){console.error(e); toast('Falha ao enviar'); }
    finally{state.loading.send = false; renderSendButton(); }
    }

    // ----- Produtos/Categorias -----
    async function loadCategories(){
      try{
        const data=await api('/api/categories?per_page=100');
        state.categories=(data.data||data||[]).filter(c=>c.visible_pos??true);
    const sel=document.getElementById('category'); sel.innerHTML='<option value="">Todas categorias</option>';
        state.categories.forEach(c=>{ const opt=document.createElement('option'); opt.value=c.id; opt.textContent=c.name; sel.appendChild(opt); });
    const savedCat=localStorage.getItem('pos_cat_id'); if(savedCat){sel.value = savedCat; state.categoryId=savedCat; }
      }catch(e){console.error(e); }
    }
    async function loadProducts(){
      try{
        state.search = document.getElementById('search').value.trim();
    state.categoryId=document.getElementById('category').value||'';
    localStorage.setItem('pos_search', state.search);
    localStorage.setItem('pos_cat_id', state.categoryId);
    const qs=new URLSearchParams({per_page:100}); if(state.search) qs.set('search',state.search); if(state.categoryId) qs.set('category_id',state.categoryId);
    const data=await api('/api/products?'+qs.toString());
    state.products=data.data||data||[];
        if(!state.products.some(p=>p.id===state.selected.id)) state.selected={id:null, qty:1};
    renderProducts();
      }catch(e){console.error(e); }
    }
    function highlight(text, term){ if(!term) return escapeHtml(text); const re=new RegExp('('+escapeReg(term)+')','ig'); return escapeHtml(text).replace(re,'<mark>$1</mark>'); }
    function escapeReg(s){ return s.replace(/[.*+?^${ }()|[\]\\]/g, '\\$&'); }
    function renderProducts(){
      const grid=document.getElementById('product-grid'); grid.innerHTML=''; document.getElementById('count').textContent=state.products.length;
      state.products.forEach(p=>{
        const selected=state.selected.id===p.id; const qty=selected?state.selected.qty:1;
    const card=document.createElement('div'); card.className='card'; if(selected) card.classList.add('selected');
        card.addEventListener('click',()=>{ if(state.selected.id!==p.id){state.selected = { id: p.id, qty: 1 }; } else {state.selected.qty = Math.max(1, state.selected.qty + 1); } renderProducts(); });
    card.innerHTML=`
    <div class="name">${highlight(p.name, state.search)}</div>
    <div class="price">${fmt(p.price)} ${p.type === 'composed' ? '· composto' : ''}</div>
    ${selected ? `
            <div class="controls" onclick="event.stopPropagation()">
              <button class="btn" onclick="decQty(${p.id})">−</button>
              <input id="qty-${p.id}" type="number" min="1" step="1" value="${qty}"
                     onfocus="this.select()"
                     onkeydown="if(event.key==='Enter'){ addItem(${p.id}, Number(this.value||1)); event.preventDefault(); }">
              <button class="btn" onclick="incQty(${p.id})">+</button>
            </div>
            <div class="add-row" onclick="event.stopPropagation()">
              <button class="btn ok add" onclick="addItem(${p.id}, Number(document.getElementById('qty-${p.id}').value||1))">Adicionar</button>
            </div>`: ''}
    `;
    grid.appendChild(card);
      });
    }
    function decQty(pid){ if(state.selected.id!==pid) return; state.selected.qty=Math.max(1,state.selected.qty-1); updateSelectedUI(); }
    function incQty(pid){ if(state.selected.id!==pid) return; state.selected.qty=Math.max(1,state.selected.qty+1); updateSelectedUI(); }
    function updateSelectedUI(){ const el=document.getElementById('qty-'+state.selected.id); if(el) el.value=state.selected.qty; }

    function escapeHtml(s){ return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }
        function debounce(fn,ms){let t; return (...a)=>{clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; }
        const debouncedSearch=debounce(loadProducts,200);

    window.addEventListener('keydown',e=>{
      if(e.key==='/' && !e.metaKey && !e.ctrlKey){e.preventDefault(); document.getElementById('search').focus(); }
        if((e.ctrlKey||e.metaKey) && e.key.toLowerCase()==='k'){e.preventDefault(); newOrder(); }
    });

        (async function init(){
      const savedOrder=localStorage.getItem('pos_order_id'); if(savedOrder){state.orderId = Number(savedOrder); refreshOrder().catch(()=>{ }); }
        const savedSearch=localStorage.getItem('pos_search'); if(savedSearch) document.getElementById('search').value=savedSearch;
        await loadCategories(); await loadProducts(); await loadOpenOrders();
        setInterval(loadOpenOrders, 5000);
    })();
        window.newOrder = newOrder;
        window.suspendOrder = suspendOrder;
        window.toggleOpenOrders = toggleOpenOrders;
        window.sendToKds = sendToKds;
        window.addItem = addItem;
        window.decQty = decQty;
        window.incQty = incQty;
        window.toggleFullscreen = toggleFullscreen;
        window.debouncedSearch = debouncedSearch
        window.pickOpen = pickOpen;

