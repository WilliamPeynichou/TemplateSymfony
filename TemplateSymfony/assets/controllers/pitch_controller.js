import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['wrapper', 'players', 'bench', 'saveIndicator'];
    static values  = { saveUrl: String, teamId: Number };

    positions     = new Map();
    selectedPlayerId = null;
    dragSource    = null;
    dragPlayerId  = null;

    connect() {
        this.initDropZone();
        this.initBenchPlayersDrag();
        this.restoreServerRenderedTokens();
    }

    // ─── Restore server-rendered tokens ───────────────────────
    restoreServerRenderedTokens() {
        this.playersTarget.querySelectorAll('.player-token').forEach(token => {
            const playerId = parseInt(token.dataset.playerId);
            const posX     = parseFloat(token.style.left);
            const posY     = parseFloat(token.style.top);
            const data     = token.dataset.playerData
                ? JSON.parse(token.dataset.playerData)
                : { id: playerId, number: '?', firstName: '', lastName: '', position: '' };

            this.positions.set(playerId, {
                posX, posY,
                instructions: token.dataset.instructions || '',
                data,
            });

            this.bindTokenEvents(token, playerId);
        });
    }

    // ─── Drop zone ─────────────────────────────────────────────
    initDropZone() {
        this.wrapperTarget.addEventListener('dragover', (e) => {
            e.preventDefault();
            this.wrapperTarget.classList.add('drag-over');
        });

        this.wrapperTarget.addEventListener('dragleave', (e) => {
            if (!this.wrapperTarget.contains(e.relatedTarget)) {
                this.wrapperTarget.classList.remove('drag-over');
            }
        });

        this.wrapperTarget.addEventListener('drop', (e) => {
            e.preventDefault();
            this.wrapperTarget.classList.remove('drag-over');

            if (this.dragPlayerId === null) return;

            const rect = this.wrapperTarget.getBoundingClientRect();
            const posX = Math.max(3, Math.min(97, ((e.clientX - rect.left)  / rect.width)  * 100));
            const posY = Math.max(3, Math.min(97, ((e.clientY - rect.top)   / rect.height) * 100));

            if (this.dragSource === 'bench') {
                this.addPlayerToPitch(this.dragPlayerId, posX, posY);
            } else if (this.dragSource === 'pitch') {
                this.movePlayerOnPitch(this.dragPlayerId, posX, posY);
            }

            this.save();
        });
    }

    // ─── Add from bench ────────────────────────────────────────
    addPlayerToPitch(playerId, posX, posY) {
        const benchEl = this.benchTarget.querySelector(`[data-player-id="${playerId}"]`);
        if (!benchEl) return;

        const data = JSON.parse(benchEl.dataset.playerData);
        this.positions.set(playerId, { posX, posY, instructions: '', data });
        this.renderToken(playerId);
        benchEl.remove();
        this.syncBenchCount();
    }

    // ─── Move on pitch ─────────────────────────────────────────
    movePlayerOnPitch(playerId, posX, posY) {
        const entry = this.positions.get(playerId);
        if (!entry) return;
        entry.posX = posX;
        entry.posY = posY;

        const token = this.playersTarget.querySelector(`[data-player-id="${playerId}"]`);
        if (token) {
            token.style.left = posX + '%';
            token.style.top  = posY + '%';
        }
    }

    // ─── Render token ──────────────────────────────────────────
    renderToken(playerId) {
        const existing = this.playersTarget.querySelector(`[data-player-id="${playerId}"]`);
        if (existing) existing.remove();

        const entry = this.positions.get(playerId);
        if (!entry) return;

        const token = document.createElement('div');
        token.className = 'player-token';
        token.dataset.playerId   = playerId;
        token.dataset.playerData = JSON.stringify(entry.data);
        token.draggable = true;
        token.style.left = entry.posX + '%';
        token.style.top  = entry.posY + '%';
        token.innerHTML = `
            <div class="player-token__circle">${entry.data.number}</div>
            <div class="player-token__name">${entry.data.lastName}</div>
        `;

        this.bindTokenEvents(token, playerId);
        this.playersTarget.appendChild(token);

        if (this.selectedPlayerId === playerId) {
            token.classList.add('player-token--selected');
        }
    }

    bindTokenEvents(token, playerId) {
        token.addEventListener('dragstart', (e) => {
            this.dragSource   = 'pitch';
            this.dragPlayerId = parseInt(playerId);
            token.classList.add('player-token--dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        token.addEventListener('dragend', () => token.classList.remove('player-token--dragging'));

        token.addEventListener('click', (e) => {
            e.stopPropagation();
            this.selectPlayer(parseInt(playerId));
        });
    }

    // ─── Select player ─────────────────────────────────────────
    selectPlayer(playerId) {
        if (this.selectedPlayerId !== null) {
            const prev = this.playersTarget.querySelector(`[data-player-id="${this.selectedPlayerId}"]`);
            if (prev) prev.classList.remove('player-token--selected');
        }

        this.selectedPlayerId = playerId;
        const token = this.playersTarget.querySelector(`[data-player-id="${playerId}"]`);
        if (token) token.classList.add('player-token--selected');

        const entry = this.positions.get(playerId);
        const data  = entry?.data ?? { id: playerId };

        this.dispatch('playerSelected', {
            detail: { playerId, data, instructions: entry?.instructions || '' },
        });
    }

    // ─── Instructions update from sidebar ─────────────────────
    updateInstructionsFromEvent(event) {
        const { playerId, instructions } = event.detail;
        const entry = this.positions.get(playerId);
        if (entry) {
            entry.instructions = instructions;
            this.save();
        }
    }

    // ─── Remove player from pitch ──────────────────────────────
    removePlayerFromEvent(event) {
        this.removePlayer(event.detail.playerId);
    }

    removePlayer(playerId) {
        const entry = this.positions.get(playerId);
        if (!entry) return;

        const token = this.playersTarget.querySelector(`[data-player-id="${playerId}"]`);
        if (token) token.remove();

        this.positions.delete(playerId);
        this.addToBench(entry.data);
        this.selectedPlayerId = null;
        this.save();
    }

    // ─── Bench management ──────────────────────────────────────
    addToBench(data) {
        // Remove "bench-all-placed" state if present
        const emptyState = this.benchTarget.parentElement?.querySelector('.bench-all-placed');
        if (emptyState) emptyState.remove();

        // Determine position group
        const pos = data.position || '';
        const posGroup = pos === 'GK' ? 'GK'
            : ['CB','LB','RB'].includes(pos) ? 'DEF'
            : ['CDM','CM','CAM'].includes(pos) ? 'MIL'
            : 'ATT';

        const el = document.createElement('div');
        el.className = 'bench-card';
        el.draggable = true;
        el.dataset.playerId   = data.id;
        el.dataset.posGroup   = posGroup;
        el.dataset.playerData = JSON.stringify(data);

        const initials = (data.firstName?.[0] ?? '') + (data.lastName?.[0] ?? '');
        el.innerHTML = `
            <div class="bench-card__avatar bench-card__avatar--${posGroup.toLowerCase()}">${initials}</div>
            <div class="bench-card__info">
                <div class="bench-card__name">${data.lastName}</div>
                <div class="bench-card__meta">
                    <span class="bench-card__num">#${data.number}</span>
                    <span class="pos-badge pos-badge--${posGroup.toLowerCase()}">${data.position}</span>
                </div>
            </div>
            <div class="bench-card__drag">⣿</div>
        `;

        this.initBenchPlayerDrag(el);
        this.benchTarget.appendChild(el);
        this.syncBenchCount();
    }

    initBenchPlayersDrag() {
        this.benchTarget.querySelectorAll('.bench-card').forEach(el => this.initBenchPlayerDrag(el));
    }

    initBenchPlayerDrag(el) {
        el.addEventListener('dragstart', (e) => {
            this.dragSource   = 'bench';
            this.dragPlayerId = parseInt(el.dataset.playerId);
            el.classList.add('bench-card--dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        el.addEventListener('dragend', () => el.classList.remove('bench-card--dragging'));
    }

    syncBenchCount() {
        const count = this.benchTarget.querySelectorAll('.bench-card').length;
        // Find sidebar controller's benchCount target
        const countEl = this.element.querySelector('[data-sidebar-target="benchCount"]');
        if (countEl) countEl.textContent = count;
    }

    // ─── Save ──────────────────────────────────────────────────
    async save() {
        this.setSaveIndicator('saving');

        const payload = { positions: [] };
        this.positions.forEach((entry, playerId) => {
            payload.positions.push({
                playerId,
                posX:         entry.posX,
                posY:         entry.posY,
                instructions: entry.instructions || '',
            });
        });

        try {
            const res = await fetch(this.saveUrlValue, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(payload),
            });
            this.setSaveIndicator(res.ok ? 'saved' : 'error');
        } catch {
            this.setSaveIndicator('error');
        }
    }

    setSaveIndicator(state) {
        if (!this.hasSaveIndicatorTarget) return;
        const el = this.saveIndicatorTarget;
        el.classList.remove('save-indicator--saving', 'save-indicator--saved');
        if (state === 'saving') {
            el.textContent = 'Sauvegarde…';
            el.classList.add('save-indicator--saving');
        } else if (state === 'saved') {
            el.textContent = 'Sauvegardé ✓';
            el.classList.add('save-indicator--saved');
            setTimeout(() => { el.textContent = ''; el.classList.remove('save-indicator--saved'); }, 2000);
        } else {
            el.textContent = 'Erreur';
        }
    }
}
