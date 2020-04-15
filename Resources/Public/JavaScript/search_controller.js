
function SearchController() {
    var _this = this;

    _this.ajaxType = 7383;

    this.init = function() {
        jQuery("body").delegate("a.solr-ajaxified", "click", _this.handleClickOnAjaxifiedUri);
    };

    this.handleClickOnAjaxifiedUri = function() {
        var clickedLink = jQuery(this);

        var solrContainer = clickedLink.closest(".tx_solr");
        var solrParent = solrContainer.parent();

        var loader = jQuery("<div class='tx-solr-loader'></div>");
        var uri = clickedLink.uri();

        solrParent.append(loader);
        uri.addQuery("type", _this.ajaxType);

        jQuery.get(
            uri.href(),
            function(data) {
                solrContainer = solrContainer.replaceWith(data);
                _this.scrollToTopOfElement(solrParent, 50);
                jQuery("body").trigger("tx_solr_updated");
                loader.fadeOut().remove();
                history.replaceState({}, null, uri.removeQuery("type").href());
            }
        );
        return false;
    };

    this.scrollToTopOfElement = function(element, deltaTop) {
        jQuery('html, body').animate({
            scrollTop: (element.offset().top - deltaTop) + 'px'
        }, 'slow');
    };

    this.setAjaxType = function(ajaxType) {
        _this.ajaxType = ajaxType;
    };
}

jQuery(document).ready(function() {
    var solrSearchController = new SearchController();
    solrSearchController.init();

    if(typeof solrSearchAjaxType !== "undefined") {
        solrSearchController.setAjaxType(solrSearchAjaxType);
    }
});
