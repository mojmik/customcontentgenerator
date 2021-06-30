<?php
namespace CustomAjaxFilters\Majax\MajaxWP;

class CustomField {

 
	public function __construct($name="",$value="",$type="",$title="",$compare="=",$valMin=false,$valMax=false,$postType="hp_listing",$icon="",$filterOrder="",$displayOrder="",$fieldformat="",$htmlTemplate="",$virtVal="") {
	 $this->name=$name;	 
	 $this->value=$value;	 
	 $this->type=$type;	 
	 $this->title=$title;	
	 $this->compare=$compare;	   
	 $this->valMin=$valMin;	
	 $this->valMax=$valMax;	
	 $this->postType=$postType;  
	 $this->icon=$icon;  	 
	 $this->fieldformat=$fieldformat;  	 
	 $this->filterOrder=$filterOrder;
	 $this->displayOrder=$displayOrder;
	 $this->fieldformat=$fieldformat;	 
	 $this->htmlTemplate=$htmlTemplate;
	 $this->virtVal=$virtVal;
	 $this->postedValue="";
	}	
	public function setVirtField($settings=[]) {
		if (!empty($settings["name"])) $this->name=$settings["name"];
		if (!empty($settings["filterOrder"])) $this->filterOrder=$settings["filterOrder"];
		if (!empty($settings["postedValue"])) $this->postedValue=$settings["postedValue"];
		if (!empty($settings["postType"])) $this->postType=$settings["postType"];
		if (!empty($settings["compare"])) $this->compare=$settings["compare"];
		
		
	}
	public function setFixFilter($filter) {
		$this->fixFilter=$filter;
	}
	public function outName() {
		return "{$this->name}";
	}
	public function getPostedValue() {
		return "{$this->postedValue}";
	}
	public function outSelectOptions() {
	   $out="";
	   $values=explode(";",$this->value);
	   foreach ($values as $val) {
		$out.="<option value='$val'>$val</option>";	
	   }
	   return $out;
	}
	public function outFieldFilter() {
		$labelFor="for='custField".urlencode($this->name)."'";
		if ($this->compare=="") return ""; //compare=="" => field not filterable, only displayable
		if ($this->typeIs("select") && $this->value!="too many") {
			//lets gen a nice selectbox
		   return "<label {$labelFor}>{$this->title}</label>
		   <div>
		   <select name='".$this->name."' 
		   data-group='majax-fields' 
		   id='custField".urlencode($this->name)."' 
		   class='majax-select' 
		   multiple='multiple'>
		   {$this->outSelectOptions()}
		   </select>
		   </div>
		   ";
		}
		else if ($this->compare==">") {
		   return "<label {$labelFor}>{$this->title}</label>
			   <div class='sliderrng' id='majax-slider-".urlencode($this->name)."'></div>
			   <input class='sliderval' readonly type='text' name='".$this->name."' data-group='majax-fields' data-mslider='majax-slider-".urlencode($this->name)."' id='custField".urlencode($this->name)."' />					
			   "; 
		}
		else if ($this->type=="bool") {
		  // return "<input class='majax-fireinputs' type='checkbox' name='".$this->name."' data-group='majax-fields' id='custField".urlencode($this->name)."' /><label {$labelFor}>{$this->title}</label>";	 
		  return "
		  <label {$labelFor}>{$this->title}</label>
		  <div>
		  	<input class='majax-fireinputs myinput large' type='checkbox' name='".$this->name."' data-group='majax-fields' id='custField".urlencode($this->name)."' />
		  </div>
		  ";	 
		}
		return "<label {$labelFor}>{$this->title}</label><input class='majax-fireinputs' type='text' name='".$this->name."' data-group='majax-fields' id='custField".urlencode($this->name)."' />";
	}
	public function initValues() {
	   global $wpdb;
	   $maxValues=50;
	   $n=0;
		   
	   $query="SELECT DISTINCT(`meta_value`) AS val FROM ".$wpdb->prefix."postmeta AS pm 
	   WHERE pm.meta_key like '{$this->name}' LIMIT 0,".($maxValues+10);
	   
	   foreach( $wpdb->get_results($query) as $key => $row) {	
		   if ($n>$maxValues) {
			   $vals="too many";
			   break;
		   }
		   if ($n>0) $vals.=";";
		   $vals.=$row->val;		
		   $n++;
	   }	
	   
	   $this->value=$vals;
	}
	
