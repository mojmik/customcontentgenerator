<?php
namespace CustomAjaxFilters\Admin;
use \CustomAjaxFilters\Majax\MajaxWP as MajaxWP;

class ComissionJunction {
 private $cjCols; 
 private $basePage;
 private $brandsSlug;
 private $categorySlug;
 private $dbPrefix;
 private $postType;
 private $cjTools;
 private $currentCat;
 private $currencyFormat;
 public function __construct($args=[]) {          
     $this->brandsSlug="brands";
     $this->categorySlug="category";
     
     if (!empty($args["prefix"])) $this->dbPrefix=$args["prefix"];
     else $this->dbPrefix=MajaxWP\MikDb::getTablePrefix();    
     
     $this->initCJcols();
     if (!empty($args["postType"])) $this->setPostType($args["postType"]);     
 }
 
 public function getCJtools() {
     if (empty($this->cjTools)) { 
         $this->cjTools=new CJTools($this->postType);
         $this->cjTools->setParam("cjCatsTempTable",$this->getTabName("tempCats"));	
         $this->cjTools->setParam("cjCatsTable",$this->getTabName("cats"));	  
         $this->cjTools->setParam("catSlugMetaName",$this->getTypeSlug());
         $metaNames=[];
         foreach ($this->cjCols as $key => $val) {        
          $metaNames[$key]=$this->getMautaFieldName($key);
         }   
         /*             
         $this->cjTools->setParam("imageSlugMetaName",$this->getMautaFieldName("imageurl"));         
         $this->cjTools->setParam("brandSlugMetaName",$this->getMautaFieldName("brand"));         
         */
         $this->cjTools->setParam("metaNames",$metaNames);   
         $this->cjTools->setParam("catSlugMetaName",$this->getTypeSlug());
         $this->cjTools->setParam("catSlug",$this->categorySlug);		
         $this->cjTools->setParam("brandSlug",$this->brandsSlug);	
         $this->cjTools->setParam("currencyFormat",$this->currencyFormat);	        
     }
     return $this->cjTools;
 }
 public function setPostType($postType) {
    $this->postType=$postType;
    MajaxWP\Caching::setPostType($this->postType);	
    $this->tableNames=[
        "main" => $this->dbPrefix."".$this->postType."_cj_import",
        "tempCats" => $this->dbPrefix."".$this->postType."_cj_tempcats",
        "cats" => $this->dbPrefix."".$this->postType."_cj_cats"
    ];
    $this->getCJtools()->setPostType($this->postType);
 }
 public function addShortCodes() {
    add_shortcode('cjcategories', [$this,'outCategoriesTreeShortCode'] );    
 }
 private function initCJcols() {
   $this->currencyFormat=Settings::loadSetting("currencyFormat","site");
   $this->cjCols=[
       "id" => ["sql" => "int(11) NOT NULL AUTO_INCREMENT", "primary" => true,
        "extra" => ["noImport" => true] 
       ],
       "buyurl" => ["sql" => "varchar(1000) NOT NULL", "csvPos" => "LINK", "displayorder" => "51"],
       "shopurl" => ["sql" => "varchar(500) NOT NULL", "csvPos" => "PROGRAM_NAME"],
       "imageurl" => ["sql" => "varchar(500) NOT NULL", "csvPos" => "IMAGE_LINK", "displayorder" => "51", 
            "extra" => ["downloadImage" => true] 
       ],
       "title" => ["sql" => "varchar(100) NOT NULL", "csvPos" => "TITLE"],
       "kw" => ["sql" => "TEXT NOT NULL"],
       "type" => ["sql" => "TEXT NOT NULL", "csvPos" => "PRODUCT_TYPE", "filterorder" => "1", "compare" => "LIKE",
            "extra" => ["removeExtraSpaces" => true, "createSlug" => "yes"] 
        ],
       "availability" => ["sql" => "TEXT NOT NULL", "csvPos" => "AVAILABILITY"],
       "description" => ["sql" => "TEXT NOT NULL", "csvPos" => "DESCRIPTION" ],
       "price" => ["sql" => "TEXT NOT NULL", "csvPos" => "PRICE",  "type" => "NUMERIC", "fieldformat" => $this->currencyFormat, "compare" => ">", "displayorder" => "51",
            "extra" => ["removePriceFormat" => true]
        ],
        "priceDiscount" => ["sql" => "TEXT NOT NULL", "csvPos" => "PRICEDISCOUNT",  "type" => "NUMERIC", "fieldformat" => $this->currencyFormat, "compare" => ">", "displayorder" => "51",
            "extra" => ["removePriceFormat" => true]
        ],
       "views" => ["sql" => "int(11) NOT NULL"],
       "tran" => ["sql" => "TINYINT(1) NOT NULL"],
       "brand" => ["sql" => "varchar(100) NOT NULL", "csvPos" => "BRAND", "displayorder" => "51", "filterorder" => "1", "compare" => "LIKE"],
       "gender" => ["sql" => "varchar(100) NOT NULL", "csvPos" => "GENDER"],
       "gtin" => ["sql" => "varchar(100) NOT NULL", "csvPos" => "GTIN", "displayorder" => "51"],
       "mpn" => ["sql" => "varchar(100) NOT NULL", "csvPos" => "MPN", "displayorder" => "51"],
       "shipping" => ["sql" => "varchar(100) NOT NULL", "csvPos" => "SHIPPING(COUNTRY:REGION:SERVICE:PRICE)"]
   ];   
 }

