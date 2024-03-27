<?php
//
//
//$filename = file('result/merged_bg.csv',FILE_IGNORE_NEW_LINES);
//
//$yearCounts = [];
//
//foreach ($filename as $line) {
//
//    $parts = explode('}##{', $line);
//
//    $resultDate = preg_match('/\d{4}/', $parts[4], $m);
//    $resultDate = $m[0] ?? null;
//
//
//    if (isset($yearCounts[$resultDate])) {
//        $yearCounts[$resultDate]++;
//    } else {
//        $yearCounts[$resultDate] = 1;
//    }
//}
//
//
//
//ksort($yearCounts, SORT_REGULAR);
//
//
//$outputFile = 'date.csv';
//$outputHandle = fopen($outputFile, 'a+');
//
//
//fputcsv($outputHandle, ['Year', 'Count']);
//
//
//foreach ($yearCounts as $year => $count) {
//    fputcsv($outputHandle, [$year, $count]);
//}
//
//fclose($outputHandle);
//
//echo "Results written to date.csv\n";
