<?php
namespace CustomAjaxFilters\Majax\MajaxWP;
use \CustomAjaxFilters\Admin as MajaxAdmin;

Class Majax {
	private $ajaxHandler;
	private $majaxLoader;
	function __construct() {
		spl_autoload_register([$this,"mLoadClass"]);
		$this->majaxLoader=new MajaxLoader();
		if (CAF_MAJAX_FAST > 1) $this->ajaxHandler=new MajaxHandlerShort($this->majaxLoader); //shortinit lightweight version
		else $this->ajaxHandler=new MajaxHandler($this->majaxLoader); //ajax-admin version
		$this->ajaxHandler->register();	
						
	}
	
	function mLoadClass($class) {	
		if (strpos($class,"MajaxWP")!==0) return;
		$path=plugin_dir_path( __FILE__ ) . "majax/".str_replace("\\","/",strtolower("$class.php"));		
        require($path);
	}
	
	function initWP() {		
		add_action('caf_cronhook',[$this,'majax_cron_job']);
				
		
		//init actions		
		
		add_action( 'wp_enqueue_scripts', [$this,'mAjaxEnqueueScripts'] );			
		add_action( 'wp_enqueue_scripts', [$this,'majaxEnqueueStyle'], 11);

		//fronted posts (aby to bralo custom posty jako obyc. posty, tohle tady nebude potreba)
		//$cptAdmin=new MajaxAdmin\AutaCustomPost("zajezd");
		add_action( 'pre_get_posts', [$this,'addCptToQuery'] );	
		add_filter( 'document_title_parts', [$this,'filter_pagetitle'] );		
		
		add_action( 'plugins_loaded', [$this,'initHook'] );
		//add_filter( 'the_title', 'wpse_alter_title', 20, 2 );
	}
	private function preLoader() {
		
		$this->majaxLoader->initFromShortCode();	
	}
	public function filter_pagetitle( $title, $id=0 ) {	
		/*
		if ($this->pageTitle) {
			$title=wp_title();
			$title.=" - ".$this->pageTitle;
		}
		global $wp_query;
		if (isset($wp_query->post->post_title)){
			return $wp_query->post->post_title;
		}
		*/	
		//load posttype from shortcode
		//init majaxquery
		//preload posts
		$this->preLoader();
		$cjCat=$this->majaxLoader->getCurrentCat();
		if (!empty($cjCat)) {
			$title_parts['title'] = "".$cjCat["path"]; 
			$p0=strpos($cjCat["desc"],'</section>');
			if ($p0!==false) {
				$p0+=strlen("</section>");
				$p=strpos($cjCat["desc"],' "');
				$desc=substr($cjCat["desc"],$p0,$p-$p0);
				$title_parts['tagline'] = $desc;
			}
			
			$title_parts['site'] = get_bloginfo( 'name' );			
			return $title_parts;
		} else {						
			$customTitle=$this->majaxLoader->getTitle();
			if ($customTitle) { 
				$title_parts['title'] = $customTitle; 
				$title_parts['site'] = get_bloginfo( 'name' );			
				return $title_parts;
			}
		} 	
		return $title;		
	}
	function initHook() {
		//tohle se resi primo bez wp
		//MimgTools::handleRequest();		
	}
	
	function addCptToQuery( $query ) {
		/*
		if (  $query->is_main_query() && is_home()  )			
			$query->set( 'post_type', array( 'post', 'cj' ) );
		*/	
					
		return $query;
	}

	function majaxEnqueueStyle() {		
		$wp_scripts = wp_scripts();	
		$mStyles=[			 
			 'majax' => ['src' => CAF_MAJAX_PLUGIN_URL . 'majax.css'],
			 'select2' => ['src' => CAF_MAJAX_PLUGIN_URL .'select2.min.css', 'srcCdn'=>'http://ajax.googleapis.com/ajax/libs/jqueryui/' . $wp_scripts->registered['jquery-ui-core']->ver . '/themes/smoothness/jquery-ui.css'],
			 'admin-ui' => [ 'src' => CAF_MAJAX_PLUGIN_URL . "jquery-ui.min.css",
				 			'srcCdn' => 'http://ajax.googleapis.com/ajax/libs/jqueryui/' . $wp_scripts->registered['jquery-ui-core']->ver . '/themes/redmond/jquery-ui.css']
		];
		
		foreach ($mStyles as $key => $value) {
			$src = (isset($value["src"])) ? $value["src"] : $value["srcCdn"];
			$key = "majax-" . $key;
			wp_register_style($key, $src);
			wp_enqueue_style($key);
		}
	}

	function mAjaxEnqueueScripts() {	
		$mScripts=[			
			'select2' => [ 'src' => CAF_MAJAX_PLUGIN_URL .'select2.min.js',
						   'srcCdn' => 'https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/js/select2.min.js',
						   'depends' => array('jquery'),
						   'inFooter' => true

			],
			'jquery-ui-slider' => ['src' => array('jquery'),
								   'inFotter' => true
			],
			'jquery-ui' => [ 'src' => CAF_MAJAX_PLUGIN_URL .'jquery-ui.min.js',
							 'srcCdn' => 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js',
			]

		];
		
		
		foreach ($mScripts as $key => $value) {
			$src = (isset($value["src"])) ? $value["src"] : $value["srcCdn"];
			$version= (isset($value["version"])) ? $value["version"] : '';
			$inFooter= (isset($value["inFooter"])) ? $value["inFooter"] : false;
			$depends= (isset($value["depends"])) ? $value["depends"] : [];
			wp_enqueue_script($key,$src,$depends,$version,$inFooter);
			if (isset($value["localizeObj"])) {
				wp_localize_script( $key, $value["localizeObj"],$value["localizeArray"]);		
			}
		}
		
	}
	
}
	
