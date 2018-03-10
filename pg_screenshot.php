<?php

$url      = $argv[1];
$filename = $argv[2];
$tempfile = str_replace(".png",".b64", $filename);
$cmd      = "node /home/arogers/bin/screenshot.js \"$url\" $tempfile";

//echo $cmd; return;

system($cmd);
base64_to_image($tempfile, $filename);


function base64_to_image($tempfile, $filename) {
    $base64_string = file_get_contents($tempfile);
    $data = explode( ',', $base64_string );
    // $data[ 0 ] == "data:image/png;base64"
    // $data[ 1 ] == <actual base64 string>

    $ifp = fopen( $filename, 'wb' );
    $c = base64_decode( $data[ 1 ] );
    fwrite( $ifp, $c );
    fclose( $ifp );
}

?>