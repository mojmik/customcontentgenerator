<?php
namespace CustomAjaxFilters\Admin;
use \CustomAjaxFilters\Majax\MajaxWP as MajaxWP;

class AutaCustomPost {	
	public $autaFields;
	private $customPostType;
	public $singular;
	public $plural;
	public $specialType;	
	private $isCj;
	private $cj;
	public $tableType;

	 public function __construct($postType="",$singular="",$plural="",$specialType="",$tableType="") {		 	
		 $this->singular=$singular;
		 $this->plural=$plural;
		 $this->customPostType=$postType;		 
		 $this->specialType=$specialType;
		 $this->tableType=$tableType;
		 add_action( 'init', [$this,'custom_post_type'] , 0 );
		 add_action( 'save_post_'.$postType, [$this,'mauta_save_post'] ); 
		 add_action( 'wp_ajax_importCSV', [$this,'importCSVprocAjax'] );
		 $this->autaFields = new AutaFields($this->customPostType);
		 if ($specialType=="cj") { 
			 $this->isCj=true;
			 $this->cj=new ComissionJunction(["postType" => $this->customPostType]); 
		 }
		 $this->checkTableTypeSettings();
	 }
	 private function checkTableTypeSettings() {
		 if ($this->tableType=="dedicated") {
			Settings::writeSetting("cptsettings-dedicatedTables-".$this->customPostType,AutaPlugin::getTable("dedicated",$this->customPostType));
		 } else {
			Settings::writeSetting("cptsettings-dedicatedTables-".$this->customPostType,"");
		 }
	 }
	 public function adminInit() {
		//admin
		add_action('admin_menu' , [$this,'add_to_admin_menu']); 
				
		//init custom fields				
		$this->autaFields->loadFromSQL();

		add_action( 'save_post_'.$this->customPostType, [$this,'saveCPT'] ); 
	 }
	 public function getCustomPostType() {
		 return $this->customPostType;
	 }
	 public function saveCPT() {			
		AutaCustomPost::sendMessageToMajax("deletecache");		
	 }
	 	
