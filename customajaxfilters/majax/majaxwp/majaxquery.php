<?php
namespace CustomAjaxFilters\Majax\MajaxWP;
use \CustomAjaxFilters\Admin as MajaxAdmin;

use stdClass;

Class MajaxQuery {	
    private $postType;	
    private $params;
    private $fields;
    private $dedicatedTable;
    private $postRowsLimit;
    private $fixFilters;
    function __construct($postType,$dedTable,$params) {	        
        $this->postType=$postType;	
        $this->dedicatedTable=$dedTable;
        $this->postRowsLimit=$params["rowsLimit"];
    }
    function getPostType() {			
		return $this->postType; 	
	}
    public function setFields($fields) {
        $this->fields=$fields;
    }
    function addToStr($sep,$add,$str) {
		if ($str) $str.=$sep.$add;
		else $str=$add;
		return $str;
	}
    function getSqlFilterMeta($fieldName) {
		return ",MAX(CASE WHEN pm1.meta_key = '$fieldName' then pm1.meta_value ELSE NULL END) as `$fieldName`";
	}
	function getSqlSelectMeta($fieldName) {
        if ($this->dedicatedTable) return ",`$fieldName`";
		return ",pm1.`$fieldName`";
	}
    function stripPmTable($s) {
        return str_replace("pm1.","",$s);
    }
    public function setFixFilters($f) {
        $this->fixFilters=$f;
    }
    function produceSQL($params=[]) {
        $id=array_key_exists("id",$params) ? $params["id"] : null;
        $from=array_key_exists("from",$params) ? $params["from"] : null;
        $countOnly=array_key_exists("countOnly",$params) ? $params["countOnly"] : false;
        $postAll=array_key_exists("postAll",$params) ? $params["postAll"] : false;

		$mType = $this->getPostType();
		$col="";
		$filters="";
		$colSelect="";
        if (!empty($this->fixFilters)) $this->fields->setFixFilters($this->fixFilters);
		foreach ($this->fields->getFieldsFilteredOrDisplayed() as $field) {			
			$fieldName=$field->outName();		
			$col.=$this->getSqlFilterMeta($fieldName);
			$colSelect.=$this->getSqlSelectMeta($fieldName);
			$filter=$field->getFieldFilterSQL();
			if ($filter && !$field->typeIs("select")) {
				$filters=$this->addToStr(" AND ",$filter,$filters);				
			}
			if ($field->typeIs("numeric")) { 
				$orderBy="cast(`".$fieldName."` AS unsigned)";
				$orderDir='ASC';
			}
		}
		
		$additionalMetas=["_thumbnail_id"];
		foreach ($additionalMetas as $fieldName) {			
			$col.=$this->getSqlFilterMeta($fieldName);
			$colSelect.=$this->getSqlSelectMeta($fieldName);
		}

		if ($id) $filters=$this->addToStr(" AND ","post_name like '$id'",$filters);
		$limit="";	
		if (!$postAll) {
		 if ($from) $limit=" LIMIT $from,".$this->postRowsLimit;	
		 else $limit=" LIMIT ".$this->postRowsLimit;	
		}
		$innerWhere="";
		if (array_key_exists("limit",$params)) $limit=" LIMIT ".$params["limit"];
		if (array_key_exists("orderBy",$params)) $orderBy=$params["orderBy"];
		if (array_key_exists("orderDir",$params)) $orderDir=$params["orderDir"];
		if (array_key_exists("innerWhere",$params)) $innerWhere=$params["innerWhere"];
		
        if ($orderBy)  $orderBy="ORDER BY ".$orderBy;
        if (!$orderBy) $orderDir="";
		//customSearch
		$customSearch="";
		if (!empty($_GET['mSearch'])) {
			$this->additionalFilters[]="mSearch";
			$contentSearch=filter_var($_GET['mSearch'], FILTER_SANITIZE_STRING); 	
			if ($contentSearch) $customSearch="post_content like '%$contentSearch%' ";
		}
        if ($this->dedicatedTable) {
            $where=MikDb::makeWhere([$innerWhere,$customSearch,$filters]);
            if ($countOnly) {
                $query="
                SELECT count(*) as cnt,post_title,post_name,post_content{$colSelect}
                    FROM {$this->dedicatedTable}
                    $where
                    $orderBy $orderDir
                ";
            } else {
                $query="
                SELECT post_title,post_name,post_content{$colSelect}  
                    FROM {$this->dedicatedTable}
                    $where
                    $orderBy $orderDir
                    $limit
                ";
            }
        } else {
            $where=MikDb::makeWhere([$filters]);
            $postFilters=MikDb::makeWhere(["post_status like 'publish'","post_type like '$mType'",$innerWhere,$customSearch]);
            if ($countOnly) {
                if (!$filters && !$innerWhere && !$customSearch) {
                    $query="
                    SELECT count(*) as cnt,post_title,post_name,post_content{$col}
                        FROM wp_posts LEFT JOIN wp_postmeta pm1 ON ( pm1.post_id = ID) 
                        $postFilters
                        GROUP BY ID
                        $orderBy $orderDir
                    ";
                } else {
                    $query="
                    SELECT count(*) as cnt  FROM
                    (SELECT post_title,post_name,post_content 
                        $col
                        FROM wp_posts LEFT JOIN wp_postmeta pm1 ON ( pm1.post_id = ID) 
                        $postFilters
                        GROUP BY ID
                        ) AS pm1
                        $where
                        $orderBy $orderDir
                    ";
                }
            } else {
                if (!$filters && !$innerWhere && !$customSearch) {
                    $query="                    
                    SELECT post_title,post_name,post_content{$col}
                        FROM wp_posts LEFT JOIN wp_postmeta pm1 ON ( pm1.post_id = ID) 
                        $postFilters
                        GROUP BY ID
                        $orderBy $orderDir
                        $limit
                        ";
                } else {
                    $query="
                    SELECT post_title,post_name,post_content{$colSelect}  FROM
                    (SELECT post_title,post_name,post_content 
                        $col
                        FROM wp_posts LEFT JOIN wp_postmeta pm1 ON ( pm1.post_id = ID) 
                        $postFilters
                        GROUP BY ID
                        ) AS pm1
                        $where
                        $orderBy $orderDir
                        $limit
                        ";
                }
                
            }
        }
		
		
		return $query;
	}
	function produceSQLWithAttachments($id="") {
		//grabs post,metas and external related tables (attachments)
		$mType = $this->getPostType();
		$col="";
		$filters="";
		$colSelect="";

		foreach ($this->fields->getFieldsFilteredOrDisplayed() as $field) {			
			$fieldName=$field->outName();		
			$col.=$this->getSqlFilterMeta($fieldName);
			$colSelect.=$this->getSqlSelectMeta($fieldName);
			$filter=$field->getFieldFilterSQL();
			if ($filter && !$field->typeIs("select")) {
				$filters=$this->addToStr(" AND ",$filter,$filters);				
			}
			if (strpos($fieldName,"cena")!==false) { 
				$orderBy="cast(pm1.`".$fieldName."` AS unsigned)";
				$orderDir='ASC';
			}
		}
		if (!$orderBy) { 
			$orderBy="post_title";
			$orderDir="ASC";
		}
		$additionalMetas=["_thumbnail_id"];
		foreach ($additionalMetas as $fieldName) {			
			$col.=$this->getSqlFilterMeta($fieldName);
			$colSelect.=$this->getSqlSelectMeta($fieldName);
		}

		if ($id) $filters=$this->addToStr(" AND ","post_name like '$id'",$filters);
		if ($filters) $filters=" WHERE $filters";
		$query=
		"
		SELECT post_title,post_name,post_content{$colSelect}  FROM
		(SELECT post_title,post_name,post_content 
			$col
			FROM wp_posts LEFT JOIN wp_postmeta pm1 ON ( pm1.post_id = ID) 
			WHERE post_id=id 
			AND post_status like 'publish' 
			AND post_type like '$mType'			
			GROUP BY ID
			) AS pm1
			$filters
			ORDER BY $orderBy $orderDir
		";
		return $query;
	}
}