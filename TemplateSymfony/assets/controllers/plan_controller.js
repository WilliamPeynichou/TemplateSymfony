import { Controller } from '@hotwired/stimulus';

// Plan controller — like pitch_controller but:
// - All players always visible in the bench (marked as placed when on canvas)
// - No player removed from bench list; just toggled as placed/unplaced
export default class extends Controller {
    static targets = ['wrapper', 'players', 'bench', 'saveIndicator'];
    static values  = { saveUrl: String };

    positions     = new Map();
    selectedPlayerId = null;
    dragSource    = null;
    dragPlayerId  = null;

    connect() {
        this.initDropZone();
        this.initBenchPlayersDrag();
        this.restoreServerRenderedTokens();
    }

    restoreServerRenderedTokens() {
        this.playersTarget.querySelectorAll('.tactical-player-token').forEach(token => {
            const playerId = parseInt(token.dataset.playerId);
            const posX     = parseFloat(token.style.left);
            const posY     = parseFloat(token.style.top);
            const data     = token.dataset.playerData
                ? JSON.parse(token.dataset.playerData)
                : { id: playerId, number: '?', firstName: '', lastName: '', position: '' };

            this.positions.set(playerId, {
                posX, posY,
                note: token.dataset.note || '',
                data,
            });

            this.bindTokenEvents(token, playerId);
        });
    }

    initDropZone() {
        this.wrapperTarget.addEventListener('dragover', (e) => {
            e.preventDefault();
            this.wrapperTarget.classList.add('tactical-drag-over');
        });

        this.wrapperTarget.addEventListener('dragleave', (e) => {
            if (!this.wrapperTarget.contains(e.relatedTarget)) {
                this.wrapperTarget.classList.remove('tactical-drag-over');
            }
        });

        this.wrapperTarget.addEventListener('drop', (e) => {
            e.preventDefault();
            this.wrapperTarget.classList.remove('tactical-drag-over');

            if (this.dragPlayerId === null) return;

            const rect = this.wrapperTarget.getBoundingClientRect();
            const posX = Math.max(3, Math.min(97, ((e.clientX - rect.left)  / rect.width)  * 100));
            const posY = Math.max(3, Math.min(97, ((e.clientY - rect.top)   / rect.height) * 100));

            if (this.dragSource === 'bench') {
                this.addPlayerToCanvas(this.dragPlayerId, posX, posY);
            } else if (this.dragSource === 'canvas') {
                this.movePlayerOnCanvas(this.dragPlayerId, posX, posY);
            }

            this.save();
        });
    }

    addPlayerToCanvas(playerId, posX, posY) {
        const benchEl = this.benchTarget.querySelector(`[data-player-id="${playerId}"]`);
        if (!benchEl) return;

        const data = JSON.parse(benchEl.dataset.playerData);
        this.positions.set(playerId, { posX, posY, note: '', data });
        this.renderToken(playerId);

        // Mark as placed in bench list
        benchEl.classList.add('tactical-bench-card--placed');
        benchEl.dataset.placed = '1';
        benchEl.draggable = false;
        const dragIcon = benchEl.querySelector('.tactical-bench-card__drag');
        if (dragIcon) dragIcon.outerHTML = '<div class="tactical-bench-card__placed-icon">✓</div>';
    }

