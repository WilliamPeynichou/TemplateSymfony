import { Controller } from '@hotwired/stimulus';

/**
 * Éditeur de plan tactique unifié (FM-style formation + libre).
 *
 * Mode formation : slots fixes, rôle/duty, auto-compose, chips tactiques.
 * Mode libre : slots draggables, placement libre, note par joueur, add/remove slot.
 */
export default class extends Controller {
    static targets = [
        'name', 'description', 'formation',
        'inPossessionNotes', 'outOfPossessionNotes', 'transitionNotes', 'setPieceNotes',
        'pitch', 'slot', 'saveIndicator',
        'inspector', 'inspectorEmpty', 'inspectorLabel', 'inspectorPlayer',
        'inspectorRole', 'inspectorInstructions', 'dutyBtn',
        'suggestion', 'suggestionName', 'suggestionScore',
        'removeSlotBtn', 'rosterPlayer', 'selectedPlayerCard', 'rosterFilter',
    ];
    static values = { saveUrl: String, applyUrl: String, roles: Object, mode: String };

    selectedSlot = null;
    strategyState = {
        mentality: 'balanced', pressingIntensity: 'medium', defensiveLine: 'standard',
        buildUpStyle: 'mixed', width: 'standard', tempo: 'standard', attackingFocus: 'balanced',
    };
    saveTimer = null;
    dirty = false;
    _dragging = null;
    _dragSource = null;
    _dragPlayerId = null;
    _freeSlotCounter = 1000; // évite les collisions d'index lors de créations dynamiques

    connect() {
        this.element.querySelectorAll('[data-field]').forEach(btn => {
            if (btn.classList.contains('fm-chip--active')) {
                this.strategyState[btn.dataset.field] = btn.dataset.value;
            }
        });

        if (this.isFreeMode()) {
            this.initFreeModeDrag();
        }

        if (this.isFreeMode()) {
            this.updateRosterAvailability();
        }
    }

    isFreeMode() {
        return this.modeValue === 'free';
    }

    // ─── Mode libre : drag & drop ─────────────────────────────

    initFreeModeDrag() {
        this.slotTargets.forEach(slot => this.makeSlotDraggable(slot));
        if (this.rosterPlayerTargets.length > 0) {
            this.rosterPlayerTargets.forEach(player => this.makeRosterPlayerDraggable(player));
        }

        const pitch = this.pitchTarget;
        pitch.addEventListener('dragover', e => e.preventDefault());
        pitch.addEventListener('drop', e => this.handlePitchDrop(e));
    }

