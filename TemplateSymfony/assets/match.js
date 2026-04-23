import './styles/match.css';

class MatchBoard {
    constructor(root) {
        this.root = root;
        this.wrapper = root.querySelector('[data-match-wrapper]');
        this.playersLayer = root.querySelector('[data-match-players]');
        this.selectedLabel = root.querySelector('[data-match-selected]');
        this.removeButton = root.querySelector('[data-match-remove]');

        this.positions = new Map();
        this.selectedPlayerId = null;
        this.dragSource = null;
        this.dragPlayerId = null;
    }

    connect() {
        if (!this.wrapper || !this.playersLayer) {
            return;
        }

        this.initDropZone();
        this.initBenchPlayers();
        this.initToolbar();
        this.initDeselect();
    }

    initDropZone() {
        this.wrapper.addEventListener('dragover', (event) => {
            event.preventDefault();
            this.wrapper.classList.add('tactical-drag-over');
        });

        this.wrapper.addEventListener('dragleave', (event) => {
            if (!this.wrapper.contains(event.relatedTarget)) {
                this.wrapper.classList.remove('tactical-drag-over');
            }
        });

        this.wrapper.addEventListener('drop', (event) => {
            event.preventDefault();
            this.wrapper.classList.remove('tactical-drag-over');

            if (this.dragPlayerId === null) {
                return;
            }

            const rect = this.wrapper.getBoundingClientRect();
            const posX = Math.max(3, Math.min(97, ((event.clientX - rect.left) / rect.width) * 100));
            const posY = Math.max(3, Math.min(97, ((event.clientY - rect.top) / rect.height) * 100));

            if (this.dragSource === 'bench') {
                this.addPlayerToPitch(this.dragPlayerId, posX, posY);
            } else if (this.dragSource === 'pitch') {
                this.movePlayerOnPitch(this.dragPlayerId, posX, posY);
            }
        });
    }

    initBenchPlayers() {
        this.root.querySelectorAll('.tactical-bench-card').forEach((element) => this.initBenchPlayerDrag(element));
    }

    initBenchPlayerDrag(element) {
        element.addEventListener('dragstart', (event) => {
            this.dragSource = 'bench';
            this.dragPlayerId = element.dataset.playerId;
            element.classList.add('tactical-bench-card--dragging');
            event.dataTransfer.effectAllowed = 'move';
        });

        element.addEventListener('dragend', () => {
            element.classList.remove('tactical-bench-card--dragging');
        });
    }

    initToolbar() {
        this.removeButton?.addEventListener('click', () => {
            if (this.selectedPlayerId !== null) {
                this.removePlayer(this.selectedPlayerId);
            }
        });
    }

    initDeselect() {
        this.wrapper.addEventListener('click', (event) => {
            if (!event.target.closest('.tactical-player-token')) {
                this.clearSelection();
            }
        });
    }

    addPlayerToPitch(playerId, posX, posY) {
        const benchElement = this.root.querySelector(`.tactical-bench-card[data-player-id="${playerId}"]`);
        if (!benchElement) {
            return;
        }

        const data = JSON.parse(benchElement.dataset.playerData);
        this.positions.set(playerId, { posX, posY, data });
        this.renderToken(playerId);
        benchElement.remove();
    }

    movePlayerOnPitch(playerId, posX, posY) {
        const entry = this.positions.get(playerId);
        if (!entry) {
            return;
        }

        entry.posX = posX;
        entry.posY = posY;

        const token = this.playersLayer.querySelector(`.tactical-player-token[data-player-id="${playerId}"]`);
        if (token) {
            token.style.left = posX + '%';
            token.style.top = posY + '%';
        }
    }

    renderToken(playerId) {
        const existing = this.playersLayer.querySelector(`.tactical-player-token[data-player-id="${playerId}"]`);
        if (existing) {
            existing.remove();
        }

        const entry = this.positions.get(playerId);
        if (!entry) {
            return;
        }

        const token = document.createElement('div');
        token.className = `tactical-player-token tactical-player-token--${entry.data.side}`;
        token.dataset.playerId = playerId;
        token.dataset.playerData = JSON.stringify(entry.data);
        token.draggable = true;
        token.style.left = entry.posX + '%';
        token.style.top = entry.posY + '%';
        token.innerHTML = `
            <div class="tactical-player-token__circle">${entry.data.number}</div>
            <div class="tactical-player-token__name">${entry.data.shortName}</div>
        `;

        this.bindTokenEvents(token, playerId);
        this.playersLayer.appendChild(token);

        if (this.selectedPlayerId === playerId) {
            token.classList.add('tactical-player-token--selected');
        }
    }

