<?php

$url      = $argv[1];
$user     = $argv[2];
$filename = "/public/screenshot/images/status.png";
$cmd      = "/public/screenshot/screenshot \"$url\" $filename";

system($cmd);
echo $filename;

?>