	public function getValues() {
	  if ($this->values=="") $this->initValues();
	  return $this->value;
	}
	public function typeIs($type) {
	 if (strtoupper($this->type)==strtoupper($type)) return true;
	 return false;
	}
	public function getFieldFilter() {
			   //$val=$_POST[$this->name];			   
			   $val=$this->postedValue;			   
			   if ($val=="") {
				return false;	
			   }
			   //$val=filter_var($val, FILTER_SANITIZE_STRING);
			   if (strpos($val,"|")>0) {
				   //multiple values in select field
				   $compare="IN";	//multiple values selection
				   if ($this->typeIs("NUMERIC")) $compare="BETWEEN"; //numeric range
				   return array(
					   'key'		=> $this->name,
					   'value'		=> explode("|",$val),
					   'type'		=> $this->type,
					   'compare'	=> $compare
				   );				
			   }
			   else if ($this->typeIs("bool")) {				
				   if ($val=="on" || $val=="1") $val="1";				
				   else $val="0";
				   return array(
					   'key'		=> $this->name,
					   'value'		=> $val,					
					   'compare'	=> '='
				   );	
			   }
			   else {				
				   //single value
				   return array(
					   'key'		=> $this->name,
					   'value'		=> $val,
					   'type'		=> $this->type,
					   'compare'	=> $this->compare
				   );
			   }
	}
	
	public function getFieldFilterSQL() {
		//$val=$_POST[$this->name];			   
		if (isset($this->fixFilter)) { 
			if ($this->typeIs("NUMERIC")) { 				 
				return "cast(`{$this->name}` as unsigned) {$this->fixFilter}";
			} else {
				return "`{$this->name}` {$this->fixFilter}";
			}
			
		}

		$val=$this->postedValue;			   
		if ($val=="") {
		 return false;	
		}
		//$val=filter_var($val, FILTER_SANITIZE_STRING);
		if (strpos($val,"|")>0) {
			//multiple values in select field
			$vals=explode("|",$val);

			if ($this->typeIs("NUMERIC")) { 				 
				 return "cast(`{$this->name}` as unsigned) BETWEEN {$vals[0]} AND {$vals[1]}";
			} else {
				$valsStr="";
				$n=0;
				foreach ($vals as $v) {
				 if ($n>0) $valsStr.=",";
				 $valsStr.="'".$v."'";	
				 $n++;
				}
				return "`{$this->name}` IN ({$valsStr})";
			}				
		}
		else if ($this->typeIs("bool")) {				
			if ($val=="on" || $val=="1") $val="1";				
			else { 
				//not checked value => doesn't matter, select all
				return "";
			}
			return "`{$this->name}` = '{$val}'";
		}
		else {				
			//single value
			if ($this->typeIs("NUMERIC")) {
				return "cast(`{$this->name}` as unsigned) {$this->compare} '{$val}'";			
			} else {
				return "`{$this->name}` {$this->compare} '{$val}'";			
			}
			
		}
	}
	public function loadPostedValue() {
		$val=isset($_POST[$this->name]) ? $_POST[$this->name] : "";	
		$val=filter_var($val, FILTER_SANITIZE_STRING);
		$this->postedValue=$val;
	}
	public function isInSelect($rowVal) {	
		if (!$this->typeIs("select")) return true;		
		$val=$this->postedValue;	
		if (!$val) return true;
		$vals=explode("|",$val);
		if (in_array($rowVal,$vals)) return true;
		
		//if ($rowVal==$val) return true;
		return false;
	}

}