 public function getFieldsExtras() {
     $extras=array();
     foreach ($this->cjCols as $key => $val) {
         if (!empty($val["extra"])) $extras[$this->getMautaFieldName($key)]=$val["extra"];
     }
     return $extras;
 }
 public function getMautaFieldName($key) {
    if (!empty($this->cjCols[$key]["mautaname"])) return $this->cjCols[$key]["mautaname"];
    return "mauta_".$this->postType."_".$key;
 }
 public function getMautaFields() {
    foreach ($this->cjCols as $key => $val) {        
         $recRow["name"]=$this->getMautaFieldName($key);
         $recRow["title"]=$key;
         $recRow["compare"]=empty($val["compare"]) ? "=" : $val["compare"];
         $recRow["displayorder"]=empty($val["displayorder"]) ? "0" : $val["displayorder"];
         $recRow["filterorder"]=empty($val["filterorder"]) ? "0" : $val["filterorder"];
         $recRow["type"]=empty($val["type"]) ? "" : $val["type"];
         $recRow["fieldformat"]=empty($val["fieldformat"]) ? "" : $val["fieldformat"];
         $recRow["htmlTemplate"]=empty($val["htmlTemplate"]) ? "" : $val["htmlTemplate"];         
         $rows[]=$recRow;        
    }
    return $rows;
 }
 function produceRecord($row,$addInfo=[]) {
    $recRow=[];
    foreach ($this->cjCols as $key => $val) {
        if (!empty($val["csvPos"])) {
         if (!empty($row[$val["csvPos"]])) $recRow[$key]=$row[$val["csvPos"]];
        }        
    }
    foreach ($addInfo as $key => $val) {
        $recRow[$key]=$val;
    }
    return $recRow;
 }
 function createCjTables() {     
        //table for storing cj import from csv
        MajaxWP\MikDb::createTable($this->getTabName("main"),$this->cjCols,["drop" => true]);

        //table for custom categories
        $customCatsCols=[
            "id" => ["sql" => "int(11) NOT NULL AUTO_INCREMENT", "primary" => true],
            "name" => ["sql" => "text"],
            "postType" => ["sql" => "text"],
        ];        
        MajaxWP\MikDb::createTable($this->getTabName("tempCats"),$customCatsCols,["drop" => true]);

        //table for custom categories
        $customCatsCols=[
            "id" => ["sql" => "int(11) NOT NULL AUTO_INCREMENT", "primary" => true],
            "slug" => ["sql" => "text"],
            "path" => ["sql" => "text"],
            "parent" => ["sql" => "text"],
            "postType" => ["sql" => "text"],
            "desc" => ["sql" => "text"],
            "counts" => ["sql" => "int(11)"]            
        ];        
        MajaxWP\MikDb::createTableIfNotExists($this->getTabName("cats"),$customCatsCols,["drop" => false]);

  }
  function handleRewriteRules($basePage=[]) {    
    $this->basePage=$basePage;
    add_filter( 'query_vars', [$this,'mautacj_query_vars'] );
    add_action('init', [$this,'mauta_rewrite_rule'], 10, 0);
    add_filter( 'redirect_canonical', [$this,'disable_canonical_redirect_for_front_page'] );
  }
  function mautacj_query_vars( $vars ) {
    $vars[] = 'mik';
    $vars[] = 'mikcat';
    $vars[] = 'mikbrand';
    $vars[] = 'mikorder';
    $vars[] = 'mimgtools';
    $vars[] = 'cpt';
    return $vars;
  }
  function mauta_rewrite_rule() {        
    $mikBrandy=$this->brandsSlug;
    $mikCatSlug=$this->categorySlug;
    $page=$this->basePage["link"];
    $pageId="page_id={$this->basePage["id"]}&";
    //$page="";
    $phpScript="index.php"; //always index.php for wp

    //tohle se resi primo v htaccess mimo wp
    //add_rewrite_rule( "^$page"."mimgtools/([^/]*)/?", $phpScript.'?'.$pageId.'mimgtools=$matches[1]','top' );
    
    add_rewrite_rule( "^$page"."$mikBrandy/([^/]*)/([^/]*)/?", $phpScript.'?'.$pageId.'mikbrand=$matches[1]&mikorder=$matches[2]','top' );
    add_rewrite_rule( "^$page"."$mikBrandy/([^/]*)/?", $phpScript.'?'.$pageId.'mikbrand=$matches[1]','top' );
    add_rewrite_rule( "^$page"."$mikCatSlug/([^/]*)/$mikBrandy/([^/]*)/([^/]*)/?", $phpScript.'?'.$pageId.'mikcat=$matches[1]&mikbrand=$matches[2]&mikorder=$matches[3]','top' );
    add_rewrite_rule( "^$page"."$mikCatSlug/([^/]*)/$mikBrandy/([^/]*)/?", $phpScript.'?'.$pageId.'mikcat=$matches[1]&mikbrand=$matches[2]','top' );
    add_rewrite_rule( "^$page"."$mikCatSlug/([^/]*)/([^/]*)/?", $phpScript.'?'.$pageId.'mikcat=$matches[1]&mikorder=$matches[2]','top' );
    add_rewrite_rule( "^$page"."$mikCatSlug/([^/]*)/?", $phpScript.'?'.$pageId.'mikcat=$matches[1]','top' );

    //cpt in url; we need it when cpt is needed sooner than it appears in shortcodes (in html titles etc.)
    add_rewrite_rule( "^$page"."c/([^/]*)/$mikBrandy/([^/]*)/([^/]*)/?", $phpScript.'?'.$pageId.'cpt=$matches[1]&mikbrand=$matches[2]&mikorder=$matches[3]','top' );
    add_rewrite_rule( "^$page"."c/([^/]*)/$mikBrandy/([^/]*)/?", $phpScript.'?'.$pageId.'cpt=$matches[1]&mikbrand=$matches[2]','top' );
    add_rewrite_rule( "^$page"."c/([^/]*)/$mikCatSlug/([^/]*)/$mikBrandy/([^/]*)/([^/]*)/?", $phpScript.'?'.$pageId.'cpt=$matches[1]&mikcat=$matches[2]&mikbrand=$matches[3]&mikorder=$matches[4]','top' );
    add_rewrite_rule( "^$page"."c/([^/]*)/$mikCatSlug/([^/]*)/$mikBrandy/([^/]*)/?", $phpScript.'?'.$pageId.'cpt=$matches[1]&mikcat=$matches[2]&mikbrand=$matches[3]','top' );
    add_rewrite_rule( "^$page"."c/([^/]*)/$mikCatSlug/([^/]*)/([^/]*)/?", $phpScript.'?'.$pageId.'cpt=$matches[1]&mikcat=$matches[2]&mikorder=$matches[3]','top' );
    add_rewrite_rule( "^$page"."c/([^/]*)/$mikCatSlug/([^/]*)/?", $phpScript.'?'.$pageId.'cpt=$matches[1]&mikcat=$matches[2]','top' );


  }
  function disable_canonical_redirect_for_front_page( $redirect ) {
    //https://wordpress.stackexchange.com/questions/185169/using-add-rewrite-rule-to-redirect-to-front-page  
    //so that something.com/categoryslug/categoryname did not redirect to something.com
    if ( is_page() && $front_page = get_option( 'page_on_front' ) ) {
        if ( is_page( $front_page ) )
            $redirect = false;
    }
    return $redirect;
   }
  
