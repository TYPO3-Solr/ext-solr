class SuggestController {
  constructor(element) {
    this.form = element;
    this.suggestURL = this.form.getAttribute('data-suggest')
    this.documentSuggestionHeader = this.form.getAttribute('data-suggest-header')
    this.suggestionItems = []
    this.documentItems = []
    this.lastSelectedIndex = null
    this.isMouseNavigation = false
    this.autoCompleteInstance = null
  }

  /**
   * Initialize autocomplete/suggestions
   */
  init() {
    this.buildSuggestionBox();
    this.setSuggestionBoxWidthAndPosition();
  }

  /**
   * Reset autocomplete and document suggestions
   */
  resetSuggestions() {
    this.suggestionItems = []
    this.documentItems = []
  }

  /**
   * Format search query. Avoid XSS
   * @param query
   * @returns {string}
   */
  formatQuery(query) {
    if (typeof query !== "string") {
      return "";
    }
    // Escape HTML characters (XSS-Protection)
    const htmlEscaped = query
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#x27;")
      .replace(/\//g, "&#x2F;");
    // Only keep letters, numbers, spaces, hyphens, underscore, period, comma
    const cleaned = htmlEscaped.replace(/[^\p{L}\p{N}\s\-_.,]/gu, "");

    // Remove multiple spaces
    return cleaned.trim().replace(/\s+/g, " ");
  }

  /**
   * Format result to avoid display errors
   * @param result
   * @returns {string}
   */
  formatResult(result) {
    if (typeof result !== "string") {
      return "";
    }
    // Escape HTML characters. Only allow <strong>
    const htmlEscaped = result
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#x27;")
      .replace(/&lt;\/?mark&gt;/g, (match) => match.includes('/') ? '</strong>' : '<strong>');

    return htmlEscaped;
  }

  /**
   * Fetch autocomplete and document suggestions
   * @param query
   * @returns {Promise<[]|*|*[]>}
   */
  async fetchSuggestions(query) {
    // Cancel request if there is one
    if (this.abortController) {
      this.abortController.abort();
    }
    this.resetSuggestions()
    // Create AbortController to avoid multiple requests
    this.abortController = new AbortController();

    try {
      const url = `${this.suggestURL}&tx_solr%5BqueryString%5D=${encodeURIComponent(query)}`;
      const response = await fetch(
        url,
        { signal: this.abortController.signal }
      );
      const data = await response.json();
      // Define autocomplete and document suggestions
      this.suggestionItems = data.suggestions ? Object.keys(data.suggestions) : [];
      this.documentItems = Array.isArray(data.documents) ? data.documents : [];

      return this.suggestionItems;
    } catch (error) {
      if (error.name === 'AbortError') {
        // Aborted request
        return [];
      }
      // Real error
      throw error;
    }
  }

  /**
   * Set input value to initial query, set cursor behind search term, remove active-state from items
   */
  resetInput() {
    const list = this.autoCompleteInstance.list;
    const selected = list.querySelector(`.${this.autoCompleteInstance.resultItem.selected}`);

    // Remove selected state
    if (selected) {
      selected.removeAttribute("aria-selected");
      selected.classList.remove(this.autoCompleteInstance.resultItem.selected);
    }

    this.autoCompleteInstance.cursor = -1
    // Set input value to initial query
    this.autoCompleteInstance.input.value = this.autoCompleteInstance.feedback.query;
    // Set cursor behind the search term
    this.setCursorBehindTerm()
  }

  /**
   * Set cursor at the end of the search term
   */
  setCursorBehindTerm() {
    const queryLength = this.autoCompleteInstance.input.value.length

    setTimeout(() => {
      this.autoCompleteInstance.input.setSelectionRange(queryLength, queryLength)
    }, 0);
  }

  /**
   * Handler for arrow up key
   */
  arrowUpHandler() {
    if (this.autoCompleteInstance.isOpen && this.lastSelectedIndex !== null) {
      // If first result item is selected and arrow up is pressed then
      // replace input value with initial query and reset suggestion box.
      if (this.lastSelectedIndex === 0) {
        this.resetInput()
      } else {
        this.autoCompleteInstance.previous()
        this.setCursorBehindTerm()
      }
    }
  }

  /**
   * Handler for arrow down key
   */
  arrowDownHandler() {
    const list = this.autoCompleteInstance.list;

    if (this.autoCompleteInstance.isOpen && list) {
      const items = list.querySelectorAll("li");

      // Navigate to next item as long as it's not last item
      if (this.lastSelectedIndex < items.length - 1) {
        this.autoCompleteInstance.next()
      }
    } else if (list) {
      // Open suggestion box on arrow down key
      this.autoCompleteInstance.open()
    }
  }

  /**
   * Handler for enter key
   */
  enterHandler() {
    const cursor = this.autoCompleteInstance.cursor
    // If nothing is selected or suggestion box is closed then submit form
    if ((cursor === undefined || cursor === -1) || !this.autoCompleteInstance.isOpen) {
      if (this.autoCompleteInstance.input.value.length) {
        this.form.submit()
      }
    } else {
      this.autoCompleteInstance.select(cursor)
    }
  }

  /**
   * Handler for escape key
   */
  escapeHandler() {
    if (this.autoCompleteInstance.isOpen) {
      this.autoCompleteInstance.close()
      this.resetInput()
    }
  }

  /**
   * Create document suggestion item under "Top Results"
   * @param documentItem
   * @returns {HTMLLIElement}
   */
  createDocumentSuggestionItem(documentItem) {
    const documentElement = document.createElement("li");
    const formattedDocument = this.formatResult(documentItem.title)
    const link = document.createElement("a");
    const index = this.autoCompleteInstance.list.querySelectorAll('li').length;

    link.href = documentItem.link;
    link.innerHTML = formattedDocument;
    documentElement.appendChild(link);
    documentElement.classList.add('autocomplete-suggestion')
    this.onResultItemHover(documentElement, index)

    return documentElement
  }

  /**
   * Create document suggestion header (Top results)
   * @returns {HTMLParagraphElement}
   */
  createDocumentSuggestionHeader() {
    const documentHeader = document.createElement("p");

    documentHeader.classList.add('autocomplete-group')
    documentHeader.innerHTML = `<span>${this.documentSuggestionHeader}</span>`;

    return documentHeader
  }

  /**
   * Set hovered result items as selected
   * @param item
   * @param index
   */
  onResultItemHover(item, index) {
    item.onmouseenter = () => {
      this.lastSelectedIndex = index
      // Set navigateVia to mouse to prevent input value replacement via hover later on. Only via keyboard.
      this.isMouseNavigation = true
      // Set hovered autocomplete suggestion as selected
      this.autoCompleteInstance.goTo(index)
    }
  }

  /**
   * Set suggestion box position and width
   */
  setSuggestionBoxWidthAndPosition() {
    const list = this.autoCompleteInstance.list;
    if (!list) return;

    // Move suggestion box to end of body tag to prevent problems with overflow-hidden parents
    document.body.appendChild(list);

    const input = this.autoCompleteInstance.input;
    let top = 0;
    let left = 0;
    let element = input;

    while (element) {
      top += element.offsetTop;
      left += element.offsetLeft;
      element = element.offsetParent;
    }

    list.style.top = `${top + input.offsetHeight}px`;
    list.style.left = `${left}px`;
    list.style.width = `${input.offsetWidth}px`;
  }

  /**
   * Build suggestion box with autoCompleteJS
   * https://tarekraafat.github.io/autoComplete.js/#/configuration
   */
  buildSuggestionBox() {
    if (this.autoCompleteInstance) {
      this.autoCompleteInstance.unInit();
    }
    this.autoCompleteInstance = new autoComplete(
      {
        selector: ".tx-solr-suggest",
        data: {
          src: async (query) => {
            const formattedQuery = this.formatQuery(query)
            return await this.fetchSuggestions(formattedQuery);
          },
          cache: false,
        },
        debounce: 300,
        wrapper: false,
        resultsList: {
          class: 'autocomplete-suggestions tx-solr-autosuggest',
          element: (list) => {
            // Add document suggestions (link) to result list under "Top results"
            if (this.documentItems.length) {
              list.append(this.createDocumentSuggestionHeader());
              this.documentItems.forEach((documentItem) => {
                list.append(this.createDocumentSuggestionItem(documentItem));
              })
            }
          }
        },
        resultItem: {
          highlight: true,
          class: 'autocomplete-suggestion',
          selected: 'autocomplete-selected',
          element: (item, data) => {
            item.innerHTML = `${this.formatResult(data.match)}`;
            // Set hovered autocomplete suggestion as selected
            const index = this.suggestionItems.indexOf(data.value);
            this.onResultItemHover(item, index)
          },
        },
        events: {
          input: {
            focus: () => {
              const inputValueLength = this.autoCompleteInstance.input.value.length
              const resultItemsLength = this.autoCompleteInstance.list.children.length
              // Reopen suggestion list when results are still the same. Otherwise, start new request
              inputValueLength && resultItemsLength ? this.autoCompleteInstance.open() : this.autoCompleteInstance.start();

            },
            selection: (event) => {
              if (!this.autoCompleteInstance.isOpen) return;
              const selectionIndex = event.detail.selection.index

              this.autoCompleteInstance.input.blur();
              // Replace input value with the selected value
              this.autoCompleteInstance.input.value = this.formatQuery(event.detail.selection.value);

              // If selected element comes after the autocomplete suggestions (document suggestion)
              if (selectionIndex >= event.detail.results.length) {
                event.preventDefault();
                const resultItems = this.autoCompleteInstance.list.querySelectorAll('li')

                // Get href from selected item and open it
                window.location.href = resultItems[selectionIndex].children[0].href
              } else {
                this.form.submit()
              }
            },
            navigate: (event) => {
              if (!this.autoCompleteInstance.isOpen) return;
              if (!this.isMouseNavigation) {
                // Replace value of focused result item with input field value (only keyboard)
                if (event.detail.selection.value) {
                  this.autoCompleteInstance.input.value = event.detail.selection.value;
                }
              }
              this.isMouseNavigation = false
            },
            open: () => {
              this.lastSelectedIndex = null
            },
            close: () => {
              this.lastSelectedIndex = null
              // Abort all running requests
              this.abortController.abort();
            },
            keydown: (event) => {
              const cursor = this.autoCompleteInstance.cursor;
              this.lastSelectedIndex = (cursor !== undefined && cursor >= 0) ? cursor : null;

              switch (event.key) {
                case 'ArrowDown':
                  this.arrowDownHandler();
                  break;
                case 'ArrowUp':
                  this.arrowUpHandler();
                  break;
                case 'Enter':
                  this.enterHandler();
                  break;
                case 'Escape':
                  this.escapeHandler();
                  break;
              }
            }
          }
        }
      }
    )
  }
}

const suggestControllers = [];
const initSuggestControllers = () => {
  suggestControllers.length = 0;
  const searchForms = document.querySelectorAll('form[data-suggest]')
  if (searchForms.length) {
    searchForms.forEach(element => {
      const controller = new SuggestController(element);
      controller.init();
      suggestControllers.push(controller);
    });
  }
};

initSuggestControllers();
window.addEventListener('resize', () => {
  suggestControllers.forEach(controller => controller.setSuggestionBoxWidthAndPosition());
});
document.body.addEventListener("tx_solr_updated", initSuggestControllers);
