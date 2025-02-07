function historyChart(elementId) {
    var dayAheadUrl = 'data.php?dayAheadToday';
    var todayUrl = 'data.php?todayHourly';
    var oldUrl = 'data.php?oldPrice';
    var freeSupplyUrl = 'data.php?freeSupplyPrice';
    var kwhUrl = 'data.php?consumedKwhToday';
    var todaysConsumption = [];

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
        yAxis: [{
            title: {
                text: 'Price per kWh in cents'
            },
            min: 4,
            minRange: 10,
        }, {
            title: {
                text: 'Price in $'
            },
            minRange: 1,
            visible: false
        }],
        plotOptions: {
            line: {
                dataLabels: {
                    enabled: false
                },
                marker: {
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
            marker: {
                enabled: true
            },
            data: []
        }, {
            name: 'Old cost',
            data: []
        }, {
            name: 'Today',
            yAxis: 1,
            dataLabels: {
                enabled: true,
                format: '${point.y:,.2f}'
            },
            data: []
        }, {
            name: 'Free Supply',
            data: [],
            type: 'area',
            color: "#d3d3d3",
            fillOpacity: 0.3,
            dataLabels: {
                enabled: false
            },
            marker: {
                enabled: false
            },
            enableMouseTracking: false,
            lineWidth: 0
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
            consumedCosts = [];
            for (i=0; i<24; ++i) {
                if (data[i] === null) {
                    consumedCosts.push(null);
                } else {
                    consumedCosts.push(data[i]*todaysConsumption[i]/100);
                }
            }
            console.log(consumedCosts);
            priceSeries = chart.series[3];
            priceSeries.setData(consumedCosts, true, false);
            
            // cost data
            chartSeries = chart.series[1];
            chartSeries.setData(data, true, false);
        }
        setTimeout(getToday, 600000);
    }
    function getToday() {
        $.get(todayUrl, function(data) {updateToday(JSON.parse(data));});
    }
    setTimeout(getToday, 1000);

    function updateConsumed(data) {
        todaysConsumption = data;
        setTimeout(getConsumed, 600000);
    }
    function getConsumed() {
        $.get(kwhUrl, function(data) {updateConsumed(JSON.parse(data));});
    }
    setTimeout(getConsumed, 1000);
    
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
    
    function updateFree(data) {
        if (chart) {
            chartSeries = chart.series[4];
            chartSeries.setData(data, true, false);
        }
    }
    $.get(freeSupplyUrl, function(data) {
        var price = parseFloat(data);
        var arr = [];
        for (i=0; i<24; i++) {
            arr.push(price);
        }
        updateFree(arr);
    });
    
}