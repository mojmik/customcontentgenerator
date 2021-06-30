<?php
namespace CustomAjaxFilters\Admin;
use \CustomAjaxFilters\Majax\MajaxWP as MajaxWP;

class AutaFields {
	private $fieldsList;
	private $customPostType;
	public function __construct($postType) {					
		add_action( 'add_meta_boxes_'.$postType, [$this,'mauta_metaboxes'] );				
		$this->customPostType=$postType;				
		$this->fieldsList=array();
	}
	public function getList() {
		return $this->fieldsList;
	}
	public function initList() {
		$this->fieldsList=array();
	}
	public function loadFromSQL($tabName="fields") {
		global $wpdb;		
		$this->fieldsList=[];
		$tableName=AutaPlugin::getTable($tabName,$this->customPostType);
		$query = "SELECT * FROM `{$tableName}` ORDER BY `displayorder`";	
		foreach( $wpdb->get_results($query) as $key => $row) {					
			$this->fieldsList[] = $this->createField($row->name,$row->type,$row->compare,$row->title,$row->value,$row->filterorder,$row->displayorder,$row->icon,$row->fieldformat,$row->htmlTemplate);
		}	
		return true;
	}
	public function addFields($rows) {
		foreach ($rows as $r) {
			if (!empty($r["name"])) { 
				$newName=$r["name"];
				$type=(empty($r["type"])) ? "" : $r["type"];
				$compare=(empty($r["compare"])) ? "=" : $r["compare"];
				$title=(empty($r["title"])) ? $newName : $r["title"];
				$options=(empty($r["options"])) ? "" : $r["options"];
				$filterorder=(empty($r["filterorder"])) ? "" : $r["filterorder"];
				$displayorder=(empty($r["displayorder"])) ? "" : $r["displayorder"];
				$icon=(empty($r["icon"])) ? "" : $r["icon"];
				$fieldformat=(empty($r["fieldformat"])) ? "" : $r["fieldformat"];
				$htmlTemplate=(empty($r["htmlTemplate"])) ? "" : $r["htmlTemplate"];
				$virtVal=(empty($r["virtVal"])) ? "" : $r["virtVal"];
				$this->addField($newName,$type,$compare,$title,$options,$filterorder,$displayorder,$icon,$fieldformat,$htmlTemplate,$virtVal);
			} 
		}
	}
	public function addField($newName,$type,$compare,$title,$options,$filterorder,$displayorder,$icon,$fieldformat,$htmlTemplate,$virtVal) {
		$f = $this->createField($newName,$type,$compare,$title,$options,$filterorder,$displayorder,$icon,$fieldformat,$htmlTemplate,$virtVal);
		$this->fieldsList[] = $f;
		$f->saveToSQL();
	}
	public function procEdit() {
		//edit field
		$name=filter_input( INPUT_POST, "name", FILTER_SANITIZE_STRING );
		$type=filter_input( INPUT_POST, "type", FILTER_SANITIZE_STRING );
		$compare=filter_input( INPUT_POST, "compare", FILTER_SANITIZE_STRING );
		$title=filter_input( INPUT_POST, "title", FILTER_SANITIZE_STRING );
		$options=filter_input( INPUT_POST, "options", FILTER_SANITIZE_STRING );
		$filterorder=filter_input( INPUT_POST, "filterorder", FILTER_SANITIZE_STRING );
		$displayorder=filter_input( INPUT_POST, "displayorder", FILTER_SANITIZE_STRING );
		$icon=filter_input( INPUT_POST, "icon", FILTER_SANITIZE_STRING );
		$fieldformat=filter_input( INPUT_POST, "fieldformat", FILTER_SANITIZE_STRING );
		$htmlTemplate=(empty($_POST["htmlTemplate"])) ? "" : $_POST["htmlTemplate"];		
		$virtVal=filter_input( INPUT_POST, "virtVal", FILTER_SANITIZE_STRING );
		
		if (isset($_POST["editField"])) {						
			foreach ($this->fieldsList as $f) {	
			 if ($f->name == $name) {
				$f->type=$type; 
				$f->compare=$compare;
				$f->title=$title;
				$f->options=$options;
				$f->filterorder=$filterorder;
				$f->displayorder=$displayorder;
				$f->icon=$icon;
				$f->fieldformat=$fieldformat;
				$f->htmlTemplate=$htmlTemplate;
				$f->virtVal=$virtVal;
				$f->saveToSQL();
				echo "changed $name";
			 }
			}
			MajaxWP\Caching::pruneCache(true,$this->customPostType);
		}
		
		//new field
		if (isset($_POST["newField"])) {			
				//create table if not exists
			 	$newName=CAF_TAB_PREFIX.sanitize_title($title);				
				$this->addField($newName,$type,$compare,$title,$options,$filterorder,$displayorder,$icon,$fieldformat,$htmlTemplate,$virtVal);												
				echo "created $name";
				MajaxWP\Caching::pruneCache(true,$this->customPostType);
		}
		
		//delete field
		if (isset($_POST["deleteField"])) {			
			$name=filter_input( INPUT_POST, "name", FILTER_SANITIZE_STRING );
			foreach ($this->fieldsList as $key=>$f) {	
			 if ($f->name == $name) {
				$index=$key;				
				$f->saveToSQL("fields",1);
				break;				
			 }
			}
			//remove from array of objects
			if (isset($index)) {
				unset($this->fieldsList[$index]);				
				echo "deleted $name $key";
			}
			MajaxWP\Caching::pruneCache(true,$this->customPostType);
		}
	}
	public function printFields() {
 	 $out="";
	 ?>
	 <h2>Edit fields</h2>
	 <?php
		foreach ($this->fieldsList as $f) {		  
		  $out.=$f->printFieldEdit();
		  //echo "<br />";
		}
		return $out;
    }
	public function printNewField() {
	 ?>
	 <h2>New field</h2>
	 <form class='caf-editFieldRow' method='post'>
		<div><div><label>name</label></div><input disabled type='text' name='name' value='' /></div>
		<div><div><label>title</label></div><input type='text' name='title' value='' /></div>	
		<div><div><label>type</label></div><input type='text' name='type' value='' /></div>
		<div><div><label>compare filter</label></div><input type='text' name='compare' value='=' /></div>
		<div><div><label>options (split with ;)</label></div><input type='text' name='options' value='' /></div>			
		<div><input name='newField' type='hidden' value='edit' /><input type='submit' value='create' /></div>
	</form>
	 <?php
 }
	function createField($name,$type,$compare,$title,$options="",$filterorder="",$displayorder="",$icon="",$fieldformat="",$htmlTemplate="",$virtVal="") {
		return new AutaField($name,$type,$title,$options,$this->customPostType,$compare,$filterorder,$displayorder,$icon,$fieldformat,$htmlTemplate,$virtVal);
	}
	function mauta_metaboxes( ) {
		global $wp_meta_boxes;
		global $post;
		/*
		https://developer.wordpress.org/reference/functions/add_meta_box/
		add_meta_box( string $id, string $title, callable $callback, string|array|WP_Screen $screen = null, string $context = 'advanced', string $priority = 'default', array $callback_args = null )
		*/
				
		$custom = get_post_custom($post->ID);
		
		foreach ($this->fieldsList as $f) {
		  //echo "aaacust: {$f->name} ".$custom[$f->name][0];
		  $val=$custom[$f->name][0];	
		  $f->addMetaBox($val);	
		}
		
		add_meta_box("addanotheritem", __( 'Add another', 'textdomain' ), [$this,'addanother_metabox'], $this->customPostType, 'side', 'low');  
		
		//
	}	
	function addanother_metabox() {		
		$urlSave=add_query_arg( 'msave', '1');
		$urlNew="'./post-new.php?post_type=".$this->customPostType."'";										
		?>
		
		<button onclick='javascipt:saveAndAdd();'>Add another</a>				 
		<?php
	}
	  
