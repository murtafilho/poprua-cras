/**
 * POPRUA v2 - Debug Panel para Mobile
 *
 * Painel flutuante que mostra no celular:
 * - Status da conexao (online/offline, WiFi/celular)
 * - Fila de uploads pendentes no IndexedDB
 * - Logs de eventos do Service Worker
 * - Envia logs ao servidor para analise posterior
 *
 * Ativacao: adicionar ?debug=1 na URL ou chamar window.showDebugPanel()
 */

const MAX_LOG_LINES = 100;
const FLUSH_INTERVAL = 10000;
const DB_NAME = 'poprua-offline';
const STORE_NAME = 'pending-uploads';

const PANEL_CSS = `
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&display=swap');

:host {
    --dbg-bg: #080c14;
    --dbg-bg-bar: #0e1420;
    --dbg-bg-btn: #141c2b;
    --dbg-bg-btn-hover: #1c2840;
    --dbg-border: #1e2a3e;
    --dbg-border-accent: rgba(79, 189, 186, 0.25);
    --dbg-text: #8fa4bd;
    --dbg-text-bright: #c8d8ea;
    --dbg-cyan: #4fbdba;
    --dbg-green: #10b981;
    --dbg-yellow: #eab308;
    --dbg-red: #ef4444;
    --dbg-blue: #3b82f6;
    --dbg-muted: #475569;
    --dbg-font: 'IBM Plex Mono', 'JetBrains Mono', monospace;
    --dbg-radius: 6px;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

.dbg-toggle {
    position: fixed;
    bottom: 74px;
    right: 10px;
    z-index: 99999;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: var(--dbg-bg);
    border: 1.5px solid var(--dbg-border);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
    transition: all 0.2s ease;
    box-shadow: 0 2px 12px rgba(0,0,0,0.4);
    overflow: hidden;
}

.dbg-toggle::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(135deg, rgba(79,189,186,0.08), transparent 60%);
}

.dbg-toggle:active {
    transform: scale(0.92);
}

.dbg-toggle svg {
    width: 16px;
    height: 16px;
    position: relative;
    z-index: 1;
}

.dbg-toggle[data-online="true"] svg { color: var(--dbg-cyan); }
.dbg-toggle[data-online="false"] svg { color: var(--dbg-red); }

.dbg-toggle[data-pending="true"]::after {
    content: '';
    position: absolute;
    top: 6px;
    right: 6px;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--dbg-yellow);
    box-shadow: 0 0 6px var(--dbg-yellow);
    animation: dbg-pulse 2s ease-in-out infinite;
}

@keyframes dbg-pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(0.8); }
}

/* === Main Panel === */

.dbg-panel {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 99998;
    height: 56vh;
    min-height: 300px;
    background: var(--dbg-bg);
    font-family: var(--dbg-font);
    font-size: 11px;
    line-height: 1.5;
    color: var(--dbg-text);
    display: none;
    flex-direction: column;
    border-top: 1px solid var(--dbg-border);
    box-shadow: 0 -8px 40px rgba(0,0,0,0.6);
    transform: translateY(100%);
    transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
}

.dbg-panel.open {
    display: flex;
    transform: translateY(0);
}

.dbg-panel.animating {
    display: flex;
}

/* === Status Bar === */

.dbg-status {
    padding: 8px 12px;
    background: var(--dbg-bg-bar);
    border-bottom: 1px solid var(--dbg-border);
    display: flex;
    align-items: center;
    gap: 2px;
    flex-shrink: 0;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}

.dbg-status::-webkit-scrollbar { display: none; }

.dbg-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 8px;
    border-radius: 4px;
    background: rgba(255,255,255,0.03);
    white-space: nowrap;
    font-size: 10px;
    letter-spacing: 0.02em;
}

.dbg-chip-label {
    color: var(--dbg-muted);
    text-transform: uppercase;
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 0.06em;
}

.dbg-chip-value {
    font-weight: 500;
}

.dbg-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

.dbg-dot.on  { background: var(--dbg-green); box-shadow: 0 0 6px rgba(16,185,129,0.5); }
.dbg-dot.off { background: var(--dbg-red); box-shadow: 0 0 6px rgba(239,68,68,0.5); }

/* === Tabs === */

.dbg-tabs {
    display: flex;
    border-bottom: 1px solid var(--dbg-border);
    background: var(--dbg-bg-bar);
    flex-shrink: 0;
}

.dbg-tab {
    flex: 1;
    padding: 7px 6px;
    text-align: center;
    font-family: var(--dbg-font);
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--dbg-muted);
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    transition: all 0.15s ease;
    -webkit-tap-highlight-color: transparent;
}

.dbg-tab:active { background: rgba(255,255,255,0.03); }
.dbg-tab.active {
    color: var(--dbg-cyan);
    border-bottom-color: var(--dbg-cyan);
}

/* === Content Area === */

.dbg-content {
    flex: 1;
    overflow: hidden;
    position: relative;
}

.dbg-tab-panel {
    position: absolute;
    inset: 0;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    padding: 6px 0;
    display: none;
}

.dbg-tab-panel.active { display: block; }

/* === Log Lines === */

.dbg-line {
    padding: 2px 12px;
    display: flex;
    gap: 8px;
    align-items: flex-start;
    border-bottom: 1px solid rgba(30,42,62,0.4);
    word-break: break-word;
}

.dbg-line:hover { background: rgba(255,255,255,0.015); }

.dbg-line-time {
    color: var(--dbg-muted);
    font-size: 9px;
    flex-shrink: 0;
    margin-top: 1px;
    font-variant-numeric: tabular-nums;
}

.dbg-line-level {
    font-size: 9px;
    font-weight: 600;
    flex-shrink: 0;
    width: 30px;
    text-align: center;
    border-radius: 2px;
    padding: 0 2px;
    margin-top: 1px;
    letter-spacing: 0.03em;
}

.dbg-line-level.debug { color: var(--dbg-muted); }
.dbg-line-level.info  { color: var(--dbg-blue); }
.dbg-line-level.warn  { color: var(--dbg-yellow); }
.dbg-line-level.error { color: var(--dbg-red); }

.dbg-line-msg {
    flex: 1;
    min-width: 0;
}

.dbg-line.error { background: rgba(239,68,68,0.04); }
.dbg-line.warn  { background: rgba(234,179,8,0.03); }

/* === Queue Cards === */

.dbg-queue-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    color: var(--dbg-muted);
    gap: 8px;
}

.dbg-queue-empty svg {
    width: 32px;
    height: 32px;
    opacity: 0.3;
}

.dbg-queue-card {
    margin: 4px 10px;
    padding: 10px 12px;
    background: var(--dbg-bg-btn);
    border: 1px solid var(--dbg-border);
    border-radius: var(--dbg-radius);
    display: flex;
    gap: 10px;
    align-items: center;
}

.dbg-queue-card-icon {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.dbg-queue-card-icon.pending  { background: rgba(234,179,8,0.12); color: var(--dbg-yellow); }
.dbg-queue-card-icon.uploading { background: rgba(59,130,246,0.12); color: var(--dbg-blue); }
.dbg-queue-card-icon.error    { background: rgba(239,68,68,0.12); color: var(--dbg-red); }

.dbg-queue-card-icon svg { width: 16px; height: 16px; }

.dbg-queue-card-info {
    flex: 1;
    min-width: 0;
}

.dbg-queue-card-name {
    color: var(--dbg-text-bright);
    font-weight: 500;
    font-size: 11px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dbg-queue-card-meta {
    color: var(--dbg-muted);
    font-size: 9px;
    margin-top: 2px;
    display: flex;
    gap: 8px;
}

.dbg-queue-card-badge {
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 2px 6px;
    border-radius: 3px;
    flex-shrink: 0;
}

.dbg-queue-card-badge.pending   { background: rgba(234,179,8,0.12); color: var(--dbg-yellow); }
.dbg-queue-card-badge.uploading { background: rgba(59,130,246,0.12); color: var(--dbg-blue); }
.dbg-queue-card-badge.error     { background: rgba(239,68,68,0.12); color: var(--dbg-red); }

/* === Network Tab === */

.dbg-net-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    padding: 10px;
}

.dbg-net-card {
    padding: 12px;
    background: var(--dbg-bg-btn);
    border: 1px solid var(--dbg-border);
    border-radius: var(--dbg-radius);
}

.dbg-net-card-label {
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--dbg-muted);
    margin-bottom: 4px;
}

.dbg-net-card-value {
    font-size: 16px;
    font-weight: 600;
    color: var(--dbg-text-bright);
}

.dbg-net-card.full { grid-column: 1 / -1; }

.dbg-net-event {
    padding: 6px 12px;
    font-size: 10px;
    display: flex;
    gap: 8px;
    border-bottom: 1px solid rgba(30,42,62,0.3);
}

.dbg-net-event-time {
    color: var(--dbg-muted);
    font-size: 9px;
    flex-shrink: 0;
    font-variant-numeric: tabular-nums;
}

/* === Toolbar === */

.dbg-toolbar {
    display: flex;
    gap: 6px;
    padding: 6px 10px;
    background: var(--dbg-bg-bar);
    border-top: 1px solid var(--dbg-border);
    flex-shrink: 0;
}

.dbg-btn {
    flex: 1;
    padding: 6px 8px;
    background: var(--dbg-bg-btn);
    color: var(--dbg-text);
    border: 1px solid var(--dbg-border);
    border-radius: var(--dbg-radius);
    font-family: var(--dbg-font);
    font-size: 10px;
    font-weight: 500;
    cursor: pointer;
    text-align: center;
    transition: all 0.15s ease;
    -webkit-tap-highlight-color: transparent;
}

.dbg-btn:active {
    background: var(--dbg-bg-btn-hover);
    transform: scale(0.97);
}

.dbg-btn.accent {
    border-color: var(--dbg-border-accent);
    color: var(--dbg-cyan);
}

.dbg-btn.danger {
    color: var(--dbg-red);
    border-color: rgba(239,68,68,0.2);
}

/* Scanline effect */
.dbg-panel::after {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 2px,
        rgba(0,0,0,0.03) 2px,
        rgba(0,0,0,0.03) 4px
    );
    z-index: 1;
}
`;

