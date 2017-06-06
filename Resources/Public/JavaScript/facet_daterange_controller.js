/**
 * The Controller. Controller responds to user actions and
 * invokes changes on the model.
 */
function DateRangeFacetController() {
    var _this = this;

    this.init = function() {
        dateSelector = jQuery(".dateselector");
        dateSelector.datepicker();
        dateSelector.change(function(){ _this.solrRequest("created"); });
    };

    this.solrRequest = function(facetName) {
        startDate = jQuery('#start_date_'+facetName);
        endDate = jQuery('#end_date_'+facetName);
        if (startDate.val() !== '' &&  endDate.val() !== '' ) {
            url = jQuery('#' + facetName + '_url').val();
            start_date = _this.convertToDate(startDate.datepicker('getDate'));
            end_date = _this.convertToDate(endDate.datepicker('getDate'));

            url = url.replace(encodeURI('___FROM___'), start_date + '0000');
            url = url.replace(encodeURI('___TO___'), end_date + '0000');
            window.location.href = url;
        }
    };

    this.convertToDate = function(date) {
        return jQuery.datepicker.formatDate('yymmdd', date);
    };
}


jQuery(document).ready(function() {
    var dateRangeFacetController = new DateRangeFacetController();
    dateRangeFacetController.init();

    jQuery("body").on("tx_solr_updated", function() {
        dateRangeFacetController.init();
    });
});
