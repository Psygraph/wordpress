<?php

require_once( "pg_db.php" );

function pg_getCurrentUsername() {
    $current_user     = wp_get_current_user();
    $current_username = $current_user->user_login;
    return $current_username;
}

function pg_getPageUsername() { // return the user's page
    $username = "foobar";
    if(get_query_var('pg_username')) {
        $username = get_query_var('pg_username');
        if($username == "current")
            $username = pg_getCurrentUsername();
    }
    #else {
    #    $post_id = get_the_ID();
    #    $author_id = get_post_field( 'post_author', $post_id );
    #    $user = get_user_by('id', $author_id);
    #    $username = $user->user_login;
    #}
    return $username;
}
function pg_isOwner() {
    return pg_getPageUsername() == pg_getCurrentUsername();
}
function pg_allowPublicAccess() {
    return pg_query( pg_getPageUsername() );
}

// [pg_link] return a link
function pg_linkShortcode( $atts ) {
    $username = pg_getPageUsername();
    $cert     = pg_getCert($username);
    //return $cert;
    // set defaults
    $atts = shortcode_atts( array(
                                'username' => "$username",
                                'cert'     => "$cert",
                                'linktext' => "here",
                                'format'   => "",
                                'max'      => "",
                                'start'    => "",
                                'end'      => "",
                                'id'       => "",
                                'page'     => "",
                                'type'     => "",
                                'interval' => "none"
                                ), $atts );
    
    // generate a link to a file
    $url = "<a href=\"";
    $url .= pg_serverUrl() . "/output.php";
    $url .= "?server="   . urlencode(pg_serverUrl());
    $url .= "&format="   . $atts['format'];
    $url .= "&username=" . $atts['username'];
    if(pg_isOwner())
        $url .= "&cert="     . $atts['cert'];
    $url .=  "\">"       . $atts['linktext'] . "</a>";
    return $url;
}

// [pg_events] return an html-formatted list of events
function pg_eventsShortcode( $atts ) {
    $username = pg_getPageUsername();
    $cert     = pg_getCert($username);

    // use WP timezone
    $min    = 60 * get_option('gmt_offset');
    $sign   = $min < 0 ? "-" : "+";
    $absmin = abs($min);
    $tz     = sprintf("%s%02d:%02d", $sign, $absmin/60, $absmin%60);
    
    // set defaults
    $atts = shortcode_atts( array(
                                'username' => "$username",
                                'cert'     => "$cert",
                                'max'      => "",
                                'start'    => "",
                                'end'      => "",
                                'id'       => "",
                                'page'     => "",
                                'category' => "",
                                'type'     => "",
                                'display'  => "",
                                'tz'       => $tz,
                                'signal'   => "events",
                                'height'   => "640px",
                                'width'    => "100%"
                                ), $atts );

    $atts['server']   = pg_serverUrl();
    $atts['embedded'] = true;
    $height   = $atts['height'];
    $width    = $atts['width'];

    if($atts['display'] == "graph") {
        return generateDisplay("graph", $atts, $height, $width);
    }
    else if($atts['display'] == "map") {
        return generateDisplay("map", $atts, $height, $width);
    }
    else if($atts['display'] == "bar") {
        return generateDisplay("bar", $atts, $height, $width);
    }
    else if($atts['display'] == "list") {
        return generateDisplay("list", $atts, $height, $width);
    }
    else {
        return "<p>Unknown display type (".$atts['display'].") requested.</p>";
    }
}

// [pg_page] return a page
function pg_pageShortcode( $atts ) {
    $username = pg_getPageUsername();
    $cert     = pg_getCert($username);

    // set defaults
    $atts = shortcode_atts( array(
                                'page'     => "client",
                                'format'   => "csv",
                                'username' => "$username",
                                'cert'     => "$cert",
                                'width'    => "100%",
                                'height'   => "640px"
                                ), $atts );

    $username = $atts['username'];
    $cert     = $atts['cert'];
    $page     = $atts['page'];
    $height   = $atts['height'];
    $width    = $atts['width'];


    if($page == "input") {
        $format = $atts['format'];
        return generateInput($username, $cert, $format, $height, $width);
    }
    else if($page == "audio") {
        return generateAudio($username, $height, $width);
    }
    else if($page == "client") {
        $username     = pg_getCurrentUsername();
        $cert         = pg_getCert($username);
        return generatePsygraph($username, $cert, $height, $width);
    }
    else if($page == "user") {
        $username     = pg_getCurrentUsername();
        $cert         = pg_getCert($username);
        return generateUser($username, $height, $width);
    }
    else if($page == "admin") {
        $username     = pg_getCurrentUsername();
        $cert         = pg_getCert($username);
        return generateAdmin($username, $cert, $height, $width);
    }
    else if($page == "generateLink") {
        $username     = pg_getCurrentUsername();
        $server       = pg_serverUrl();
        return generateLink($username, $server, $cert, $height, $width);
    }
    else {
        return "<p>Unknown page requested: $page</p>";
    }
}

function generatePsygraph($username, $cert, $height, $width) {
    $server = pg_serverUrl();
    $url  = "https://psygraph.com/webclient/wp.php";
    $url .= "?username=" . urlencode($username);
    // OK to use cert here, since it is only for the current_user.
    $url .= "&cert="     . urlencode($cert);
    $url .= "&server="   . urlencode($server);
    
    // the iframe will get resized to 100% if its ID remains psygraph
    $response = '<iframe id="psygraph" src="'.$url.'" height="'.$height.'" width="'.$width.'" allowfullscreen="true"></iframe>';
    return $response;
}

