function SuggestController() {

    this.init = function () {

        jQuery('form[data-suggest]').each(function () {
            var $form = $(this), $searchBox = $form.find('.tx-solr-suggest'), $formAutoComplete;

            if ($form.find('.tx-solr-autocomplete').length > 0){
                $formAutoComplete = $form.find('.tx-solr-autocomplete');
            } else {
                $formAutoComplete = $('body');
            }

            $form.find('.tx-solr-suggest-focus').focus();

            jQuery.ajaxSetup({jsonp: "tx_solr[callback]"});

            // when no specific container found, use the form as container
            if ($searchBox.length === 0) {
                $searchBox = $form;
            }
            $searchBox.css('position', 'relative');

            // Prevent submit of empty search form
            $form.on('submit', function (e) {
                if ($form.find('.tx-solr-suggest').val() === '') {
                    e.preventDefault();
                    $form.find('.tx-solr-suggest').focus();
                }
            });

            $form.find('.tx-solr-suggest').devbridgeAutocomplete({
                serviceUrl: $form.data('suggest'),
                dataType: 'jsonp',
                paramName: 'tx_solr[queryString]',
                groupBy: 'category',
                maxHeight: 1000,
                appendTo: $formAutoComplete,
                autoSelectFirst: false,
                triggerSelectOnValidInput: false,
                width: $searchBox.outerWidth() * 0.66,
                onSelect: function (suggestion) {
                    // go to link when selecting found result
                    if (suggestion.data.link) {
                        // Open youtube in overlay
                        if (suggestion.data.link.indexOf('https://www.youtube.com') === 0) {
                            openVideoOverlay(suggestion.data.link);
                        } else {
                            location.href = suggestion.data.link;
                        }
                        // else trigger form submit (do search)
                    } else {
                        $form.trigger('submit');
                    }
                },
                transformResult: function (response) {
                    if (!response.suggestions) return {suggestions: []};
                    var firstSuggestion, result = {
                        suggestions: $.map(response.suggestions, function (count, suggestion) {
                            if (!firstSuggestion) firstSuggestion = suggestion;
                            return {value: suggestion, data: {category: 'suggestion', count: count}};
                        })
                    };

                    $.each(response.documents, function (key, value) {
                        var dataObject = value;

                        var defaultGroup = $form.data('suggest-header') ? $form.data('suggest-header') : 'Top results';
                        dataObject.category = defaultGroup;

                        // if a group is set we try to get a label
                        if(dataObject.group) {
                            dataObject.category = $form.data('suggest-header-' + dataObject.group) ? $form.data('suggest-header-' + dataObject.group) : dataObject.group;
                        }

                        result.suggestions.push(
                            {
                                value: firstSuggestion,
                                data: dataObject
                            }
                        );
                    });

                    return result;
                },
                beforeRender: function (container) {
                    // remove first group header
                    container.find('.autocomplete-group:first').remove();
                    container.addClass('tx-solr-autosuggest');

                    // add active class to container
                    $searchBox.parent().addClass('autocomplete-active').fadeIn();
                },
                formatResult: function (suggestion, currentValue) {
                    // Do not replace anything if there current value is empty
                    if (!currentValue) {
                        return suggestion.value;
                    }
                    var pattern = '(' + $.Autocomplete.utils.escapeRegExChars(currentValue.trim()) + ')';
                    // normal suggestion
                    if (suggestion.data.category === 'suggestion') {
                        return suggestion.value
                            .replace(new RegExp(pattern, 'gi'), '<strong>$1<\/strong>')
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/&lt;(\/?strong)&gt;/g, '<$1>');

                        // results
                    } else {
                        var title = suggestion.data.title
                            .replace(new RegExp(pattern, 'gi'), '<em>$1<\/em>')
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/&lt;(\/?em)&gt;/g, '<$1>');

                        return '<div class="' + suggestion.data.type + '">' +
                            (!!suggestion.data.previewImage ? '<figure ' + (!!suggestion.data.hasVideo ? 'class="hasVideo"' : '') + '><img src="' + suggestion.data.previewImage + '" /></figure>' : '') +
                            '<a href="' + suggestion.data.link + '" class="internal-link">' + title + '</a>' +
                            '</div>';
                    }

                }
            }).on('blur', function () {
                $searchBox.parent().removeClass('autocomplete-active');
                var $box = $(this);
                setTimeout(function () {
                    $box.devbridgeAutocomplete('hide');
                }, 200);
            });
        });
    };
}

jQuery(document).ready(function() {
    /** solr search autocomplete **/
    var solrSuggestController = new SuggestController();
    solrSuggestController.init();

    jQuery("body").on("tx_solr_updated", function() {
        solrSuggestController.init();
    });
});

