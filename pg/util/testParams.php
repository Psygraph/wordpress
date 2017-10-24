<?php
// preferences file for tests

require_once("./http.php");

function getParams($argv) {
    
    $username = $argv[1];
    $password = $argv[2];
    
    // set a few defaults
    $params['url'] = "http://192.168.108.3/psygraph/wp-content/plugins/psygraph/pg";
    $params['format'] = "csv";
    $params['tempDir'] = sys_get_temp_dir();
    
    // get parameters from the command line
    for( $i=3; $i<count($argv); $i+=2 ) {
        $params[$argv[$i]] = $argv[$i+1];
    }


    // get the cert, since we dont put the password into the form.
    $params['username'] = $username;
    $tmpParams['username'] = $username;
    $tmpParams['password'] = $password;
    $tmpParams['url'] = $params['url'];
    $out = test_command($tmpParams, "getCert");
    if(strlen($out) != 20) {
        print "INVALID CERT: ". $out ."\n";
        exit(-1);
    }
    $params['cert'] = $out;

    return $params;
}

?>