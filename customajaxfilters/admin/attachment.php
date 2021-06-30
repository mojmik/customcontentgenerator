<?php
namespace CustomAjaxFilters\Admin;
use \CustomAjaxFilters\Majax\MajaxWP as MajaxWP;

class Attachment {	
    //private $fields;
    private $aList;
    private $cpt;
    private $name;
    private $tableName;
    private $fieldsDef;    
    

    public function __construct($name="attachments",$cpt,$createTable=true) {
        /*
            primary .. primary ket
            required .. always prerent
            type .. type (sql)
            readonly 
            notnull .. notnull (sql)
            autoinc .. autoincrement (sql)
            form-alternative .. handled alternatively on edit forms (like selects etc.)
        */
        $this->fieldsDef=[];
        $this->fieldsDef["id"]=["primary" =>true,"required"=>true,"type"=>"int","readonly"=>true,"notnull"=>true,"autoinc"=>true];
        $this->fieldsDef["cpt"]=["required"=>true,"type"=>"text","readonly"=>true,"fixval"=>$cpt,"hidden"=>true];
        $this->fieldsDef["postId"]=["required"=>true,"type"=>"text","form-alternative"=>true];
        $this->fieldsDef["datum"]=["type"=>"text"];
        $this->fieldsDef["cena"]=["type"=>"text"];
        $this->fieldsDef["aType"]=["type"=>"text"];
        $this->cpt=$cpt;
        $this->name=$name;
        $this->tableName = AutaPlugin::getTable($this->cpt."_".$this->name); 		
        if ($createTable) MajaxWP\MikDb::createTableIfNotExists($this->tableName,$this->fieldsDef,["debug" => false]);
    }
    public function recreate() {
        MajaxWP\MikDb::createTable($this->tableName,$this->fieldsDef,["drop" => true,"debug" => false]);
        echo "recreated";
    }
    
    public function loadSql() {
        global $wpdb;		
		$this->aList=[];
	
        $query = "SELECT * FROM `{$this->tableName}`";        	
		foreach( $wpdb->get_results($query,ARRAY_A) as $key => $row) {					
			$this->aList[] = $row;
		}	
		return true;
    }
    
    public function procEdit() {
        global $wpdb;
        
        //edit field
        $inField=[];
        foreach ($this->fieldsDef as $f => $fDef) {
            if (empty($fDef["autoinc"]) || !$fDef["autoinc"]) $inField[$f]=filter_input( INPUT_POST, $f, FILTER_SANITIZE_STRING );    
        }
        $inField["cpt"]=$this->cpt; 

		if (isset($_POST["editA"])) {	
            echo "<br /> edit ".$inField["id"];		            
            if (!empty($inField["id"]))	{
                $whereField=["id" => $inField["id"]];
                $query=MajaxWP\MikDb::getUpdateSql($this->tableName,$inField,$whereField);
                $wpdb->get_results($query);	                 
                //echo "<br /> $query {$this->name} saved";
                MajaxWP\Caching::pruneCache(true,$this->cpt);
            }            
		}
		
		//new field
		if (isset($_POST["newA"])) {	                
                $query=MajaxWP\MikDb::getInsertSql($this->tableName,$inField);
                //echo "<br /> $query {$this->name} saved";
                $wpdb->get_results($query);	 
				MajaxWP\Caching::pruneCache(true,$this->cpt);
		}
		
		//delete field
		if (isset($_POST["deleteA"])) {			
			
			MajaxWP\Caching::pruneCache(true,$this->cpt);
		}
    }
    private function outSelectPost() {
        global $wpdb;
        $posts = $wpdb->get_results( "SELECT ID,post_title
            FROM $wpdb->posts
            WHERE post_type = '{$this->cpt}' AND post_status = 'publish'            
        "); 
        ?>
        <div>
            <div><label><?= $this->cpt?></label></div>
            <select name='postId'>       
            <?php
            foreach ($posts as $p) {
                ?>
                <option value='<?= $p->ID?>'><?= $p->post_title?></option>
                <?php
            }
            ?>
            </select>
        </div>
        <?php
    }
    public function printFormField($title,$value,$fDef) {
        if (isset($fDef["autoinc"]) && $fDef["autoinc"]===true) return "";
        if (isset($fDef["form-alternative"]) && $fDef["form-alternative"]===true) return "";
        if (!empty($fDef["fixval"])) $value=$fDef["fixval"];  
        if (isset($fDef["hidden"]) && $fDef["hidden"]===true) {
            ?>
            <input type='hidden' name='<?= $title?>' value='<?= $value?>' />                   
            <?php
        }   else {
                if (isset($fDef["readonly"]) && $fDef["readonly"]===true) {
                    ?>
                    <div><div><label><?= $title?></label></div><input type='text' readonly name='<?= $title?>' value='<?= $value?>' /></div>                    
                    <?php
                    
                } else {
                    ?>
                    <div><div><label><?= $title?></label></div><input type='text' name='<?= $title?>' value='<?= $value?>' /></div>                    
                    <?php
                }
        }            
    }
	public function printEdit() {      
      if (empty($this->aList) || count($this->aList)<1) {                     
          return false;
      }
	 ?>
	 <h2>Edit <?= $this->name?></h2>
	 <?php     
		foreach ($this->aList as $a) {		  
          ?>
          <form action='<?= remove_query_arg( 'do')?>' method='post' class='caf-editFieldRow'>	 	 	               
		  <?php            
          foreach ($this->fieldsDef as $f => $fDef) {
            $this->printFormField($f,$a[$f],$fDef);
          }		
          $this->outSelectPost();	
          ?>
          <div><input name='editA' type='submit' value='edit' /><input name='deleteA' type='submit' value='delete' /></div>	
          </form>
          <?php
		}		
    }
	public function printNew() {
	 ?>
	 <h2>New <?= $this->name?></h2>
	 <form class='caf-editFieldRow' method='post'>
        <?php            
        foreach ($this->fieldsDef as $f => $fDef) {
        $this->printFormField($f,"",$fDef);
        }		    
        $this->outSelectPost();				
        ?>					
		<div><input name='newA' type='hidden' value='edit' /><input type='submit' value='create' /></div>
	 </form>
	 <?php
 }
}