	function custom_post_type() {
	 $textDomain=AutaPlugin::$textDomain; 
	// Set UI labels for Custom Post Type
		$labels = array(
			'name'                => _x( $this->customPostType, 'Post Type General Name', $textDomain ),
			'singular_name'       => _x( $this->singular, 'Post Type Singular Name', $textDomain ),
			'menu_name'           => __( CAF_SHORT_TITLE." - ".$this->customPostType, $textDomain ),
			'parent_item_colon'   => __( 'Parent', $textDomain)." ".$this->singular,
			'all_items'           => __( 'All', $textDomain)." ".$this->plural,
			'view_item'           => __( 'Show', $textDomain )." ".$this->singular,
			'add_new_item'        => __( 'Add', $textDomain )." ".$this->singular,
			'add_new'             => __( 'Add', $textDomain ),
			'edit_item'           => __( 'Edit', $textDomain )." ".$this->singular,
			'update_item'         => __( 'Update', $textDomain )." ".$this->singular,
			'search_items'        => __( 'Find', $textDomain )." ".$this->singular,
			'not_found'           => __( 'Not found', $textDomain ),
			'not_found_in_trash'  => __( 'Not found in trash', $textDomain ),
		);
		 
	// Set other options for Custom Post Type
		 
		$args = array(
			'label'               =>  $this->plural,
			'description'         => $this->plural." ".__( 'in stock', $textDomain ),
			'labels'              => $labels,			
			'supports'            => array( 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'comments', 'revisions', 'custom-fields' ),			
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'menu_position'       => 5,
			'can_export'          => true,
			'has_archive'         => true,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'rewrite' => array('slug' => $this->customPostType),
			//'rewrite' => false,
			'capability_type'     => 'post',
			'show_in_rest' => true,
	 
		);
		 
		// Registering your Custom Post Type
		register_post_type( $this->customPostType, $args );
	 
	 }
	 function removeAll() {
		global $wpdb;
		$query="
				DELETE a,b,c
				FROM wp_posts a
				LEFT JOIN wp_term_relationships b ON ( a.ID = b.object_id )
				LEFT JOIN wp_postmeta c ON ( a.ID = c.post_id )
				WHERE a.post_type like '".$this->customPostType."';
			";
			$result = $wpdb->get_results($query);  
		if ($this->isCj) {						
			MajaxWP\MikDb::clearTable($this->cj->getCatsTabName());
			MajaxWP\MikDb::clearTable($this->cj->getMainTabName());
			
		}	
		
		$dedicatedTable=Settings::loadSetting("dedicatedTables-".$this->customPostType,"cptsettings");
		if ($dedicatedTable) {
			$query="
				TRUNCATE TABLE $dedicatedTable;
			";
			$result = $wpdb->get_results($query);  
		}
	}	
	function importCSVprocAjax() {
		$do=filter_input( INPUT_POST, "doajax", FILTER_SANITIZE_STRING );
		$type=filter_input( INPUT_POST, "csvtype", FILTER_SANITIZE_STRING );
		$tabName=filter_input( INPUT_POST, "table", FILTER_SANITIZE_STRING );
		$from=filter_input( INPUT_POST, "from", FILTER_SANITIZE_STRING );
		$to=filter_input( INPUT_POST, "to", FILTER_SANITIZE_STRING );
		if ($do=="makeposts") {
			$extras=[];
			$importCSV=new ImportCSV($this->customPostType);	
			if ($type=="cjcsv") {
				
				$extras=$this->cj->getFieldsExtras();										
				$this->cj->getCJtools()->setParam("tableName",$tabName);									
				$this->cj->getCJtools()->createPostsFromTable($this->autaFields->getList(),$from,$to,$extras);
			} else {
				$importCSV->setParam("tableName",$tabName);					
				$importCSV->createPostsFromTable($this->autaFields->getList(),$from,$to,$extras);	
			}			
			echo json_encode(["result"=>"imported"]).PHP_EOL;
			wp_die();
		}	
		if ($do=="createCats")	{			
			$this->cj->getCJtools()->createCategories();
			echo json_encode(["result"=>"categories created"]).PHP_EOL;
			wp_die();
		}
		if ($do=="udpateCatsDesc2") {			
			$this->cj->getCJtools()->updateCatsDescription($from,$to);
			echo json_encode(["result"=>"categories description updated"]).PHP_EOL;
			wp_die();
		}
		if ($do=="getCatsCnt") {			
			$rows=$this->cj->getCJtools()->getCats();
			echo json_encode(["result"=>count($rows)]).PHP_EOL;
			wp_die();
		}
		if ($do=="countcsvlines") {
			$fn=$from;
			$this->cj->createCjTables();	
			$importCSV=new ImportCSV($this->customPostType);							
			$result=$importCSV->countLines($fn);
			$importCSV->setParam("tableName",$tabName);
			//$importCSV->clearImportTable();
			echo json_encode(["result"=>$result]).PHP_EOL;
			wp_die();
		}
		if ($do=="ajaximportcsv") {
			$fn=$from;
			//ty uz se vytvori v countcsvlines
			//$this->cj->createCjTables();	
			$importCSV=new ImportCSV($this->customPostType);				
			$importCSV
			->setParam("separator",",")
			->setParam("tableName",$tabName)
			->setParam("encoding","UTF-8")
			->setParam("enclosure","\"")
			->setParam("emptyFirst","true")
			->setParam("cj",$this->cj)
			->setParam("createTable",false);
			$result=$importCSV->doImportCSVfromWP($fn,intval($to),intval($to)+500);
			if ($result) {					
				$this->autaFields->makeTable("fields");
				$this->autaFields->addFields($this->cj->getMautaFields());										
			}
			echo json_encode(["result"=>$result]).PHP_EOL;
			wp_die();
		}
		if ($do=="getPostsCnt") {
			$ded=new DedicatedTables($this->customPostType);
			$ded->initTable();
			echo json_encode(["result"=>$ded->countPosts()]).PHP_EOL;
			wp_die();
		}
		if ($do=="createFromPosts") {
			$ded=new DedicatedTables($this->customPostType);
			echo json_encode(["result"=>$ded->createFromPosts($from,$to)]).PHP_EOL;
			wp_die();
		}
	}
	function importCSVproc() {
		$do=filter_input( INPUT_GET, "do", FILTER_SANITIZE_STRING );
		if ($do=="removeexttables") {
			$prefix=MajaxWP\MikDb::getTablePrefix().$this->customPostType."_";
			MajaxWP\MikDb::dropTable($prefix."cj_import");	
			MajaxWP\MikDb::dropTable($prefix."cj_cats");	
			MajaxWP\MikDb::dropTable($prefix."cj_tempcats");	
			MajaxWP\MikDb::dropTable($prefix."fields");	
		}
		if ($do=="removeall") {
			$this->removeAll();
		} else {
			$importCSV=new ImportCSV($this->customPostType);
		}	
		if ($do=="genthumbs") {			
			$importCSV->preInsertThumbs();	
		}			  
		
		if ($do=="csv") {			
			$tabName=MajaxWP\MikDb::getTablePrefix()."csvtab";	
			$separator=filter_input( INPUT_POST, "separator", FILTER_SANITIZE_STRING );
			$encoding=filter_input( INPUT_POST, "encoding", FILTER_SANITIZE_STRING );
			if ($importCSV->gotUploadedFile()) {
				$importCSV
						->setParam("separator",$separator)
						->setParam("tableName",$tabName)
						->setParam("createTable",true)
						->setParam("encoding",$encoding);
				if ($importCSV->doImportCSVfromWP()=="imported") {
					
				}
			} 		
		  }
		  if ($do=="cjcsv") {		
			$tabName=$this->cj->getMainTabName();	
			if ($importCSV->gotUploadedFile()) {								
				$this->cj->createCjTables();				
				$importCSV
				->setParam("separator",",")
				->setParam("tableName",$tabName)
				->setParam("encoding","UTF-8")
				->setParam("enclosure","\"")
				->setParam("emptyFirst","true")
				->setParam("cj",$this->cj)
				->setParam("createTable",false);
				if ($importCSV->doImportCSVfromWP()=="imported") {					
					$this->autaFields->makeTable("fields");
					$this->autaFields->addFields($this->cj->getMautaFields());										
				}
			} 
		  }
		  if ($do=="csv" || $do=="cjcsv") {
			$importCSV->showImportCSV($do);	
			$countReadyCSV=MajaxWP\MikDb::wpdbTableCount($tabName);
			if ($countReadyCSV>0) { 
				echo "<br />$countReadyCSV csv rows ready";
				$importCSV->showMakePosts($do,$tabName,$countReadyCSV);			
			}
		  }
		  if ($do=="csvbulkimport") {
				$files = glob(CAF_PLUGIN_PATH."*.txt");
				?>
				
				<?php
				$n=0;
				foreach($files as $fn) {	

					?>
					<form data-form='csvBulkImport'>
						<div class="row">
							<input style='width:100px;' type='submit' value='<?= __("Process",CAF_TEXTDOMAIN)?>' />
							<span style="width:60%;"><?= basename($fn)?></span>
							<input data-fn='csvbulkfn' type='hidden' name='file<?= $n?>' value='<?= $fn?>' />														
							<span style='width:200px;' data-fn='statuscsvbulk-file<?= $n?>' ></span>
						</div>
					</form>
					<?php
					$n++;
				}
				?>

				<?php
		  }
		  	 
		  if ($do=="createcatpages") {	
			$cjPages=new CJpages($this->customPostType,$this->cj->getCJtools());  		
			echo $cjPages->createCatPages();
		  }
		  if ($do=="cjcatdebug") {
			echo $this->cj->getCJtools()->updateCatsDescription();
		  }
		  		  
	}
	function csvMenu() {
		$setUrl = [	
			[__("csv import",CAF_TEXTDOMAIN),add_query_arg( 'do', 'csv'),__("import csv file",CAF_TEXTDOMAIN)],			
			[__("csv bulk import",CAF_TEXTDOMAIN),add_query_arg( 'do', 'csvbulkimport'),__("import csv files uploaded by ftp",CAF_TEXTDOMAIN)],						
			[__("remove all",CAF_TEXTDOMAIN),add_query_arg( 'do', 'removeall'),__("remove all posts of this type",CAF_TEXTDOMAIN)]												
		];
		//$setUrl[]=["dedicated tables debug",add_query_arg( 'do', 'creatededicatedtable'),"create dedicated table from posts debug (for huge sites)"],			
		if ($this->isCj) {
			array_push($setUrl,
				[__("cj csv import",CAF_TEXTDOMAIN),add_query_arg( 'do', 'cjcsv'),__("import cj csv file",CAF_TEXTDOMAIN)],
				[__("remove cj tables",CAF_TEXTDOMAIN),add_query_arg( 'do', 'removeexttables'),__("drop tables for cj fields and categories",CAF_TEXTDOMAIN)],
				[__("create pages",CAF_TEXTDOMAIN),add_query_arg( 'do', 'createcatpages'),__("create random pages from posts",CAF_TEXTDOMAIN)],
				[__("cj cats description and counts",CAF_TEXTDOMAIN),add_query_arg( 'do', ''),__("create description and counts for categories",CAF_TEXTDOMAIN), "catdescajax"]
				);				

		} 
		
		?>
		<h1>CSV options</h1>
		<ul>
		<?php	 
		foreach ($setUrl as $s) { 
			$id=(empty($s[3])) ? "" : "id='".$s[3]."'";
		?>
			<li><a <?= $id?> href='<?= $s[1]?>'><?= $s[0]?></a><br /><?= $s[2]?></li>		  		  
		<?php
		}
		?>
		</ul>
		<div id="ajaxprogress"></div>
		<div class="majax-loader" data-component="loader" style="display: none;">
			<svg width="38" height="38" viewBox="0 0 38 38" xmlns="http://www.w3.org/2000/svg">
			<defs>
			<linearGradient x1="8.042%" y1="0%" x2="65.682%" y2="23.865%" id="gradient">
			<stop stop-color="#ffc107" stop-opacity="0" offset="0%"></stop>
			<stop stop-color="#ffc107" stop-opacity=".631" offset="63.146%"></stop>
			<stop stop-color="#ffc107" offset="100%"></stop>
			</linearGradient>
			</defs>
			<g fill="none" fill-rule="evenodd">
			<g transform="translate(1 1)">
			<path d="M36 18c0-9.94-8.06-18-18-18" stroke="url(#gradient)" stroke-width="3"></path>
			<circle fill="#fff" cx="36" cy="18" r="1"></circle>
			</g>
			</g>
			</svg>
		</div>
		<?php	
		$this->importCSVproc();		
		?>
		<div id="mautaCSVimportResults"></div>
		<?php
	}

