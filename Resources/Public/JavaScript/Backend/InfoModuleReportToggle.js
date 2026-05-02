const OVERVIEW_SELECTOR = '.solr-info-module__report-overview';
const BUTTON_SELECTOR = '.solr-info-module__report-toggle-all';
const ITEM_SELECTOR = '.solr-info-module__report-items.collapse';

const isAllOpen = (overview) => {
    const items = overview.querySelectorAll(ITEM_SELECTOR);
    if (items.length === 0) {
        return false;
    }
    return Array.from(items).every((item) => item.classList.contains('show'));
};

const setExpanded = (overview, expanded) => {
    overview.querySelectorAll(ITEM_SELECTOR).forEach((item) => {
        item.classList.toggle('show', expanded);
        const trigger = overview.querySelector(`[data-bs-target="#${item.id}"]`);
        if (trigger) {
            trigger.classList.toggle('collapsed', !expanded);
            trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
    });
};

const updateButton = (overview, button) => {
    const allOpen = isAllOpen(overview);
    button.dataset.state = allOpen ? 'all-open' : 'collapsed';
    button.setAttribute('aria-pressed', allOpen ? 'true' : 'false');
};

document.querySelectorAll(OVERVIEW_SELECTOR).forEach((overview) => {
    const button = overview.querySelector(BUTTON_SELECTOR);
    if (!button) {
        return;
    }

    button.addEventListener('click', (event) => {
        event.preventDefault();
        setExpanded(overview, !isAllOpen(overview));
        updateButton(overview, button);
    });

    overview.querySelectorAll(ITEM_SELECTOR).forEach((item) => {
        item.addEventListener('shown.bs.collapse', () => updateButton(overview, button));
        item.addEventListener('hidden.bs.collapse', () => updateButton(overview, button));
    });

    updateButton(overview, button);
});
