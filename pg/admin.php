<?php

include_once("pg.php");
include_once("in.php");

$FORM = getHttpParams();

if(isset($FORM['query']) && isset($FORM['username']) ) {
    if($FORM['query'] == "publicAccess") {
        $uid = getIDFromUsername( $FORM["username"] );
        $data = array();
        $data['publicAccess'] = getUserDataValue($uid, "publicAccess");
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
    $response  = "<h2>Changes processed.</h2>\n";

    $publicAccess = getUserDataValue($FORM['uid'], "publicAccess");
    $paString = $publicAccess ? "on" : "off";
    $response .= "Public access set to '".$paString."'\n";

    $response .= "<hr/>";
    $response .= "<p><a href='javascript:history.back()'>Go back</a></p>\n";
    printHTMLResult($response);
}
else {
    $url = $FORM['server'] ."/admin.php";
    $response  = '<div id="pgUpload">';
    $response .= '<form action="'.$url.'" method="post" enctype="multipart/form-data">';

    // publicAccess
    $pa = getUserDataValue($FORM['uid'], "publicAccess");
    $checked = $pa ? "checked" : "";
    $response .= '<p><em>By checking the following box, you are allowing URL-access to your data on this server.</em></p>';
    $response .= '<label for="publicAccess">Allow public access:</label>';
    $response .= '<input name="publicAccess" id="publicAccess"  type="checkbox" '.$checked.' /><br/><br/>';

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
