<?php
namespace CustomAjaxFilters\Admin;
use \CustomAjaxFilters\Majax\MajaxWP as MajaxWP;
/*
 for large tables, where post+postmeta would be slow
*/

class DedicatedTables {
    private $render;
    private $postType;
    public function __construct($postType,$args=[]) {  
        $this->postType=$postType;
    }
    private function getTableName() {
        return AutaPlugin::getTable("dedicated",$this->postType);
    }
    private function initFields() {
        $this->render=new MajaxWP\MajaxRender(true,["type" => $this->postType]);
        $requiredFields=$this->render->getFields()->getAllFields();        
        $allFields=[
            "id" => ["sql" => "int(11) NOT NULL AUTO_INCREMENT", "primary" => true],
            "post_name" => ["sql" => "TEXT NOT NULL"],
            "post_title" => ["sql" => "TEXT NOT NULL"],
            "post_content" => ["sql" => "TEXT NOT NULL"],
            "_thumbnail_id" => ["sql" => "TEXT NOT NULL"]
        ];        
        foreach ($requiredFields as $r) {
            $allFields[$r->outName()]=["sql" => "TEXT NOT NULL"];
        }
        return $allFields;
    }
    
    public function initTable($clear=true) {
        $fieldsDef=$this->initFields($this->postType);
        MajaxWP\MikDb::createTableIfNotExists($this->getTableName(),$fieldsDef);
        if ($clear) MajaxWP\MikDb::clearTable($this->getTableName());
        Settings::writeSetting("cptsettings-dedicatedTables-".$this->postType,$this->getTableName());
    }
    public function countPosts() {
        return wp_count_posts( $this->postType )->publish;        
    }
  
    public function createFromPosts($from,$to) {
        $allFields=$this->initFields();
        $rows=$this->getRows($from,$to);
        $n=0;
        foreach ($rows as $r) {
            $dRow=[];
            foreach ($allFields as $name => $f) {
                if ($name!="id") $dRow[$name]=$r[$name];
            }
            $this->insertRow($dRow);
            $n++;
        }
        return "$from..$to $n created";
    }
    public function insertRow($row) {
        foreach ($row as $key => $val) {
            $row[$key]=esc_sql($val);            
        }     
        MajaxWP\MikDb::insertRow($this->getTableName(),$row); 
    }
    private function getRows($from,$to) {
        global $wpdb;
        $cnt=$to-$from;
        $params=[];
        $params["limit"]="$from,$cnt";
        $params["orderBy"]="";
        $params["orderDir"]="";
        $query=$this->render->getMajaxQuery()->produceSQL($params);
        $rows= $wpdb->get_results( $query, ARRAY_A );
        //AutaPlugin::logWrite($query);
        return $rows;
    }
    

}