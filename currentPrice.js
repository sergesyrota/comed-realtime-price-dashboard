function currentPrice(elementId) {
// URL for data endpoint
var url = 'data.php?currentPrice';

var gaugeOptions = {

    chart: {
        type: 'solidgauge',
        backgroundColor: 'transparent',
    },

    title: null,

    pane: {
        center: ['50%', '85%'],
        size: '140%',
        startAngle: -90,
        endAngle: 90,
        background: {
            backgroundColor: (Highcharts.theme && Highcharts.theme.background2) || '#EEE',
            innerRadius: '60%',
            outerRadius: '100%',
            shape: 'arc'
        }
    },

    tooltip: {
        enabled: false
    },

    // the value axis
    yAxis: {
        stops: [
            [0.3, '#55BF3B'], // green
            [0.5, '#DDDF0D'], // yellow, 8c
            [0.7, '#DF5353'] // red, 12c
        ],
        lineWidth: 0,
        minorTickInterval: null,
        tickAmount: 2,
        title: {
            y: -70
        },
        labels: {
            y: 16
        }
    },

    plotOptions: {
        solidgauge: {
            dataLabels: {
                y: 5,
                borderWidth: 0,
                useHTML: true
            }
        }
    }
};

// Current price
var chartCurrent = Highcharts.chart(elementId, Highcharts.merge(gaugeOptions, {
    yAxis: {
        min: 0,
        max: 20,
        title: {
            text: 'Current cost'
        }
    },

    credits: {
        enabled: false
    },

    series: [{
        name: 'Speed',
        data: [0],
        dataLabels: {
            format: '<div style="text-align:center"><span style="font-size:25px;color:' +
                ((Highcharts.theme && Highcharts.theme.contrastTextColor) || 'black') + '">{y}</span><br/>' +
                   '<span style="font-size:12px;color:silver">c/kWh</span></div>'
        },
        tooltip: {
            valueSuffix: ' c/kWh'
        }
    }]

}));

function updateCurrentPrice(price) {
    if (chartCurrent) {
        point = chartCurrent.series[0].points[0];
        point.update(parseFloat(price));
    }
    // set update for next time period
    setTimeout(getCurrentPrice, 300000);
}
function getCurrentPrice() {
  $.get(url, function(data) {updateCurrentPrice(parseFloat(data));});
}
setTimeout( getCurrentPrice, 1000);
};