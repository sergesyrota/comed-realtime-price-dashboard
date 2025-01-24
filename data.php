<?php

date_default_timezone_set('America/Chicago');

if (!empty($argv[1])) {
    $data = new ComedData();
    var_dump($data->dayAheadToday());
    // var_dump($data->todayHourly());
    // var_dump($data->get5MinutePrices());
    var_dump($data->currentPrice());
    die();
}

$allowedFunctions = ['currentPrice', 'todayHourly', 'dayAheadToday', 'oldPrice', 'consumedKwhToday', 'myCostVsAvgCost'];

$func = $_SERVER['QUERY_STRING'];
if (!in_array($func, $allowedFunctions)) {
    throwError($code, "'$func' is not a recognized data point.");
}

try {
    $data = new ComedData();
    echo json_encode(call_user_func([$data, $func]));
} catch (Exception $e) {
    throwError(500, $e->getMessage());
}

class ComedData {
    private $distributionCharge = 3.6;
    private $transmissionCharge = 1.0;
    private $capacityCharge = 1.6;

    public function __construct()
    {}

    public function currentPrice() {
        $fiveMinute = $this->get5MinutePrices();
        $avgSoFar = $fiveMinute['averagePrice'];
        $knownMinutes = $fiveMinute['knownMinutes'];
        $unknownMinutes = 60 - $knownMinutes;

        $dayAhead = $this->dayAheadToday();
        $currentHour = (int)date('G');
        $dayAheadPrice = isset($dayAhead[$currentHour]) ? $dayAhead[$currentHour] : $this->currentPrice();

        // Giving double the weight to known minutes, so that we're more biased toward what we've seen already.
        $predictedAvg = ($avgSoFar * $knownMinutes * 2 + $dayAheadPrice * $unknownMinutes) / (2 * $knownMinutes + $unknownMinutes);
        return round($predictedAvg,1); // other charges already included
    }

    public function todayHourly() {
        $data = file_get_contents(
            'https://hourlypricing.comed.com/rrtp/ServletFeed?type=day&date='.date('Ymd')
        );
        return $this->parseServletFeed($data); // Not really going to ever have last hour, as it's all live data, and then switched to tomorrow.
    }

    public function dayAheadToday() {
        $data = file_get_contents('https://hourlypricing.comed.com/rrtp/ServletFeed?type=daynexttoday&date='.date('Ymd'));
        $dataTomorrow = file_get_contents('https://hourlypricing.comed.com/rrtp/ServletFeed?type=daynexttoday&date='.date('Ymd', time() + 24*3600));
        return $this->parseServletFeed($data, $dataTomorrow);
    }

    public function get5MinutePrices() {
        $startTs = date('YmdH01'); // This shows prices for the time *ending* in the specified timestamp
        $endTs = date('YmdH59');
        $url = 'https://hourlypricing.comed.com/api?type=5minutefeed&datestart=' . $startTs . '&dateend=' . $endTs;
        $data = json_decode(file_get_contents($url), true);
        
        $totalPrice = 0;
        
        foreach ($data as $entry) {
            $totalPrice += $entry['price'];
        }
        
        $averagePrice = count($data) > 0 ? $totalPrice / count($data) : 0;
        
        return [
            'averagePrice' => $averagePrice + $this->distributionCharge + $this->transmissionCharge + $this->capacityCharge,
            'knownMinutes' => count($data)*5,
            'data' => $data
        ];
    }

    private function parseServletFeed($textToday, $textTomorrow = null) {
        $regex = '%\[Date.UTC\(\d+,\d+,\d+,(?P<hour>\d+),\d+,\d+\), (?P<price>[-\d\.]+)\]%';
        preg_match_all($regex, $textToday, $matchesToday);
        $res = [];
        unset($matchesToday['price'][0]); // Discard first result, as it's for hour ending at midnight today
        foreach ($matchesToday['price'] as $id=>$price) {
            $res[$matchesToday['hour'][$id]] = (float)$price + $this->transmissionCharge + $this->distributionCharge + $this->capacityCharge;
        }
        $resHourly = [];
        for ($i=1; $i<24; $i++) {
            $resHourly[] = (empty($res[$i]) ? null : $res[$i]);
        }
        // What is this doing?
        for($i=22; $i>0; $i--) {
            if ($resHourly[$i] !== null) break;
            unset($resHourly[$i]);
        }
        if ($textTomorrow !== null) {
            // Last hour of today is not shown today, but instead is in tomorrow's feed.
            preg_match_all($regex, $textTomorrow, $matchesTomorrow);
            $resHourly[] = (float)$matchesTomorrow['price'][0] + $this->transmissionCharge + $this->distributionCharge + $this->capacityCharge;

        }
        return $resHourly;
    }

    public function oldPrice() {
        return 7.1 + $this->transmissionCharge + $this->distributionCharge;
    }
    
    public function consumedKwhToday() {
        $xml = `rrdtool xport --step 3600 DEF:data=/var/lib/munin/local/srv1.local-energy_monitor_all_py-total-d.rrd:42:AVERAGE XPORT:data:Data -s -1days`;
        preg_match_all('%<row><t>(?P<tsEnd>[\d]+)<\/t><v>(?P<data>[\d\.e\-\+]+)<\/v><\/row>%', $xml, $matches);
        $data = [];
        foreach ($matches['tsEnd'] as $k=>$tsEnd) {
            if (date('d') != date('d', $tsEnd-3600)) {
                continue;
            }
            $point = [
                'tsStart' => $tsEnd-3600,
                'tsEnd'   => $tsEnd,
                'humanTime' => date('m-d H:i', $tsEnd-3600),
                'data'    => $matches['data'][$k],
                'kWh'     => (float)$matches['data'][$k] * 3.5967048427
            ];
            //print_r($point);
            $data[] = $point['kWh'];
        }
        return $data;
    }
    
    public function myCostVsAvgCost() {
        $today = $this->todayHourly();
        $consumed = $this->consumedKwhToday();
        $avg = array_sum($today) / count($today);
        $totalKwh = $totalCost = 0.0;
        foreach ($today as $i=>$cost) {
            $totalKwh += $consumed[$i];
            $totalCost += $consumed[$i] * $cost;
        }
        $myAvg = $totalCost / $totalKwh;
        return ['avg' => $avg, 'myAvg' => $myAvg];
    }
}

function throwError($code, $msg) {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit(1);
}
