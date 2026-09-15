class DateRangeFacetController {
  constructor(element) {
    this.elements = element
    this.facetName = element.getAttribute('data-facet-name')
    this.minDatePicker = this.elements.querySelector('#'+this.facetName+'_min')
    this.maxDatePicker = this.elements.querySelector('#'+this.facetName+'_max')
    this.urlTemplate = element.getAttribute('data-facet-url')
    this.gap = element.getAttribute('data-range-gap')
    this.startValidationText = element.getAttribute('data-range-start-validation-text')
    this.endValidationText = element.getAttribute('data-range-end-validation-text')
    this.resetUrl = element.getAttribute('data-facet-reset-url')
    this.maxDate = this.maxDatePicker.max
    this.minDate = this.minDatePicker.min
  }

  /**
   * Initialize datepicker functions
   */
  init() {
    this.setMinMax(this.maxDatePicker, 'min', this.addGap(this.minDatePicker.value, 1))
    this.setMinMax(this.minDatePicker, 'max', this.addGap(this.maxDatePicker.value, -1))

    this.minDatePicker.addEventListener('change', () => {
      this.setMinMax(this.maxDatePicker, 'min', this.addGap(this.minDatePicker.value, 1))
      this.filter()
    })
    this.maxDatePicker.addEventListener('change', () => {
      this.setMinMax(this.minDatePicker, 'max', this.addGap(this.maxDatePicker.value, -1))
      this.filter()
    })
  }

  /**
   * Set min/max attribute after selecting date
   * Reset min/max if date input was cleared
   * @param input
   * @param attr
   * @param value
   */
  setMinMax(input, attr, value) {
    if (value) {
      input.setAttribute(attr, value)
    } else {
      // Reset min/max when date input was cleared to initial value
      attr === 'min' ? input.setAttribute(attr, this.minDate) : input.setAttribute('max', this.maxDate)
    }
  }

  /**
   * Add or subtract a gap from date
   * @param dateString
   * @param direction - 1 or -1
   * @returns {string|null}
   */
  addGap(dateString, direction) {
    const date = new Date(dateString + 'T00:00:00');
    if (isNaN(date.getTime())) return null;

    const match = this.gap.match(/^([+-]?\d+)(DAY|MONTH|YEAR)$/i);
    if (!match) return null;

    const amount = parseInt(match[1]) * direction;
    const unit = match[2].toUpperCase();

    switch (unit) {
      case 'DAY':
        date.setDate(date.getDate() + amount);
        break;
      case 'MONTH':
        date.setMonth(date.getMonth() + amount);
        break;
      case 'YEAR':
        date.setFullYear(date.getFullYear() + amount);
        break;
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  /**
   * Validate date range and show alert if false (iPhone doesn't support min max date)
   * Also prevents wrong range when user types in date with keyboard
   * @returns {boolean}
   */
  validateDate() {
    if (this.minDatePicker.value < this.minDatePicker.min || this.minDatePicker.value > this.minDatePicker.max) {
      const validationText = this.startValidationText
        .replace('###MIN###', this.minDatePicker.min)
        .replace('###MAX###', this.minDatePicker.max)
      alert(validationText)
      return false
    }
    if (this.maxDatePicker.value < this.maxDatePicker.min || this.maxDatePicker.value > this.maxDatePicker.max) {
      const validationText = this.endValidationText
        .replace('###MIN###', this.maxDatePicker.min)
        .replace('###MAX###', this.maxDatePicker.max)
      alert(validationText)
      return false
    }
    return true
  }

  /**
   * Build URL and call it
   */
  filter() {
    if (this.minDatePicker.value && this.maxDatePicker.value) {
      const minDate = this.minDatePicker.value.replaceAll("-", "") + '0000';
      const maxDate = this.maxDatePicker.value.replaceAll("-", "") + '2359';
      let url = this.urlTemplate

      url = url.replace(encodeURI('___FROM___'), minDate);
      url = url.replace(encodeURI('___TO___'), maxDate);
      this.validateDate() ? window.location.href = url : null
    } else {
      if (!this.minDatePicker.value && !this.maxDatePicker.value) {
        window.location.href = this.resetUrl
      }
    }
  }
}

const dateRangeFacetControllers = [];
const initDateRange = () => {
  dateRangeFacetControllers.length = 0;
  const dateInputs  = document.querySelectorAll(".tx-solr-daterange")
  if (dateInputs.length) {
    dateInputs.forEach(datepicker => {
      const controller = new DateRangeFacetController(datepicker);
      controller.init();
      dateRangeFacetControllers.push(controller);
    });
  }
};

initDateRange();
document.body.addEventListener("tx_solr_updated", initDateRange);
