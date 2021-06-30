<?php
/*
 this feeds ajax from wordpress with minimal loading
*/
namespace CustomAjaxFilters\Majax;
use \CustomAjaxFilters\Admin as MajaxAdmin;

header('Content-Type: text/html');
header( 'X-Content-Type-Options: nosniff' );
header('Cache-Control: no-cache');
header('Pragma: no-cache');

define('SHORTINIT', true);
define('DOING_AJAX', true);


require_once( '../../../../../wp-config.php' );



require_once(plugin_dir_path( __FILE__ ) . '/majaxwp/customfields.php');
require_once(plugin_dir_path( __FILE__ ) . '/majaxwp/customfield.php');
require_once(plugin_dir_path( __FILE__ ) . '/majaxwp/majaxhtmlelements.php');
require_once(plugin_dir_path( __FILE__ ) . '/majaxwp/majaxform.php');
require_once(plugin_dir_path( __FILE__ ) . '/majaxwp/majaxloader.php');
require_once(plugin_dir_path( __FILE__ ) . '/majaxwp/majaxquery.php');
require_once(plugin_dir_path( __FILE__ ) . '/majaxwp/majaxrender.php');
require_once(plugin_dir_path( __FILE__ ) . '/majaxwp/majaxitem.php');
require_once(plugin_dir_path( __FILE__ ) . '/majaxwp/caching.php');
require_once(plugin_dir_path( __FILE__ ) . '/majaxwp/mikdb.php');
require_once(plugin_dir_path( __FILE__ ) . '/majaxwp/imagecache.php');
require_once(plugin_dir_path( __FILE__ ) . '/majaxwp/translating.php');
require_once(plugin_dir_path( __FILE__ ) . '../admin/importcsv.php');
require_once(plugin_dir_path( __FILE__ ) . '../admin/settings.php');
define('CAF_TAB_PREFIX','mauta_');
define('CAF_MAJAX_PATH',plugin_dir_path( __FILE__ ));
//define( 'CAF_MAJAX_PLUGIN_URL', plugin_dir_url( __FILE__ ) . "majax/");

$action=$_POST["action"];
$atts["language"]=(empty($_POST["language"])) ? "" : $_POST["language"];
$atts["type"]=(empty($_POST["mautaCPT"])) ? "" : $_POST["mautaCPT"];

if ($action=="formInit") {
	$renderer = new MajaxWP\MajaxRender(false,$atts); //use false pro preloading hardcoded fields (save one sql query)
	$renderer->showFormFields($_POST["mautaCPT"]);	
}
if ($action=="contact_filled") {
	$renderer = new MajaxWP\MajaxRender(true,$atts); //use false pro preloading hardcoded fields (save one sql query)
	MajaxWP\MikDb::init(DB_HOST,DB_USER,DB_PASSWORD,DB_NAME);		
	if (isset($_POST["category"])) {
		$renderer->getMajaxLoader()->getPostIdFromAjax();
		$rows=$renderer->getMajaxLoader()->getSingle();
		$renderer->showRows($rows,["custTitle" => "single","miscAction"=>"contactFilled"]);		
	}    
	else {
		//form without posts
		$renderer->showFormFilled("contactFilled","kontakt form");
	}	
	exit;
}
if ($action=="single_row") {
	$renderer = new MajaxWP\MajaxRender(true,$atts); //use false pro preloading hardcoded fields (save one sql query)
	MajaxWP\MikDb::init(DB_HOST,DB_USER,DB_PASSWORD,DB_NAME);		
	$renderer->getMajaxLoader()->getPostIdFromAjax();
	$rows=$renderer->getMajaxLoader()->getSingle();
	$renderer->showRows($rows,["custTitle" => "single","miscAction"=>"action"]);		
	exit;
}
if ($action=="filter_rows") {
	$renderer = new MajaxWP\MajaxRender(true,$atts); //use false pro preloading hardcoded fields (save one sql query)
	MajaxWP\MikDb::init(DB_HOST,DB_USER,DB_PASSWORD,DB_NAME);	
	
	$page=intval($_POST["aktPage"]);
	$buildCounts=MajaxAdmin\Settings::loadSetting("buildCounts","site");	
	/*
	buildcounts	loads all rows and slice arrays afterwards. might choke in big sites
	if no form filters shown, this is not needed
	*/
	if ($buildCounts) {
		$query=$renderer->getMajaxQuery()->produceSQL(["postAll" => true]);
		$rows=MajaxWP\Caching::getCachedRows($query);
		$countsJson=MajaxWP\Caching::getCachedJson("json_$query");
		$countsRows=$renderer->buildCounts($rows,$countsJson);	
		if (!$countsJson) {
			MajaxWP\Caching::addCache("json_$query",$countsRows);
		}
		$renderer->showRows($countsRows,["custTitle" => "majaxcounts","limit"=>0]);
		$renderer->showRows($renderer->filterMetaSelects($rows),["aktPage" => $page,"sliceArray"=>true]);		
	} else {
		$rows=$renderer->getMajaxLoader()->getMulti(["page" => $page]);
		$renderer->showRows($renderer->filterMetaSelects($rows),["aktPage" => $page]);		
	}
	
	
	exit;
}


