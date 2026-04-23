import { Controller } from '@hotwired/stimulus';

/**
 * Éditeur de stratégie tactique FM-style :
 * - sélection d'un slot sur le terrain
 * - modification rôle / devoir / joueur / instructions
 * - changement de formation (recharge les slots)
 * - auto-compose à partir des suggestions serveur
 * - autosave
 */
export default class extends Controller {
    static targets = [
        'name', 'description', 'formation',
        'inPossessionNotes', 'outOfPossessionNotes', 'transitionNotes', 'setPieceNotes',
        'pitch', 'slot', 'saveIndicator',
        'inspector', 'inspectorEmpty', 'inspectorLabel', 'inspectorPlayer',
        'inspectorRole', 'inspectorInstructions', 'dutyBtn',
        'suggestion', 'suggestionName', 'suggestionScore',
    ];
    static values = { saveUrl: String, applyUrl: String, roles: Object };

    selectedSlot = null;
    strategyState = {
        mentality:'balanced', pressingIntensity:'medium', defensiveLine:'standard',
        buildUpStyle:'mixed', width:'standard', tempo:'standard', attackingFocus:'balanced',
    };
    saveTimer = null;
    dirty = false;

    connect() {
        this.element.querySelectorAll('[data-field]').forEach(btn => {
            if (btn.classList.contains('fm-chip--active')) {
                this.strategyState[btn.dataset.field] = btn.dataset.value;
            }
        });
    }

