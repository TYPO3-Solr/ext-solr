/* Dutch initialisation for the jQuery UI date picker plugin. */
/* Written by alterNET Internet BV (support@alternet.nl). */
jQuery(function($){
	$.datepicker.regional['nl'] = {
		closeText: 'sluiten',
		prevText: '&#x3c;vorige',
		nextText: 'volgende&#x3e;',
		currentText: 'vandaag',
		monthNames: ['januari','februari','maart','april','mei','juni',
		'juli','augustus','september','oktober','november','december'],
		monthNamesShort: ['jan','feb','mrt','apr','mei','jun',
		'jul','aug','sep','okt','nov','dec'],
		dayNames: ['zondag','maandag','dinsdag','woensdag','donderdag','vrijdag','zaterdag'],
		dayNamesShort: ['zo','ma','di','wo','do','vr','za'],
		dayNamesMin: ['zo','ma','di','wo','do','vr','za'],
		weekHeader: 'Wk',
		dateFormat: 'dd-mm-yy',
		firstDay: 1,
		isRTL: false,
		showMonthAfterYear: false,
		yearSuffix: ''};
	$.datepicker.setDefaults($.datepicker.regional['nl']);
});
