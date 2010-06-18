jQuery(document).ready(function($){
  $('.tx-solr-facet-hidden').hide();
  $('a.tx-solr-facet-show-all').click(function() {
	  if ($(this).parent().siblings('.tx-solr-facet-hidden:visible').length == 0) {
	      $(this).parent().siblings('.tx-solr-facet-hidden').show();
	      $(this).text(tx_solr_facetLabels.showFewer);
	    }
	    else {
	      $(this).parent().siblings('.tx-solr-facet-hidden').hide();
	      $(this).text(tx_solr_facetLabels.showMore);
	    }
	    return false;
  }).appendTo($('#tx-solr-facets-available:has(.tx-solr-facet-hidden)'));
});
