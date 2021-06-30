<?php
namespace CustomAjaxFilters\Admin;
use \CustomAjaxFilters\Majax\MajaxWP as MajaxWP;

class CJtools {
    private $params;
    private $customPostType;
    private $language;
    private $currentCat;
    private $categorySeparator;
    private $replacements;
    public function __construct($customPostType) {
        $this->setPostType($customPostType);
        if (empty($this->language)) $this->language=Settings::loadSetting("language","site");
        $this->translating=new MajaxWP\Translating($this->language);  
        $this->categorySeparator=">";
        $this->separatorVariations=[ "|"," > ","&gt;", "> "," >"];
        $this->bannedCategories=["Heureka.cz","NÁBYTEK","Nábytek"];      
        $this->replacements=[];
        $this->dedicatedTable=false;
    }
    private function getDedicatedTable() {
        if (array_key_exists("dedicatedTable",$this->params)) $this->dedicatedTable=$this->params["dedicatedTable"];
        if (!$this->dedicatedTable) $this->dedicatedTable=Settings::loadSetting("dedicatedTables-".$this->customPostType,"cptsettings");
        return $this->dedicatedTable;        
    }
    

    public function setParam($name, $val) {
        $this->params[$name]=$val;
        return $this;
    }
    public function getParams($key1,$key2=false) {
        if ($key2!==false) return $this->params[$key1][$key2];
        else return $this->params[$key1];
    }
    public function setPostType($postType) {
        $this->customPostType=$postType;  
    }

    public function updateImage($content, $id) {
        preg_match('/< *img[^>]*src *= *["\']?([^"\']*)/i', $content, $matches);
        $imgSrc = $matches[1];
        $content = str_replace($imgSrc, "/mimgtools.php?id=$id", $content);
        $content = str_replace("'", "''", $content);
        $fn = wp_get_upload_dir()["basedir"]."/mimgnfo-$id";
        file_put_contents($fn, $imgSrc);
        return $content;
    }
   
    function mReplText($description) {
        if (!array_key_exists("loaded",$this->replacements)) {
            $txt = Settings:: loadSetting("replacements", "cj");
            if ($txt) {
               $rowsOut=[];
               $rows=explode("\n",$txt);
               foreach ($rows as $r) {                   
                   $r=explode("|",str_replace("\"","",$r));
                   $rowsOut[$r[0]]=$r[1];
               }
            }
            $this->replacements["replacements"]=$rowsOut;
            $this->replacements["loaded"]=true;
        }
        if (empty($this->replacements["replacements"])) return $description;
        $oriDesc=$description;
        foreach($this->replacements["replacements"] as $s => $r) {
            $description = str_replace($s, $r, $description);
            if ($description!=$oriDesc) break;
        }
        return $description;
    }
    
    
    private function stripCategories($str,$params=[]) {
        $startCat=(array_key_exists("startCat",$params)) ? $params["startCat"] : 0;
        $maxCatDepth=(array_key_exists("maxCatDepth",$params)) ? $params["maxCatDepth"] : 9;        
        $types=explode($this->categorySeparator,$str);
        $female=array("dámská","dámské","dámský","dámy","ona","ženy","žena","female","ladies","lady","woman","women","women''s","dívka");
        $male=array("pánská","pánské","pánský","páni","on","muž","muži","male","gentlemen","men","men''s","man","chlapec");
        $catDepth=0;
        $out="";
        $prevType="";
        foreach ($types as $type) {  	  	   
                $lower=mb_strtolower($type,'UTF-8');
                if (Mutils::containsWord($lower,$female)) {
                    $catGender=$type;
                }
                else if (Mutils::containsWord($lower,$male)) {
                    $catGender=$type;  
                }
                else if ($catDepth>=$startCat && $catDepth<$maxCatDepth) {
                $type=Mutils::titleCase($type);  
                //if ($debug) echo "<br />$lower not catch";
                if ($prevType!=$type) {
                    if ($out) $out.=">";
                    $out.=$type;			
                }		
                $prevType=$type;		   
                }
                $catDepth++;		
        }
        return $out;
    }
    