   public function getTypeSlug() {
    return $this->getMautaFieldName("type");
   }
   private function getTabName($type) {
    if (!empty($this->tableNames[$type])) return $this->tableNames[$type];
    return false;
   }
   public function getTempCatsTabName() {
       return  $this->getTabName("tempCats");
   }
   public function getMainTabName() {
    return  $this->getTabName("main");
   }
   public function getCatsTabName() {
    return $this->getTabName("cats");    
   }   

 
   
   function getCategoriesArr() {
    global $wpdb;
    $cacheOff=true;
    $catTabName=$this->getTabName("cats");    
        
    if (!$cacheOff) {
        /* load from cache */
        $catsFinal=MajaxWP\Caching::getCachedJson("sortedcats".$this->postType);
        if ($catsFinal!==false) {
            return $catsFinal;
        }   
    } else {
        /* load from table */
        $query = "SELECT * FROM `{$catTabName}` WHERE `postType`='$this->postType' AND `counts`>8 ORDER BY rand()";
        //$catsFinal=$wpdb->get_results($query, ARRAY_A);
        $catsFinal=MajaxWP\Caching::getCachedRows($query);
        if (!empty($catsFinal) && count($catsFinal)>0) return $catsFinal;
    }
    
    return $catsFinal;
   }
   
