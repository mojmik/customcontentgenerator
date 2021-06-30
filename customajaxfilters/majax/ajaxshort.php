<?php
/*
 this feeds ajax from wordpress with minimal loading
*/
namespace CustomAjaxFilters\Majax;

define('SHORTINIT', true);
define('DOING_AJAX', true);

function logWrite($val) {
    file_put_contents("log2.txt",date("d-m-Y h:i:s")." ".$val."\n",FILE_APPEND | LOCK_EX);
 }

//IMPORTANT: Change with the correct path to wp-load.php in your installation
require_once( '../../../wp-load.php' );

global $wpdb;

wp_cookie_constants();

require_once(plugin_dir_path( __FILE__ ) . '/MajaxWP/majaxrender.php');
require_once(plugin_dir_path( __FILE__ ) . '/MajaxWP/majaxhandlershort.php');
require_once(plugin_dir_path( __FILE__ ) . '/MajaxWP/customfields.php');
require_once(plugin_dir_path( __FILE__ ) . '/MajaxWP/customfield.php');
require_once(plugin_dir_path( __FILE__ ) . '/MajaxWP/majaxitem.php');
$renderer = new MajaxWP\MajaxRender(false);

$checkNonce=false;
if ($checkNonce) {
    require_once( ABSPATH . WPINC . '/user.php' );
    require_once( ABSPATH . WPINC . '/capabilities.php' );
    require_once( ABSPATH . WPINC . '/class-wp-user.php' );
    require_once( ABSPATH . WPINC . '/class-wp-roles.php' );
    require_once( ABSPATH . WPINC . '/class-wp-role.php' );
    require_once( ABSPATH . WPINC . '/class-wp-session-tokens.php' );
    require_once( ABSPATH . WPINC . '/class-wp-user-meta-session-tokens.php' );
    require_once( ABSPATH . WPINC . '/pluggable.php' );
    check_ajax_referer(MajaxWP\MajaxHandlerShort::NONCE,'security');    
}

$action=$_POST["action"];

$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM wp_posts WHERE post_type like '%s' LIMIT 10", 'mauta' ), ARRAY_A);

$renderer->showRows($rows);
