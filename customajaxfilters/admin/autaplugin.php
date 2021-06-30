<?php
namespace CustomAjaxFilters\Admin;
use \CustomAjaxFilters\Majax\MajaxWP as MajaxWP;

class AutaPlugin {		
	private $customPost=[];
	public static $textDomain="mauta-plugin";		
	public static $menuSlug="caf-main-settings";
       
    
	
	public function __construct() {			
        
		register_activation_hook( CAF_PLUGIN_FILE_URL, [$this,'caf_plugin_install'] );
		register_deactivation_hook( CAF_PLUGIN_FILE_URL, [$this,'caf_plugin_uninstall'] );
		add_action('admin_menu' , [$this,'pluginSettingsMenu']); 			
		add_action( 'wp_ajax_createCPT', [$this,'createCPTproc'] );
		//add_action( 'wp_ajax_editCPT', [$this,'editCPTproc'] );
		$this->loadCustomPosts();
		$this->editCPTproc();
		//admin		
	}
	
	function loadCustomPosts() {	
		global $wpdb;
		$query = "SELECT * FROM `".AutaPlugin::getTable("main")."`";	
		foreach( $wpdb->get_results($query) as $key => $row) {					
			$acp=new AutaCustomPost($row->slug,$row->singular,$row->plural,$row->specialType,$row->tableType); 
			$acp->adminInit();
			$this->customPost[]=$acp; 
			
			\CustomAjaxFilters\Majax\MajaxWP\Caching::checkPruneCacheNeeded($row->slug);											
		}	
	}
	function initWP() {
		add_action( 'admin_enqueue_scripts', [$this,'mautaEnqueueStylesAndScripts'], 11);
		
	}
	function mautaEnqueueStylesAndScripts() {				
		$mStyles=[
			 'mauta' => ['src' => plugin_dir_url( __FILE__ ) . 'mauta.css']			 
		];
		
		foreach ($mStyles as $key => $value) {
			$src = (isset($value["src"])) ? $value["src"] : $value["srcCdn"];
			$key = 'autawp-' . $key;
			wp_register_style($key, $src);
			wp_enqueue_style($key);
		}
		wp_enqueue_script( 'autapluginjs', plugin_dir_url( __FILE__ ) . 'auta-plugin.js', array('jquery') );		
	}

	public static function getTable($tab,$cpt="") {
	  global $wpdb;	
	  if ($tab=="main") return $wpdb->prefix.CAF_TAB_PREFIX."plugin_main";
	  if ($tab=="settings") return $wpdb->prefix.CAF_TAB_PREFIX."plugin_settings";
	  if ($tab=="fields") return $wpdb->prefix.CAF_TAB_PREFIX.$cpt."_fields";
	  if ($tab=="attachments") return $wpdb->prefix.CAF_TAB_PREFIX."attachments";
	  if ($tab=="dedicated") return $wpdb->prefix.CAF_TAB_PREFIX.$cpt."_ded";
	  return $wpdb->prefix.CAF_TAB_PREFIX.$tab;
	}

	function caf_plugin_install() {
		global $wpdb;			
		$table_name = AutaPlugin::getTable("main"); 
		
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
		  id mediumint(9) NOT NULL AUTO_INCREMENT,	
		  slug text NOT NULL,
		  singular text NOT NULL,
		  plural text DEFAULT '' NOT NULL,
		  specialType text DEFAULT '' NOT NULL,
		  tableType text DEFAULT '' NOT NULL,
		  PRIMARY KEY  (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		$table_name = AutaPlugin::getTable("settings"); 
		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,	
			opt text NOT NULL,
			val text NOT NULL,
			PRIMARY KEY  (id)
		  ) $charset_collate;";  
		  require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		  dbDelta( $sql );
		
		add_action('caf_cronhook',[$this,'caf_cron_job']);
		if ( ! wp_next_scheduled( 'caf_cronhook' ) ) {
			wp_schedule_event( time(), 'daily', 'caf_cronhook' );
		}
		$templating=new MajaxWP\MajaxHtmlElements();
		$templating->checkPath();
		Settings::checkPath();
	}
	function caf_plugin_uninstall() {
		wp_clear_scheduled_hook( 'caf_cronhook' );
	}
	function caf_cron_job() {		
		\CustomAjaxFilters\Majax\MajaxWP\Caching::pruneCache();		
	}
	 
	function pluginSettingsMenu() {    
		//adds menu item
		$page_title = CAF_SHORT_TITLE.' - settings';   
		$menu_title = CAF_SHORT_TITLE.' - settings';   
		$capability = 'manage_options';   
		$menu_slug  = AutaPlugin::$menuSlug;   
		$function   =  [$this,'mainSettings'];   
		$icon_url   = 'dashicons-media-code';   
		$position   = 5;    
		add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position ); 

