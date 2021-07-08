<?php
namespace CustomAjaxFilters;

class Loader {	

    public function __construct() {	        
        spl_autoload_register([$this,"mLoadClass"]);        
        define('CAF_MAJAX_PATH',plugin_dir_path( __FILE__ ). "majax/");  
        //translations
        add_action('init', [$this,"globalInit"]);

        $baseName=plugin_basename(__FILE__);
        $currentScript=basename(__FILE__);
        define('CAF_RELPATH_MAIN',str_replace($currentScript,"",$baseName));        

        $cjActive=true;
		if ($cjActive)	{

            /*
            *   There we need to load all pages with category/brands structure for permalink rewriting
            */

            $cj=new Admin\ComissionJunction();
            $page=["link" => "", "id" => get_option( 'page_on_front' )];
            $cj->handleRewriteRules($page); 
            $cj->addShortCodes();                
		}
    }
    public function globalInit() {
        //load_plugin_textdomain( CAF_TEXTDOMAIN, false, CAF_PLUGIN_PATH.'/customajaxfilters/languages' );
        load_plugin_textdomain( CAF_TEXTDOMAIN, false, CAF_RELPATH_MAIN.'languages' );
    }
    public function initAdmin() {
        $mautawp=new Admin\AutaPlugin(); 
        $mautawp->initWP();
    }
    public function initFrontend() {
        define( 'CAF_MAJAX_PLUGIN_URL', plugin_dir_url( __FILE__ ) . "majax/");
        define('CAF_MAJAX_FAST',4); 
        /* from slow to fast
            1. majaxhandler + admin-ajax.php (all wp functionality)
            2. ajaxhandlernotsoshort (custom ajaxhandler, wp_query functionality)
            3. ajaxhandlershort (custom ajaxhandler, $wpdb->get_results functionality) 
            4. ajaxhandlersupershort (custom ajaxhandler, custom database wrapper) 
            
            2+3+4:
            custom fields hardcoded 
            
            3+4:
                get posts with metas:
                option1:
                SELECT p.ID, p.post_title, 
                    MAX(CASE WHEN pm1.meta_key = 'mauta_kategorie' then pm1.meta_value ELSE NULL END) as price,
                    MAX(CASE WHEN pm1.meta_key = 'mauta_znacka' then pm1.meta_value ELSE NULL END) as regular_price,
                    MAX(CASE WHEN pm1.meta_key = 'mauta_cenaden' then pm1.meta_value ELSE NULL END) as sale_price,
                    MAX(CASE WHEN pm1.meta_key = 'mauta_automat' then pm1.meta_value ELSE NULL END) as automat
                    FROM wp_posts p LEFT JOIN wp_postmeta pm1 ON ( pm1.post_id = p.ID)                 
                    WHERE p.post_type like 'mauta' AND p.post_status = 'publish' 
                    GROUP BY p.ID, p.post_title

                option2:
                SELECT p.*, 
                    GROUP_CONCAT(pm.meta_key ORDER BY pm.meta_key DESC SEPARATOR '||') as meta_keys, 
                    GROUP_CONCAT(pm.meta_value ORDER BY pm.meta_key DESC SEPARATOR '||') as meta_values 
                    FROM wp_posts p 
                    LEFT JOIN wp_postmeta pm on pm.post_id = p.ID 
                    WHERE p.post_type = 'mauta' and p.post_status = 'publish' 
                    GROUP BY p.ID
                option3:
                    create special table for mauta custom posts type for faster filtering
            */

            /*
            add_action('my_cron_hook','majax_cron_hook');
            if ( ! wp_next_scheduled( 'majax_cron_hook') ) {
                wp_schedule_event( time(), 'daily', 'my_cron_hook' );
            }
            function majax_cron_hook() {
                Caching::pruneCache();
            }
            */

            /*
            idelani by bylo mit jednu tabulku
            id|content|image|meta1|meta2|..
            asi by bylo dobre ji vyrobit, pri vetsich databazich
            */
        $majax=new Majax\MajaxWP\Majax();
        $majax->initWP();
    }
    function mLoadClass($class) {	
		if (strpos($class,"CustomAjaxFilters")!==0) return;
		$path=CAF_PLUGIN_PATH.str_replace("\\","/",strtolower("$class.php"));		
        require($path);
    }

}