$(document).ready(function(){

	$(".tx-solr-q").autocomplete(
		tx_solr_suggestUrl,
		{
			width: 300,
			scroll: false,
			dataType: "json",
			minChars: 3,
			selectFirst: false,

			formatItem: function(data, i, max, value, term) {
				return value;
			},

			parse: function(data) {
				var rs = new Array();
				var output = new Array();

				i = 0;
				for (var term in data) {
					output[i] = {
						data   : term,
						value  : term + ' (' + data[term] + ')',
						result : term,
						count  : data[term]
					};
					i++;
				}

				return output;
			}
		}
	);

}).result(function(event, data, formatted){
	$(event.target).closest('form').submit();
});
