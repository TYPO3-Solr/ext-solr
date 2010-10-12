jQuery(document).ready(function(){
	jQuery('.tx-solr-q').autocomplete(
		{
			source: function(request, response) {
				jQuery.ajax({
					url: tx_solr_suggestUrl,
					dataType: 'json',
					data: {
							// TODO
						term: request.term.toLowerCase()
					},
					success: function(data) {
						var rs = new Array();
						var output = new Array();

						i = 0;
						for (var term in data) {
							var unformatted_label = term + ' <span class="result_count">(' + data[term] + ')</span>';
							output[i++] = {
								label	: unformatted_label.replace(new RegExp('(?![^&;]+;)(?!<[^<>]*)(' +
											jQuery.ui.autocomplete.escapeRegex(request.term) +
											')(?![^<>]*>)(?![^&;]+;)', 'gi'), '<strong>$1</strong>'),
								value  : term
							};
						}

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