function generateUser($username, $cert, $height, $width) {
    $fn = __DIR__ . "/user_page.html";
    $content = "<p>Could not load file: 'user_page.html'.</p>";
    if( file_exists($fn) ) {
        $content = file_get_contents($fn);
    }
    return $content;
}

function generateAudio($username, $height, $width) {
    $text = "";
    if(pg_isOwner() || pg_allowPublicAccess()) {
        $ids = pg_getAllMediaIDs($username);
        $text = implode(",", $ids);
    }
    $content  = '<div style="div {margin-left: 40px;}" class="wpview-clipboard" contenteditable="true">';
    $content .= do_shortcode( '[playlist ids="' . $text . '"]');
    $content .= '</div>';
    return $content;
}

function generateInput($username, $cert, $format, $height, $width) {
    $url = pg_serverUrl() . "/input.php";
    $response  = '<div id="pgUpload">';
    $response .= '<form action="'.$url.'" method="post" enctype="multipart/form-data">';
    $response .= '<label for="file">Filename: </label>';
    $response .= '<input type="file"   name="pgFile"                     id="pgFile"/>';
    $response .= '<br/><br/>';
    $response .= '<input type="hidden" name="respond"  value="true"      id="respond"/>';
    $response .= '<input type="hidden" name="format"   value="'.$format.'"   id="format"/>';
    $response .= '<input type="hidden" name="username" value="'.$username.'" id="user"/>';
    if(pg_isOwner())
        $response .= '<input type="hidden" name="cert"     value="'.$cert.'"     id="cert"/>';
    $response .= '<input type="submit" name="submit" value="Submit"/>';
    $response .= '<br/>';
    $response .= '</form>';
    $response .= '</div>';
    return $response;
}

function generateAdmin($username, $cert, $height, $width) {
    $response = "";
    if(pg_isOwner()) {
        $server = pg_serverUrl();
        $url  = $server . "/admin.php?username=" . urlencode($username);
        $url .= "&cert="     . urlencode($cert);
        $url .= "&server="   . urlencode($server);
        
        $response .= '<iframe id="pg_admin" src="'.$url.'" height="'.$height.'" width="'.$width.'" allowfullscreen="true"></iframe>';
    }
    else {
        $response .= '<p>You must be the logged-in owner of this page in order to make administrative changes.</p>';
    }
    return $response;
}

function generateLink($username, $server, $cert, $height, $width) {
    $response = "";
    if(pg_isOwner() || pg_allowPublicAccess()) {
        $url  = $server . "/generateLink.php?username=" . urlencode($username);
        if(!pg_allowPublicAccess())
            $url .= "&cert="     . urlencode($cert);
        $url .= "&server="   . urlencode($server);
        
        $response .= '<iframe id="pg_link" src="'.$url.'" height="'.$height.'" width="'.$width.'" allowfullscreen="true"></iframe>';
    }
    return $response;
}

function generateDisplay($name, $atts, $height, $width) {
    $repsonse = "";
    if(pg_isOwner() || pg_allowPublicAccess()) {
        $url = pg_serverUrl() . "/page.php";
        // write atts to the URL
        $num = 0;
        foreach ($atts as $name => $value) {
            if($num++ == 0)
                $url .= "?$name=" . urlencode($value);
            else
                $url .= "&$name=" . urlencode($value);
        }
        
        $response = '<iframe id="pg_'.$name.'" src="'.$url.'" height="'.$height.'" width="'.$width.'" allowfullscreen="true"></iframe>';
    }
    else {
        $username = pg_getPageUsername();
        $response = "This user ($username) does not allow data to be viewed publically.";
    }
    return $response;
}

function pg_query($username) {
    $url = pg_serverUrl() . "/admin.php";    
    $atts['query'] = "publicAccess";
    $atts['username'] = $username;
    // PHP post requests were disabled by hostgator.  lame.
    //$resp = post_request($url, $atts);
    $resp = get_request($url, $atts);
    $s = json_decode($resp, true);
    return $s['publicAccess'];
}

function post_request($url, $data, $optional_headers = null, $getresponse = true) {
    $data = http_build_query($data);
    $proto = "http";
    //if(preg_match("/https/", $url))
    //    $proto = "https";
    $params = array($proto => array(
        'method' => 'POST',
        'content' => $data,
        'header'=> "Content-type: application/x-www-form-urlencoded\r\n"
        . "Content-Length: " . strlen($data) . "\r\n",
        //'header' => 'Content-Type: application/x-www-form-urlencoded' . PHP_EOL
    ));
    
    if ($optional_headers !== null) {
        $params[$proto]['header'] = $optional_headers;
    }
    $ctx = stream_context_create($params);
    $fp = @fopen($url, 'rb', false, $ctx);
    if (!$fp) {
        return "";
    }
    if ($getresponse){
        $response = stream_get_contents($fp);
        return $response;
    }
    return "";
}
function get_request($url, $data, $optional_headers = null, $getresponse = true) {
    $data = http_build_query($data);
    $url .= "?".$data;
    $proto = "http";
    //if(preg_match("/https/", $url))
    //    $proto = "https";
    $params = array($proto => array(
        'method' => 'GET'
        //'header' => 'Content-Type: application/x-www-form-urlencoded' . PHP_EOL
    ));
    
    if ($optional_headers !== null) {
        $params[$proto]['header'] = $optional_headers;
    }
    $ctx = stream_context_create($params);
    $fp = @fopen($url, 'rb', false, $ctx);
    if (!$fp) {
        return "";
    }
    if ($getresponse){
        $response = stream_get_contents($fp);
        return $response;
    }
    return "";
}


?>