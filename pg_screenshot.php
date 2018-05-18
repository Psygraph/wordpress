<?php

$url      = $argv[1];
$user     = $argv[2];
$cat      = $argv[3];
$filename = "temp_$cat.png";
$filename = "/public/screenshot/images/$filename";
$cmd      = "/public/screenshot/screenshot \"$url\" $filename";

system($cmd);

print($filename);

?>