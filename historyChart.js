function historyChart(elementId) {
    var dayAheadUrl = 'data.php?dayAheadToday';
    var todayUrl = 'data.php?todayHourly';
    var oldUrl = 'data.php?oldPrice';

    var chart = Highcharts.chart(elementId, {
        chart: {
            type: 'line'
        },
        legend: {
            align: 'right',
            layout: 'vertical',
        },
        title: {
            text: 'Today\'s price and forecast'
        },
        xAxis: {
            categories: ['12', '1','2','3','4','5','6','7','8','9','10','11','12','1','2','3','4','5','6','7','8','9','10','11']
        },
        yAxis: {
            title: {
                text: 'Price per kWh in cents'
            },
            min: 5,
            minRange: 10,
        },
        plotOptions: {
            line: {
                dataLabels: {
                    enabled: false
                },
                enableMouseTracking: false
            }
        },
        series: [{
            name: 'Projection',
            data: []
        }, {
            name: 'Actual',
            dataLabels: {
                enabled: true
            },
            data: []
        }, {
            name: 'Old cost',
            data: []
        }]
    });
    
    function updateDayAhead(data) {
        if (chart) {
            chartSeries = chart.series[0];
            chartSeries.setData(data, true, false);
        }
    }
    function getDayAhead() {
        $.get(dayAheadUrl, function(data) {updateDayAhead(JSON.parse(data));});
    }
    setTimeout(getDayAhead, 1000);
    
    function updateToday(data) {
        if (chart) {
            chartSeries = chart.series[1];
            chartSeries.setData(data, true, false);
        }
        setTimeout(getToday, 600000);
    }
    function getToday() {
        $.get(todayUrl, function(data) {updateToday(JSON.parse(data));});
    }
    setTimeout(getToday, 1000);
    
    function updateOld(data) {
        if (chart) {
            chartSeries = chart.series[2];
            chartSeries.setData(data, true, false);
        }
    }
    $.get(oldUrl, function(data) {
        var price = parseFloat(data);
        var arr = [];
        for (i=0; i<24; i++) {
            arr.push(price);
        }
        updateOld(arr);
    });
    
}