    public function createPostsFromTable($fields, $from = null, $to = null, $extras = []) {
        global $wpdb;
        $table = $this->params["tableName"];
        $dedicatedTable=$this->getDedicatedTable();
        if ($dedicatedTable) { 
            $ded=new DedicatedTables($this->customPostType);
            $ded->initTable(false);
            $dedicatedTable=true;
        }
        $limit = "";
        if (!empty($to)) {
            if (!$from) $from = "0";
            $limit = "LIMIT ".$from.",".($to - $from);
        }
        $results = $wpdb->get_results("SELECT * FROM $table $limit", ARRAY_A);

        //get columns of csvtab
        //get columns of wp_mauta_fields
        $terms = [];
        foreach($results as $r) {
            //create post
            $key = "title";
            $r[$key] = $this->mReplText($r[$key]);
            $r[$key] = str_replace("<br />", "&nbsp;", $r[$key]);
            $r[$key] = strip_tags($r[$key]);
            $titleSafe = str_replace("'", "&#8242;", $r[$key]);
            
            $r["brand"] = $this->mReplText($r["brand"]);

            $key = "description";
            $r[$key] = $this->mReplText($r[$key]);
            $r[$key] = str_replace("</li><li>", "&nbsp;", $r[$key]);
            $r[$key] = str_replace("<br />", "&nbsp;", $r[$key]);
            $r[$key] = str_replace("<br>", "&nbsp;", $r[$key]);
            $r[$key] = strip_tags($r[$key]);
            $r[$key] = Mutils::ms_escape_string($r[$key]);
            $r[$key] = str_replace(", ", ",", $r[$key]); //oprava carek
            $r[$key] = str_replace(",", ", ", $r[$key]); //oprava carek  
            //osetreni qoutes
            $r[$key] = str_replace("''", "^^^", $r[$key]); 
            $r[$key] = str_replace("^^^", "'", $r[$key]); 
            $r[$key] = str_replace("'", "''", $r[$key]); 

            $buyurl = $r["buyurl"];
            
            $r["imageurl"] = str_replace("http://", "https://", $r["imageurl"]);            
            if (!empty($r["imageurl"])) {                
                $id=md5($r["imageurl"]);
                $fn=wp_get_upload_dir()["basedir"]."/mimgnfo-$id";
                file_put_contents($fn,$r["imageurl"]); 
                $r["imageurl"] = "/mimgtools/$id/";                            
            }
            
            //$imageHtml = "<a title='".$titleSafe."' href='$buyurl' rel='nofollow'><img alt='".$titleSafe."' width=300 src='{$r["imageurl"]}' /></a>";
            $imageHtml = "<img alt='".$titleSafe."' src='{$r["imageurl"]}' />";
            $r["imageurl"] = $imageHtml;
            
            $r["price"] = str_replace(" CZK", "", $r["price"]);
            $r["price"] = str_replace(" USD", "", $r["price"]);
            
            $randDiscount=rand(120,160);
            $price=intval($r["price"]);
            $priceDisc=0;
            if ($price>0) {
                $priceDisc=intval(ceil(($price/100)*$randDiscount));
            }             
            $r["priceDiscount"]=$priceDisc;
            

            $postArr = [];
            $postArr["post_title"] = $r["title"];
            $postArr["post_status"] = "publish";
            $postArr["post_content"] = $r["description"];
            $postArr["post_type"] = $this->customPostType;
            $postId = wp_insert_post($postArr);
            if ($dedicatedTable) {
                $row=[
                    "post_title" => $r["title"], 
                    "post_name" => sanitize_title($r["title"]), 
                    "post_content" => $r["description"]
                ];
            }
            //echo json_encode(["result"=> "inserted {$postArr["post_title"]} $postId"]).PHP_EOL;


            //all fields detected in csvtab 
            foreach($fields as $f) {
                $name = $f->name;
                $title = $f->title;
                $metaValue = $r[$title];
                $createMeta = true;

                //skip not interesting fields
                if (!$f->filterorder && !$f->displayorder) continue;

                //extras-apply filters like trim to all values
                if (!empty($extras[$name]["removeExtraSpaces"])) $metaValue = Mutils::removeExtraSpaces($metaValue);
                if (!empty($extras[$name]["removePriceFormat"])) $metaValue = Mutils::removePriceFormat($metaValue);
                
                if (!empty($extras[$name]["createSlug"])) {                    
                    $metaValue = $this->sanitizePath($metaValue);
                    $metaValue=$this->stripCategories($metaValue);
                    $metaValueSlug = $this->sanitizeSlug($metaValue);
                    add_post_meta($postId, $name, $metaValueSlug);
                    if ($dedicatedTable) $row[$name]=$metaValueSlug;
                    $terms[$metaValueSlug] = $metaValue;
                    $createMeta=false;
                }
                if (!empty($extras[$name]["noImport"])) $createMeta=false;
                
                if (isset($metaValue) && $createMeta) {
                    add_post_meta($postId, $name, $metaValue);
                    if ($dedicatedTable) $row[$name]=$metaValue;
                }
            }
            if ($dedicatedTable) { 
                $ded->insertRow($row);                
            } 

        }

        //create temp category table 
        if (!empty($this->params["cjCatsTempTable"])) {
            foreach($terms as $term => $value) {
                //$row=["slug" => $term, "name" => $value, "postType" => $this->customPostType];
                $row = ["name" => $value, "postType" => $this->customPostType];
                MajaxWP\MikDb:: insertRow($this->params["cjCatsTempTable"], $row);
            }
        }

    }
    function getChildCategories($parentId) {
        return MajaxWP\MikDb:: wpdbGetRows($this->params["cjCatsTable"], ["id", "path", "counts"], [["name"=> "parent", "value" => $parentId]]);
    }
    function getCatById($id) {
        $cats = MajaxWP\MikDb:: wpdbGetRows($this->params["cjCatsTable"], "*", [["name"=> "id", "value" => $id]]);
        return $cats[0];
    }
    function getCatByPath($path) {
        $cats = MajaxWP\MikDb:: wpdbGetRows($this->params["cjCatsTable"], "*", [["name"=> "path", "value" => $path]]);
        return $cats[0];
    }
    function getCatBySlug($slug,$useCache=false) {
        if (!$slug) return false;
        $cats = MajaxWP\MikDb:: wpdbGetRows($this->params["cjCatsTable"], "*", [["name"=> "slug", "operator" => "LIKE", "value" => $slug]],$useCache);        
        if (empty($cats[0])) return [];
        $this->currentCat=$cats[0];
        return $this->currentCat;
    }
    
