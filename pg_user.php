<?php

require_once( "pg_db.php" );

// =====================================================================
// utilities to determine current user and page owner
// =====================================================================
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

// =====================================================================
// User settings
// =====================================================================
function pgUser_publicAccess($username = "") {
    if($username=="")
        $username = pg_getPageUsername();
    return pg_query($username, "publicAccess");
}

function pgUser_createPosts($username = "") {
    if($username=="")
        $username = pg_getPageUsername();
    return pg_query($username, "createPosts" );
}

function pgUser_emailFrequency($username = "") {
    if($username=="")
        $username = pg_getPageUsername();
    return pg_query($username, "emailFrequency" );
}
function pg_query($username, $query) {
    $url = pg_serverUrl() . "/admin.php";
    $atts['query']    = $query;
    $atts['username'] = $username;
    // PHP post requests were disabled by hostgator.  lame.
    $resp = post_request($url, $atts);
    //$resp = get_request($url, $atts);
    $s = json_decode($resp, true);
    return $s[$query];
}


// =====================================================================
// Periodic email
// =====================================================================
function pg_run_daily() {
    $users = pg_allUsers();
    $ret = "Sending mail to: ";
    foreach ($users as $username) {
        if(pgUser_emailFrequency($username) == "daily") {
            $ret .= " " . pg_sendStatusEmail($username, "daily");
        }
    }
    return $ret;
}
function pg_run_weekly() {
    $users = pg_allUsers();
    $ret = "Sending mail to: ";
    foreach($users as $username) {
        if(pgUser_emailFrequency($username) == "weekly") {
            $ret .= " " . pg_sendStatusEmail($username, "weekly");
        }
    }
    return $ret;
}
function pg_sendStatusEmail($username, $frequency) {
    $email    = pg_getEmail($username);
    $cert     = pg_getCert($username);
    $server   = pg_hostUrl();
    $url  = $server . "/webclient/wp.php";
    $url .= "?username=" . urlencode($username);
    $url .= "&cert="     . urlencode($cert);
    $url .= "&server="   . urlencode($server);

    if($frequency=="daily")
        $url .= "&hist=7";
    else
        $url .= "&hist=30";

    // Make status graphs for each category
    $attachments = array();
    $categories = pg_query($username, "categories");//["Meditate", "Exercise"];
    foreach($categories as $cat) {
        $finalURL = $url . "&category=" . $cat;
        $cmd = "php " . __DIR__ . "/pg_screenshot.php \"$finalURL\" $username $cat";
        $filename = shell_exec($cmd);
        array_push($attachments, $filename);
    }

    $to          = $email;
    $subject     = "Psygraph $frequency status";
    $body        = "<p>Dear $username,</p>";
    $body       .= "<p>Attached is the graph of your recent activity.</p>";
    $body       .= "<p>This graph was generated from a read-only version of the app at: $url</p>";
    $body       .= "<p>Kind regards, <br/><i>-Psygraph</i></p>";
    $body       .= '<hr/>';
    //$body       .= '<p>Attachments:</p><p>'. implode("</p><p>", $attachments) . '</p>';
    $headers     = array('Content-Type: text/html; charset=UTF-8');

    if( wp_mail( $to, $subject, $body, $headers, $attachments ) ) {
        //echo 'The test message was sent. Check your email inbox.';
    }
    else {
        //echo 'The message was not sent!';
    }
    return $email;
}
function pg_sendUploadEmail($username, $title, $postURL, $mediaURL) {
    $email = pg_getEmail($username);
    $cert  = pg_getCert($username);

    //$postURL .= "?username=" . urlencode($username);
    //$postURL .= "&cert="     . urlencode($cert);

    $to          = $email;
    $subject     = "Psygraph Note Uploaded";
    $body        = "<p>Dear $username,</p>";
    $body       .= "<p>Your recent Psygraph note \"$title\" has been uploaded.</p>";
    $body       .= "<p>If you have turned on public access, that content is available at:</p>";
    $body       .= "<ul>";
    if($postURL != "") {
        $body .= "<li>Post: $postURL</li>";
    }
    if($mediaURL != "") {
        $body .= "<li>Media: $mediaURL</li>";
    }
    $body       .= "</ul>";
    $body       .= "<p>Kind regards, <br/> <i>-Psygraph</i></p>";
    $headers     = array('Content-Type: text/html; charset=UTF-8');
    wp_mail( $to, $subject, $body, $headers);
}

function pg_getEmail($username) {
    $user = get_user_by('login', $username);
    $user_info = get_userdata($user->ID);
    $email = $user_info->user_email;
    return $email;
}
// =====================================================================
// If a user deletes their WP account, delete all media files they uploaded using Psygraph.
// =====================================================================
function pg_allUsers() {
    global $wpdb;
    $table_name = pg_getTableName();
    // select users from the database
    $sql = "SELECT username FROM $table_name";
    //$results = $wpdb->query($sql);
    $results = $wpdb->get_results($sql);
    $names = array();
    foreach($results as $user) {
        $names[] = $user->username;
    }
    return $names;
}

function pg_deleteUserCB($userID) {
    global $wpdb;
    $table_name = pg_getTableName();

    // delete the user from the database
    $user = get_userdata($userID);
    $username = $user->user_login;
    $sql = "DELETE FROM $table_name WHERE username='$username'";
    $wpdb->query($sql);

    // delete all media for the user
    $ids = pg_getAllEventIDs($username);
    foreach ($ids as $id) {
        if( false === wp_delete_attachment( $id ) ) {
            // Log failure to delete attachment.
        }
    }
    /*
    $postID = pg_getUserPostID($username);
    if(!$postID)
        return;
    $attachments = get_posts( array(
                                  'post_type'      => 'attachment',
                                  'posts_per_page' => -1,
                                  'post_status'    => 'any',
                                  'post_parent'    => $postID
                                  ) );
    foreach ( $attachments as $file ) {
        if ( false === wp_delete_attachment( $file->ID ) ) {
            // Log failure to delete attachment.
        }
    }
    wp_delete_post($postID);
    */
}

?>