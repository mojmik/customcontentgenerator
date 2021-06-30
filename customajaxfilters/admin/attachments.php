<?php
namespace CustomAjaxFilters\Admin;
use \CustomAjaxFilters\Majax\MajaxWP as MajaxWP;

class Attachments {	
	private $attachmetsList;
	private $attachmentViews;
	private $cpt;
    public function __construct($cpt) {
		$this->attachmetsList=["attachments","kapacity"];
		$this->cpt=$cpt;
    }

    public function addToAdminMenu($parent_slug,$capability) {
        $n=0;
		foreach ($this->attachmetsList as $a) {
			$n++;
			$page_title = CAF_SHORT_TITLE." - $a";   		
			$menu_title = ucfirst($a);   
			$menu_slug  = $this->cpt."-plugin-settings-attachments-$n";   			   
			$hook=add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, [$this,'caf_cpt_attachments_page']);			
			$this->attachmentViews[$hook] = $a;
			$n++;
		}	
    }
    
    function caf_cpt_attachments_page() {
		//renders menu actions & settings page in backend
		$currAttachment = $this->attachmentViews[current_filter()];
		?>
		<h1><?= $this->cpt?> <?= $currAttachment?> actions </h1>
		<?php
		$setUrl = [
					  ["delete all",add_query_arg( 'do', 'deleteall'),"remove all"],
					  ["export all",add_query_arg( ['do'=>'exportall','noheader'=>'1']),"export to csv"],				
					  ["import all",add_query_arg( 'do', 'importall'),"export from csv"],
				  ];
		?>
		<ul>
		<?php	 
		foreach ($setUrl as $s) { 
		?>
			<li><a href='<?= $s[1]?>'><?= $s[0]?></a><br /><?= $s[2]?></li>		  		  
		<?php
		}
		?>
		</ul>
		<?php	  
		$do=filter_input( INPUT_GET, "do", FILTER_SANITIZE_STRING );
		  
        $attachment=new Attachment($currAttachment,$this->cpt);

		if ($do=="deleteall") {		    
            $attachment->recreate();
		}	 
		if ($do=="exportall") {		    
			
		}
		if ($do=="importall") {	    		
		 
		}
                
		$attachment->procEdit();
		$attachment->loadSql();
		$attachment->printNew();
		$attachment->printEdit();		 
	  }	
}