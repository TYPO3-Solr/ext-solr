class SearchController {
  constructor() {
    this.ajaxType = 7383;
    this.ajaxLink = document.querySelectorAll("a.solr-ajaxified");
    this.solrContainer = null
    this.solrContainerParent = null
    this.loader = null
  }

  /**
   * Initialize ajax search
   */
  init() {
    if(typeof solrSearchAjaxType !== "undefined") {
      this.ajaxType = solrSearchAjaxType;
    }
    this.ajaxLink.forEach((link)=> {
      link.addEventListener("click", (event) => {
        event.preventDefault();
        this.handleClickOnAjaxifiedUri(link, event)
      })
    })
  }

  /**
   * Create and add loading spinner to solr container
   */
  addLoadingSpinner() {
    this.loader  = document.createElement("div");
    this.loader.classList.add('tx-solr-loader')
    this.solrContainerParent.append(this.loader)
  }

  /**
   * Fade out loading spinner
   */
  removeLoadingSpinner() {
    this.loader.style.transition = "opacity 0.3s";
    this.loader.style.opacity = "0";
    this.loader.addEventListener("transitionend", () => this.loader.remove());
  }

  /**
   * Replace solr container content with fetched content
   * @param fetchedContent
   */
  replaceContent(fetchedContent) {
    const range = document.createRange();
    const fragment = range.createContextualFragment(fetchedContent);
    this.solrContainer.replaceWith(fragment);
  }

  scrollToTopOfElement(element, deltaTop) {
    const targetPosition = element.getBoundingClientRect().top + window.scrollY - deltaTop;

    window.scrollTo({
      top: targetPosition,
      behavior: "smooth"
    });
  }

  /**
   * Fetch data
   * @param url
   */
  fetchData(url) {
    fetch(url.toString())
      .then(response => response.text())
      .then(data => {
        this.replaceContent(data)
        this.removeLoadingSpinner()
        this.scrollToTopOfElement(this.solrContainerParent, 50)
        document.body.dispatchEvent(new CustomEvent("tx_solr_updated", { bubbles: true }));

        // Set browser URL
        url.searchParams.delete("type");
        history.replaceState({}, null, url.pathname + url.search);
      });
  }

  /**
   * Handle click on ajaxified link
   * @param link
   * @param event
   */
  handleClickOnAjaxifiedUri(link, event) {
    const uri = link.getAttribute('href')
    const ajaxifiedUri = new URL(uri, window.location.origin);
    this.solrContainer = event.target.closest('.tx_solr')
    this.solrContainerParent = this.solrContainer.parentElement

    ajaxifiedUri.searchParams.set('type', this.ajaxType)
    this.addLoadingSpinner()
    this.fetchData(ajaxifiedUri)
  }
}

const searchControllers = [];
const initAjaxifiedSearch = () => {
  searchControllers.length = 0;
  let ajaxLinks = document.querySelectorAll('a.solr-ajaxified');
  if (ajaxLinks.length) {
    const controller = new SearchController();
    controller.init();
    searchControllers.push(controller);
  }
};

initAjaxifiedSearch();
document.body.addEventListener("tx_solr_updated", initAjaxifiedSearch);