    // ─── Chips & inputs ───────────────────────────────────
    pickChip(event) {
        const btn = event.currentTarget;
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

    // ─── Formation change (reloads slots via server) ──────
    async changeFormation(event) {
        const newKey = event.target.value;
        if (!confirm(`Changer la formation pour ${newKey} ? Les slots seront réinitialisés.`)) {
            event.target.value = event.target.querySelector('[selected]').value;
            return;
        }
        this.setStatus('Changement de formation…');
        try {
            const res = await fetch(this.applyUrlValue, {
                method: 'POST',
                headers: { 'Content-Type':'application/json' },
                body: JSON.stringify({ formation: newKey }),
            });
            const data = await res.json();
            if (data.redirect) window.location = data.redirect;
        } catch (e) { this.setStatus('Erreur: ' + e.message); }
    }

    // ─── Slot selection ───────────────────────────────────
    selectSlot(event) {
        const slot = event.currentTarget;
        this.slotTargets.forEach(s => s.classList.remove('fm-pitch-slot--selected'));
        slot.classList.add('fm-pitch-slot--selected');
        this.selectedSlot = slot;

        this.inspectorEmptyTarget.hidden = true;
        this.inspectorTarget.hidden = false;

        this.inspectorLabelTarget.textContent = `${slot.dataset.label} — ${slot.dataset.positionCode}`;

        // Populate role select with roles in the group
        const group = slot.dataset.positionGroup;
        const currentRole = slot.dataset.role;
        this.inspectorRoleTarget.innerHTML = '';
        Object.entries(this.rolesValue)
            .filter(([, r]) => r.group === group)
            .forEach(([key, r]) => {
                const opt = document.createElement('option');
                opt.value = key;
                opt.textContent = r.label;
                if (key === currentRole) opt.selected = true;
                this.inspectorRoleTarget.appendChild(opt);
            });

        // Populate player select
        this.inspectorPlayerTarget.value = slot.dataset.playerId || '';

        // Highlight preferred players for this group
        Array.from(this.inspectorPlayerTarget.options).forEach(opt => {
            if (!opt.value) return;
            const match = opt.dataset.positionGroup === group;
            opt.style.fontWeight = match ? '600' : 'normal';
            opt.style.color = match ? '' : 'var(--on-surface-variant)';
        });

        // Duty buttons state
        this.dutyBtnTargets.forEach(btn => {
            btn.classList.toggle('fm-chip--active', btn.dataset.value === slot.dataset.duty);
        });

        // Instructions
        this.inspectorInstructionsTarget.value = slot.dataset.instructions || '';

        // Suggestion badge
        if (slot.dataset.suggestedId && slot.dataset.suggestedScore) {
            this.suggestionTarget.hidden = false;
            this.suggestionNameTarget.textContent = slot.dataset.suggestedName;
            this.suggestionScoreTarget.textContent = slot.dataset.suggestedScore;
            this.suggestionTarget.dataset.suggestedId = slot.dataset.suggestedId;
        } else {
            this.suggestionTarget.hidden = true;
        }
    }

    closeInspector() {
        if (this.selectedSlot) this.selectedSlot.classList.remove('fm-pitch-slot--selected');
        this.selectedSlot = null;
        this.inspectorTarget.hidden = true;
        this.inspectorEmptyTarget.hidden = false;
    }

    applySuggestion() {
        const id = this.suggestionTarget.dataset.suggestedId;
        if (!id || !this.selectedSlot) return;
        this.inspectorPlayerTarget.value = id;
        this.updatePlayer();
    }

    updatePlayer() {
        if (!this.selectedSlot) return;
        const val = this.inspectorPlayerTarget.value;
        this.selectedSlot.dataset.playerId = val;
        const text = val
            ? (this.inspectorPlayerTarget.options[this.inspectorPlayerTarget.selectedIndex].textContent || '').replace(/^#\d+\s+/, '').split(' (')[0]
            : '— libre —';
        const num = val ? (this.inspectorPlayerTarget.options[this.inspectorPlayerTarget.selectedIndex].textContent.match(/#(\d+)/) || [])[1] : null;
        const tokenNum = this.selectedSlot.querySelector('.fm-pitch-slot__num');
        const tokenName = this.selectedSlot.querySelector('.fm-pitch-slot__name');
        if (tokenNum) tokenNum.textContent = num || this.selectedSlot.dataset.label;
        if (tokenName) tokenName.innerHTML = val ? text.split(' ').slice(-1)[0] : '<em>— libre —</em>';
        this.markDirty();
    }

    updateRole() {
        if (!this.selectedSlot) return;
        const v = this.inspectorRoleTarget.value;
        this.selectedSlot.dataset.role = v;
        const roleEl = this.selectedSlot.querySelector('.fm-pitch-slot__role');
        if (roleEl) {
            const label = this.rolesValue[v]?.label || v;
            const duty = this.selectedSlot.dataset.duty;
            const dutyLabel = duty === 'defend' ? 'Défensif' : (duty === 'attack' ? 'Offensif' : 'Soutien');
            roleEl.innerHTML = `${label} · <span class="fm-duty fm-duty--${duty}">${dutyLabel}</span>`;
        }
        this.markDirty();
    }

    updateDuty(event) {
        if (!this.selectedSlot) return;
        const v = event.currentTarget.dataset.value;
        this.selectedSlot.dataset.duty = v;
        this.dutyBtnTargets.forEach(b => b.classList.toggle('fm-chip--active', b.dataset.value === v));
        this.updateRole(); // refresh role display with new duty label
    }

    updateInstructions() {
        if (!this.selectedSlot) return;
        this.selectedSlot.dataset.instructions = this.inspectorInstructionsTarget.value;
        this.markDirty();
    }

    // ─── Auto-compose ────────────────────────────────────
    autoAssign() {
        const used = new Set();
        this.slotTargets.forEach(slot => {
            const suggestedId = slot.dataset.suggestedId;
            if (suggestedId && !used.has(suggestedId)) {
                used.add(suggestedId);
                slot.dataset.playerId = suggestedId;
                const tokenNum = slot.querySelector('.fm-pitch-slot__num');
                const tokenName = slot.querySelector('.fm-pitch-slot__name');
                if (tokenName) tokenName.textContent = slot.dataset.suggestedName.split(' ').slice(-1)[0];
                // Try to find player option to get the number
                const select = this.inspectorPlayerTarget;
                const opt = Array.from(select.options).find(o => o.value === suggestedId);
                if (opt && tokenNum) {
                    const m = opt.textContent.match(/#(\d+)/);
                    tokenNum.textContent = m ? m[1] : slot.dataset.label;
                }
            }
        });
        this.markDirty();
        this.save();
    }

    // ─── Save ────────────────────────────────────────────
    async save() {
        this.setStatus('Enregistrement…');
        const payload = {
            name: this.nameTarget.value,
            description: this.descriptionTarget.value,
            inPossessionNotes: this.inPossessionNotesTarget.value,
            outOfPossessionNotes: this.outOfPossessionNotesTarget.value,
            transitionNotes: this.transitionNotesTarget.value,
            setPieceNotes: this.setPieceNotesTarget.value,
            ...this.strategyState,
            slots: this.slotTargets.map(slot => ({
                slotIndex: Number(slot.dataset.slotIndex),
                positionCode: slot.dataset.positionCode,
                label: slot.dataset.label,
                role: slot.dataset.role,
                duty: slot.dataset.duty,
                posX: Number(slot.style.left.replace('%', '')),
                posY: Number(slot.style.top.replace('%', '')),
                playerId: slot.dataset.playerId || null,
                individualInstructions: slot.dataset.instructions || null,
            })),
        };

        try {
            const res = await fetch(this.saveUrlValue, {
                method: 'POST',
                headers: { 'Content-Type':'application/json' },
                body: JSON.stringify(payload),
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
}