   function getPermaLink($category,$brand="",$sanitize=false) {
    $mikBrandy=$this->brandsSlug;
    $mikCatSlug=$this->categorySlug;
    $page=$this->basePage["link"];
    $catSlug=($sanitize) ? $this->getCjTools()->sanitizeSlug($category) : $category;
    $link="{$page}/{$mikCatSlug}/{$catSlug}/";
    if (!empty($brand)) $link.="{$mikBrandy}/{$brand}";
    return $link;
   }
   function getCategoryNameFromPath($path) {
    $pos=strrpos($path,">");
    if ($pos>0) return substr($path,$pos+1);
    return $path;
   }
 
   function outParentCategory($cats,$thisCat,$depth,$maxDepth=1) {
    //recursively output category branch    
    $goDeeper=true;
    ?>    
    <ul>
        <?php         
        if ($this->currentCat==$thisCat["slug"]) {
            ?>
            <li><strong><?= $this->getCategoryNameFromPath($thisCat["path"])?> (<?= $thisCat["counts"]?>)</strong>
            <?php
        }
        else {
            $goDeeper=($depth<$maxDepth) || (strpos($this->currentCat,$thisCat["slug"])!==false);            
            ?>
            <li><a href='<?= $this->getPermaLink($thisCat["slug"])?>'><?= $this->getCategoryNameFromPath($thisCat["path"])?> (<?= $thisCat["counts"]?>)</a>
            <?php
        }            
        if ($goDeeper) {
            foreach ($cats as $key => $c) {
                if ($c["parent"]===$thisCat["id"]) { 
                    echo $this->outParentCategory($cats,$c,$depth+1,$maxDepth);
                }
            }
        }        
    ?>        
        </li>
    </ul>
    <?php
   }
   function outCategoriesTreeShortCode($atts=[]) {
    $this->currentCat=get_query_var("mikcat");
    ob_start();       
    if (!empty($atts["type"])) { 
        $this->setPostType($atts["type"]);        
        $cats=$this->getCategoriesArr();
    } else return false;
    
    $max= (!array_key_exists("max",$atts)) ? 15 : $atts["max"];
    $brands= (!array_key_exists("nobrands",$atts)) ? true : false;
    $filter= (!array_key_exists("nofilter",$atts)) ? true : false;
    $maxDepth=0;
    if (!$filter) $maxDepth=9;
    if ($brands) $this->getCjTools()->showBrandyNav($this->getMautaFieldName("brand"));
    ?>
    <div>
    <?php
    if ($filter) {
        if ($this->currentCat) {
            ?>
                    <a href='/'><?= $this->getCjTools()->translating->loadTranslation("(all categories)")?></a>
            <?php
        }
        else {
            ?>
                    <strong><?= $this->getCjTools()->translating->loadTranslation("(all categories)")?></strong>    
            <?php
        }
    }    
    $n=0;
    foreach ($cats as $c) {
        //root cats
        if ($max!==null && $max!="0" && $n>$max) break; //display only 15 root cats
        if (!$c["parent"]) { 
            echo $this->outParentCategory($cats,$c,0,$maxDepth);
            $n++;
        }
    }
    ?>
    </div>
    <?php
    return ob_get_clean();
   }
}
