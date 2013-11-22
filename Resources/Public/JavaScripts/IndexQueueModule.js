jQuery(function() {
    var stats = JSON.parse(jQuery('#indexqueue_stats').text());

    var keys = ["indexed", "erroneous", "pending"];
    for(var i=0; i < keys.length; i++) {
        stats[keys[i]] = parseInt(stats[keys[i]] || 0)
    }

    var data = [];
    data.push({value: stats.indexed, color: "#9FC299"});
    data.push({value: stats.erroneous, color: "#FF3D3D"});
    data.push({value: stats.pending, color: "#EB813F"});
    console.log(stats);
    var total = stats.indexed + stats.erroneous + stats.pending;

    function setLegendNumbers(id, partial, total) {
        jQuery(id).text(partial + ' (' + (partial / total * 100).toFixed(2)  + '%)')
    }

    setLegendNumbers('#pending_numbers', stats.pending, total);
    setLegendNumbers('#error_numbers', stats.erroneous, total);
    setLegendNumbers('#indexed_numbers', stats.indexed, total);


    var ctx = document.getElementById("indexqueue_stats_chart").getContext("2d");
    new Chart(ctx).Pie(data, {animation: false});



    jQuery('.show_error').bind('click', function() {
        var obj = jQuery(this);
        var error = jQuery.trim(obj.prev().text());

        var w = window.open('', '', 'width='+screen.width+',height=400,resizeable,scrollbars');
        w.document.write("<pre>" + error);
        w.document.close();
    })
});
