<?php
namespace CustomAjaxFilters\Majax\MajaxWP;
use \CustomAjaxFilters\Admin as MajaxAdmin;

class Translating {   
    private $lng;
    function __construct($lng) {  
        $this->lng=$lng;
    }
     function loadTranslation($what) {
        $tran=$what;
        $file=plugin_dir_path( __FILE__ ) ."translations/translations.txt";
        $importCSV=new MajaxAdmin\ImportCSV();	
		$importCSV->setParam("separator","^")
        ->setParam("colsOnFirstLine",false);
        $languages=["sk" => 2, "cs" => 1, "en" => 0];
        //$language=substr(get_locale(),0,2);        
        if (empty($this->lng) || !$this->lng) $this->lng="en";
        $rows=$importCSV->loadCsvValuesFromColumnWithKey($file,0,$what,$languages[$this->lng]);
        if (count($rows)) $tran=$rows[count($rows)-1];
        //MajaxAdmin\AutaPlugin::logWrite("",$rows);
        return $tran;
    }
     function translateArrayRecursive($params) {
        foreach ($params as $paramKey => $value) {
            if (is_string($value)) {
                preg_match_all('/_\((.*?)\)/s', $value, $matches);
                for ($i = 0; $i < count($matches[1]); $i++) {
                    $key = $matches[0][$i];
                    $m = $matches[1][$i];
                    $repl=$this->loadTranslation($m);            
                    $params[$paramKey]=str_replace($key,$repl,$params[$paramKey]);
                }
            }
            else if(is_array($value)) $params[$paramKey]=$this->translateArrayRecursive($params[$paramKey]);
        }
        return $params; 
    }
}
?>