// SVG icons as functions for reuse
const ICONS = {
    terminal: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>`,
    wifi: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>`,
    wifiOff: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="1" y1="1" x2="23" y2="23"/><path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/><path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/><path d="M10.71 5.05A16 16 0 0 1 22.56 9"/><path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>`,
    upload: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>`,
    image: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>`,
    check: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>`,
    alertCircle: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
    loader: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg>`,
    inbox: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>`,
};

class DebugPanel {
    constructor() {
        this._logs = [];
        this._visible = false;
        this._flushTimer = null;
        this._buffer = [];
        this._netEvents = [];
        this._activeTab = 'logs';
        this._initialized = false;

        // Shadow DOM refs
        this._host = null;
        this._shadow = null;
        this._els = {};
    }

    init() {
        if (this._initialized) return;
        this._initialized = true;

        this._createShadowDOM();
        this._hookConsole();
        this._monitorConnection();
        this._monitorServiceWorker();
        this._startFlush();
        this._updateStatus();

        setInterval(() => this._updateStatus(), 5000);
        this._log('info', 'Debug panel inicializado');
    }

    _createShadowDOM() {
        // Host element
        this._host = document.createElement('div');
        this._host.id = 'poprua-debug';
        document.body.appendChild(this._host);

        this._shadow = this._host.attachShadow({ mode: 'open' });

        const style = document.createElement('style');
        style.textContent = PANEL_CSS;
        this._shadow.appendChild(style);

        // Toggle button
        const toggle = document.createElement('div');
        toggle.className = 'dbg-toggle';
        toggle.setAttribute('data-online', navigator.onLine);
        toggle.innerHTML = ICONS.terminal;
        toggle.addEventListener('click', () => this.toggle());
        this._els.toggle = toggle;
        this._shadow.appendChild(toggle);

        // Panel
        const panel = document.createElement('div');
        panel.className = 'dbg-panel';

        // Status bar
        const status = document.createElement('div');
        status.className = 'dbg-status';
        this._els.status = status;
        panel.appendChild(status);

        // Tabs
        const tabs = document.createElement('div');
        tabs.className = 'dbg-tabs';

        const tabData = [
            { id: 'logs', label: 'Console' },
            { id: 'queue', label: 'Fila' },
            { id: 'network', label: 'Rede' },
        ];

        tabData.forEach(t => {
            const tab = document.createElement('button');
            tab.className = `dbg-tab ${t.id === 'logs' ? 'active' : ''}`;
            tab.textContent = t.label;
            tab.dataset.tab = t.id;
            tab.addEventListener('click', () => this._switchTab(t.id));
            tabs.appendChild(tab);
        });
        panel.appendChild(tabs);

        // Content
        const content = document.createElement('div');
        content.className = 'dbg-content';

        // Logs tab
        const logsPanel = document.createElement('div');
        logsPanel.className = 'dbg-tab-panel active';
        logsPanel.dataset.panel = 'logs';
        this._els.logs = logsPanel;
        content.appendChild(logsPanel);

        // Queue tab
        const queuePanel = document.createElement('div');
        queuePanel.className = 'dbg-tab-panel';
        queuePanel.dataset.panel = 'queue';
        this._els.queue = queuePanel;
        content.appendChild(queuePanel);

        // Network tab
        const netPanel = document.createElement('div');
        netPanel.className = 'dbg-tab-panel';
        netPanel.dataset.panel = 'network';
        this._els.network = netPanel;
        content.appendChild(netPanel);

        panel.appendChild(content);

        // Toolbar
        const toolbar = document.createElement('div');
        toolbar.className = 'dbg-toolbar';

        const btnClear = this._makeBtn('Limpar', () => this._clearLogs());
        const btnFlush = this._makeBtn('Enviar', () => this._flush(), 'accent');
        const btnClose = this._makeBtn('Fechar', () => this.toggle(), 'danger');

        toolbar.append(btnClear, btnFlush, btnClose);
        panel.appendChild(toolbar);

        this._els.panel = panel;
        this._shadow.appendChild(panel);
    }

