<?php
namespace CustomAjaxFilters\Majax\MajaxWP;
use \CustomAjaxFilters\Admin as MajaxAdmin;

use stdClass;

Class MajaxLoader {	
    private $customPostType;
    private $postId;
    private $isMajax;
    private $majaxQuery;
    private $renderParams;
    private $postRowsLimit;
    private $aktPage;
    private $res;
    private $shortCodes;
    private $fields;
    private $title;
    private $cjBrand;
    private $cjCat;
    private $cntRows;
    private $catDesc;
    private $isCJ;
    private $cj;
    private $currentCat;
    private $subType;
    private $relatedRows;
    private $relatedCat;
    function __construct($params=[]) {	        
        $this->postRowsLimit=9;
        $this->additionalFilters=[];

    }
    public function getParams() {
        $params["postRowsLimit"]=$this->postRowsLimit;
        $params["customPostType"]=$this->customPostType;
        $params["majaxQuery"]=$this->majaxQuery;
        $params["postId"]=$this->postId;
        $params["aktPage"]=$this->aktPage;
        $params["res"]=$this->res;
        $params["fields"]=$this->fields;
        $params["dedicatedTable"]=$this->dedicatedTable;
        $params["cjCat"]=$this->cjCat;
        $params["cjBrand"]=$this->cjBrand;
        $params["cntRows"]=$this->cntRows;
        $params["catDesc"]=$this->catDesc;
        $params["relatedRows"]=$this->relatedRows;
        $params["relatedCat"]=$this->relatedCat;
        return $params;
    }
    private function loadFields() {
		//$this->logWrite("cpt!@ {$this->getPostType()}");
        Caching::setPostType($this->customPostType);
		$this->fields=new CustomFields();
		$this->fields->prepare($this->customPostType);
		$this->fields->loadPostedValues();			
		ImageCache::loadImageCache($this->customPostType);
		$this->majaxQuery->setFields($this->fields);
	}
    public function initDefaults($postType,$loadFields) {
        //no shortcode, eg. ajax
        
        $this->customPostType=$postType;
		$this->dedicatedTable=MajaxAdmin\Settings::loadSetting("dedicatedTables-".$this->customPostType,"cptsettings");
		$params=[];
		$params["rowsLimit"]=$this->postRowsLimit;
		$this->majaxQuery=new MajaxQuery($this->customPostType,$this->dedicatedTable,$params);
		if ($loadFields) $this->loadFields();			
    }
    public function getTitle() {
        return $this->title;
    }
    public function initFromShortCode() {
        global $post;
        if (!isset($post->post_content)) return false;
        $pattern = get_shortcode_regex();
        $this->shortCodes=[];
        if (   preg_match_all( '/'. $pattern .'/s', $post->post_content, $matches ) )   {
            $keys = array();
            $result = array();
            foreach( $matches[0] as $key => $value) {
                $value=substr($value,1,-1);
                if (substr($value,strlen($value)-2)=="\"]") $value=substr($value,0,strlen($value)-2)."\" ]";
                // $matches[3] return the shortcode attribute as string
                // replace space with '&' for parse_str() function
                $get = shortcode_parse_atts($value);
                if (count($get)>0) {
                    $shortCodeId=$get[0];
                    foreach ($get as $key => $val) {
                        if ($key!==0) $this->shortCodes[$shortCodeId][$key]=$val;
                    }
                    
                }
            }
           
            //display the result
        }
        //init defaults
        $innerShortCodeParams=[];
        foreach ($this->shortCodes as $shortCodeName => $shortCodeParams) {
            if ($shortCodeName=="majaxcontent" || $shortCodeName=="majaxstaticcontent") $this->isMajax=true;
            foreach ($shortCodeParams as $p => $val) {
                $v=str_replace("\"","",$val);
                if ($p=="type") $this->customPostType=$v;
                else if ($p=="typ") $this->subType=$v;
                else if ($p=="rows-limit") $this->postRowsLimit=intval($v);
                else if ($p=="cj") $this->isCJ=true;
                //custom filters that came in shortcode
                else $innerShortCodeParams[$p]=$v;
            }
        }

        if (!$this->isMajax) return false;
        $this->postId=isset($_GET['id']) ? filter_var($_GET['id'], FILTER_SANITIZE_STRING) : "";
        $this->aktPage=filter_input( INPUT_GET, "aktPage", FILTER_SANITIZE_NUMBER_INT );
        $this->dedicatedTable=MajaxAdmin\Settings::loadSetting("dedicatedTables-".$this->customPostType,"cptsettings");    
        $this->majaxQuery=new MajaxQuery($this->customPostType,$this->dedicatedTable,["rowsLimit" => $this->postRowsLimit]);
        $this->loadFields();   

        foreach ($innerShortCodeParams as $p => $value) {
            if ($this->fields->getFieldByName($p)) {
                $exVal=explode(";",$value);
                if (count($exVal)>1) {
                    $compare=$exVal[0];
                    $value=$exVal[1];
                    if ($compare=="lessthan") $compare="<";
                    if ($compare=="morethan") $compare=">";
                } else {
                    $compare="LIKE";
                }
                
                $this->fixFilters[]=["name" =>  $key, "filter" => $value, "sqlCompare" => $compare];
                $this->additionalFilters[]=$key;
            }
        }

        if (isset($this->subType)) { 		 
			$this->fixFilters[]=["name" => "mauta_typ", "filter" => $this->subType];					
		}
        
        if ($this->postId) {
			$rows=$this->getSingle();	            
            $this->res=$rows;            
            $this->title=(empty($rows[0]["post_title"])) ? "" : $rows[0]["post_title"];
            $this->cntRows=1;
            $this->cj=new MajaxAdmin\ComissionJunction(["postType" => $this->customPostType]);
            $this->getRelated();
        } else {
            $this->cjBrand=urlDecode(get_query_var("mikbrand"));
            $this->cjCat=get_query_var("mikcat");
            if (!$this->cjCat && !$this->cjBrand && !$this->aktPage) { 
                $rows=$this->getFrontpage();
            } else {
                $this->cj=new MajaxAdmin\ComissionJunction(["postType" => $this->customPostType]);
                
                if ($this->cjCat) {
                    $this->currentCat=$this->cj->getCjTools()->getCatBySlug($this->cjCat,true);
					$this->catDesc=$this->currentCat["desc"];
                    $this->fixFilters[]=["name" =>  $this->cj->getTypeSlug(), "filter" => $this->cjCat."%", "sqlCompare" => "LIKE"];                    
                }
                if ($this->cjBrand) {
					$this->fixFilters[]=["name" =>  $this->cj->getMautaFieldName("brand"), "filter" => $this->cjBrand, "sqlCompare" => "LIKE"];	
					$this->additionalFilters[]="brand";
                    $this->title=$this->cjBrand;
				}
                $this->majaxQuery->setFixFilters($this->fixFilters);
                $rows=$this->getPage();
            }
            $this->res=$rows;
        }
        $this->cntRows=$this->getRowsCnt();
    }
    private function getRowsCnt() {
        if ($this->postId) return 1;
        if (count($this->additionalFilters)>0 || !$this->cjCat) {
            $query=$this->majaxQuery->produceSQL(["countOnly" =>true,"orderBy" => false]);	
			$rowsCount=Caching::getCachedRows($query);
			return $rowsCount[0]["cnt"];
        }
        if ($this->cjCat) {
            return $this->currentCat["counts"];
        }
    }
    public function getCurrentCat() {
        return $this->currentCat;
    }
    public function getPostIdFromAjax() {
        $this->postId=$_POST["category"];
    }
    public function getSingle() {
        $query=$this->majaxQuery->produceSQL(["id" => $this->postId, "limit" => 1, "orderBy" => false]);			
        $useCache=Caching::getUseCache("single");            
        $rows=MikDb::wpDbGetRowsPrepared($query,$useCache);	            
        return $rows;
    }
    public function getMulti($params=[]) {
        //$desc="multi-{$page}-{$this->postRowsLimit}";
        if (array_key_exists("page",$params)) $page=$params["page"];
        $query=$this->majaxQuery->produceSQL(["from" => $page*$this->postRowsLimit]);
		$rows=Caching::getCachedRows($query);
        return $rows;
    }
    private function getFrontpage() {
        $query=$this->majaxQuery->produceSQL(["from" =>$this->aktPage*$this->postRowsLimit,"orderBy" => "rand()", "orderDir" => ""]); 
        $useCache=Caching::getUseCache("multi");
		$rows=MikDb::wpDbGetRowsPrepared($query,$useCache);	
        return $rows;
    }
    private function getPage() {
        $query=$this->majaxQuery->produceSQL(["from" =>$this->aktPage*$this->postRowsLimit,"orderBy" => "rand()", "orderDir" => ""]);
		$useCache=Caching::getUseCache("multi");
		$rows=MikDb::wpDbGetRowsPrepared($query,$useCache);	
        return $rows;
    }
    private function getRelated() {
        $cjCat=$this->res[0][$this->cj->getTypeSlug()];								
        $fixFilters[]=["name" =>  $this->cj->getTypeSlug(), "filter" => $cjCat."%", "sqlCompare" => "LIKE"];
        $this->majaxQuery->setFixFilters($fixFilters);
        $query=$this->majaxQuery->produceSQL(["limit" => "3","orderBy" => "rand()","orderDir" => "", "innerWhere" => " NOT post_name = '".$this->res[0]["post_name"]."'"]);
        $relatedRows=Caching::getCachedRows($query);
        $thisCat=$this->cj->getCjTools()->getCatBySlug($cjCat,true);
        $lastCat=$this->cj->getCjTools()->getCatPathNice($thisCat["path"],true);  
        $this->relatedRows=$relatedRows;
        $this->relatedCat=$lastCat;
    }
}