<?php
namespace CustomAjaxFilters\Majax\MajaxWP;
use \CustomAjaxFilters\Admin as MajaxAdmin;

Class MajaxHandlerShort {	
	const ACTION = 'majax';
	const NONCE =  'majax-ajax';

	public $ajaxRender;
	public $shortInit=true;
	private $cjActive;

	function __construct($majaxLoader) {		
		$this->majaxLoader=$majaxLoader;
	}

	public function register()  {				
		add_shortcode('majaxfilter', [$this,'printFilters'] );
		add_shortcode('majaxcontent', [$this,'printContent'] );
		add_shortcode('majaxstaticcontent', [$this,'showStaticContent'] );
		add_shortcode('majaxstaticform', [$this,'showStaticForm'] );
		add_shortcode('majaxsearchbox', [$this,'showSearchBox'] );
		add_action('wp_loaded', [$this, 'register_script']);
	}
	
	function initRender($atts) {	
		$atts["majaxLoader"]=$this->majaxLoader;
		$this->ajaxRender=new MajaxRender(false,$atts);		
	}
	function setAtts($atts = []) {
		$atts = array_change_key_case( (array) $atts, CASE_LOWER );		
		return $atts;	
	}

	function printFilters($atts = []) {			
		ob_start();					
		$this->initRender($this->setAtts($atts));
		$this->ajaxRender->printFilters();
		return ob_get_clean();
	}
	function printContent($atts = []) {	
		ob_start();	
		$this->initRender($this->setAtts($atts));
		$params=["showSomePostsForStart" => true];
		$this->ajaxRender->printContent($params);
		return ob_get_clean();
	}
	function showStaticContent($atts = []) {	
		ob_start();	
		$this->initRender($this->setAtts($atts));
		$this->ajaxRender->showStaticContent($atts);
		return ob_get_clean();
	}
	function showSearchBox($atts = []) {	
		ob_start();	
		$this->initRender($this->setAtts($atts));
		$this->ajaxRender->showSearchBox($atts);
		return ob_get_clean();
	}
	function showStaticForm($atts = []) {	
		ob_start();	
		$this->initRender($this->setAtts($atts));
		$this->ajaxRender->showStaticForm();
		return ob_get_clean();
	}

	function add_async_forscript($url) 	{
    if (strpos($url, '#asyncload')===false)
        return $url;
    else if (is_admin())
        return str_replace('#asyncload', '', $url);
    else
        return str_replace('#asyncload', '', $url)."' async='async' defer='defer"; 
	}
	
	public function register_script()    {	      		
		//recaptcha
		add_filter('clean_url', [$this,'add_async_forscript'], 11, 1);
		wp_enqueue_script('majaxrecaptcha','https://www.google.com/recaptcha/api.js?render=explicit&onload=onReCaptchaLoad',[],null);
		wp_enqueue_script('majaxrecaptcha');

		wp_register_script('majaxelements', CAF_MAJAX_PLUGIN_URL . 'majaxelements.js', array( 'jquery' ) );	
		wp_enqueue_script('majaxelements');
		wp_register_script('majaxviewcomponents', CAF_MAJAX_PLUGIN_URL . 'majaxviewcomponents.js', array( 'jquery' ) );	
		wp_enqueue_script('majaxviewcomponents');
		wp_register_script('majaxview', CAF_MAJAX_PLUGIN_URL . 'majaxview.js', array( 'jquery' ) );	
		wp_enqueue_script('majaxview');
		wp_register_script('majaxprc', CAF_MAJAX_PLUGIN_URL . 'majaxprc.js', array( 'jquery' ) );	
		wp_enqueue_script('majaxprc');				
		wp_register_script('majax-script', CAF_MAJAX_PLUGIN_URL . 'majax.js', array( 'jquery' ) );		
		wp_localize_script('majax-script', 'majax', $this->get_ajax_data());
		wp_enqueue_script('majax-script');				
	}
	
	private function get_ajax_data() {
		if (CAF_MAJAX_FAST==4) $ajaxPhp="ajaxsupershort.php";
		if (CAF_MAJAX_FAST==3) $ajaxPhp="ajaxshort.php";
		if (CAF_MAJAX_FAST==2) $ajaxPhp="ajaxnotsoshort.php";		
				
        return array(
			'ajax_url' =>  CAF_MAJAX_PLUGIN_URL . $ajaxPhp,
            'action' => self::ACTION,
            'nonce' => wp_create_nonce(MajaxHandlerShort::NONCE)
		);		
	}
	
	public static function logWrite($val) {
	 file_put_contents(plugin_dir_path( __FILE__ ) . "log.txt",date("d-m-Y h:i:s")." ".$val."\n",FILE_APPEND | LOCK_EX);
	}
}