    makeSlotDraggable(slot) {
        slot.draggable = true;
        slot.addEventListener('dragstart', e => {
            this._dragging = slot;
            this._dragSource = 'pitch';
            this._dragPlayerId = slot.dataset.playerId || '';
            slot.classList.add('fm-pitch-slot--dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        slot.addEventListener('dragend', () => {
            slot.classList.remove('fm-pitch-slot--dragging');
            this._dragging = null;
            this._dragSource = null;
            this._dragPlayerId = null;
        });
    }

    makeRosterPlayerDraggable(player) {
        player.addEventListener('dragstart', (e) => {
            if (player.classList.contains('fm-roster__item--disabled')) {
                e.preventDefault();
                return;
            }

            this._dragSource = 'roster';
            this._dragPlayerId = player.dataset.playerId;
            player.classList.add('fm-roster__item--dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        player.addEventListener('dragend', () => {
            player.classList.remove('fm-roster__item--dragging');
            this._dragSource = null;
            this._dragPlayerId = null;
        });
    }

    handlePitchDrop(e) {
        if (!this._dragging && this._dragSource !== 'roster') return;
        const rect = this.pitchTarget.getBoundingClientRect();
        const posX = Math.max(3, Math.min(97, ((e.clientX - rect.left)  / rect.width)  * 100));
        const posY = Math.max(3, Math.min(97, ((e.clientY - rect.top)   / rect.height) * 100));

        if (this._dragSource === 'pitch' && this._dragging) {
            this._dragging.style.left = posX + '%';
            this._dragging.style.top  = posY + '%';
        }

        if (this._dragSource === 'roster' && this._dragPlayerId) {
            const existingSlot = this.slotTargets.find((slot) => slot.dataset.playerId === this._dragPlayerId);
            if (existingSlot) {
                existingSlot.style.left = posX + '%';
                existingSlot.style.top = posY + '%';
                this.selectSlot({ currentTarget: existingSlot });
            } else {
                this.addFreeSlotFromRoster(this._dragPlayerId, posX, posY);
            }
        }

        this._dragSource = null;
        this._dragPlayerId = null;
        this.markDirty();
    }

    addFreeSlotFromRoster(playerId, posX, posY) {
        const rosterBtn = this.rosterPlayerTargets.find((button) => button.dataset.playerId === String(playerId));
        if (!rosterBtn) return;

        const idx  = this._freeSlotCounter++;
        const slot = document.createElement('div');
        slot.className  = 'fm-pitch-slot fm-pitch-slot--mid';
        slot.dataset.strategyTarget = 'slot';
        slot.dataset.slotIndex      = idx;
        slot.dataset.positionCode   = rosterBtn.dataset.playerPosition || 'CM';
        slot.dataset.positionGroup  = rosterBtn.dataset.playerGroup || 'MID';
        slot.dataset.role           = 'box_to_box';
        slot.dataset.duty           = 'support';
        slot.dataset.label          = rosterBtn.dataset.playerLastName || '?';
        slot.dataset.playerId       = playerId;
        slot.dataset.instructions   = '';
        slot.dataset.suggestedId    = '';
        slot.dataset.suggestedName  = '';
        slot.dataset.suggestedScore = '';
        slot.style.left = `${posX}%`;
        slot.style.top  = `${posY}%`;
        slot.innerHTML  = `
            <div class="fm-pitch-slot__token"><span class="fm-pitch-slot__num">${rosterBtn.dataset.playerNumber || '?'}</span></div>
            <div class="fm-pitch-slot__name">${rosterBtn.dataset.playerLastName || ''}</div>
        `;
        slot.setAttribute('data-action', 'click->strategy#selectSlot');

        this.pitchTarget.appendChild(slot);
        this.makeSlotDraggable(slot);

        this.selectSlot({ currentTarget: slot });
    }

    removeSelectedSlot() {
        if (!this.selectedSlot) return;
        this.selectedSlot.remove();
        this.selectedSlot = null;
        this.inspectorTarget.hidden      = true;
        this.inspectorEmptyTarget.hidden = false;
        if (this.hasRemoveSlotBtnTarget) this.removeSlotBtnTarget.style.display = 'none';
        this.updateRosterAvailability();
        this.markDirty();
        this.save();
    }

    // ─── Chips & inputs (mode formation) ──────────────────────

    pickChip(event) {
        const btn   = event.currentTarget;
        const field = btn.dataset.field;
        const value = btn.dataset.value;
        this.strategyState[field] = value;
        this.element.querySelectorAll(`[data-field="${field}"]`).forEach(b => {
            b.classList.toggle('fm-chip--active', b.dataset.value === value);
        });
        this.markDirty();
    }

    markDirty() {
        this.dirty = true;
        this.setStatus('Modifié…');
        clearTimeout(this.saveTimer);
        this.saveTimer = setTimeout(() => this.save(), 800);
    }

    // ─── Changement formation (mode formation) ────────────────

    async changeFormation(event) {
        const newKey = event.target.value;
        if (!confirm(`Changer la formation pour ${newKey} ? Les slots seront réinitialisés.`)) {
            event.target.value = event.target.querySelector('[selected]').value;
            return;
        }
        this.setStatus('Changement de formation…');
        try {
            const res  = await fetch(this.applyUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ formation: newKey }),
            });
            const data = await res.json();
            if (data.redirect) window.location = data.redirect;
        } catch (e) { this.setStatus('Erreur: ' + e.message); }
    }

    // ─── Sélection de slot ────────────────────────────────────

    selectSlot(event) {
        const slot = event.currentTarget;
        this.slotTargets.forEach(s => s.classList.remove('fm-pitch-slot--selected'));
        slot.classList.add('fm-pitch-slot--selected');
        this.selectedSlot = slot;

        this.inspectorEmptyTarget.hidden = true;
        this.inspectorTarget.hidden      = false;

        this.inspectorLabelTarget.textContent =
            this.isFreeMode()
                ? (slot.querySelector('.fm-pitch-slot__name')?.textContent?.trim() || 'Joueur')
                : `${slot.dataset.label} — ${slot.dataset.positionCode}`;

        if (!this.isFreeMode()) {
            const group       = slot.dataset.positionGroup;
            const currentRole = slot.dataset.role;
            this.inspectorRoleTarget.innerHTML = '';
            Object.entries(this.rolesValue)
                .filter(([, r]) => r.group === group)
                .forEach(([key, r]) => {
                    const opt = document.createElement('option');
                    opt.value       = key;
                    opt.textContent = r.label;
                    if (key === currentRole) opt.selected = true;
                    this.inspectorRoleTarget.appendChild(opt);
                });

            this.dutyBtnTargets.forEach(btn => {
                btn.classList.toggle('fm-chip--active', btn.dataset.value === slot.dataset.duty);
            });

            if (slot.dataset.suggestedId && slot.dataset.suggestedScore) {
                this.suggestionTarget.hidden = false;
                this.suggestionNameTarget.textContent  = slot.dataset.suggestedName;
                this.suggestionScoreTarget.textContent = slot.dataset.suggestedScore;
                this.suggestionTarget.dataset.suggestedId = slot.dataset.suggestedId;
            } else {
                this.suggestionTarget.hidden = true;
            }
        }

        this.inspectorPlayerTarget.value         = slot.dataset.playerId || '';
        this.inspectorInstructionsTarget.value   = slot.dataset.instructions || '';
        if (this.isFreeMode()) {
            this.renderSelectedPlayerCard();
            this.highlightRosterPlayer(slot.dataset.playerId || '');
            this.updateRosterAvailability();
        }

        // Afficher le bouton "Retirer le slot" en mode libre
        if (this.isFreeMode() && this.hasRemoveSlotBtnTarget) {
            this.removeSlotBtnTarget.style.display = '';
        }
    }

    closeInspector() {
        if (this.selectedSlot) this.selectedSlot.classList.remove('fm-pitch-slot--selected');
        this.selectedSlot = null;
        this.inspectorTarget.hidden      = true;
        this.inspectorEmptyTarget.hidden = false;
        if (this.isFreeMode() && this.hasRemoveSlotBtnTarget) {
            this.removeSlotBtnTarget.style.display = 'none';
        }
    }

    applySuggestion() {
        const id = this.suggestionTarget.dataset.suggestedId;
        if (!id || !this.selectedSlot) return;
        this.inspectorPlayerTarget.value = id;
        this.updatePlayer();
    }

    updatePlayer() {
        if (!this.selectedSlot) return;
        const val  = this.inspectorPlayerTarget.value;
        this.selectedSlot.dataset.playerId = val;
        const rosterBtn = this.hasRosterPlayerTarget
            ? this.rosterPlayerTargets.find((btn) => btn.dataset.playerId === String(val))
            : null;
        const option = !this.isFreeMode()
            ? this.inspectorPlayerTarget.options[this.inspectorPlayerTarget.selectedIndex]
            : null;
        const text = val
            ? (rosterBtn ? rosterBtn.dataset.playerName : (option?.textContent || '').replace(/^#\d+\s+/, '').split(' (')[0])
            : '— libre —';
        const num  = val
            ? (rosterBtn ? rosterBtn.dataset.playerNumber : ((option?.textContent.match(/#(\d+)/) || [])[1] || null))
            : null;
        const lastName = val
            ? (rosterBtn ? rosterBtn.dataset.playerLastName : text.split(' ').slice(-1)[0])
            : null;

        const tokenNum  = this.selectedSlot.querySelector('.fm-pitch-slot__num');
        const tokenName = this.selectedSlot.querySelector('.fm-pitch-slot__name');
        if (tokenNum)  tokenNum.textContent  = num || this.selectedSlot.dataset.label;
        if (tokenName) tokenName.innerHTML   = val ? lastName : (this.isFreeMode() ? '<em>?</em>' : '<em>— libre —</em>');

        if (this.isFreeMode() && val) {
            this.inspectorLabelTarget.textContent = text;
        }

        if (this.isFreeMode()) {
            this.renderSelectedPlayerCard();
            this.highlightRosterPlayer(val);
            this.updateRosterAvailability();
        }
        this.markDirty();
    }

    pickRosterPlayer(event) {
        if (!this.selectedSlot || !this.isFreeMode()) return;

        const button = event.currentTarget;
        this.inspectorPlayerTarget.value = button.dataset.playerId;
        this.updatePlayer();
    }

    clearPlayer() {
        if (!this.selectedSlot || !this.isFreeMode()) return;

        this.inspectorPlayerTarget.value = '';
        this.updatePlayer();
    }

    filterRoster(event) {
        if (!this.isFreeMode()) return;

        const filter = event.currentTarget.dataset.filter || 'all';
        this.rosterFilterTargets.forEach((button) => {
            button.classList.toggle('fm-roster__filter--active', button.dataset.filter === filter);
        });

        this.rosterPlayerTargets.forEach((button) => {
            const group = button.dataset.filterGroup || 'all';
            button.style.display = filter === 'all' || group === filter ? '' : 'none';
        });
    }

    updateRole() {
        if (!this.selectedSlot || this.isFreeMode()) return;
        const v       = this.inspectorRoleTarget.value;
        this.selectedSlot.dataset.role = v;
        const roleEl  = this.selectedSlot.querySelector('.fm-pitch-slot__role');
        if (roleEl) {
            const label     = this.rolesValue[v]?.label || v;
            const duty      = this.selectedSlot.dataset.duty;
            const dutyLabel = duty === 'defend' ? 'Défensif' : (duty === 'attack' ? 'Offensif' : 'Soutien');
            roleEl.innerHTML = `${label} · <span class="fm-duty fm-duty--${duty}">${dutyLabel}</span>`;
        }
        this.markDirty();
    }

    updateDuty(event) {
        if (!this.selectedSlot || this.isFreeMode()) return;
        const v = event.currentTarget.dataset.value;
        this.selectedSlot.dataset.duty = v;
        this.dutyBtnTargets.forEach(b => b.classList.toggle('fm-chip--active', b.dataset.value === v));
        this.updateRole();
    }

    updateInstructions() {
        if (!this.selectedSlot) return;
        this.selectedSlot.dataset.instructions = this.inspectorInstructionsTarget.value;
        this.markDirty();
    }

    // ─── Auto-compose (mode formation) ───────────────────────

    autoAssign() {
        const used = new Set();
        this.slotTargets.forEach(slot => {
            const suggestedId = slot.dataset.suggestedId;
            if (suggestedId && !used.has(suggestedId)) {
                used.add(suggestedId);
                slot.dataset.playerId = suggestedId;
                const tokenName = slot.querySelector('.fm-pitch-slot__name');
                if (tokenName) tokenName.textContent = slot.dataset.suggestedName.split(' ').slice(-1)[0];
                const select = this.inspectorPlayerTarget;
                const opt    = Array.from(select.options).find(o => o.value === suggestedId);
                const tokenNum = slot.querySelector('.fm-pitch-slot__num');
                if (opt && tokenNum) {
                    const m = opt.textContent.match(/#(\d+)/);
                    tokenNum.textContent = m ? m[1] : slot.dataset.label;
                }
            }
        });
        this.markDirty();
        this.save();
    }

    // ─── Save ─────────────────────────────────────────────────

    async save() {
        this.setStatus('Enregistrement…');
        const payload = {
            name:                this.nameTarget.value,
            description:         this.descriptionTarget.value,
            inPossessionNotes:   this.inPossessionNotesTarget.value,
            outOfPossessionNotes:this.outOfPossessionNotesTarget.value,
            transitionNotes:     this.transitionNotesTarget.value,
            setPieceNotes:       this.setPieceNotesTarget.value,
            ...this.strategyState,
            slots: this.slotTargets.map(slot => ({
                slotIndex:              Number(slot.dataset.slotIndex),
                positionCode:           slot.dataset.positionCode   || 'CM',
                label:                  slot.dataset.label          || '?',
                role:                   slot.dataset.role           || 'box_to_box',
                duty:                   slot.dataset.duty           || 'support',
                posX:                   Number(slot.style.left.replace('%', '')),
                posY:                   Number(slot.style.top.replace('%', '')),
                playerId:               slot.dataset.playerId       || null,
                individualInstructions: slot.dataset.instructions   || null,
            })),
        };

        try {
            const res = await fetch(this.saveUrlValue, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(payload),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            this.dirty = false;
            this.setStatus('✓ Sauvegardé');
            setTimeout(() => this.setStatus(''), 1500);
        } catch (e) { this.setStatus('⚠ ' + e.message); }
    }

    setStatus(t) {
        if (this.hasSaveIndicatorTarget) this.saveIndicatorTarget.textContent = t;
    }

    updateRosterAvailability() {
        if (!this.isFreeMode() || !this.hasRosterPlayerTarget) return;

        const assignedIds = new Set(
            this.slotTargets
                .map((slot) => slot.dataset.playerId)
                .filter((playerId) => playerId)
        );

        this.rosterPlayerTargets.forEach((button) => {
            const isAssigned = assignedIds.has(button.dataset.playerId);
            button.classList.toggle('fm-roster__item--disabled', isAssigned);
        });
    }

    renderSelectedPlayerCard() {
        if (!this.hasSelectedPlayerCardTarget || this.rosterPlayerTargets.length === 0) return;

        const playerId = this.inspectorPlayerTarget.value;
        const button = this.rosterPlayerTargets.find((item) => item.dataset.playerId === String(playerId));

        if (!button) {
            this.selectedPlayerCardTarget.innerHTML = '<span class="fm-selected-player__empty">Aucun joueur affecté</span>';
            return;
        }

        this.selectedPlayerCardTarget.innerHTML = `
            <div class="fm-selected-player__main">
                <span class="fm-selected-player__number">#${button.dataset.playerNumber}</span>
                <span class="fm-selected-player__name">${button.dataset.playerName}</span>
            </div>
            <span class="fm-selected-player__badge fm-selected-player__badge--${button.dataset.playerGroup.toLowerCase()}">${button.dataset.playerPosition}</span>
        `;
    }

    highlightRosterPlayer(playerId) {
        if (this.rosterPlayerTargets.length === 0) return;
        this.rosterPlayerTargets.forEach((button) => {
            button.classList.toggle('fm-roster__item--active', playerId !== '' && button.dataset.playerId === String(playerId));
        });
    }
}
