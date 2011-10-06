jQuery(document).ready(function(){

	// Change back to the old behavior of auto-complete
	// http://jqueryui.com/docs/Upgrade_Guide_184#Autocomplete
	jQuery.ui.autocomplete.prototype._renderItem = function( ul, item ) {
			return jQuery( "<li></li>" )
				.data( "item.autocomplete", item )
				.append( "<a>" + item.label + "</a>" )
				.appendTo( ul );
		};

	jQuery('.tx-solr-q').autocomplete(
		{
			source: function(request, response) {
				jQuery.ajax({
					url: tx_solr_suggestUrl,
					dataType: 'json',
					data: {
						termLowercase: request.term.toLowerCase(),
						termOriginal: request.term,
						L: jQuery('div.tx-solr input[name="L"]').val()
					},
					success: function(data) {
						var rs     = [],
							output = [];

						jQuery.each(data, function(term, termIndex) {
							var unformatted_label = term + ' <span class="result_count">(' + data[term] + ')</span>';
							output.push({
								label : unformatted_label.replace(new RegExp('(?![^&;]+;)(?!<[^<>]*)(' +
											jQuery.ui.autocomplete.escapeRegex(request.term) +
											')(?![^<>]*>)(?![^&;]+;)', 'gi'), '<strong>$1</strong>'),
								value : term
							});
						});

						response(output);
					}
				})
			},
			select: function(event, ui) {
				this.value = ui.item.value;
				jQuery(event.target).closest('form').submit();
			},
			delay: 0,
			minLength: 3
		}
	);
});