	function saveFields($destinationTab="fields")	{		
		foreach ($this->fieldsList as $f) {			
		  $f->saveToSQL($destinationTab);	
		}			
	}   
	function dropAll() {

	}
	function makeTable($tabName="fields",$rebuild=false,$dropOnly=false) {
		global $wpdb;
		$tableName=AutaPlugin::getTable($tabName,$this->customPostType);
		if(!$rebuild && $wpdb->get_var("SHOW TABLES LIKE '$tableName'") == $tableName) {
		 //table exists and not rebuild
		 return true;
		}		
		$wpdb->query( "DROP TABLE IF EXISTS {$tableName}");
		if ($dropOnly) return true;
		$charset_collate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$tableName} (
		  id mediumint(9) NOT NULL AUTO_INCREMENT,	
		  name tinytext,
		  value text,
		  title text,
		  type tinytext,
		  compare tinytext,
		  valMin text,
		  valMax text,
		  postType tinytext,
		  filterorder smallint,
		  displayorder smallint,
		  icon text,
		  fieldformat text,
		  htmlTemplate text,
		  virtVal text,
		  PRIMARY KEY  (id)
		) $charset_collate;";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		maybe_create_table($tableName, $sql );
		$this->fieldsList=array();	
	}	
	function initMinMax() {		
		foreach ($this->fieldsList as $f) {
		 if (!$f->typeIs("numeric")) continue;
		 echo "<br />".$f->name." min:".$f->getValMin();
		 echo " max:".$f->getValMax();
		 $f->saveToSQL();
		}		
	}
}
