import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'panel',
        'benchPanel', 'benchTab', 'benchCount',
        'playerPanel', 'playerTab', 'playerTabLabel',
        'header', 'body', 'footer',
        'name', 'avatar', 'number', 'position',
        'age', 'foot', 'height', 'weight', 'note',
    ];

    currentPlayerId = null;

    connect() {
        document.addEventListener('click', this.handleOutsideClick.bind(this));
        this.showBench();
    }

    disconnect() {
        document.removeEventListener('click', this.handleOutsideClick.bind(this));
    }

    handleOutsideClick(e) {
        if (
            this.currentPlayerId !== null &&
            !this.panelTarget.contains(e.target) &&
            !e.target.closest('.tactical-player-token')
        ) {
            this.close();
        }
    }

    showBench() {
        this.benchPanelTarget.style.display = '';
        this.playerPanelTarget.style.display = 'none';
        this.benchTabTarget.classList.add('tactical-panel-tab--active');
        this.playerTabTarget.style.display = 'none';

        document.querySelectorAll('.tactical-player-token--selected').forEach(el => {
            el.classList.remove('tactical-player-token--selected');
        });
        this.currentPlayerId = null;
    }

    showPlayerPanel() {
        this.benchPanelTarget.style.display = 'none';
        this.playerPanelTarget.style.display = '';
        this.benchTabTarget.classList.remove('tactical-panel-tab--active');
        this.playerTabTarget.style.display = '';
    }

    open({ detail: { playerId, data, note } }) {
        this.currentPlayerId = playerId;

        this.nameTarget.textContent     = data.firstName + ' ' + data.lastName;
        this.numberTarget.textContent   = '#' + data.number;
        this.positionTarget.textContent = data.position || '—';
        this.ageTarget.textContent      = data.age    ? data.age + ' ans' : '—';
        this.heightTarget.textContent   = data.height ? data.height + ' cm' : '—';
        this.weightTarget.textContent   = data.weight ? data.weight + ' kg' : '—';
        this.noteTarget.value           = note || '';
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

    close() {
        this.showBench();
    }

    filterBench({ target }) {
        const filter = target.dataset.filter;
        this.element.querySelectorAll('.tactical-pos-filter').forEach(btn => {
            btn.classList.toggle('tactical-pos-filter--active', btn.dataset.filter === filter);
        });
        this.element.querySelectorAll('.tactical-bench-card').forEach(card => {
            const group = card.dataset.posGroup;
            card.style.display = (filter === 'all' || group === filter) ? '' : 'none';
        });
    }

    saveNote() {
        if (this.currentPlayerId === null) return;
        this.element.dispatchEvent(new CustomEvent('plan:updateNote', {
            bubbles: true,
            detail:  { playerId: this.currentPlayerId, note: this.noteTarget.value },
        }));
    }

    removePlayer() {
        if (this.currentPlayerId === null) return;
        this.element.dispatchEvent(new CustomEvent('plan:removePlayer', {
            bubbles: true,
            detail:  { playerId: this.currentPlayerId },
        }));
        this.close();
    }
}
