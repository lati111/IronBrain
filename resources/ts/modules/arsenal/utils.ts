export function showEl(id: string): void {
    document.getElementById(id)?.classList.remove('hidden');
}

export function hideEl(id: string): void {
    document.getElementById(id)?.classList.add('hidden');
}

export function initFilters(activeFilters: Record<string, string>, onFilter: () => void): void {
    document.querySelectorAll<HTMLButtonElement>('.filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const group = btn.dataset.filterGroup!;
            const value = btn.dataset.filterValue!;

            document.querySelectorAll<HTMLButtonElement>(`.filter-btn[data-filter-group="${group}"]`).forEach(b => {
                b.classList.remove('selected');
            });
            btn.classList.add('selected');

            activeFilters[group] = value;
            onFilter();
        });
    });
}
