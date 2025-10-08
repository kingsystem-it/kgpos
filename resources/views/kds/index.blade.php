<!doctype html>
<html lang="it">

<head>
  <meta charset="utf-8">
  <title>KDS – KG POS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      --bg: #0b0f12;
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
      padding: 14px 20px;
      border-bottom: 1px solid #1f2937;
    }

    header h1 {
      font-size: 18px;
      margin: 0;
      letter-spacing: .5px;
    }

    .controls {
      display: flex;
      gap: 8px;
      align-items: center;
    }

    .badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 999px;
      font-size: 12px;
      color: #0b1115;
      margin-left: 8px;
    }

    .tabs {
      display: flex;
      gap: 8px;
      padding: 12px 20px;
      border-bottom: 1px solid #1f2937;
      align-items: center;
    }

    .tabs button {
      background: #0e141a;
      color: var(--text);
      border: 1px solid #1f2937;
      padding: 8px 12px;
      border-radius: 8px;
      cursor: pointer;
    }

    .tabs button.active {
      border-color: var(--accent);
      box-shadow: 0 0 0 1px var(--accent) inset;
    }

    .content {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 12px;
      padding: 16px;
    }

    .card {
      background: var(--card);
      border: 1px solid #1f2937;
      border-radius: 16px;
      padding: 16px;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }

    .name {
      font-size: 22px;
      font-weight: 700;
      letter-spacing: .2px;
    }

    .qty {
      font-size: 18px;
      color: var(--muted);
      font-weight: 600;
    }

    .meta {
      display: flex;
      gap: 10px;
      align-items: center;
      color: var(--muted);
      font-size: 12px;
      flex-wrap: wrap;
    }

    .btn {
      border: 1px solid #1f2937;
      background: #0e141a;
      color: var(--text);
      padding: 10px 12px;
      border-radius: 10px;
      cursor: pointer;
      font-size: 14px;
    }

    .btn:hover {
      border-color: #334155;
    }

    .btn.ok {
      border-color: #14532d;
      background: #0b1912;
    }

    .btn.warn {
      border-color: #7c2d12;
      background: #1a120b;
    }

    .muted {
      color: var(--muted);
    }

    .pill {
      padding: 2px 8px;
      border-radius: 999px;
      border: 1px solid #334155;
      font-size: 12px;
    }
  </style>
</head>