	 function add_to_admin_menu() {
		//add import to cpt
		$parent_slug='edit.php?post_type='.$this->customPostType;
		$page_title=CAF_SHORT_TITLE.' admin';		
		$capability='edit_posts';
		$menu_slug=basename(__FILE__);
		$function = [$this,'csvMenu'];
		$menu_title='Import';
		add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function);
		
		//adds sub menu item
		$page_title = CAF_SHORT_TITLE.' - fields';   		
		$menu_title = "Fields";   
		$capability = 'manage_options';   
		$menu_slug  = $this->customPostType.'-plugin-settings';   
		$function   =  [$this,'caf_cpt_fields_page'];   
		add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function);
		if (CAF_ALLOW_ATTACHMENTS) {
			$attachments=new Attachments($this->customPostType); 
			$attachments->addToAdminMenu($parent_slug,$capability);
		}
		
		
	} 
	static function sendMessageToMajax($message) {
		$fn=wp_upload_dir()["basedir"]."/$message.txt";				
		if (!file_exists($message)) {
			file_put_contents($fn,$message,FILE_APPEND | LOCK_EX);
		}
	}

	function caf_cpt_fields_page() {
	  //renders menu actions & settings page in backend
	  ?>
	  <h1><?= $this->singular?> fields actions </h1>
	  <?php
	  $setUrl = [
					["recreate",add_query_arg( 'do', 'recreate'),"remove all"],
					["export fields",add_query_arg( ['do'=>'exportfields','noheader'=>'1']),"export fields to csv"],				
					["import fields",add_query_arg( 'do', 'importfields'),"import fields from csv"],
					["init min max",add_query_arg( 'do', 'initminmax'),"load min-max from current posts (needed for filtering)"]
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
	  displayorder 51..60 = you can use {metaOut[field title]} in templates for display<br />
	  displayorder 1..20 = you can use {metaOut[0]} in templates for display<br />
	  displayorder 20..30 = you can use {metaOut[1]} in templates for display<br />
	  displayorder 31..40 = you can use {metaOut[3]} in templates for display<br />
	  displayorder 41..50 = you can use {featuredHtml} in templates for display<br />
	  <?php	  
	  $do=filter_input( INPUT_GET, "do", FILTER_SANITIZE_STRING );
	  $cpt=filter_input( INPUT_GET, "cpt", FILTER_SANITIZE_STRING );
	    
	  $this->autaFields->makeTable("fields");
	  if ($do=="recreate") {		    
		$this->autaFields->makeTable("fields",true);		
		$this->autaFields->initList();
	  }	 
	  if ($do=="exportfields") {		    
		  $exportCsv=new ExportCSV();
		  $thisTable=AutaPlugin::getTable("fields",$this->customPostType);
		  $exportCsv->exportTable($thisTable);
	  }
	  if ($do=="importfields") {	    		
		$thisTable=AutaPlugin::getTable("fields",$this->customPostType);
		$importCSV=new ImportCSV($this->customPostType);
		$importCSV->setParam("separator",";");			
		$importCSV->setParam("tableName",$thisTable);	
		$importCSV->showImportCSV();
		if ($importCSV->gotUploadedFile()) {			
			if ($importCSV->doImportCSVfromWP()=="imported") {
				$this->autaFields->loadFromSQL();
			}
		} 
	  }
	  if ($do=="initminmax") {
		$thisTable=AutaPlugin::getTable("fields",$this->customPostType);
		$this->autaFields->initMinMax();
		MajaxWP\Caching::pruneCache(true,$this->customPostType);
	  }
	  $this->autaFields->procEdit();
	  $this->autaFields->printNewField();
	  $this->autaFields->printFields();		 
	}	
	function editCptHtml() {
		//checked='checked'
		?>
			<form id="mAutaEdit<?= $this->getCustomPostType();?>" method='post' class='caf-editFieldRow editCPT'>
				<div><div><label>singular name</label></div><input type='text' name='singular' value='<?= $this->singular?>' /></div>	
				<div><div><label>plural name</label></div><input type='text' name='plural' value='<?= $this->plural?>' /></div>
				<div><div><label>is comission junction?</label></div><input name='specialType' type='checkbox' <?= ($this->specialType=="cj" ? "value='1' checked='checked'" : "")?> /></div>
				<div><div><label>dedicated table?</label></div><input name='tableType' type='checkbox' <?= ($this->tableType=="dedicated" ? "value='1' checked='checked'" : "")?> /></div>				
				<div><input name='cafActionEdit' type='submit' value='Edit' /></div>
				<input name='slug' type='hidden' value='<?= $this->getCustomPostType();?>' />
			</form>
			<form method='post' class='removeCPT'>
				<input name='cafActionRemove' type='submit' value='Remove' />
				<input name='slug' type='hidden' value='<?= $this->getCustomPostType();?>' />
			</form>			
			<?php
	}

	function mauta_save_post()	{		
		global $post; 
		$somethingChanged=__return_false();
		if(empty($_POST)) return; //tackle trigger by add new 		
		//save meta fields
		foreach ($this->autaFields->getList() as $f) {
			if (!empty($_POST[$f->name])) {
				$val=$_POST[$f->name];
				if ($f->type=="bool" && $val!="on") $val="0";
				if ($f->type=="bool" && $val=="on") $val="1";					
				update_post_meta($post->ID, $f->name, $val);	
				$somethingChanged=true;
			}								
		}
		if ($somethingChanged) MajaxWP\Caching::pruneCache(true,$this->customPostType);
	} 
}
