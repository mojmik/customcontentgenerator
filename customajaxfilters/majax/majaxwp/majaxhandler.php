<?php
namespace CustomAjaxFilters\Majax\MajaxWP;

Class MajaxHandler {	
	const ACTION = 'majax';
	const NONCE =  'majax-ajax';

	public $ajaxRender;
	private $majaxLoader;
	function __construct($majaxLoader) {		
		$this->majaxLoader=$majaxLoader;
	}

	public function register()  {		

		$this->ajaxRender=new MajaxRender();
	
		add_shortcode('majaxfilter', [$this,'printFilters'] );
		add_shortcode('majaxcontent', [$this,'printContent'] );
		add_shortcode('majaxstaticcontent', [$this,'showStaticContent'] );
		add_shortcode('majaxstaticform', [$this,'showStaticForm'] );
		add_action('wp_loaded', [$this, 'register_script']);
		
		
		add_action('wp_ajax_filter_rows', [$this,'handleShow'] );
		add_action('wp_ajax_nopriv_filter_rows', [$this,'handleShow'] );
		
		add_action('wp_ajax_filter_count_results', [$this,'handleCount'] );
		add_action('wp_ajax_nopriv_filter_count_results', [$this,'handleCount'] );

        add_action('wp_loaded', [$this, 'register_script']);
	}
	
	public function register_script()    {	
        wp_register_script('majax-script', CAF_MAJAX_PLUGIN_URL . 'majax.js',array('jquery'));
        wp_localize_script('majax-script', 'majax', $this->get_ajax_data());
        wp_enqueue_script('majax-script');
	}
	
	private function get_ajax_data() {
        return array(
			'ajax_url' =>  admin_url( 'admin-ajax.php' ),
            'action' => self::ACTION,
            'nonce' => wp_create_nonce(MajaxHandler::NONCE)
        );
	}
	
	public function handleShow()    {
        $this->handle("show");
	}
	public function handleCount()    {
        $this->handle("count");
	}
	private function handle($action="")    {
        
        check_ajax_referer(MajaxHandler::NONCE,'security');
		if ($action=="show") $this->ajaxRender->filter_rows_continuous();
		if ($action=="count") $this->ajaxRender->filter_count_results();
        
        die();
	}
	function setAtts($atts = []) {
		$atts = array_change_key_case( (array) $atts, CASE_LOWER );		
		return $atts;	
	}
	function initRender($atts) {		
		$this->ajaxRender=new MajaxRender(false,$atts);		
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
		$this->ajaxRender->printContent();
		return ob_get_clean();
	}
	function showStaticContent($atts = []) {	
		ob_start();	
		$this->initRender($this->setAtts($atts));
		$this->ajaxRender->showStaticContent($atts);
		return ob_get_clean();
	}
	function showStaticForm($atts = []) {	
		ob_start();	
		$this->initRender($this->setAtts($atts));
		$this->ajaxRender->showStaticForm();
		return ob_get_clean();
	}
	
	function logWrite($val) {
	 file_put_contents(plugin_dir_path( __FILE__ ) . "log.txt",date("d-m-Y h:i:s")." ".$val."\n",FILE_APPEND | LOCK_EX);
	}
}