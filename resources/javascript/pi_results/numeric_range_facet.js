var timers = {};
var requestUrls = {};
function solrRangeRequest(facetName, delimiter) {
	clearTimeout(timers[facetName]);
	timers[facetName] = setTimeout(function() {
		url = jQuery("#facet-" + facetName + "-url").val();
		start = jQuery("#facet-" + facetName + "-range").slider("values", 0);
		end = jQuery("#facet-" + facetName + "-range").slider("values", 1);
		requestUrls[facetName] = url + start + delimiter + end;
		if ((start !== "") && (end !== "")) {
			window.location.href = requestUrls[facetName];
		}
	}, 1500);
}