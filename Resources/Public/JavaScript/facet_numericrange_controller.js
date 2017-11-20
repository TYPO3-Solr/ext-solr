/**
 * The Controller. Controller responds to user actions and
 * invokes changes on the model.
 */
function NumericRangeFacetController() {
    var _this = this;

    this.init = function () {
        jQuery(".facet-type-numericRange-data").each(function () {
            var facetName = jQuery(this).data("facet-name");
            var rangeMin = jQuery(this).data("range-min");
            var rangeMax = jQuery(this).data("range-max");
            var rangeMinSelected = jQuery(this).data("range-min-selected");
            var rangeMaxSelected = jQuery(this).data("range-max-selected");
            var urlTemplate = jQuery(this).data("facet-url");

            if (rangeMinSelected === 0 && rangeMaxSelected === 0) {
                rangeMinSelected = rangeMin;
                rangeMaxSelected = rangeMax;
            }

            var rangeGap = jQuery(this).data("range-gap");

            jQuery(this).slider({
                range: true,
                values: [ rangeMinSelected, rangeMaxSelected],
                min: rangeMin,
                max: rangeMax,
                step: rangeGap,
                slide: function (event, ui) {
                    min = ui.values[0];
                    max = ui.values[1];
                    if (isNaN(min)) { min = 0; }
                    if (isNaN(max)) { max = 0; }

                    url = urlTemplate.replace('___FROM___', min.toString());
                    url = url.replace('___TO___', max.toString());
                    _this.load(url);
                    jQuery("#facet-" + facetName + "-value").html(min.toString() + "-" + max.toString());
                }
            });
        });
    };

    var timers = {};

    this.load = function (url) {
        clearTimeout(timers[url]);
        loadResult = function (url) {
            window.location.href = url;
        };
        timers[url] = setTimeout(loadResult(url), 1500);
    };
}

jQuery(document).ready(function () {
    var numericRangeFacetController = new NumericRangeFacetController();
    numericRangeFacetController.init();

    jQuery("body").on("tx_solr_updated", function() {
        numericRangeFacetController.init();
    });
});
