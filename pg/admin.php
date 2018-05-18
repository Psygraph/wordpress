<?php

include_once("pg.php");
include_once("in.php");

$FORM = getHttpParams();

if(isset($FORM['query']) && isset($FORM['username']) ) {
    $query = $FORM['query'];
    if($query == "publicAccess" || $query == "createPosts" || $query == "emailFrequency") {
        $uid  = getIDFromUsername( $FORM["username"] );
        $data = array();
        $data[$query] = getUserDataValue($uid, $query);
        $jdata = json_encode($data, true);
        printResult($jdata);
        exit(0);
    }
    elseif($query == "categories") {
        $uid  = getIDFromUsername( $FORM["username"] );
        $allCategories = getCategories($uid);
        $cats = array();
        for($j=0; $j<count($allCategories); $j++) {
            $cid = $allCategories[$j][0];
            $cats[] = getCategoryNameFromID($cid);
        }
        $data[$query] = $cats;
        $jdata = json_encode($data, true);
        printResult($jdata);
        exit(0);
    }
    exit(1);
}

$FORM = handleLogin($FORM);

if($FORM['uid'] < 0) {
    printLoginFail($FORM);
}

$username = $FORM['username'];
$cert = $FORM['cert'];
$reason = checkUserPermission($username, "write");
if($reason) {
    printResult( $reason );
}

$resp = "";
if(isset( $FORM['respond']) && $FORM['respond']) {
    $publicAccess = strcmp($FORM['publicAccess'], "on") ? 0 : 1;
    setUserDataValue($FORM['uid'], "publicAccess", $publicAccess);
    $createPosts = strcmp($FORM['createPosts'], "on") ? 0 : 1;
    setUserDataValue($FORM['uid'], "createPosts", $createPosts);
    $emailFrequency = $FORM['emailFrequency'];
    setUserDataValue($FORM['uid'], "emailFrequency", $emailFrequency);
    $response  = "<h2>Changes processed.</h2>\n";

    $publicAccess = getUserDataValue($FORM['uid'], "publicAccess");
    $string = $publicAccess ? "on" : "off";
    $response .= "Public access: ".$string."<br/>\n";

    $createPosts = getUserDataValue($FORM['uid'], "createPosts");
    $string = $createPosts ? "on" : "off";
    $response .= "Create posts: ".$string."<br/>\n";

    $emailFrequency = getUserDataValue($FORM['uid'], "emailFrequency");
    $response .= "Email Frequency: ".$emailFrequency."<br/>\n";

    $response .= "<hr/>";
    $response .= "<p><a href='javascript:history.back()'>Go back</a></p>\n";
    printHTMLResult($response);
}
else {
    $url = $FORM['server'] ."/admin.php";
    $response  = '<div id="pgUpload">';
    $response .= '<form action="'.$url.'" method="post" enctype="multipart/form-data">';

    // publicAccess
    $string    = getUserDataValue($FORM['uid'], "publicAccess");
    $checked   = $string ? "checked" : "";
    $response .= '<p><em>Allow public web access to your data on this server.</em></p>';
    $response .= '<label for="publicAccess">Public access:</label>';
    $response .= '<input name="publicAccess" id="publicAccess"  type="checkbox" '.$checked.' /><br/><br/>';

    $string    = getUserDataValue($FORM['uid'], "createPosts");
    $checked   = $string ? "checked" : "";
    $response .= '<p><em>Create public WordPress posts on this server.</em></p>';
    $response .= '<label for="createPosts">Create posts:</label>';
    $response .= '<input name="createPosts" id="createPosts"  type="checkbox" '.$checked.' /><br/><br/>';

    $string    = getUserDataValue($FORM['uid'], "emailFrequency");
    $response .= '<p><em>Specify how often you would like to receive emails showing your progress.</em></p>';
    $response .= '<label for="emailFrequency">Email frequency:</label>';
    $response .= '<select name="emailFrequency" id="emailFrequency">';
    $selected  = $string=="never"? "selected" : "";
    $response .= '<option value="never" '.$selected.'>never</option>';
    $selected  = $string=="daily"? "selected" : "";
    $response .= '<option value="daily" '.$selected.'>daily</option>';
    $selected  = $string=="weekly"? "selected" : "";
    $response .= '<option value="weekly" '.$selected.'>weekly</option>';
    $response .= '</select><br/><br/>';

    $response .= '<input type="hidden" name="respond"  value="true"  id="respond"/>';
    $response .= '<input type="hidden" name="server"   value="'. $FORM['server']. '"   id="format"/>';
    $response .= '<input type="hidden" name="username" value="'. $FORM['username']. '" id="user"/>';
    $response .= '<input type="hidden" name="cert"     value="'. $FORM['cert']. '"     id="cert"/>';
    $response .= '<input type="submit" name="submit" value="Submit"/>';
    $response .= '<br/>';
    $response .= '</form>';
    $response .= '</div>';
    printHTMLResult($response);
}

?>
