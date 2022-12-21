<?php


if (!empty($argv[1])) {
    $data = new ComedData();
    var_dump($data->dayAheadToday());
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
        $data = json_decode(file_get_contents('https://hourlypricing.comed.com/api?type=currenthouraverage'));
        $price = $data[0]->price + $this->distributionCharge + $this->capacityCharge;
        return $price;
    }

    public function todayHourly() {
        $data = file_get_contents(
            'https://hourlypricing.comed.com/rrtp/ServletFeed?type=day&date='.date('Ymd')
        );
        $dataTomorrow = file_get_contents(
            'https://hourlypricing.comed.com/rrtp/ServletFeed?type=day&date='.date('Ymd', time() + 24*3600)
        );
        return $this->parseServletFeed($data, $dataTomorrow);
    }

    public function dayAheadToday() {
        $data = file_get_contents('https://hourlypricing.comed.com/rrtp/ServletFeed?type=daynexttoday&date='.date('Ymd'));
        $dataTomorrow = file_get_contents('https://hourlypricing.comed.com/rrtp/ServletFeed?type=daynexttoday&date='.date('Ymd', time() + 24*3600));
        return $this->parseServletFeed($data, $dataTomorrow);
    }

    private function parseServletFeed($textToday, $textTomorrow) {
        $regex = '%\[Date.UTC\(\d+,\d+,\d+,\d+,\d+,\d+\), (?P<price>[-\d\.]+)\]%';
        preg_match_all($regex, $textToday, $matchesToday);
        preg_match_all($regex, $textTomorrow, $matchesTomorrow);
        $res = [];
        unset($matchesToday['price'][0]); // Discard first result, as it's for hour ending at midnight today
        foreach ($matchesToday['price'] as $price) {
            $res[] = (float)$price + $this->transmissionCharge + $this->distributionCharge + $this->capacityCharge;
        }
        // Last hour of today is not shown today, but instead is in tomorrow's feed.
        $res[] = (float)$matchesTomorrow['price'][0] + $this->transmissionCharge + $this->distributionCharge + $this->capacityCharge;
        return $res;
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