    movePlayerOnCanvas(playerId, posX, posY) {
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

    renderToken(playerId) {
        const existing = this.playersTarget.querySelector(`[data-player-id="${playerId}"]`);
        if (existing) existing.remove();

        const entry = this.positions.get(playerId);
        if (!entry) return;

        const token = document.createElement('div');
        token.className = 'tactical-player-token';
        token.dataset.playerId   = playerId;
        token.dataset.playerData = JSON.stringify(entry.data);
        token.draggable = true;
        token.style.left = entry.posX + '%';
        token.style.top  = entry.posY + '%';
        token.innerHTML = `
            <div class="tactical-player-token__circle">${entry.data.number}</div>
            <div class="tactical-player-token__name">${entry.data.lastName}</div>
        `;

        this.bindTokenEvents(token, playerId);
        this.playersTarget.appendChild(token);

        if (this.selectedPlayerId === playerId) {
            token.classList.add('tactical-player-token--selected');
        }
    }

    bindTokenEvents(token, playerId) {
        token.addEventListener('dragstart', (e) => {
            this.dragSource   = 'canvas';
            this.dragPlayerId = parseInt(playerId);
            token.classList.add('tactical-player-token--dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        token.addEventListener('dragend', () => token.classList.remove('tactical-player-token--dragging'));

        token.addEventListener('click', (e) => {
            e.stopPropagation();
            this.selectPlayer(parseInt(playerId));
        });
    }

    selectPlayer(playerId) {
        if (this.selectedPlayerId !== null) {
            const prev = this.playersTarget.querySelector(`[data-player-id="${this.selectedPlayerId}"]`);
            if (prev) prev.classList.remove('tactical-player-token--selected');
        }

        this.selectedPlayerId = playerId;
        const token = this.playersTarget.querySelector(`[data-player-id="${playerId}"]`);
        if (token) token.classList.add('tactical-player-token--selected');

        const entry = this.positions.get(playerId);
        const data  = entry?.data ?? { id: playerId };

        this.dispatch('playerSelected', {
            detail: { playerId, data, note: entry?.note || '' },
        });
    }

    updateNoteFromEvent(event) {
        const { playerId, note } = event.detail;
        const entry = this.positions.get(playerId);
        if (entry) {
            entry.note = note;
            this.save();
        }
    }

    removePlayerFromEvent(event) {
        this.removePlayer(event.detail.playerId);
    }

    removePlayer(playerId) {
        const entry = this.positions.get(playerId);
        if (!entry) return;

        const token = this.playersTarget.querySelector(`[data-player-id="${playerId}"]`);
        if (token) token.remove();

        this.positions.delete(playerId);
        this.selectedPlayerId = null;

        // Unmark as placed in bench list
        const benchEl = this.benchTarget.querySelector(`[data-player-id="${playerId}"]`);
        if (benchEl) {
            benchEl.classList.remove('tactical-bench-card--placed');
            benchEl.dataset.placed = '0';
            benchEl.draggable = true;
            const placedIcon = benchEl.querySelector('.tactical-bench-card__placed-icon');
            if (placedIcon) placedIcon.outerHTML = '<div class="tactical-bench-card__drag">⣿</div>';
            this.initBenchPlayerDrag(benchEl);
        }

        this.save();
    }

    initBenchPlayersDrag() {
        this.benchTarget.querySelectorAll('.tactical-bench-card:not(.tactical-bench-card--placed)').forEach(el => this.initBenchPlayerDrag(el));
    }

    initBenchPlayerDrag(el) {
        el.addEventListener('dragstart', (e) => {
            if (el.dataset.placed === '1') { e.preventDefault(); return; }
            this.dragSource   = 'bench';
            this.dragPlayerId = parseInt(el.dataset.playerId);
            el.classList.add('tactical-bench-card--dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        el.addEventListener('dragend', () => el.classList.remove('tactical-bench-card--dragging'));
    }

    async save() {
        this.setSaveIndicator('saving');

        const payload = { positions: [] };
        this.positions.forEach((entry, playerId) => {
            payload.positions.push({
                playerId,
                posX: entry.posX,
                posY: entry.posY,
                note: entry.note || '',
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
        el.classList.remove('tactical-save-indicator--saving', 'tactical-save-indicator--saved');
        if (state === 'saving') {
            el.textContent = 'Sauvegarde…';
            el.classList.add('tactical-save-indicator--saving');
        } else if (state === 'saved') {
            el.textContent = 'Sauvegardé ✓';
            el.classList.add('tactical-save-indicator--saved');
            setTimeout(() => { el.textContent = ''; el.classList.remove('tactical-save-indicator--saved'); }, 2000);
        } else {
            el.textContent = 'Erreur';
        }
    }
}
