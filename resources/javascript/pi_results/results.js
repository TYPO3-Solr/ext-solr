jQuery(document).ready(function(){

	jQuery('.tx-solr-facet-hidden').hide();
	jQuery('a.tx-solr-facet-show-all').click(function() {
		if (jQuery(this).parent().siblings('.tx-solr-facet-hidden:visible').length == 0) {
			jQuery(this).parent().siblings('.tx-solr-facet-hidden').show();
			jQuery(this).text(tx_solr_facetLabels.showFewer);
		} else {
			jQuery(this).parent().siblings('.tx-solr-facet-hidden').hide();
			jQuery(this).text(tx_solr_facetLabels.showMore);
		}

		return false;
	});
});