import { Controller } from '@hotwired/stimulus';

/**
 * Tri / filtre / recherche sur la table effectif FM.
 */
export default class extends Controller {
    static targets = ['tbody'];
    static values  = { teamId: Number };

    currentGroup = 'all';
    currentSearch = '';
    sortField = null;
    sortAsc = false;

    connect() { this.applyFilters(); }

    filter(event) {
        const btn = event.currentTarget;
        this.element.querySelectorAll('.fm-filter').forEach(b => b.classList.remove('fm-filter--active'));
        btn.classList.add('fm-filter--active');
        this.currentGroup = btn.dataset.group;
        this.applyFilters();
    }

    search(event) {
        this.currentSearch = (event.target.value || '').trim().toLowerCase();
        this.applyFilters();
    }

    sort(event) {
        const field = event.currentTarget.dataset.field;
        if (this.sortField === field) {
            this.sortAsc = !this.sortAsc;
        } else {
            this.sortField = field;
            this.sortAsc = field === 'name' || field === 'number';
        }

        const rows = Array.from(this.tbodyTarget.querySelectorAll('tr'));
        rows.sort((a, b) => {
            const av = a.dataset[field] || '';
            const bv = b.dataset[field] || '';
            const an = Number(av); const bn = Number(bv);
            const cmp = (!isNaN(an) && !isNaN(bn) && av !== '' && bv !== '')
                ? (an - bn)
                : String(av).localeCompare(String(bv));
            return this.sortAsc ? cmp : -cmp;
        });
        rows.forEach(r => this.tbodyTarget.appendChild(r));
    }

    applyFilters() {
        const rows = this.tbodyTarget.querySelectorAll('tr');
        rows.forEach(row => {
            const groupOk = this.currentGroup === 'all' || row.dataset.group === this.currentGroup;
            const nameOk  = !this.currentSearch || (row.dataset.name || '').includes(this.currentSearch);
            row.hidden = !(groupOk && nameOk);
        });
    }
}
