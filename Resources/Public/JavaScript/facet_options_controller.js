/**
 * The Controller. Controller responds to user actions and
 * invokes changes on the model.
 */
function OptionFacetController() {
    var _this = this;

    this.init = function () {
        jQuery('.tx-solr-facet-hidden').hide();
        jQuery('a.tx-solr-facet-show-all').click(function() {
            if (jQuery(this).parent().siblings('.tx-solr-facet-hidden:visible').length == 0) {
                jQuery(this).parent().siblings('.tx-solr-facet-hidden').show();
                jQuery(this).text(jQuery(this).data('label-less'));
            } else {
                jQuery(this).parent().siblings('.tx-solr-facet-hidden').hide();
                jQuery(this).text(jQuery(this).data('label-more'));
            }

            return false;
        });
    };
}

jQuery(document).ready(function () {
    var optionsController = new OptionFacetController();
    optionsController.init();

    jQuery("body").on("tx_solr_updated", function() {
        optionsController.init();
    });
});
