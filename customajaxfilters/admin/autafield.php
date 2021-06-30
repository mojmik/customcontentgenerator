<?php
namespace CustomAjaxFilters\Admin;

class AutaField {
 public $name;
 public $type;
 public function __construct($name,$type="",$title="",$options="",$postType="",$compare="",$filterorder="",$displayorder="",$icon="",$fieldformat="",$htmlTemplate="",$virtVal="") {
	  $this->name=$name;	 
	  $this->type=$type;	
	  $this->id=CAF_TAB_PREFIX.$name;
	  $this->title=__($title);	 
	  $this->options=$options;	  
	  $this->customPostType=$postType;		 
	  $this->compare=$compare;
	  $this->filterorder=$filterorder;
	  $this->displayorder=$displayorder;
	  $this->icon=$icon;
	  $this->fieldformat=$fieldformat;
	  $this->htmlTemplate=$htmlTemplate;
	  $this->virtVal=$virtVal;
	  $this->valMin=null;
	  $this->valMax=null;
 } 
 public function addMetaBox($val) {
	$this->val=$val;
	AutaPlugin::logWrite("add metabox: {$this->customPostType} {$this->name}");	
	add_meta_box("postfunctiondiv{$this->name}", $this->title, [$this,'mauta_metabox_html'], $this->customPostType, 'side', 'high');  
 }
 function mauta_metabox_html() 	{				
		$val = isset($this->val)?$this->val:'';	
		//echo "aaaval:".$this->val;
		if ($this->type=="select") {				
		$options=explode(";",$this->options);		
		?>
		<select name="<?php echo $this->name?>">		
		<?php		
		foreach ($options as $opt) {
		$selected=($val==$opt)?"selected='selected'":"";
		?>
			<option <?= $selected?> value="<?= $opt?>"><?= $opt?></option>
		<?php
		}
		?>
		</select>		
		<?php
		}
		else if ($this->type=="bool") {
		if ($val=="on" || $val=="1") $checked="checked";			
		?>		 
		<input type='checkbox' name="<?php echo $this->name?>" <?php echo $checked; ?> />			
		<?php 
		}
		else {?>
		<input name="<?php echo $this->name?>" value="<?php echo $this->val; ?>" />		
		<?php
		}
 }
 
