<?php
  /*
Plugin Name: Psygraph
Plugin URI: http://psygraph.com
Description: This plugin integrates with the Psygraph mobile app (an app that tracks your meditation, breathing, and mindfulness) to visualize your data in WordPress.  The provided shortcodes can do things like generate progress charts, show the history of meditation sessions, and allow playback of recorded audio notes.
Version: 0.8.6
Author: Alec Rogers
Author URI: http://arborrhythms.com
License: http://creativecommons.org/licenses/by-sa/4.0/
  */


// activate/deactivate plugin
require_once(plugin_dir_path(__FILE__)."/pg_db.php");
require_once(plugin_dir_path(__FILE__)."/pg_user.php");
require_once(plugin_dir_path(__FILE__)."/pg_settings.php");


// remove any data when a user is deleted
add_action('delete_user', 'pg_deleteUserCB');

// add shortcode 
require_once(plugin_dir_path(__FILE__)."/pg_shortcode.php");
add_shortcode('pg_page',   'pg_pageShortcode');
add_shortcode('pg_events', 'pg_eventsShortcode');
add_shortcode('pg_link',   'pg_linkShortcode');

// add xml-rpc methods for Psygraph server
require_once(plugin_dir_path(__FILE__)."/pg_xmlrpcMethods.php");
require_once(plugin_dir_path(__FILE__)."/pg_wp_functions.php");
add_filter('xmlrpc_methods', 'pg_xmlrpcMethods' );

// add a settings page
require_once(plugin_dir_path(__FILE__)."/pg_settings.php");
add_action('admin_menu', 'pg_settings_add_page');
add_action('admin_init', 'pg_settings_init');

$pg_plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$pg_plugin", 'pg_settings_link' ); 

// process a query var instead of creating a post for each user
function pg_query_vars($aVars) {
    $aVars[] = "pg_username";
    return $aVars;
}
add_filter('query_vars', 'pg_query_vars');

// hook pg_rewrite_rule into rewrite_rules_array
function pg_init() {
    $page = pg_settingsValue("page");
    add_rewrite_rule('^'.$page.'/([^/]+)/?', 'index.php?pagename=psygraph_template&pg_username=$matches[1]', 'top');
    wp_register_style('psygraph', plugins_url('psygraph.css',__FILE__ ));
    wp_register_script( 'psygraph', plugins_url('psygraph.js',__FILE__ ));
}
add_action('init', 'pg_init');

// use the registered psygraph js and css
function pg_enqueue_scripts() {
    wp_enqueue_style('psygraph');
    wp_enqueue_script('psygraph');
    wp_enqueue_script('jquery');
}

add_action('wp_enqueue_scripts', 'pg_enqueue_scripts');

// =====================================================================
// Handle plugin installation and removal
// =====================================================================
function pg_activate() {
    global $wpdb;
    $table_name = pg_getTableName();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
      username varchar(45) NOT NULL PRIMARY KEY,
      cert varchar(45) DEFAULT NULL,
      time int(11) DEFAULT NULL
    ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
    $wpdb->query( $sql );

    pg_createTemplate();

    // Create a settings file for local installs of the pg server
    // Make sure it is chmod 0600
    $params = getPGPrefs(pg_wp_getVars());
    $creds = request_filesystem_credentials(site_url() . '/wp-admin/', '', false, false, array());
    if ( ! WP_Filesystem($creds) ) {
        return;
    }
    global $wp_filesystem;
    $wp_filesystem->put_contents(__DIR__."/pgConfig.xml", $params, (0600 & ~ umask()) ); //WP_PLUGIN_DIR."/psygraph/pgConfig.xml"

    // add weekly and daily events
    if (! wp_next_scheduled ( 'pg_weekly' )) {
        wp_schedule_event(strtotime("Sunday 01:00"), 'weekly', 'pg_weekly');
    }
    add_action('pg_weekly', 'pg_run_weekly');
    if (! wp_next_scheduled ( 'pg_daily' )) {
        wp_schedule_event(strtotime("today 01:00"), 'daily', 'pg_daily');
    }
    add_action('pg_daily', 'pg_run_daily');
}
register_activation_hook( __FILE__, 'pg_activate' );

function pg_deactivate() {
    global $wpdb;
    $table_name = pg_getTableName();

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $wpdb->query( "DROP TABLE IF EXISTS ".$table_name .";");

    wp_clear_scheduled_hook('pg_weekly');
    wp_clear_scheduled_hook('pg_daily');
}
register_deactivation_hook( __FILE__, 'pg_deactivate' );

function pg_uninstall() {
    pg_deleteTemplate();
}
register_uninstall_hook( __FILE__, 'pg_uninstall' );


?>