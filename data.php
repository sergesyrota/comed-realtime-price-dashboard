<?php

$allowedFunctions = ['currentPrice', 'todayHourly', 'dayAheadToday', 'oldPrice'];

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
    private $capacityCharge = 2.83;

    public function __construct()
    {}

    public function currentPrice() {
        $data = json_decode(file_get_contents('https://hourlypricing.comed.com/api?type=currenthouraverage'));
        $price = $data[0]->price + $this->distributionCharge + $this->capacityCharge;
        return $price;
    }

    public function todayHourly() {
        $data = file_get_contents('https://hourlypricing.comed.com/rrtp/ServletFeed?type=day&date='.date('Ymd'));
        return $this->parseServletFeed($data);
    }

    public function dayAheadToday() {
        $data = file_get_contents('https://hourlypricing.comed.com/rrtp/ServletFeed?type=daynexttoday&date='.date('Ymd'));
        return $this->parseServletFeed($data);
    }

    private function parseServletFeed($text) {
        preg_match_all('%\[Date.UTC\(\d+,\d+,\d+,\d+,\d+,\d+\), (?P<price>[-\d\.]+)\]%', $text, $matches);
        $res = [];
        foreach ($matches['price'] as $price) {
            $res[] = (float)$price + $this->distributionCharge + $this->capacityCharge;
        }
        return $res;
    }

    public function oldPrice() {
        return 7.06 + $this->distributionCharge;
    }
}

function throwError($code, $msg) {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit(1);
}
