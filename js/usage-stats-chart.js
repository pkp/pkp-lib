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
				ticks: {
					maxTicksLimit: 13,
				},
			},
			y: {
				grid: {
					color: 'rgba(0,0,0,0.05)',
					drawTicks: false,
				},
				ticks: {
					beginAtZero: true
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

		// Build full history arrays — one entry per month from first publication to now
		var allTimeData = [],
			allTimeLabels = [],
			currentDate = new Date(),
			currentYear = currentDate.getFullYear(),
			currentMonth = currentDate.getMonth() + 1; // 1-indexed
		var years = Object.keys(graphData.data).map(Number).sort();
		for (var y = 0; y < years.length; y++) {
			var year = years[y];
			var lastMonth = (year === currentYear) ? currentMonth : 12;
			for (var month = 1; month <= lastMonth; month++) {
				allTimeData.push(graphData.data[year][month] || 0);
				allTimeLabels.push(pkpUsageStats.locale.months[month - 1] + ' ' + year);
			}
		}

		// Default view: last 12 months
		var dataArray = allTimeData.length > 12 ? allTimeData.slice(-12) : allTimeData;
		var labelsArray = allTimeLabels.length > 12 ? allTimeLabels.slice(-12) : allTimeLabels;

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

		// Add toggle button when there is more than 12 months of data
		if (allTimeData.length > 12) {
			(function(chart, graph, allTimeData, allTimeLabels) {
				var showingAllTime = false;
				var toggleBtn = document.createElement('button');
				toggleBtn.type = 'button';
				toggleBtn.className = 'usageStatsToggle';
				toggleBtn.textContent = pkpUsageStats.locale.allTime;
				toggleBtn.addEventListener('click', function() {
					showingAllTime = !showingAllTime;
					chart.data.labels = showingAllTime ? allTimeLabels : allTimeLabels.slice(-12);
					chart.data.datasets[0].data = showingAllTime ? allTimeData : allTimeData.slice(-12);
					chart.update();
					toggleBtn.textContent = showingAllTime
						? pkpUsageStats.locale.lastYear
						: pkpUsageStats.locale.allTime;
				});
				graph.parentNode.insertBefore(toggleBtn, graph.nextSibling);
			})(pkpUsageStats.charts[objectType + '_' + objectId], graph, allTimeData, allTimeLabels);
		}

		// Fire an event when the chart is initialized
		initializedEvent = document.createEvent('Event');
		initializedEvent.initEvent('usageStatsChartLoaded.pkp', true, true);
		graph.dispatchEvent(initializedEvent);
	}
})();
