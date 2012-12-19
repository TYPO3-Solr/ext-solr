function solrRequest(facetName, delimiter) {

	if (jQuery('#start_date_'+facetName).val() != '' &&  jQuery('#end_date_'+facetName).val() != '' ) {
		url = jQuery('#' + facetName + '_url').val();

		start_date = convertToDate(jQuery('#start_date_'+facetName).datepicker('getDate'));
		end_date = convertToDate(jQuery('#end_date_'+facetName).datepicker('getDate'));
		url = url + encodeURI(start_date +'0000' + delimiter + end_date + '2359');

		window.location.href = url;
	};

}

function convertToDate (date) {
	return jQuery.datepicker.formatDate('yymmdd', date);
}