		$parent_slug=$menu_slug;
		$page_title=CAF_SHORT_TITLE.' - custom types';		
		$menu_slug=basename(__FILE__);
		$function = [$this,'customposts_settings_page'];
		$menu_title=CAF_SHORT_TITLE.' - custom types';
		add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function);
		
	}
	
	function mainSettings() {		
		Settings::adminAllSettings(AutaPlugin::getTable("settings"));		
	}
	function createCPTproc() {
		global $wpdb;
		$cafAction=filter_input( INPUT_POST, "cafAction", FILTER_SANITIZE_STRING );  
		$singular=filter_input( INPUT_POST, "singular", FILTER_SANITIZE_STRING );  
		$plural=filter_input( INPUT_POST, "plural", FILTER_SANITIZE_STRING );  						 				
		if ($cafAction=="create") {
			$slug=sanitize_title($singular);
			$wpdb->insert( 
				AutaPlugin::getTable("main"), 
				array( 
					'slug' => $slug, 
					'singular' => $singular, 
					'plural' => $plural, 
				) 
			);
			$cpt=new AutaCustomPost($slug,$singular,$plural); 				
			$cpt->autaFields->makeTable("fields");						
			$this->customPost[]=$cpt;		
			\CustomAjaxFilters\Majax\MajaxWP\Caching::checkPath($slug);
		}	
		echo "ok, created ".$cpt->editCptHtml();
	}
	function editCPTproc() {
		global $wpdb;
		if (empty($_POST["cafActionEdit"])) return "";
		$cafAction=filter_input( INPUT_POST, "cafAction", FILTER_SANITIZE_STRING );  
		$singular=filter_input( INPUT_POST, "singular", FILTER_SANITIZE_STRING );  
		$plural=filter_input( INPUT_POST, "plural", FILTER_SANITIZE_STRING );  						 				
		$slug=filter_input( INPUT_POST, "slug", FILTER_SANITIZE_STRING );  		
		$specialType=(isset($_POST["specialType"])) ? "cj" : "";		
		$tableType=(isset($_POST["tableType"])) ? "dedicated" : "";
		if (isset($_POST["cafActionEdit"])) {
			$wpdb->update(AutaPlugin::getTable("main"), array('singular' => $singular, 'plural' => $plural, 'specialType' => $specialType, 'tableType' => $tableType), array('slug' => $slug) );
			foreach ($this->customPost as $cpt ) {
				if ($cpt->getCustomPostType()==$slug) {
					$cpt->singular=$singular;
					$cpt->plural=$plural;					  
					$cpt->specialType=$specialType;	
					$cpt->tableType=$tableType;	
				}
			}
		}
		if (isset($_POST["cafActionRemove"])) {
			$wpdb->delete( AutaPlugin::getTable("main"), array( 'slug' => $slug ) );			
			foreach ($this->customPost as $key=>$cpt ) {
				if ($cpt->getCustomPostType()==$slug) {
					$keyDel=$key;	
					$cpt->autaFields->makeTable("fields",true,true);
				}
			}
			if (isset($keyDel)) array_splice($this->customPost,$key,1);
			echo json_encode(["id"=>"mAutaEdit".$cpt->getCustomPostType()]);
			wp_die();
		}	
	}
	
	function customposts_settings_page() {  
		global $wpdb;
		?>
		<div id="mAutaCustomPosts">
		Custom posts definition
		<?php 
		if (isset($mess)) { ?>		
		 <h2><?=$mess ?></h2>
		<?php
		}
		?>
		<h2>New custom post</h2>
		<h3>Custom post create</h3>
		<form class='caf-editFieldRow createCPT'>			
			<div><div><label>singular name</label></div><input type='text' name='singular' value='' /></div>	
			<div><div><label>plural name</label></div><input type='text' name='plural' value='' /></div>
			<div><input type='submit' value='create' /></div>
			<input name='cafAction' type='hidden' value='create' />
		</form>
		
		<?php
		if (is_array($this->customPost) && count($this->customPost)>0) {
			?>
			<h3>Custom post edit</h3>
			<?php
			foreach ($this->customPost as $cpt) {			
			 echo $cpt->editCptHtml();
			}
		} else {
		?>
		<h3>Create some custom post types first!</h3>	
		<?php			
		}
		?>
		</div>
		<?php
	}


	
	static function logWrite($val,$arr=[]) {
	 if (is_array($arr) && count($arr)>0) {
		ob_start();
        var_dump($arr);
        $val .= ob_get_clean();
	 }
	 file_put_contents(plugin_dir_path( __FILE__ ) . "log.txt",date("d-m-Y h:i:s")." ".$val."\n",FILE_APPEND | LOCK_EX);
	}
}
