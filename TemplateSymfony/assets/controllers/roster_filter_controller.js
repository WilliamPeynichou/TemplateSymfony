import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['row'];

    filter(event) {
        const button = event.currentTarget;
        const filter = button.dataset.filter || 'all';

        this.element.querySelectorAll('.attendance-sheet__filter-btn').forEach(item => {
            item.classList.toggle('attendance-sheet__filter-btn--active', item === button);
        });

        this.rowTargets.forEach(row => {
            const matches =
                filter === 'all' ||
                (filter === 'available' && row.dataset.status === 'present') ||
                (filter === 'injured' && row.dataset.status === 'injured') ||
                (filter === 'absent' && row.dataset.status === 'absent') ||
                (filter === 'risk' && row.dataset.presenceRisk === '1') ||
                row.dataset.position === filter;

            row.hidden = !matches;
        });
    }
}
