import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'panel',
        'benchPanel', 'benchTab', 'benchCount',
        'playerPanel', 'playerTab', 'playerTabLabel',
        'header', 'body', 'footer',
        'name', 'avatar', 'number', 'position',
        'age', 'foot', 'height', 'weight', 'instructions',
    ];

    currentPlayerId = null;

    connect() {
        document.addEventListener('click', this.handleOutsideClick.bind(this));
        // Show bench panel by default
        this.showBench();
    }

    disconnect() {
        document.removeEventListener('click', this.handleOutsideClick.bind(this));
    }

    handleOutsideClick(e) {
        if (
            this.currentPlayerId !== null &&
            !this.panelTarget.contains(e.target) &&
            !e.target.closest('.player-token')
        ) {
            this.close();
        }
    }

    // ─── Tab: show bench ───────────────────────────────────────
    showBench() {
        this.benchPanelTarget.style.display = '';
        this.playerPanelTarget.style.display = 'none';

        this.benchTabTarget.classList.add('panel-tab--active');
        this.playerTabTarget.style.display = 'none';

        // Deselect player tokens
        document.querySelectorAll('.player-token--selected').forEach(el => {
            el.classList.remove('player-token--selected');
        });
        this.currentPlayerId = null;
    }

    // ─── Tab: show player fiche ────────────────────────────────
    showPlayerPanel() {
        this.benchPanelTarget.style.display = 'none';
        this.playerPanelTarget.style.display = '';

        this.benchTabTarget.classList.remove('panel-tab--active');
        this.playerTabTarget.style.display = '';
    }

    // ─── Open: called by pitch via pitch:playerSelected event ──
    open({ detail: { playerId, data, instructions } }) {
        this.currentPlayerId = playerId;

        // Fill player data
        this.nameTarget.textContent     = data.firstName + ' ' + data.lastName;
        this.numberTarget.textContent   = '#' + data.number;
        this.positionTarget.textContent = data.position || '—';
        this.ageTarget.textContent      = data.age    ? data.age + ' ans' : '—';
        this.heightTarget.textContent   = data.height ? data.height + ' cm' : '—';
        this.weightTarget.textContent   = data.weight ? data.weight + ' kg' : '—';
        this.instructionsTarget.value   = instructions || '';
        this.playerTabLabelTarget.textContent = data.lastName;

        const foot = data.strongFoot;
        this.footTarget.textContent = foot === 'right' ? 'Droit' :
                                      foot === 'left'  ? 'Gauche' :
                                      foot === 'both'  ? 'D/G' : '—';

        if (data.photo) {
            this.avatarTarget.innerHTML = `<img src="/uploads/players/${data.photo}" alt="">`;
        } else {
            const initials = (data.firstName?.[0] ?? '') + (data.lastName?.[0] ?? '');
            this.avatarTarget.textContent = initials;
        }

        this.showPlayerPanel();
    }

    // ─── Close: return to bench ────────────────────────────────
    close() {
        this.showBench();
    }

    // ─── Filter bench by position group ───────────────────────
    filterBench({ target }) {
        const filter = target.dataset.filter;

        this.element.querySelectorAll('.pos-filter').forEach(btn => {
            btn.classList.toggle('pos-filter--active', btn.dataset.filter === filter);
            btn.classList.toggle('active', btn.dataset.filter === filter);
        });

        this.element.querySelectorAll('.bench-card').forEach(card => {
            const group = card.dataset.posGroup;
            card.style.display = (filter === 'all' || group === filter) ? '' : 'none';
        });
    }

    // ─── Save instructions ─────────────────────────────────────
    saveInstructions() {
        if (this.currentPlayerId === null) return;
        this.element.dispatchEvent(new CustomEvent('composition:updateInstructions', {
            bubbles: true,
            detail:  { playerId: this.currentPlayerId, instructions: this.instructionsTarget.value },
        }));
    }

    // ─── Remove player from pitch ──────────────────────────────
    removePlayer() {
        if (this.currentPlayerId === null) return;
        this.element.dispatchEvent(new CustomEvent('composition:removePlayer', {
            bubbles: true,
            detail:  { playerId: this.currentPlayerId },
        }));
        this.close();
    }

    // ─── Update bench count badge ──────────────────────────────
    updateBenchCount(count) {
        if (this.hasBenchCountTarget) {
            this.benchCountTarget.textContent = count;
        }
    }
}
