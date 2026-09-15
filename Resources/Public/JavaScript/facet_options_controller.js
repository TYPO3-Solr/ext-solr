class OptionFacetController {
  constructor(toggles, filters) {
    this.toggles = toggles
    this.filters = filters
    this.hiddenFacets = document.querySelectorAll('.tx-solr-facet-hidden')
  }

  /**
   * Initialize toggle and filter functions
   */
  init() {
    this.toggles.length ? this.initToggle() : null
    this.filters.length ? this.initFilter() : null
  }

  /**
   * Initialize toggle functions (show more/less)
   */
  initToggle() {
    this.hiddenFacets.forEach(el => {
      el.style.display = 'none';
    });

    this.toggles.forEach(link => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const parent = link.parentElement;
        const hiddenSiblings = Array.from(parent.parentElement.children).filter(
          sibling => sibling !== parent && sibling.classList.contains('tx-solr-facet-hidden')
        );

        const anyVisible = hiddenSiblings.some(el => el.style.display !== 'none');

        if (anyVisible) {
          hiddenSiblings.forEach(el => el.style.display = 'none');
          link.textContent = link.dataset.labelMore;
        } else {
          hiddenSiblings.forEach(el => el.style.display = '');
          link.textContent = link.dataset.labelLess;
        }
      });
    });
  }

  /**
   * Initialize filter functions
   */
  initFilter() {
    this.filters.forEach(searchBox => {
      const facet = searchBox.closest('.facet');
      const searchItems = facet.querySelectorAll('.facet-filter-item');

      searchBox.addEventListener('keyup', () => {
        const value = searchBox.value.toLowerCase();
        searchItems.forEach(item => {
          item.style.display = item.textContent.toLowerCase().includes(value) ? '' : 'none';
        });
      });
    });
  }
}

const optionFacetControllers = [];
const initFacetOptions = () => {
  let toggles = document.querySelectorAll('a.tx-solr-facet-show-all');
  let filters = document.querySelectorAll('.facet-filter-box');

  optionFacetControllers.length = 0;
  if (toggles.length || filters.length) {
    const optionFacetController = new OptionFacetController(toggles, filters);
    optionFacetController.init();
  }
};

initFacetOptions();
document.body.addEventListener("tx_solr_updated", initFacetOptions);