    _makeBtn(label, onClick, variant) {
        const btn = document.createElement('button');
        btn.className = `dbg-btn ${variant || ''}`;
        btn.textContent = label;
        btn.addEventListener('click', onClick);
        return btn;
    }

    _switchTab(tabId) {
        this._activeTab = tabId;

        this._shadow.querySelectorAll('.dbg-tab').forEach(t => {
            t.classList.toggle('active', t.dataset.tab === tabId);
        });
        this._shadow.querySelectorAll('.dbg-tab-panel').forEach(p => {
            p.classList.toggle('active', p.dataset.panel === tabId);
        });

        if (tabId === 'queue') this._renderQueue();
        if (tabId === 'network') this._renderNetwork();
        if (tabId === 'logs') this._scrollToBottom();
    }

    toggle() {
        this._visible = !this._visible;
        const panel = this._els.panel;

        if (this._visible) {
            panel.classList.add('animating');
            requestAnimationFrame(() => {
                panel.classList.add('open');
                panel.classList.remove('animating');
            });
            this._scrollToBottom();
            if (this._activeTab === 'queue') this._renderQueue();
            if (this._activeTab === 'network') this._renderNetwork();
        } else {
            panel.classList.remove('open');
            setTimeout(() => {
                if (!this._visible) panel.classList.remove('animating');
            }, 300);
        }
    }

