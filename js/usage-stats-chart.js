/**
 * @file js/usage-stats-chart.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief A small handler to initialize Chart.js graphs on the frontend.
 */
(function () {
	/*global pkpUsageStats, Chart */

	if (
		typeof pkpUsageStats === 'undefined' ||
		typeof pkpUsageStats.data === 'undefined'
	) {
		return;
	}

	var graphs, noStatsNotice;

	// Check for .querySelectorAll in browser support
	try {
		graphs = document.querySelectorAll('.usageStatsGraph');
		noStatsNotice = document.querySelectorAll('.usageStatsUnavailable');
	} catch (e) {
		return;
	}

	// Hide the unavailable stats notice when a chart is loaded
	document.addEventListener('usageStatsChartLoaded.pkp', function (e) {
		for (var i = 0; i < noStatsNotice.length; i++) {
			if (
				typeof noStatsNotice[i].dataset.objectType !== 'undefined' &&
				typeof noStatsNotice[i].dataset.objectId !== 'undefined' &&
				noStatsNotice[i].dataset.objectType === e.target.dataset.objectType &&
				noStatsNotice[i].dataset.objectId === e.target.dataset.objectId
			) {
				noStatsNotice[i].parentNode.removeChild(noStatsNotice[i]);
			}
		}
	});

	// Define default chart options
	var chartOptions = {
		plugins: {
			legend: {
				display: false,
			},
		},
		tooltip: {
			titleFontColor: '#333',
			bodyFontColor: '#333',
			footerFontColor: '#333',
			backgroundColor: '#ddd',
			cornerRadius: 2,
		},
		elements: {
			line: {
				borderColor: 'rgba(0,0,0,0.4)',
				borderWidth: 2,
				borderJoinStyle: 'round',
				backgroundColor: 'rgba(0,0,0,0.3)',
				tension: 0.5,
				fill: true,
			},
			bar: {
				backgroundColor: 'rgba(0,0,0,0.3)',
			},
			point: {
				radius: 2,
				hoverRadius: 6,
				borderWidth: 2,
				hitRadius: 5,
			},
		},
		scales: {
			x: {
				grid: {
					color:
						pkpUsageStats.config.chartType === 'bar'
							? 'transparent'
							: 'rgba(0,0,0,0.05)',
					drawTicks: false,
				},
			},
			y: {
				grid: {
					color: 'rgba(0,0,0,0.05)',
					drawTicks: false,
				},
			},
		},
	};

	// Fire an event to allow third-party customization of the options
	var optionsEvent = document.createEvent('Event');
	optionsEvent.initEvent('usageStatsChartOptions.pkp', true, true);
	optionsEvent.chartOptions = chartOptions;
	document.dispatchEvent(optionsEvent);

	var graph, objectType, objectId, graphData, initializedEvent;
	pkpUsageStats.charts = {};
	for (var g = 0; g < graphs.length; g++) {
		graph = graphs[g];

		// Check for markup we can use
		if (
			typeof graph.dataset.objectType === 'undefined' ||
			typeof graph.dataset.objectId === 'undefined'
		) {
			continue;
		}

		objectType = graph.dataset.objectType;
		objectId = graph.dataset.objectId;

		// Check that data exists for this graph
		if (
			typeof pkpUsageStats.data[objectType] === 'undefined' ||
			pkpUsageStats.data[objectType][objectId] === 'undefined'
		) {
			continue;
		}

		// Do nothing if there's no data for this chart
		if (typeof pkpUsageStats.data[objectType][objectId].data === 'undefined') {
			graph.parentNode.removeChild(graph);
			continue;
		}

		graphData = pkpUsageStats.data[objectType][objectId];

		// Turn the data set into an array
		var dataArray = [],
			labelsArray = [],
			currentDate = new Date(),
			currentYear = currentDate.getFullYear(),
			currentMonth = currentDate.getMonth();
		// Get the data from the last year
		for (var month = currentMonth + 1; month <= 11; month++) {
			if (!(currentYear - 1 in graphData.data)) {
				dataArray.push(0);
			} else {
				dataArray.push(graphData.data[currentYear - 1][month + 1]);
			}
			labelsArray.push(pkpUsageStats.locale.months[month]);
		}
		// Get the data from the current year
		for (month = 0; month <= currentMonth; month++) {
			if (!(currentYear in graphData.data)) {
				dataArray.push(0);
			} else {
				dataArray.push(graphData.data[currentYear][month + 1]);
			}
			labelsArray.push(pkpUsageStats.locale.months[month]);
		}
		pkpUsageStats.charts[objectType + '_' + objectId] = new Chart(graph, {
			type: pkpUsageStats.config.chartType,
			data: {
				labels: labelsArray,
				datasets: [
					{
						label: graphData.label,
						data: dataArray,
					},
				],
			},
			options: chartOptions,
		});

		// Fire an event when the chart is initialized
		initializedEvent = document.createEvent('Event');
		initializedEvent.initEvent('usageStatsChartLoaded.pkp', true, true);
		graph.dispatchEvent(initializedEvent);
	}
})();