    bindTokenEvents(token, playerId) {
        token.addEventListener('dragstart', (event) => {
            this.dragSource = 'pitch';
            this.dragPlayerId = playerId;
            token.classList.add('tactical-player-token--dragging');
            event.dataTransfer.effectAllowed = 'move';
        });

        token.addEventListener('dragend', () => {
            token.classList.remove('tactical-player-token--dragging');
        });

        token.addEventListener('click', (event) => {
            event.stopPropagation();
            this.selectPlayer(playerId);
        });

        token.addEventListener('dblclick', (event) => {
            event.stopPropagation();
            this.removePlayer(playerId);
        });
    }

    selectPlayer(playerId) {
        if (this.selectedPlayerId !== null) {
            const previous = this.playersLayer.querySelector(`.tactical-player-token[data-player-id="${this.selectedPlayerId}"]`);
            previous?.classList.remove('tactical-player-token--selected');
        }

        this.selectedPlayerId = playerId;
        const token = this.playersLayer.querySelector(`.tactical-player-token[data-player-id="${playerId}"]`);
        token?.classList.add('tactical-player-token--selected');

        const entry = this.positions.get(playerId);
        if (!entry) {
            return;
        }

        const sideLabel = entry.data.side === 'home' ? 'Domicile' : 'Extérieur';
        if (this.selectedLabel) {
            this.selectedLabel.innerHTML = `
                <span class="tactical-match-selected__label">${entry.data.name}</span>
                <span class="tactical-match-selected__meta">${sideLabel} · #${entry.data.number} · ${entry.data.position}</span>
            `;
        }

        if (this.removeButton) {
            this.removeButton.disabled = false;
        }
    }

    clearSelection() {
        if (this.selectedPlayerId !== null) {
            const previous = this.playersLayer.querySelector(`.tactical-player-token[data-player-id="${this.selectedPlayerId}"]`);
            previous?.classList.remove('tactical-player-token--selected');
        }

        this.selectedPlayerId = null;
        if (this.selectedLabel) {
            this.selectedLabel.innerHTML = '<span class="tactical-match-selected__label">Aucun joueur sélectionné</span>';
        }
        if (this.removeButton) {
            this.removeButton.disabled = true;
        }
    }

    removePlayer(playerId) {
        const entry = this.positions.get(playerId);
        if (!entry) {
            return;
        }

        const token = this.playersLayer.querySelector(`.tactical-player-token[data-player-id="${playerId}"]`);
        token?.remove();

        this.positions.delete(playerId);
        this.addToBench(entry.data);

        if (this.selectedPlayerId === playerId) {
            this.clearSelection();
        }
    }

    addToBench(data) {
        const bench = this.root.querySelector(`.tactical-bench-list[data-bench-side="${data.side}"]`);
        if (!bench) {
            return;
        }

        const posGroup = data.position === 'GK'
            ? 'GK'
            : ['CB', 'LB', 'RB'].includes(data.position)
                ? 'DEF'
                : ['CDM', 'CM', 'CAM'].includes(data.position)
                    ? 'MIL'
                    : 'ATT';

        const element = document.createElement('div');
        element.className = `tactical-bench-card tactical-bench-card--${data.side}`;
        element.draggable = true;
        element.dataset.playerId = data.id;
        element.dataset.posGroup = posGroup;
        element.dataset.side = data.side;
        element.dataset.playerData = JSON.stringify(data);

        if (data.photo) {
            element.innerHTML = `
                <img src="/uploads/players/${data.photo}" class="tactical-bench-card__photo" alt="">
                <div class="tactical-bench-card__info">
                    <div class="tactical-bench-card__name">${data.name}</div>
                    <div class="tactical-bench-card__meta">
                        <span class="tactical-bench-card__num">#${data.number}</span>
                        <span class="tactical-chip-pos tactical-chip-pos--${posGroup.toLowerCase()}">${data.position}</span>
                    </div>
                </div>
                <div class="tactical-bench-card__drag">⣿</div>
            `;
        } else {
            element.innerHTML = `
                <div class="tactical-bench-card__avatar tactical-bench-card__avatar--${posGroup.toLowerCase()}">${(data.shortName || '').slice(0, 2).toUpperCase()}</div>
                <div class="tactical-bench-card__info">
                    <div class="tactical-bench-card__name">${data.name}</div>
                    <div class="tactical-bench-card__meta">
                        <span class="tactical-bench-card__num">#${data.number}</span>
                        <span class="tactical-chip-pos tactical-chip-pos--${posGroup.toLowerCase()}">${data.position}</span>
                    </div>
                </div>
                <div class="tactical-bench-card__drag">⣿</div>
            `;
        }

        this.initBenchPlayerDrag(element);
        bench.appendChild(element);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-match-board]').forEach((root) => {
        new MatchBoard(root).connect();
    });
});
