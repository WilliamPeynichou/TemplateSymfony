import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'row',
        'statusInput',
        'reasonWrap',
        'reasonInput',
        'reasonPlaceholder',
        'playersCount',
        'presentCount',
        'lateCount',
        'absentCount',
        'excusedCount',
    ];

    connect() {
        this.refreshSummary();
        this.rowTargets.forEach(row => this.syncRow(row));
    }

    selectStatus(event) {
        const button = event.currentTarget;
        const row = button.closest('[data-attendance-target="row"]');
        if (!row) {
            return;
        }

        const status = button.dataset.status || 'present';
        row.dataset.status = status;
        this.syncRow(row);
        this.refreshSummary();
    }

    markAllPresent() {
        this.rowTargets.forEach(row => {
            row.dataset.status = 'present';
            this.syncRow(row);
        });

        this.refreshSummary();
    }

    clearReasons() {
        this.reasonInputTargets.forEach(input => {
            input.value = '';
        });
    }

    filterRows(event) {
        const button = event.currentTarget;
        const filter = button.dataset.filter || 'all';

        this.element.querySelectorAll('.attendance-sheet__filter-btn').forEach(filterButton => {
            filterButton.classList.toggle('attendance-sheet__filter-btn--active', filterButton === button);
        });

        this.rowTargets.forEach(row => {
            const matches = filter === 'all' || row.dataset.status === filter;
            row.hidden = !matches;
        });
    }

    syncRow(row) {
        const status = row.dataset.status || 'present';
        const input = row.querySelector('[data-attendance-target="statusInput"]');
        if (input) {
            input.value = status;
        }

        row.querySelectorAll('.attendance-sheet__status-btn').forEach(button => {
            button.classList.toggle('attendance-sheet__status-btn--active', button.dataset.status === status);
        });

        const reasonWrap = row.querySelector('[data-attendance-target="reasonWrap"]');
        const reasonInput = row.querySelector('[data-attendance-target="reasonInput"]');
        const reasonPlaceholder = row.querySelector('[data-attendance-target="reasonPlaceholder"]');
        const needsReason = status === 'absent' || status === 'excused';

        if (reasonWrap) {
            reasonWrap.hidden = !needsReason;
        }

        if (reasonInput) {
            reasonInput.disabled = !needsReason;
            if (!needsReason) {
                reasonInput.value = '';
            }
        }

        if (reasonPlaceholder) {
            reasonPlaceholder.hidden = needsReason;
        }
    }

    refreshSummary() {
        const counts = {
            players: this.rowTargets.length,
            present: 0,
            late: 0,
            absent: 0,
            excused: 0,
        };

        this.rowTargets.forEach(row => {
            const status = row.dataset.status || 'present';
            if (status in counts) {
                counts[status] += 1;
            }
        });

        this.playersCountTarget.textContent = String(counts.players);
        this.presentCountTarget.textContent = String(counts.present);
        this.lateCountTarget.textContent = String(counts.late);
        this.absentCountTarget.textContent = String(counts.absent);
        this.excusedCountTarget.textContent = String(counts.excused);
    }
}