    function getCats($from=null,$limitCnt=null,$where=null) {
        $params["tableNames"]=$this->params["cjCatsTable"];
        $params["cols"]="*";
        $params["where"]=[];
        $params["where"][]=["name"=> "postType", "value" => $this->customPostType];
        if (!empty($where)) { 
            foreach ($where as $w) $params["where"][]=$w;
        }
        $params["limit"]=[];
        if ($from!==null) $params["limit"][]=$from;
        if ($limitCnt!==null) $params["limit"][]=$limitCnt;
        $cats = MajaxWP\MikDb:: wpdbGetRowsAdvanced($params);        
        return $cats;
    }
    
    function getCatMeta($catPath, $queryMetaName, $exact = true, $distinct=true,$limit="",$order="") {        
        $prefix = MajaxWP\MikDb:: getWPprefix();
        $catMetaName = $this->params["catSlugMetaName"];        

        if (!$exact) $catPath.= "%";
        
        $dedTable=$this->getDedicatedTable();
        if ($dedTable) {
            if ($distinct) $selectVar="DISTINCT(`$queryMetaName`)";        
            else $selectVar="`$queryMetaName`";
            $query = "
            SELECT $selectVar AS `meta_value`
            FROM {$dedTable}
            WHERE
            `{$catMetaName}` LIKE '{$catPath}'
            $order
            $limit
            ";   
        } else {
            if ($distinct) $selectVar="DISTINCT(pm2.meta_value)";        
            else $selectVar="pm2.meta_value";
            if ($catPath && $catPath!="%") {
                $query = "
                SELECT $selectVar
                FROM {$prefix}posts po LEFT JOIN {$prefix}postmeta pm1 ON(pm1.post_id = ID) LEFT JOIN {$prefix}postmeta pm2 ON(pm2.post_id = ID)
                WHERE
                po.post_status like 'publish'
                AND po.post_type like '{$this->customPostType}'
                AND pm1.meta_key = '{$catMetaName}'
                AND pm1.meta_value LIKE '{$catPath}'
                AND pm2.meta_key = '$queryMetaName'
                $order
                $limit
                ";             
            }
            else {
                $query = "
                SELECT $selectVar
                FROM {$prefix}posts po LEFT JOIN {$prefix}postmeta pm2 ON(pm2.post_id = ID)
                WHERE            
                po.post_status like 'publish'
                AND po.post_type like '{$this->customPostType}'            
                AND pm2.meta_key = '$queryMetaName'  
                $order       
                $limit
                ";  
            }
        }
        //return $wpdb->get_results($query, ARRAY_A);
        return MajaxWP\Caching::getCachedRows($query);

    }
    function getPostsByCategory($catSlug, $exact = true,$dedTable=false,$dedFields=[]) {
        $prefix = MajaxWP\MikDb:: getWPprefix();
        global $wpdb;
        $catMetaName = $this->params["catSlugMetaName"];
        if (!$exact) $catSlug.= "%";
        if ($dedTable) {
            $cols="";
            foreach ($dedFields as $d) {
                $cols.=",".$d;
            }
            $query = "
            SELECT post_title{$cols}
            FROM $dedTable
            WHERE `{$catMetaName}` LIKE '{$catSlug}'
            ";             
        } else {
            $query = "
            SELECT post_title, ID
            FROM {$prefix}posts po LEFT JOIN {$prefix}postmeta pm1 ON(pm1.post_id = ID)
            WHERE
             po.post_status like 'publish'
            AND po.post_type like '{$this->customPostType}'
            AND pm1.meta_key = '{$catMetaName}'
            AND pm1.meta_value LIKE '{$catSlug}'
            ";             
        }
        
        return $wpdb->get_results($query, ARRAY_A);
    }

