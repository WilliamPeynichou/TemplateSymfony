import { Controller } from '@hotwired/stimulus';

/**
 * Édition des attributs FM d'un joueur — sliders + autosave.
 */
export default class extends Controller {
    static targets = [
        'attr', 'condition', 'conditionValue', 'potential', 'potentialValue',
        'rating', 'status', 'editor', 'editorTitle', 'editorRange', 'editorValue',
    ];
    static values = { saveUrl: String };

    pendingAttr = null;
    pendingElement = null;
    dirty = false;
    saveTimer = null;

    onCondition(event) {
        this.conditionValueTarget.textContent = event.target.value;
        this.scheduleSave();
    }

    onPotential(event) {
        this.potentialValueTarget.textContent = event.target.value;
        this.scheduleSave();
    }

    onMorale(event) {
        const v = event.currentTarget.dataset.value;
        this.element.querySelectorAll('.fm-morale-picker .fm-morale').forEach(b => b.classList.toggle('is-active', b.dataset.value === v));
        this.element.dataset.morale = v;
        this.scheduleSave();
    }

    openEditor(event) {
        const wrap = event.currentTarget;
        const attr = wrap.dataset.attr;
        const label = wrap.dataset.label || attr;
        const valueEl = wrap.querySelector('[data-player-card-target="attr"]');
        const current = Number(valueEl.textContent || 10);

        this.pendingAttr = attr;
        this.pendingElement = valueEl;

        this.editorTitleTarget.textContent = label;
        this.editorRangeTarget.value = current;
        this.editorValueTarget.textContent = current;
        this.editorTarget.hidden = false;
    }

    onEditorChange(event) {
        const v = Number(event.target.value);
        this.editorValueTarget.textContent = v;
        if (this.pendingElement) {
            this.pendingElement.textContent = v;
            this.pendingElement.classList.remove('fm-attr__value--top','fm-attr__value--mid','fm-attr__value--low');
            this.pendingElement.classList.add('fm-attr__value--' + (v >= 15 ? 'top' : (v >= 10 ? 'mid' : 'low')));
        }
        this.scheduleSave();
    }

    closeEditor() {
        this.editorTarget.hidden = true;
        this.pendingAttr = null;
        this.pendingElement = null;
    }

    scheduleSave() {
        this.dirty = true;
        this.setStatus('Modifications en attente…');
        clearTimeout(this.saveTimer);
        this.saveTimer = setTimeout(() => this.save(), 500);
    }

    async save() {
        if (!this.dirty) return;
        this.setStatus('Enregistrement…');

        const payload = {};
        this.attrTargets.forEach(el => {
            payload[el.dataset.attr] = Number(el.textContent || 10);
        });
        payload.condition = Number(this.conditionTarget.value);
        payload.potentialAbility = Number(this.potentialTarget.value);
        const morale = this.element.dataset.morale;
        if (morale) payload.morale = morale;

        try {
            const res = await fetch(this.saveUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' },
                body: JSON.stringify(payload),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            if (this.hasRatingTarget && typeof data.currentAbility === 'number') {
                this.ratingTarget.textContent = data.currentAbility;
                this.ratingTarget.classList.remove('fm-rating--top','fm-rating--mid','fm-rating--low');
                this.ratingTarget.classList.add('fm-rating--' + (data.currentAbility >= 75 ? 'top' : (data.currentAbility >= 55 ? 'mid' : 'low')));
            }
            this.dirty = false;
            this.setStatus('✓ Enregistré');
            setTimeout(() => this.setStatus(''), 1500);
        } catch (err) {
            this.setStatus('⚠ Erreur : ' + err.message);
        }
    }

    setStatus(text) {
        if (this.hasStatusTarget) this.statusTarget.textContent = text;
    }
}
