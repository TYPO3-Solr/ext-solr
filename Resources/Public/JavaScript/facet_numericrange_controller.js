class NumericRangeFacetController {
  constructor(element) {
    this.element = element
    this.inputs = this.element.querySelectorAll('.tx-solr-numericRange__input');
    this.thumbLeft = this.element.querySelector('.tx-solr-numericRange__slider__thumb.left');
    this.thumbRight = this.element.querySelector('.tx-solr-numericRange__slider__thumb.right');
    this.thumbSize = this.thumbLeft.offsetWidth;
    this.rangeBetween = this.element.querySelector('.tx-solr-numericRange__slider__range-between');
    this.labelMin = this.element.querySelector('.tx-solr-numericRange__values--min');
    this.labelMax = this.element.querySelector('.tx-solr-numericRange__values--max');
    this.inputMin = this.inputs[0]
    this.inputMax = this.inputs[1]
    this.step = parseInt(this.inputMin.step)
    this.urlTemplate = this.element.getAttribute('data-facet-url')
    this.trackWidth = this.element.querySelector('.tx-solr-numericRange__slider').offsetWidth;
  }

  /**
   * Initialize min/max slider and register events
   */
  init() {
    this.setMinValueCustomSlider();
    this.setMaxValueCustomSlider();
    this.setEvents();
  }

  /**
   * Set values shown under range slider
   * @param label
   * @param input
   */
  setLabelValue(label, input) {
    label.textContent = input.value;
  }

  /**
   * Set width of input fields to match with pseudo thumb position
   */
  setInputWidth() {
    const maxPercentage = 100 / this.inputMax.max * this.inputMax.value
    const minPercentage = 100 / this.inputMax.max * this.inputMin.value

    this.inputMin.setAttribute('max', this.inputMax.value)
    this.inputMax.setAttribute('min', this.inputMin.value)
    this.inputMin.style.width = this.trackWidth / 100 * maxPercentage + 'px'
    this.inputMax.style.width = this.trackWidth / 100 * (100 - minPercentage) + 'px'
    this.inputMax.style.right = 0
  }

  /**
   * Set position of slider min thumb
   */
  setMinValueCustomSlider() {
    const maximum = Math.min(parseInt(this.inputMin.value), parseInt(this.inputMax.value) - this.step);
    const percent = ((maximum - this.inputMin.min) / (this.inputMax.max - this.inputMin.min));
    const leftPx = (percent * (this.trackWidth - this.thumbSize)) + (this.thumbSize / 2);
    const leftPercent = (leftPx / this.trackWidth) * 100;

    this.inputMin.value = maximum;
    this.setInputWidth()
    this.setLabelValue(this.labelMin, this.inputMin)
    this.inputMin.setAttribute('value', this.inputMin.value)
    this.thumbLeft.style.left = `calc(${leftPercent}% - ${this.thumbSize / 2}px)`;
    this.rangeBetween.style.left = leftPercent + '%';
  }

  /**
   * Set position of slider max thumb
   */
  setMaxValueCustomSlider() {
    const minimum = Math.max(parseInt(this.inputMax.value), parseInt(this.inputMin.value) + this.step);
    const percent = ((minimum - this.inputMin.min) / (this.inputMax.max - this.inputMin.min));
    const leftPx = (percent * (this.trackWidth - this.thumbSize)) + (this.thumbSize / 2);
    const leftPercent = (leftPx / this.trackWidth) * 100;

    this.inputMax.value = minimum;
    this.setInputWidth()
    this.setLabelValue(this.labelMax, this.inputMax)
    this.inputMax.setAttribute('value', this.inputMax.value)
    this.thumbRight.style.right = `calc(${100 - leftPercent}% - ${this.thumbSize / 2}px)`;
    this.rangeBetween.style.right = (100 - leftPercent) + '%';
  }

  /**
   * Build URL and call it
   */
  applyRangeFilter() {
    let url = this.urlTemplate.replace('___FROM___', this.inputMin.value.toString());

    url = url.replace('___TO___', this.inputMax.value.toString());
    this.load(url)
  }

  /**
   * Load URL when applyRangeFilter isn't called again within 1,5s
   * @param url
   */
  load (url) {
    clearTimeout(this.loadTimeout);
    this.loadTimeout = setTimeout(() => {
      window.location.href = url;
    }, 1500);
  };

  setThumbEvents(input, thumb) {
    input.addEventListener('pointerdown', () => {
      thumb.classList.add('solr-active');
    });
    input.addEventListener('focus', () => {
      thumb.classList.add('solr-focus');
    });
    input.addEventListener('blur', () => {
      thumb.classList.remove('solr-focus');
    });
    input.addEventListener('keyup', (e) => {
      if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
        thumb.classList.remove('solr-active');
        this.applyRangeFilter();
      }
    });
    ['pointerup', 'lostpointercapture'].forEach(event => {
      input.addEventListener(event, () => {
        thumb.classList.remove('solr-active');
        this.applyRangeFilter()
      });
    });
  }

  /**
   * Set events for min and max slider thumb
   */
  setEvents() {
    // Events for min/max input
    this.inputMin.addEventListener('input', () => {
      this.setMinValueCustomSlider();
    });
    this.inputMax.addEventListener('input', () => {
      this.setMaxValueCustomSlider();
    });
    // Events for slider thumb
    this.setThumbEvents(this.inputMin, this.thumbLeft)
    this.setThumbEvents(this.inputMax, this.thumbRight)
  }
}

const numericRangeFacetControllers = [];
const initRangeSliders = () => {
  numericRangeFacetControllers.length = 0;
  const rangeInputs = document.querySelectorAll(".facet-type-numericRange-data")
  if (rangeInputs.length) {
    rangeInputs.forEach(slider => {
      const controller = new NumericRangeFacetController(slider);
      controller.init();
      numericRangeFacetControllers.push(controller);
    });
  }
};

initRangeSliders();
window.addEventListener('resize', initRangeSliders);
document.body.addEventListener("tx_solr_updated", initRangeSliders);