    function mRndTxt($texts) {
        if (is_array($texts)) return $texts[array_rand($texts)];
        return $texts;
    }
    function countPosts($c,$dedTable) {
            global $wpdb;
            $catMetaName=$this->params["catSlugMetaName"];
            $catSlug=$this->sanitizeSlug($c["slug"]);
            if ($dedTable) {
                $query="
                SELECT COUNT(post_title) as cnt
                     FROM {$dedTable} 
                     WHERE 
                     {$catMetaName} LIKE '{$catSlug}%'
                 ";
            } else {
                $query="
                SELECT COUNT(post_title) as cnt
                     FROM {$wpdb->prefix}posts po LEFT JOIN {$wpdb->prefix}postmeta pm1 ON ( pm1.post_id = ID) 
                     WHERE po.ID=pm1.post_id
                     AND po.post_status like 'publish' 
                     AND po.post_type like '{$this->customPostType}' 
                     AND pm1.meta_key = '{$catMetaName}' 
                     AND pm1.meta_value LIKE '{$catSlug}%'
                 ";
            }
            
                    
            $cnt=$wpdb->get_var($query);
            if ($cnt<1) {
                //weird
                $cnt=$cnt;
            }
            return $cnt;
           
    }
    function updateCatsDescription($from=null,$to=null) {
        $cats = $this->getCats($from,$to-$from);
        //$cats=$this->getCats(null,null,[["name"=>"slug","operator"=>"LIKE","value"=>"detsky-pokoj%"]]);        
        $doCounts=true;
        $doDesc=true;
        $doParents=true;
        $skipNonBlank=false;
        $dedTable=$this->getDedicatedTable();
        foreach($cats as $c) {
            if ($skipNonBlank && $c["counts"]>0) continue;
            if ($doDesc) $desc = $this->prepareCatDescription($c,$dedTable);
            if ($doCounts) $postsCount=$this->countPosts($c,$dedTable);
            //find parents
            if ($doParents) {
                $parentId=false;
                $rPos=strrpos($c["path"],">");
                if ($rPos>0) {
                    $parentPath=substr($c["path"],0,$rPos);
                    $parentId=$this->getCatByPath($parentPath)["id"];                
                }
            }
            
            $update=[];
            $update[]=["name" => "desc", "value" => $desc];
            $update[]=["name" => "counts", "type" => "%d", "value" => $postsCount];
            if ($parentId) {
                $update[]=["name" => "parent", "type" => "%d", "value" => $parentId];
            } 
            MajaxWP\MikDb:: wpdbUpdateRows($this->params["cjCatsTable"], 
                        $update                          
                    , 
                    [
                        ["name"=> "id", "type" => "%d", "value" => $c["id"]]
                    ]);
        }
        return "cats description updated";
    }
    function sanitizePath($path) {
        $sep=$this->categorySeparator;
        $path=Mutils::removeExtraSpaces($path);
        foreach ($this->separatorVariations as $v)  {
            if (strlen($v)>=strlen($sep)) $path=str_replace($v,$sep,$path);
        }
        //trim from start        
        if (substr($path,0,strlen($sep)) == $sep) $path=substr($path,strlen($sep)+1);
        foreach ($this->bannedCategories as $ban) {
            if ($path == $ban) $path=false;
            if (strpos($path,$ban.$sep)!==false) $path=str_replace($ban.$sep,"",$path);
            if (strpos($path,$sep.$ban)!==false) $path=str_replace($sep.$ban,"",$path);
        }
        return $path;
    }
    function sanitizeSlug($path,$sanitizePath=false) {
        if ($sanitizePath) $path=$this->sanitizePath($path);
        $sep=$this->categorySeparator;        
        return sanitize_title(str_replace($sep, "-", $path));
    }
    
    
    function prepareCatDescription($cat,$dedTable=false) {
        $desc = "#catTitle#enjoymorethan#cntPosts#txtproducts#cats#txtby#brands.#prods#txtAlso#subcats.";        
        $txtproducts = "";
        $enjoy = "";
        $txtby = "";
        $subCatStr = "";
        
        $subcats = $this->getChildCategories($cat["id"]);
        $cntSubCats = count($subcats);
        $n = 0;
        if ($cntSubCats > 3) $cntSubCats = 3;
        foreach($subcats as $subcat) {
            if ($n < $cntSubCats && ($subcat["id"] != $cat["id"])) {
                if ($subCatStr) {
                    $subCatStr.= ", ";
                }
                $subCatStr.= "".$this->getCatPathNice($subcat["path"]);
                $n++;
            }
        }
        unset($subcats);
        if ($subCatStr) {
            $txtAlso = " ".__("Also",CAF_TEXTDOMAIN);
            $subCatStr = " ".$subCatStr;
        }
        else {
            $txtAlso = "";
        }
        $desc = str_replace("#txtAlso", $txtAlso, $desc);
        $desc = str_replace("#subcats", $subCatStr, $desc);

        $termPosts = $this->getPostsByCategory($cat["slug"], false,$dedTable,[$this->params["metaNames"]["brand"],$this->params["metaNames"]["imageurl"]]);

        $brandyArr = array();
        $prodNames = array();
        $prodImages=array();
        $brandyStr = "";
        $prodStr = "";
        $cntPosts = 0;
        $catParent = "";
        $subCatStr = "";

        foreach($termPosts as $mPost) {
            if (!$dedTable) {
                $brand = get_post_meta($mPost["ID"], $this->params["metaNames"]["brand"]);
                $images = get_post_meta($mPost["ID"], $this->params["metaNames"]["imageurl"]);
            } else {
                $brand = $mPost[$this->params["metaNames"]["brand"]];
                $images = $mPost[$this->params["metaNames"]["imageurl"]];
            }
            
            $prodName = $mPost["post_title"];
            $prodImage = Mutils::getImageFromImageUrl($images[0]);
            if (!in_array($brand[0], $brandyArr)) array_push($brandyArr, $brand[0]);
            if (!in_array($prodName, $prodNames)) array_push($prodNames, $prodName);
            if (!in_array($prodImage, $prodImages)) array_push($prodImages, $prodImage);
            $cntPosts++;
        }
        $title="<h1>".$this->getCatPathNice($cat["path"])."</h1>";
        if (count($prodImages)>2) {
            $title=$prodImages[0]."$title".$prodImages[count($prodImages)-1];
        } 
        $desc = str_replace("#catTitle", "<section class='catTitle'>".$title."</section>", $desc);
        //file_put_contents(get_template_directory()."/adfeed.log"," updatetermsdesc2 cnt:$cntPosts ".$cat["path"]." proc:$d/$y now: ".getDateTime()." \n",FILE_APPEND);	
        unset($termPosts);

        $enjoy = "";
        $enjoy = $this->mRndTxt(array(__("Enjoy",CAF_TEXTDOMAIN), __("Find",CAF_TEXTDOMAIN), __("Choose- ",CAF_TEXTDOMAIN), __("Shop- ",CAF_TEXTDOMAIN), __("Eshops with",CAF_TEXTDOMAIN), __("Shops with",CAF_TEXTDOMAIN)));

        if ($cntPosts > 19) {
            if (strlen($cntPosts) > 1) $cntPosts = str_pad(substr($cntPosts, 0, 1), strlen($cntPosts), "0");
            $cntPosts = " ".$cntPosts;
            $enjoy.= " ".$this->mRndTxt(array(__("more than",CAF_TEXTDOMAIN), __("over",CAF_TEXTDOMAIN)));
            $txtproducts = " ".$this->mRndTxt(array(__("products",CAF_TEXTDOMAIN), __("offers",CAF_TEXTDOMAIN), __("sales",CAF_TEXTDOMAIN)));
        }
        else {
            $cntPosts = "";
            $txtproducts = "";
        }
        $desc = str_replace("#cntPosts", $cntPosts, $desc);
        $desc = str_replace("#enjoymorethan", $enjoy, $desc);


        $desc = str_replace("#txtproducts", $txtproducts, $desc);
        shuffle($brandyArr);
        $cntBrandy = count($brandyArr);
        if ($cntBrandy > 3) $cntBrandy = 3;
        for ($n = 0; $n < $cntBrandy; $n++) {
            if ($brandyStr) $brandyStr.= ", ";
            $brandyStr.= $brandyArr[$n];
        }
        if ($brandyStr) {
            $brandyStr = " ".$brandyStr." ".$this->mRndTxt(array(__("and other brands",CAF_TEXTDOMAIN), __("and other producers",CAF_TEXTDOMAIN)));
            $txtby = ", ".__("by",CAF_TEXTDOMAIN);
        }
        else {
            $txtby = "";
            $brandyStr = "";
        }
        $desc = str_replace("#txtby", $txtby, $desc);

        $desc = str_replace("#brands", $brandyStr, $desc);

        $cntProd = count($prodNames);
        shuffle($prodNames);
        if ($cntProd > 3) $cntProd = 3;
        for ($n = 0; $n < $cntProd; $n++) {
            if ($prodStr) $prodStr.= ", ";
            $prodStr.= $prodNames[$n];
        }
        if ($prodStr) {
            $prodStr = " ".$prodStr.".";
        }
        $desc = str_replace("#prods", $prodStr, $desc);


        if ($cat["parent"]) {
            //echo "<h3>".($cat->parent)."</h3>";
            $parentTerm = $this->getCatById($cat["parent"]);
            $catParent = $parentTerm["path"]." - ";
            $kats = ' "'.$catParent.$cat["path"].'"';
        }
        else {
            $kats = ' "'.$cat["path"].'"';
        }
        $desc = str_replace("#cats", $kats, $desc);
        //$result=wp_update_term($cat["id"], $taxonomy, array('description' => $desc));
        return $desc;
    }
    
    
    function createCategories() {
        global $wpdb;    
        $categoriesSorted=array();
    
        $catTabName=$this->params["cjCatsTempTable"];    
        $query="SELECT DISTINCT(`name`) AS category FROM `{$catTabName}` WHERE `postType`='{$this->customPostType}';";
        $categories = $wpdb->get_results($query);	 
    
        $catId=0;    
        foreach ($categories as $c) {  
            $c->category=$this->sanitizePath($c->category);
            if (!$c->category) continue;
            $thisCatArr=explode($this->categorySeparator,$c->category);
            $parent=null;
            $prevCat="";
            $n=0;
            $thisCatPath="";        
            foreach ($thisCatArr as $cat) {
                if ($n>0) { 
                    $thisCatPath.=$this->categorySeparator;    
                    $parent=$prevCat;
                    $parent=$this->findCategoryParent($categoriesSorted,$prevCat,$n-1);
                }
                else $parent=null;            
                $cat=trim($cat);  
                $thisCatPath.=$cat;          
                $existingCat=$this->findCategory($categoriesSorted,$parent,$n,$cat);
                if ($existingCat===false) {
                    $categoriesSorted[$catId] = ["name" => $cat, "parent" => $parent, "level" => $n, "path" => $thisCatPath];
                    $prevCat=$catId;
                    $catId++;
                }
                else {                 
                    $prevCat=$existingCat;
                }
                
                $n++;
            }      
        }
 
    
        $catTabName=$this->params["cjCatsTable"];    
        //MajaxWP\MikDb::clearTable($catTabName);	
        $catsFinal=[];
        $map=[];
        foreach ($categoriesSorted as $key => $c) {
            if ($c["path"]) {
                $row=["slug" => $this->sanitizeSlug($c["path"]), 
                "path" => $c["path"], 
                "parent" => ($c["parent"]===null) ? null : $map[$c["parent"]], 
                "postType" => $this->customPostType
                ];    
                //check if cat already exists
                $currRow=MajaxWP\MikDb::wpdbGetRows($catTabName,"path",[["name" => "path", "value" => $c["path"]]]);
                if (empty($currRow)) {
                    $map[$key]=MajaxWP\MikDb::insertRow($catTabName,$row);
                    $catsFinal[]=$row;
                }         
            }        
        }
        //write to cache
        MajaxWP\Caching::addCache("sortedcats".$this->customPostType,$catsFinal,"sortedcats".$this->customPostType); 
    
        return $catsFinal;      
    }
    function getCatPathNice($path="",$last=false) {
        if (!$path) { 
            if (!empty($this->currentCat["path"])) $path=$this->currentCat["path"];
        }
        if ($last) {
            $cats=explode(">",$path);
            if (is_array($cats)) return end($cats);
            else return $path;
        }
        return str_replace(">", "- ",$path);
    }
 