    // === Status bar ===

    _updateStatus() {
        if (!this._els.status) return;

        const online = navigator.onLine;
        const conn = navigator.connection || navigator.mozConnection;
        const connType = conn?.type || '-';
        const effectiveType = conn?.effectiveType || '-';
        const downlink = conn?.downlink ? `${conn.downlink}` : '-';

        // Update toggle
        if (this._els.toggle) {
            this._els.toggle.setAttribute('data-online', online);
            this._els.toggle.innerHTML = online ? ICONS.wifi : ICONS.wifiOff;
        }

        this._countPending().then(count => {
            if (this._els.toggle) {
                this._els.toggle.setAttribute('data-pending', count > 0);
            }

            this._els.status.innerHTML = `
                <div class="dbg-chip">
                    <div class="dbg-dot ${online ? 'on' : 'off'}"></div>
                    <span class="dbg-chip-value" style="color: ${online ? 'var(--dbg-green)' : 'var(--dbg-red)'}">
                        ${online ? 'Online' : 'Offline'}
                    </span>
                </div>
                <div class="dbg-chip">
                    <span class="dbg-chip-label">Tipo</span>
                    <span class="dbg-chip-value">${connType}</span>
                </div>
                <div class="dbg-chip">
                    <span class="dbg-chip-label">Vel</span>
                    <span class="dbg-chip-value">${effectiveType} ${downlink !== '-' ? downlink + 'M' : ''}</span>
                </div>
                <div class="dbg-chip">
                    <span class="dbg-chip-label">Fila</span>
                    <span class="dbg-chip-value" style="color: ${count > 0 ? 'var(--dbg-yellow)' : 'var(--dbg-green)'}">${count}</span>
                </div>
                <div class="dbg-chip">
                    <span class="dbg-chip-label">SW</span>
                    <div class="dbg-dot ${navigator.serviceWorker?.controller ? 'on' : 'off'}"></div>
                </div>
            `;
        });
    }

