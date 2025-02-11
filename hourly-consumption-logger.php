<?php

date_default_timezone_set('UTC');

function getConsumedKwh() {
    
    // Total energy data
    $xml = `rrdtool xport --step 3600 DEF:data=/var/lib/munin/local/srv1.local-energy_monitor_all_py-total-d.rrd:42:AVERAGE XPORT:data:Data -s -9days`; // 9 days max for hourly
    preg_match_all('%<row><t>(?P<tsEnd>[\d]+)<\/t><v>(?P<data>[\d\.e\-\+Na]+)<\/v><\/row>%', $xml, $matches);
    
    // Charger energy data
    $chargeXml = `rrdtool xport --step 3600 DEF:data=/var/lib/munin/local/srv1.local-energy_monitor_one_py-c01-d.rrd:42:AVERAGE XPORT:data:Data -s -9days`;
    preg_match_all('%<row><t>(?P<tsEnd>[\d]+)<\/t><v>(?P<data>[\d\.e\-\+Na]+)<\/v><\/row>%', $chargeXml, $chargeMatches);

    $data = [];
    foreach ($matches['tsEnd'] as $k => $tsEnd) {
        $point = [
            'tsStart'      => $tsEnd - 3600,
            'tsEnd'        => $tsEnd,
            'humanTime'    => (new DateTime("@" . ($tsEnd - 3600)))
                                ->setTimezone(new DateTimeZone('UTC'))
                                ->format('c'),
            'chicagoTime'  => (new DateTime("@" . ($tsEnd - 3600)))
                                ->setTimezone(new DateTimeZone('America/Chicago'))
                                ->format('c'),
            'data'         => $matches['data'][$k],
            'kWh'          => (float)$matches['data'][$k] * 3.5967048427,
            'chargerkWh'   => (float)$chargeMatches['data'][$k] * 3.5967048427,
        ];
        $data[] = $point;
    }
    return $data;
}

// CLI script to update CSV (consumption.csv)
$dataPoints = getConsumedKwh();

// Define the CSV file location (placed in the same folder)
$csvFile = __DIR__ . '/hourly-consumption-log.csv';
$existingTsEnd = 0;

// If the file exists, load it to determine the latest tsEnd so far.
if (file_exists($csvFile)) {
    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        // Read header
        $header = fgetcsv($handle, 0, ",", "\"", "\\");
        while (($row = fgetcsv($handle, 0, ",", "\"", "\\")) !== FALSE) {
            // Assuming tsEnd is in the second column.
            $tsEnd = (int)$row[1];
            if ($tsEnd > $existingTsEnd) {
                $existingTsEnd = $tsEnd;
            }
        }
        fclose($handle);
    }
} else {
    // Create a new CSV file with header
    if (($handle = fopen($csvFile, "w")) !== FALSE) {
        fputcsv($handle, ["tsStart", "tsEnd", "humanTime", "chicagoTime", "data", "kWh", "chargerkWh"], ",", "\"", "\\");
        fclose($handle);
    }
}

// Open the CSV file in append mode
if (($handle = fopen($csvFile, "a")) !== FALSE) {
    foreach ($dataPoints as $point) {
        // Skip any rows already recorded (avoid duplicates)
        if ($point['tsEnd'] <= $existingTsEnd) {
            continue;
        }
        // Discard points less than 10 minutes after the hour-end.
        if (time() < $point['tsEnd'] + 600) {
            continue;
        }
        fputcsv(
            $handle, 
            [
                $point['tsStart'], 
                $point['tsEnd'], 
                $point['humanTime'], 
                $point['chicagoTime'], 
                $point['data'], 
                $point['kWh'],
                $point['chargerkWh']
            ],
            ",",
            "\"",
            "\\"
        );
    }
    fclose($handle);
}
