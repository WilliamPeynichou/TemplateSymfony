import { Controller } from '@hotwired/stimulus';

/**
 * Gestion des convocations : drag & drop 4 colonnes + motifs + sauvegarde auto.
 */
export default class extends Controller {
    static targets = ['zone', 'list', 'card', 'reasonPanel', 'reasonPlayerName', 'reasonSelect', 'reasonNotes'];
    static values  = { saveUrl: String, reasons: Object };

    _dragging     = null;
    _activeCard   = null;
    saveTimer     = null;

    connect() {
        this.listTargets.forEach(list => {
            list.addEventListener('dragover',  e => this._onDragOver(e, list));
            list.addEventListener('drop',      e => this._onDrop(e, list));
            list.addEventListener('dragleave', e => this._onDragLeave(e, list));
        });
    }

    // ─── Drag & drop ────────────────────────────────────────────

    dragStart(event) {
        this._dragging = event.currentTarget;
        this._dragging.classList.add('callup-card--dragging');
        event.dataTransfer.effectAllowed = 'move';
    }

    dragEnd(event) {
        event.currentTarget.classList.remove('callup-card--dragging');
        this._dragging = null;
        this.listTargets.forEach(l => l.classList.remove('callup-col__list--over'));
    }

    _onDragOver(event, list) {
        event.preventDefault();
        list.classList.add('callup-col__list--over');
    }

    _onDragLeave(event, list) {
        list.classList.remove('callup-col__list--over');
    }

    _onDrop(event, list) {
        event.preventDefault();
        list.classList.remove('callup-col__list--over');
        if (!this._dragging || list.contains(this._dragging)) return;

        const newRole = list.dataset.role;
        this._dragging.dataset.role = newRole;

        // En mode absent, ouvrir le panneau motif si pas encore de raison
        if (newRole === 'absent' && !this._dragging.dataset.reason) {
            this._openPanelFor(this._dragging);
        }

        list.appendChild(this._dragging);
        this._updateCounters();
        this._scheduleSave();
    }

    // ─── Panneau motif ───────────────────────────────────────────

    openReasonPanel(event) {
        event.stopPropagation();
        const card = event.currentTarget.closest('[data-callup-target~="card"]');
        this._openPanelFor(card);
    }

    _openPanelFor(card) {
        this._activeCard = card;
        this.reasonPlayerNameTarget.textContent =
            card.querySelector('.callup-card__name')?.textContent?.trim() || 'Joueur';
        this.reasonSelectTarget.value = card.dataset.reason || '';
        this.reasonNotesTarget.value  = card.dataset.notes  || '';
        this.reasonPanelTarget.hidden = false;
    }

    closeReasonPanel() {
        this.reasonPanelTarget.hidden = true;
        this._activeCard = null;
    }

    applyReason() {
        if (!this._activeCard) return;
        this._activeCard.dataset.reason = this.reasonSelectTarget.value;
        this._activeCard.dataset.notes  = this.reasonNotesTarget.value;

        // Mettre à jour le badge visuel
        const badge = this._activeCard.querySelector('.callup-card__badge--reason');
        const label = this.reasonsValue[this.reasonSelectTarget.value] || '';
        if (badge) {
            badge.textContent = label;
            badge.hidden = !label;
        } else if (label) {
            const pos = this._activeCard.querySelector('.callup-card__pos');
            if (pos) {
                const b = document.createElement('span');
                b.className = 'callup-card__badge callup-card__badge--reason';
                b.textContent = label;
                pos.appendChild(b);
            }
        }
        this._scheduleSave();
    }

    // ─── Compteurs ───────────────────────────────────────────────

    _updateCounters() {
        const counts = { starter: 0, substitute: 0, not_called: 0, absent: 0 };
        this.cardTargets.forEach(c => { counts[c.dataset.role] = (counts[c.dataset.role] || 0) + 1; });
        Object.entries(counts).forEach(([role, cnt]) => {
            const el = document.getElementById('num-' + role);
            if (el) el.textContent = cnt;
        });
    }

    // ─── Save auto ───────────────────────────────────────────────

    _scheduleSave() {
        clearTimeout(this.saveTimer);
        this._setStatus('Modifié…');
        this.saveTimer = setTimeout(() => this._save(), 800);
    }

    async _save() {
        this._setStatus('Enregistrement…');
        const players = this.cardTargets.map(card => ({
            playerId:     Number(card.dataset.playerId),
            role:         card.dataset.role,
            reason:       card.dataset.reason || null,
            notes:        card.dataset.notes  || null,
            jerseyNumber: card.dataset.jersey ? Number(card.dataset.jersey) : null,
        }));

        try {
            const res = await fetch(this.saveUrlValue, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ players }),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            this._setStatus('✓ Sauvegardé');
            setTimeout(() => this._setStatus(''), 1500);
        } catch (e) {
            this._setStatus('⚠ ' + e.message);
        }
    }

    _setStatus(text) {
        const el = document.getElementById('callupSaveIndicator');
        if (el) el.textContent = text;
    }
}