    async _countPending() {
        try {
            const db = await this._openDB();
            return await new Promise((resolve) => {
                const tx = db.transaction(STORE_NAME, 'readonly');
                const countReq = tx.objectStore(STORE_NAME).count();
                countReq.onsuccess = () => resolve(countReq.result);
                countReq.onerror = () => resolve(0);
            });
        } catch {
            return 0;
        }
    }

    _openDB() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(DB_NAME, 1);
            req.onupgradeneeded = (e) => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains(STORE_NAME)) {
                    const store = db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
                    store.createIndex('status', 'status', { unique: false });
                    store.createIndex('vistoriaId', 'vistoriaId', { unique: false });
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    }

    // === Logs ===

    _hookConsole() {
        const self = this;
        const original = {};

        ['log', 'info', 'warn', 'error'].forEach(level => {
            original[level] = console[level];
            console[level] = function (...args) {
                original[level].apply(console, args);
                const msg = args.map(a => {
                    if (typeof a === 'object') {
                        try { return JSON.stringify(a, null, 1); } catch { return String(a); }
                    }
                    return String(a);
                }).join(' ');
                self._log(level === 'log' ? 'debug' : level, msg);
            };
        });

        window.addEventListener('error', (e) => {
            this._log('error', `Uncaught: ${e.message} at ${e.filename}:${e.lineno}`);
        });
        window.addEventListener('unhandledrejection', (e) => {
            this._log('error', `Unhandled rejection: ${e.reason}`);
        });
    }

    _log(level, message) {
        const entry = { level, message: message.substring(0, 1000), timestamp: Date.now() };

        this._logs.push(entry);
        if (this._logs.length > MAX_LOG_LINES) this._logs.shift();

        this._buffer.push(entry);
        this._appendLogLine(entry);
    }

    _appendLogLine(entry) {
        if (!this._els.logs) return;

        const time = new Date(entry.timestamp).toLocaleTimeString('pt-BR', {
            hour: '2-digit', minute: '2-digit', second: '2-digit',
        });

        const line = document.createElement('div');
        line.className = `dbg-line ${entry.level}`;
        line.innerHTML = `
            <span class="dbg-line-time">${time}</span>
            <span class="dbg-line-level ${entry.level}">${entry.level.toUpperCase().substring(0, 4)}</span>
            <span class="dbg-line-msg">${this._escapeHtml(entry.message)}</span>
        `;
        this._els.logs.appendChild(line);

        // Prune old DOM nodes
        while (this._els.logs.children.length > MAX_LOG_LINES) {
            this._els.logs.removeChild(this._els.logs.firstChild);
        }

        if (this._visible && this._activeTab === 'logs') {
            this._scrollToBottom();
        }
    }

    _escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    _scrollToBottom() {
        if (this._els.logs) {
            this._els.logs.scrollTop = this._els.logs.scrollHeight;
        }
    }

    _clearLogs() {
        this._logs = [];
        if (this._els.logs) this._els.logs.innerHTML = '';
        this._log('info', 'Logs limpos');
    }

    // === Queue tab ===

    async _renderQueue() {
        const container = this._els.queue;
        if (!container) return;

        try {
            const db = await this._openDB();
            const records = await new Promise((resolve) => {
                const tx = db.transaction(STORE_NAME, 'readonly');
                const getReq = tx.objectStore(STORE_NAME).getAll();
                getReq.onsuccess = () => resolve(getReq.result);
                getReq.onerror = () => resolve([]);
            });

            if (records.length === 0) {
                container.innerHTML = `
                    <div class="dbg-queue-empty">
                        ${ICONS.inbox}
                        <span>Nenhum upload pendente</span>
                    </div>
                `;
                return;
            }

            container.innerHTML = records.map(r => {
                const size = r.compressedSize ? `${Math.round(r.compressedSize / 1024)}KB` : '?';
                const age = this._formatAge(Date.now() - r.createdAt);
                const status = r.status || 'pending';
                const statusIcon = status === 'error' ? ICONS.alertCircle
                    : status === 'uploading' ? ICONS.loader
                    : ICONS.upload;

                return `
                    <div class="dbg-queue-card">
                        <div class="dbg-queue-card-icon ${status}">${statusIcon}</div>
                        <div class="dbg-queue-card-info">
                            <div class="dbg-queue-card-name">${this._escapeHtml(r.filename || 'sem nome')}</div>
                            <div class="dbg-queue-card-meta">
                                <span>Vistoria #${r.vistoriaId}</span>
                                <span>${size}</span>
                                <span>${age}</span>
                            </div>
                        </div>
                        <div class="dbg-queue-card-badge ${status}">${status}</div>
                    </div>
                `;
            }).join('');

        } catch (e) {
            container.innerHTML = `<div class="dbg-queue-empty"><span>Erro: ${e.message}</span></div>`;
        }
    }

    _formatAge(ms) {
        const s = Math.floor(ms / 1000);
        if (s < 60) return `${s}s`;
        const m = Math.floor(s / 60);
        if (m < 60) return `${m}min`;
        const h = Math.floor(m / 60);
        return `${h}h ${m % 60}min`;
    }

    // === Network tab ===

    _renderNetwork() {
        const container = this._els.network;
        if (!container) return;

        const online = navigator.onLine;
        const conn = navigator.connection || navigator.mozConnection;

        let cardsHtml = `
            <div class="dbg-net-grid">
                <div class="dbg-net-card">
                    <div class="dbg-net-card-label">Status</div>
                    <div class="dbg-net-card-value" style="color: ${online ? 'var(--dbg-green)' : 'var(--dbg-red)'}">
                        ${online ? 'Online' : 'Offline'}
                    </div>
                </div>
                <div class="dbg-net-card">
                    <div class="dbg-net-card-label">Tipo</div>
                    <div class="dbg-net-card-value">${conn?.type || 'N/A'}</div>
                </div>
                <div class="dbg-net-card">
                    <div class="dbg-net-card-label">Velocidade</div>
                    <div class="dbg-net-card-value">${conn?.effectiveType || 'N/A'}</div>
                </div>
                <div class="dbg-net-card">
                    <div class="dbg-net-card-label">Downlink</div>
                    <div class="dbg-net-card-value">${conn?.downlink ? conn.downlink + ' Mbps' : 'N/A'}</div>
                </div>
                <div class="dbg-net-card">
                    <div class="dbg-net-card-label">RTT</div>
                    <div class="dbg-net-card-value">${conn?.rtt ? conn.rtt + 'ms' : 'N/A'}</div>
                </div>
                <div class="dbg-net-card">
                    <div class="dbg-net-card-label">Save Data</div>
                    <div class="dbg-net-card-value">${conn?.saveData ? 'Sim' : 'Nao'}</div>
                </div>
            </div>
        `;

        // Recent events
        if (this._netEvents.length > 0) {
            cardsHtml += `<div style="padding: 8px 12px; border-top: 1px solid var(--dbg-border);">
                <div style="font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--dbg-muted); margin-bottom: 4px;">Eventos recentes</div>
            </div>`;
            cardsHtml += this._netEvents.slice(-20).reverse().map(e => `
                <div class="dbg-net-event">
                    <span class="dbg-net-event-time">${new Date(e.ts).toLocaleTimeString('pt-BR')}</span>
                    <span style="color: ${e.color || 'var(--dbg-text)'}">${this._escapeHtml(e.msg)}</span>
                </div>
            `).join('');
        }

        container.innerHTML = cardsHtml;
    }

    // === Connection monitoring ===

    _monitorConnection() {
        window.addEventListener('online', () => {
            this._addNetEvent('Conexao restaurada', 'var(--dbg-green)');
            this._log('info', 'Conexao restaurada');
            this._updateStatus();
        });
        window.addEventListener('offline', () => {
            this._addNetEvent('Conexao perdida', 'var(--dbg-red)');
            this._log('warn', 'Conexao perdida');
            this._updateStatus();
        });

        if (navigator.connection) {
            navigator.connection.addEventListener('change', () => {
                const c = navigator.connection;
                const msg = `Tipo: ${c.type} | ${c.effectiveType} | ${c.downlink}Mbps | RTT: ${c.rtt}ms`;
                this._addNetEvent(msg, 'var(--dbg-blue)');
                this._log('info', `Conexao alterada: ${msg}`);
                this._updateStatus();
            });
        }
    }

    _addNetEvent(msg, color) {
        this._netEvents.push({ ts: Date.now(), msg, color });
        if (this._netEvents.length > 50) this._netEvents.shift();
        if (this._visible && this._activeTab === 'network') this._renderNetwork();
    }

    // === Service Worker monitoring ===

    _monitorServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            this._log('warn', 'Service Worker nao suportado');
            return;
        }

        navigator.serviceWorker.addEventListener('message', (event) => {
            const data = event.data;
            if (data.type === 'UPLOAD_SUCCESS') {
                this._log('info', `Upload OK: ${data.filename} (vistoria ${data.vistoriaId})`);
                this._addNetEvent(`Upload: ${data.filename}`, 'var(--dbg-green)');
            } else if (data.type === 'UPLOAD_ERROR') {
                this._log('error', `Upload ERRO: ${data.filename} (HTTP ${data.status})`);
                this._addNetEvent(`Erro upload: ${data.filename} (${data.status})`, 'var(--dbg-red)');
            }
            this._updateStatus();
            if (this._activeTab === 'queue') this._renderQueue();
        });

        navigator.serviceWorker.addEventListener('controllerchange', () => {
            this._log('info', 'Service Worker controller alterado');
            this._updateStatus();
        });
    }

    // === Server flush ===

    _startFlush() {
        this._flushTimer = setInterval(() => this._flush(), FLUSH_INTERVAL);
    }

    async _flush() {
        if (this._buffer.length === 0 || !navigator.onLine) return;

        const batch = this._buffer.splice(0, 50);
        const appBase = document.querySelector('meta[name="app-base"]')?.content || '';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        try {
            const response = await fetch(`${appBase}/api/client-logs`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ logs: batch }),
            });

            if (response.ok) {
                const data = await response.json();
                this._addNetEvent(`${data.received} logs enviados`, 'var(--dbg-cyan)');
            }
        } catch {
            this._buffer.unshift(...batch);
        }
    }
}

// === Init ===

const debugPanel = new DebugPanel();

function initDebugPanel() {
    const params = new URLSearchParams(window.location.search);
    if (params.has('debug') || localStorage.getItem('poprua-debug') === '1') {
        debugPanel.init();
        if (params.has('debug')) {
            localStorage.setItem('poprua-debug', '1');
        }
    }
}

window.showDebugPanel = () => {
    localStorage.setItem('poprua-debug', '1');
    debugPanel.init();
    debugPanel.toggle();
};

window.hideDebugPanel = () => {
    localStorage.removeItem('poprua-debug');
    const host = document.getElementById('poprua-debug');
    if (host) host.remove();
};

document.addEventListener('DOMContentLoaded', initDebugPanel);

export { DebugPanel, debugPanel };
