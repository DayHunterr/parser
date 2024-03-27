<?php
//
//$papagal = file('result/sorted_papagal.csv',FILE_IGNORE_NEW_LINES);
//$finaci = file('result/finaci_bg.csv',FILE_IGNORE_NEW_LINES);
//
//$buf = [];
//$stream = fopen('merged_bg.csv','a+');
//
//foreach ($finaci as $finaciItem){
//    $financiData = explode('}##{',$finaciItem);
//    if(!isset($buf[$financiData[2]])){
//        fwrite($stream,$finaciItem.PHP_EOL);
//        $buf[$financiData[2]] = true;
//    }
//}
//
//foreach ($papagal as $papagalItem){
//    $papagalData = explode('}##{',$papagalItem);
//
//    if(!isset($buf[$papagalData[2]])){
//        fwrite($stream,$papagalItem.PHP_EOL);
//        $buf[$papagalData[2]] = true;
//    }
//}
//fclose($stream);
//
//echo "Merged file created successfully!\n";
//
//