 public function printFieldEdit() {
	 ?>
	
	 <form action='<?= remove_query_arg( 'do')?>' method='post' class='caf-editFieldRow'>	 	 	 
			<div><div><label>name</label></div><input type='text' readonly='true' name='name' value='<?= $this->name?>' /></div>
			<div><div><label>type</label></div><input type='text' name='type' value='<?= $this->type?>' /></div>
			<div><div><label>compare</label></div><input type='text' name='compare' value='<?= $this->compare?>' /></div>
			<div><div><label>options (split with ;)</label></div><input type='text' name='options' value='<?= $this->options?>' /></div>
			<div><div><label>title</label></div><input type='text' name='title' value='<?= $this->title?>' /></div>	
			<div><div><label>filterorder</label></div><input type='text' name='filterorder' value='<?= $this->filterorder?>' /></div>	
			<div><div><label>displayorder</label></div><input type='text' name='displayorder' value='<?= $this->displayorder?>' /></div>	
			<div><div><label>fieldformat</label></div><input type='text' name='fieldformat' value='<?= $this->fieldformat?>' /></div>	
			<div><div><label>html template</label></div><textarea type='text' name='htmlTemplate'><?= $this->htmlTemplate?></textarea></div>	
			<div><div><label>virtual value</label></div><input type='text' name='virtVal' value='<?= $this->virtVal?>' /></div>	
			<div><div><label>icon</label></div>

			<div class='iconEdit'>
			<?php
			if( $image = wp_get_attachment_image_src( $this->icon ) ) {
	
				echo '
					<a href="#" class="icon-upl"><img src="' . $image[0] . '" /></a>
					<input type="hidden" name="icon" value="'.$this->icon.'" />		
					<a href="#" class="icon-rmv">Remove image</a>
								
					';
			
			} else {
			
				echo '
					<a href="#" class="icon-upl">Upload image</a>
					<input type="hidden" name="icon" value="'.$this->icon.'" />		
					<a href="#" class="icon-rmv" style="display:none">Remove image</a>
									
					';
			
			}
			?>
			</div>

			</div>			
						
			<div><input name='editField' type='submit' value='edit' /><input name='deleteField' type='submit' value='delete' /></div>	

	 </form>	

	 <?php
 }
  public function saveToSQL($tabName="fields",$deleteOnly=false) {
   global $wpdb;   
    
   if (is_array($this->options)) {	   
		$this->value=implode(";",$this->options);
		$this->compare="=";
   } 	  
   else $this->value=$this->options;   
   $tableName=AutaPlugin::getTable($tabName,$this->customPostType);
   $query = "DELETE FROM `{$tableName}` WHERE `name` like '{$this->name}';";   
   $wpdb->get_results($query);	
   if (!$deleteOnly) {   	
	$icon=$this->icon;		
	if ($tabName=="ajax") {
		$icon=wp_get_attachment_url($icon);
	}	
	$query = "INSERT INTO `{$tableName}` ( `name`, `value`, `type`, `title`, `compare`, `valMin`, `valMax`, `postType`, `filterorder`, `displayorder`, `icon`, `fieldformat`, `htmlTemplate`, `virtVal`) 
		VALUES ('{$this->name}', '{$this->value}', '{$this->type}', '{$this->title}', '{$this->compare}', '{$this->valMin}', '{$this->valMax}', '{$this->customPostType}', '{$this->filterorder}', '{$this->displayorder}', '{$icon}', '{$this->fieldformat}', '{$this->htmlTemplate}', '{$this->virtVal}');";   
	$wpdb->get_results($query);	 
	return "<br /> $query {$this->name} saved";
   }
 }
 public function typeIs($type) {
	if (strtoupper($this->type)==strtoupper($type)) return true;
	return false;
 }
 public function initValMin() {
	global $wpdb;
	$cast1="";
	$cast2="";
	if ($this->typeIs("NUMERIC")) { 
		 $cast1="CAST("; //numeric range
		 $cast2="as SIGNED)"; //numeric range
	}		
	$query="SELECT MIN($cast1`meta_value`$cast2) AS min FROM ".$wpdb->prefix."postmeta AS pm, ".$wpdb->prefix."posts AS po 
	WHERE pm.meta_key like '{$this->name}' AND po.post_status = 'publish' 
	AND po.post_type = '{$this->customPostType}'";
	
	$min = $wpdb->get_var($query);	 
	$this->valMin=$min;
	return $this->valMin;
 }
  public function initValMax() {
	global $wpdb;
	$cast1="";
	$cast2="";
	if ($this->typeIs("NUMERIC")) { 
		 $cast1="CAST("; //numeric range
		 $cast2="as SIGNED)"; //numeric range
	}	
	$query = "SELECT MAX($cast1`meta_value`$cast2) AS max FROM ".$wpdb->prefix."postmeta AS pm, ".$wpdb->prefix."posts AS po 
	WHERE pm.meta_key like '{$this->name}' AND po.post_status = 'publish' 
	AND po.post_type = '{$this->customPostType}'";	

	/*
	$query = "SELECT MAX(`meta_value`) AS max FROM ".$wpdb->prefix."postmeta AS pm 
	WHERE pm.meta_key like '{$this->name}'";	
	*/
	
	$max = $wpdb->get_var($query);	 
	$this->valMax=$max;
	//echo "<br />".$query;
	return $this->valMax;
 }
 public function getValMin() {
   return $this->initValMin();   
 }
 public function getValMax() {
   return $this->initValMax();   
 }
}