    function getUrl($cat,$brand="") {
        $url="/";
        $mikBrandSlug=$this->params["brandSlug"];
        $mikCatSlug=$this->params["catSlug"]; 
        if ($cat) {
            $url.=$mikCatSlug."/".$cat."/";
        }
        if ($brand) {
            $url.=$mikBrandSlug."/".urlencode($brand)."/";
        }
        return $url;
    }
    function findCategoryParent($cats,$id,$level) { //categories array, thought parent category id
        $parentName=$cats[$id]["name"];
        foreach ($cats as $key => $c) {
            if ($c["name"] == $parentName && $level==0) return $key;
            if ($c["name"] == $parentName && $level>0) return $this->findCategoryParent($cats,$key,$level-1);
        }
        if ($level==0) return $id;
       }
       function findCategory($cats,$parent,$level,$name) {
        foreach ($cats as $key => $c) {
            if ($level==0) {
                if ($c["name"] == $name) return $key;
            }
            else {
                if ($c["name"] == $name && $c["parent"]==$parent) return $key;
            }
        }
        return false;
    }        
    function showBrandyNav($metaName) {                
        $mikBrand=urlDecode(get_query_var("mikbrand"));
        $catSlug=get_query_var("mikcat");
        $brandyArr=$this->getCatMeta($catSlug,$metaName,false,true," LIMIT 1,15","ORDER BY rand()");
        $brandsArr=[];
        if (count($brandyArr)<2) return false;
        foreach ($brandyArr as $brand) {
            $brandVal=$brand["meta_value"];
            if (!in_array($brandVal,$brandsArr) && $brandVal) $brandsArr[]=$brandVal;
        }
        $brandyArr=$brandsArr;
        //if (empty($brandsArr) || count($brandsArr)<2) return "";
        //showBrandyNav($thisTerm->name,$thisTerm->slug,$brandyStr);
        
        if (!empty($this->currentCat)) $name=$this->getCatPathNice();
        else $name="";
        //$brandsText=$this->translating->loadTranslation("products by brands");
        $allBrandsText=$this->translating->loadTranslation("(all brands)");
        ?>
        
        <?php
        if ($mikBrand) {
            ?>
            <a href='<?= $this->getUrl($catSlug)?>'><?= $allBrandsText?></a>
            <?php
        } else {
            ?>
            <strong><?= $allBrandsText?></strong>
            <?php
        }
        ?>
        
        <ul>
        <?php
        foreach ($brandyArr as $brand) {
         //$url="/$mikCatSlug/$catSlug/$mikBrandy/".urlEncode($brand)."/";         
         ?>
         <li>
         <?php
         if ($brand == $mikBrand) {    
          ?>
          <strong><?= $brand?></strong>
          <?php          
         }  
         else {
          ?>
            <a href='<?= $this->getUrl($catSlug,$brand)?>'><?= $brand?></a>
          <?php
         }
         ?>
         </li>
         <?php
        }
        ?>
        </ul>
        <?php
        return true;
    }
}