<body>
  <header>
    <h1>KDS – KG POS</h1>
    <div class="controls">
      <span class="muted">{{ auth()->user()->email }}</span>
      <button class="btn" onclick="toggleFullscreen()">Tela cheia</button>
      <button id="btn-wake" class="btn" onclick="toggleWakeLock()">Manter tela ligada</button>
      <button id="btn-sound" class="btn" onclick="toggleSound()">Som: off</button>
      <button class="btn" id="btn-test" onclick="testBeep()">Testar som</button>
    </div>
  </header>

  <div class="tabs">
    <button id="tab-sent" class="active" onclick="setStatus('sent')">Enviados <span id="badge-sent" class="badge" style="background:#38bdf8;">0</span></button>
    <button id="tab-prepared" onclick="setStatus('prepared')">Preparados <span id="badge-prepared" class="badge" style="background:#f59e0b;">0</span></button>
    <div class="muted" style="margin-left:auto">
      Última atualização: <span id="last-upd">—</span>
    </div>
  </div>

  <div class="tabs" style="gap:12px">
    <label class="muted">Rota:</label>
    <input id="routeId" type="number" min="0" placeholder="(opcional)" style="width:140px; background:#0e141a; color:var(--text); border:1px solid #1f2937; border-radius:8px; padding:6px 8px;">
    <button class="btn" onclick="reloadNow()">Atualizar</button>
  </div>

  <div id="grid" class="content"></div>

  <script>
    let state = {
      status: 'sent',
      routeId: null,
      timer: null,
      prevIds: new Set(),
      sound: false,
      wake: null,
      cache: {
        sent: [],
        prepared: []
      },
      pendingBeep: false
    };
    let audioCtx = null;

    // --------- Áudio ---------
    async function ensureAudio() {
      try {
        if (!audioCtx) audioCtx = new(window.AudioContext || window.webkitAudioContext)();
        if (audioCtx.state === 'suspended') await audioCtx.resume();
        return true;
      } catch {
        return false;
      }
    }

    async function playAlert() {
      try {
        const ok = await ensureAudio();
        if (!ok) throw new Error('no-audio');
        const ctx = audioCtx,
          now = ctx.currentTime;
        const beeps = [{
          f: 880,
          t: 0
        }, {
          f: 1046,
          t: .25
        }, {
          f: 1318,
          t: .5
        }];
        for (const {
            f,
            t
          }
          of beeps) {
          const o = ctx.createOscillator(),
            g = ctx.createGain();
          o.type = 'square';
          o.frequency.setValueAtTime(f, now + t);
          g.gain.setValueAtTime(0.0001, now + t);
          g.gain.exponentialRampToValueAtTime(0.95, now + t + 0.01);
          g.gain.exponentialRampToValueAtTime(0.0001, now + t + 0.24);
          o.connect(g).connect(ctx.destination);
          o.start(now + t);
          o.stop(now + t + 0.26);
        }
        if (navigator.vibrate) navigator.vibrate([70, 70, 70]);
        return true;
      } catch (e) {
        console.warn('Audio error', e);
        return false;
      }
    }

    function testBeep() {
      playAlert();
    }

    async function toggleSound() {
      state.sound = !state.sound;
      document.getElementById('btn-sound').textContent = 'Som: ' + (state.sound ? 'on' : 'off');
      if (state.sound) {
        await playAlert();
      } // beep de teste ao ligar
    }

    document.addEventListener('visibilitychange', async () => {
      if (document.visibilityState === 'visible') {
        if (audioCtx && audioCtx.state === 'suspended') {
          try {
            await audioCtx.resume();
          } catch {}
        }
        if (state.sound && state.pendingBeep) {
          state.pendingBeep = false;
          playAlert();
        }
        fetchQueue(); // atualiza a fila ao voltar o foco
      }
    });

    // --------- UI base ---------
    function setStatus(s) {
      state.status = s;
      document.getElementById('tab-sent').classList.toggle('active', s === 'sent');
      document.getElementById('tab-prepared').classList.toggle('active', s === 'prepared');
      render(state.cache[s] || []);
      reloadNow();
    }

    function reloadNow() {
      state.routeId = document.getElementById('routeId').value || null;
      fetchQueue();
    }

    // --------- Dados (busca as duas filas) ---------
    async function fetchQueue() {
      try {
        const mkParams = (s) => {
          const p = new URLSearchParams({
            status: s
          });
          if (state.routeId) p.set('route_id', state.routeId);
          return p.toString();
        };

        const [rSent, rPrep] = await Promise.all([
          fetch('/api/kds/queue?' + mkParams('sent'), {
            credentials: 'same-origin',
            cache: 'no-store'
          }),
          fetch('/api/kds/queue?' + mkParams('prepared'), {
            credentials: 'same-origin',
            cache: 'no-store'
          }),
        ]);
        if (!rSent.ok || !rPrep.ok) throw new Error('HTTP ' + rSent.status + '/' + rPrep.status);

        const sent = (await rSent.json()).data || [];
        const prepared = (await rPrep.json()).data || [];

        // badges
        document.getElementById('badge-sent').textContent = sent.length;
        document.getElementById('badge-prepared').textContent = prepared.length;

        // som quando chegam itens novos em "Enviados"
        if (state.sound) {
          const newIds = sent.map(i => i.id).filter(id => !state.prevIds.has(id));
          if (newIds.length > 0) {
            const ok = await playAlert();
            if (!ok) state.pendingBeep = true;
          }
          state.prevIds = new Set(sent.map(i => i.id));
        }

        // cache + render do tab ativo
        state.cache.sent = sent;
        state.cache.prepared = prepared;
        render(state.cache[state.status]);

        document.getElementById('last-upd').textContent = new Date().toLocaleTimeString();
      } catch (e) {
        console.error(e);
        document.getElementById('last-upd').textContent = 'erro';
      }
    }

    // --------- Render ---------
    function render(items) {
      const grid = document.getElementById('grid');
      grid.innerHTML = '';
      if (items.length === 0) {
        grid.innerHTML = '<div class="muted">Sem itens na fila…</div>';
        return;
      }
      for (const it of items) {
        const card = document.createElement('div');
        card.className = 'card';
        const when = it.status === 'prepared' ? it.prepared_at : it.sent_at;
        const since = when ? timeSince(when) : '';
        card.innerHTML = `
        <div class="row">
          <div class="name">${escapeHtml(it.name)}</div>
          <div class="qty">x ${Number(it.quantity)}</div>
        </div>
        <div class="meta">
          <span class="pill">#${it.id}</span>
          <span class="pill">Pedido ${it.order_id}</span>
          ${it.anchor ? `<span class="pill">${escapeHtml(it.anchor)}</span>` : ''}
          ${it.route_id ? `<span class="pill">Rota ${it.route_id}</span>` : ''}
          <span class="pill">${it.status.toUpperCase()}</span>
          <span class="muted">${since}</span>
        </div>
        <div class="row">
          <div></div>
          <div>
            ${it.status==='sent' ? `<button class="btn warn" onclick="markPrepared(${it.id})">Preparar</button>` : ''}
            ${['sent','prepared'].includes(it.status) ? `<button class="btn ok" onclick="markServed(${it.id})">Servir</button>` : ''}
          </div>
        </div>
      `;
        grid.appendChild(card);
      }
    }

    // --------- Ações ---------
    async function markPrepared(id) {
      if (!confirm('Marcar item #' + id + ' como PREPARADO?')) return;
      const r = await fetch('/api/kds/items/' + id + '/prepared', {
        method: 'POST',
        credentials: 'same-origin'
      });
      if (r.ok) fetchQueue();
    }
    async function markServed(id) {
      if (!confirm('Marcar item #' + id + ' como SERVIDO?')) return;
      const r = await fetch('/api/kds/items/' + id + '/served', {
        method: 'POST',
        credentials: 'same-origin'
      });
      if (r.ok) fetchQueue();
    }

    // --------- Utils ---------
    function parseUtc(ts) {
      if (!ts) return null;
      if (typeof ts === 'string') {
        // "YYYY-MM-DD HH:MM:SS" -> "YYYY-MM-DDTHH:MM:SSZ" (UTC)
        if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/.test(ts)) {
          ts = ts.replace(' ', 'T') + 'Z';
        }
        // "YYYY-MM-DDTHH:MM:SS" (sem TZ) -> adiciona Z
        else if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/.test(ts)) {
          ts = ts + 'Z';
        }
      }
      const d = new Date(ts);
      return isNaN(d) ? null : d;
    }

    function timeSince(ts) {
      const d = parseUtc(ts);
      if (!d) return '';
      const s = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
      if (s < 60) return s + 's';
      const m = Math.floor(s / 60),
        rem = s % 60;
      return m + 'm ' + rem + 's';
    }

    function escapeHtml(s) {
      return String(s).replace(/[&<>"']/g, c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      } [c]));
    }

    async function toggleWakeLock() {
      const btn = document.getElementById('btn-wake');
      try {
        if (!('wakeLock' in navigator)) throw new Error('no-wakelock');
        if (!state.wake) {
          state.wake = await navigator.wakeLock.request('screen');
          state.wake.addEventListener('release', () => {
            state.wake = null;
            btn.textContent = 'Manter tela ligada';
          });
          btn.textContent = 'Mantendo tela ligada';
        } else {
          await state.wake.release();
          state.wake = null;
          btn.textContent = 'Manter tela ligada';
        }
      } catch {
        alert('Wake Lock não suportado neste navegador.');
      }
    }

    function toggleFullscreen() {
      if (!document.fullscreenElement) document.documentElement.requestFullscreen().catch(() => {});
      else document.exitFullscreen();
    }

    function startPolling() {
      if (state.timer) clearInterval(state.timer);
      state.timer = setInterval(fetchQueue, 3000);
    }

    // init
    fetchQueue();
    startPolling();
  </script>
</body>

</html>