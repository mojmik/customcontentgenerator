<?php
namespace CustomAjaxFilters\Majax\MajaxWP;
use \CustomAjaxFilters\Admin as MajaxAdmin;

use stdClass;

Class MajaxRender {		
	private $postType;
	private $htmlElements;
	private $siteSettings;
	private $translating;
	private $postRowsLimit; //rows limit (static show)
	private $additionalFilters;
	private $fixFilters;
	private $totalPages;
	private $majaxQuery;
	private $loaderParams;
	function __construct($preparePost=false,$atts=[]) {			
			$this->siteSettings["language"]="";				
			if (!empty($atts)) { 
				if (array_key_exists("type",$atts)) $this->setPostType($atts["type"]);
				if (array_key_exists("language",$atts)) $this->siteSettings["language"]=$atts["language"];							
				if (array_key_exists("majaxLoader",$atts)) $this->majaxLoader=$atts["majaxLoader"];
			} 
			if (empty($this->siteSettings["language"])) $this->siteSettings["language"]=MajaxAdmin\Settings::loadSetting("language","site");
			if (empty($this->siteSettings["clickAction"])) $this->siteSettings["clickAction"]=MajaxAdmin\Settings::loadSetting("clickAction","site");
			$this->translating=new Translating($this->siteSettings["language"]);			
			$this->htmlElements=new MajaxHtmlElements($this->translating);			
			$this->htmlElements->setPostType($this->postType);
			//init custom fields
			$this->additionalFilters=[];			
			$this->fixFilters=[];

			if (empty($this->majaxLoader)) {
				//no shortcode, need to init the loader
				$this->majaxLoader=new MajaxLoader();
				$this->majaxLoader->initDefaults($this->getPostType(),$preparePost);
			} else {
				//already preloaded
			}
			$this->loaderParams=$this->majaxLoader->getParams();
			$this->postRowsLimit=$this->loaderParams["postRowsLimit"];
			$this->customPostType=$this->loaderParams["customPostType"];
			$this->majaxQuery=$this->loaderParams["majaxQuery"];				
			$this->fields=$this->loaderParams["fields"];			
	}

	public function getMajaxQuery() {
		return $this->majaxQuery;
	}
	public function getMajaxLoader() {
		return $this->majaxLoader;
	}		
	function getPostType() {			
		return $this->postType; 	
	}
	function setPostType($cpt="") {		
		if ($cpt) $this->postType=$cpt;	
		else $this->postType=filter_var($_POST['type'], FILTER_SANITIZE_STRING); 	
		Caching::setPostType($this->getPostType());		
	}

	
	
	function showStaticContent($atts = []) {		
		//[majaxstaticcontent type="cj" cj="1"]
		
		$postId=$this->loaderParams["postId"];
		$aktPage=$this->loaderParams["aktPage"];
		$showCjCat=($this->loaderParams["cjCat"]) ? true : false;
		$cntRows=($this->loaderParams["cntRows"]) ? $this->loaderParams["cntRows"] : false;
		$catDesc=($this->loaderParams["catDesc"]) ? $this->loaderParams["catDesc"] : false;
		$relatedRows=($this->loaderParams["relatedRows"]) ? $this->loaderParams["relatedRows"] : false;
		$relatedCat=($this->loaderParams["relatedCat"]) ? $this->loaderParams["relatedCat"] : false;
		$emptyDiv=($postId) ? false : true;		
		$this->htmlElements->showMainPlaceHolderStatic(true,$this->postType,$emptyDiv);
		
		//get results
		if ($postId) { 				
			$this->htmlElements->showIdSign();
		}
		$rows=$this->loaderParams["res"];			

		$excerpt=false;
		$templateName="single";
		if ($cntRows>1) {
			$excerpt=true;
			$templateName="multi";
			if ($showCjCat) {
				echo $this->htmlElements->getHtml("cat-header","cat",["desc" => $catDesc],true);
			}
		}

		$n=0;
		foreach ($rows as $row) {			
			$n++;
			$metaMisc=$this->buildInit();
			$item=[];
			//$row["mauta_cj_imageurl"]=str_replace("/mimgtools/","/mimgtools.php?mimgtools=",$row["mauta_cj_imageurl"]);
			$item=$this->buildItem($row,[],0,$excerpt);	
			$this->htmlElements->showPost("s$n",$row["post_name"],$row["post_title"],$item["image"],$item["content"],$metaMisc["misc"],$item["meta"],$templateName);
		}
		if (!empty($relatedRows)) {
			echo $this->htmlElements->getHtml("related-listings","cat",["catName" => $relatedCat],true);
			$excerpt=(count($relatedRows)>1) ? true : false;
			foreach ($relatedRows as $row) {			
				$n++;
				$metaMisc=$this->buildInit();
				$item=[];
				$item=$this->buildItem($row,[],0,$excerpt);		
				$this->htmlElements->showPost("s$n",$row["post_name"],$row["post_title"],$item["image"],$item["content"],$metaMisc["misc"],$item["meta"],"multi");
			}
		}
		
		if ($cntRows>$this->postRowsLimit) { 			 
			$this->htmlElements->showPagination($this->getPagination($cntRows,$aktPage,$this->postRowsLimit));			 
	    } else {
		   $this->totalPages=1;
	    }

		if ($cntRows<1) {
			$html=$this->htmlElements->getHtml("noluck","post",[],true);
			echo $html;
		}		
		$this->htmlElements->showMainPlaceHolderStatic(false);		
		$this->htmlElements->showFixFields($this->fixFilters);
		$this->initVals=[];
		$this->initVals[]=["name" => "totalPages", "val" => $this->totalPages];
		$this->initVals[]=["name" => "totalRows", "val" => $cntRows];
		$this->initVals[]=["name" => "staticPages", "val" => "1"];
		$this->htmlElements->showInitValsForAjax($this->initVals);
	}
	function showStaticForm($atts = []) {								
		$mForm=new MajaxForm($this->getPostType());		
		$this->htmlElements->showMainPlaceHolderStatic(true,$this->getPostType());
		$this->htmlElements->getHtml("contactForm","form",["title"=>"contact title","type" => "dotaz"]);
		//$mForm->printForm("majaxContactForm",$title);
		$this->htmlElements->showMainPlaceHolderStatic(false);
	}
	function showFormFilled($templateName) {
		$mForm=new MajaxForm($this->getPostType());
		$mForm->setTemplate($this->htmlElements,"contactFormMessage");
		echo json_encode($mForm->runForm()).PHP_EOL;
	}
	function showFormFields($type) {
		$mForm=new MajaxForm($type);
		echo json_encode($mForm->renderForm()).PHP_EOL;			
	}
	
	function printFilters($atts = []) {				 
		//prints filter, run by shortcode majaxfilter					
		 $this->htmlElements->showFilters($this->postType,$this->fields->getFieldsFiltered());		 		 
	}	
	function printContent($atts = []) {	
		//prints content, run by shortcode majaxcontent		
		if (!empty($atts["showSomePostsForStart"]) && $atts["showSomePostsForStart"])	{
			$this->showStaticContent($atts);
		}	
		else $this->htmlElements->showMainPlaceHolder();		
	}	
	
	public function getFields() {
		return $this->fields;
	}
	
	
	
	
	function filterMetaSelects($rows) {
		$outRows=[];
		$fields=$this->fields->getFieldsOfType("select");	

		foreach ($rows as $row) {
			$skip=false;
			foreach ($fields as $field) {				
				$skip=false;
				
				if (!$field->isInSelect($row[$field->outName()])) {					
					$skip=true;
					break;
				}
				
			}
			if (!$skip) $outRows[]=$row;
		}
		return $outRows;
	}		
	
	function buildItem($row,$addFields=[],$getJson=1,$excerpt=false) {
		$ajaxItem=new MajaxItem();
		if (!$row["post_name"]) $row["post_name"]=sanitize_title($row["post_title"]);
		$ajaxItem->addField("title",$row["post_title"])
		->addField("name",$row["post_name"])
		->addField("content",$row["post_content"]);
		if (isset($row["ID"])) $ajaxItem->addField("id",$row["ID"]);
		if (isset($row["slug"])) $ajaxItem->addField("url",$row["slug"]);		
		$ajaxItem->addField("image",ImageCache::getImageUrlFromId($row["_thumbnail_id"]));
		if ($excerpt) $ajaxItem->shrinkField("content");
		foreach ($addFields as $key => $value) {
			$ajaxItem->addField($key,$value);
		}
		foreach ($this->fields->getFieldsDisplayed() as $field) {
		 $ajaxItem->addMeta($field->outName(),$row[$field->outName()]);
		}	

		$out=$ajaxItem->expose($getJson);
		return $out;					
	}
	function buildInit($templateName="") {
		$row=[];
		$row["title"]="buildInit";

		foreach ($this->fields->getFieldsFilteredOrDisplayed() as $field) {	
			$fieldName=$field->outName();							
			$row["misc"][$fieldName]["icon"]=$field->icon;
			$row["misc"][$fieldName]["fieldformat"]=$field->fieldformat;
			$row["misc"][$fieldName]["min"]=$field->valMin;
			$row["misc"][$fieldName]["max"]=$field->valMax;			
			$row["misc"][$fieldName]["displayorder"]=$field->displayOrder;	
			$row["misc"][$fieldName]["title"]=$field->title;	
			$row["misc"][$fieldName]["type"]=$field->type;	
			$row["misc"][$fieldName]["htmlTemplate"]=$field->htmlTemplate;	
		}

		foreach ($this->fields->getFieldsVirtual() as $field) {		
			$fieldName=$field->outName();	
			$row["misc"][$fieldName]["icon"]=$field->icon;
			$row["misc"][$fieldName]["fieldformat"]=$field->fieldformat;
			$row["misc"][$fieldName]["min"]=$field->valMin;
			$row["misc"][$fieldName]["max"]=$field->valMax;			
			$row["misc"][$fieldName]["displayorder"]=$field->displayOrder;	
			$row["misc"][$fieldName]["title"]=$field->title;	
			$row["misc"][$fieldName]["type"]=$field->type;	
			$row["misc"][$fieldName]["htmlTemplate"]=$field->htmlTemplate;	
			$row["misc"][$fieldName]["virtVal"]=$field->virtVal;	//first character .. # - clone value from other field, ! - fix value
		}
		//$row["misc"]["neco"]["virtVal"]="#mauta_cenaden"; //first character .. # - clone value from other field, ! - fix value
		//$row["misc"]["neco"]["title"]="Cena bez dph";
		if ($templateName<>"") {
			$row["htmltemplate"][$templateName]=$this->htmlElements->loadTemplate($templateName);	
			$row["htmltemplate"][$templateName]=$this->htmlElements->translateTemplate($row["htmltemplate"][$templateName]);
		}
		$row["mainClass"]="majaxOutDynamic";
		$row["totalPages"]=$this->totalPages;
		$row["language"]=$this->siteSettings["language"];
		return $row;	
	}
	function buildCounts($rows,$cachedJson) {
		$out=[];
		$c=[];	
		if ($cachedJson) {
			$out=$cachedJson;
		}
		else {
			$out[]=["meta_key" => "clearall", "meta_value" => "clearall", "count" => "0", "post_title" => "" ];
			if (!empty($rows) && count($rows)>0) {
				$nonNumericFields=$this->fields->getFieldsWhere(["type" => ["operator" => "!=", "value" => "NUMERIC"], "filterOrder" => ["operator" => ">", "value" => "0"]]);

				foreach ($rows as $row) {
					foreach ($nonNumericFields as $field) {
						$val=$row[$field->outName()];
						if (empty($c[$field->outName()][$val])) $c[$field->outName()][$val]=0;
						$c[$field->outName()][$val]++;
					}	
					
				}
				foreach ($nonNumericFields as $field) {			
					$fieldName=$field->outName();		
					if (!empty($c[$fieldName])) {
						foreach ($c[$fieldName] as $val => $cnt) {	
							//$this->logWrite("iter:{$fieldName} {$val} {$cnt} ");				
								$m["meta_key"]=$fieldName;
								$m["meta_value"]=$val;
								$m["count"]=$cnt;
								$m["post_title"]="counts";
								$out[]=$m;
						}
					}				
				}	
			}
			
			$out[]=["meta_key" => "endall", "meta_value" => "endall", "count" => "0", "post_title" => "" ];
	
			$this->logWrite("json out:".json_encode($out));
		}		
		return $out;
	}
	function getPagination($cntTotal,$aktPage,$cntPerPage) {
		$row=[];
		$row["title"]="pagination";
		$pages=ceil($cntTotal/$cntPerPage);				
		$this->totalPages=$pages;
		if ($pages<=0) return $row;
		for ($n=0;$n<$pages;$n++) {			
			if ($n==$aktPage) $row[$n] = "2";
			else $row[$n] = "1";
		}		
		return $row;
	}	
	function showRows($rows,$params=[]) {
		$delayBetweenPostsSeconds=array_key_exists("delayBetweenPostsSeconds",$params) ? $params["delayBetweenPostsSeconds"] : 0;
		$custTitle=array_key_exists("custTitle",$params) ? $params["custTitle"] : "";
		$limit=array_key_exists("limit",$params) ? $params["limit"] : 9;
		$aktPage=array_key_exists("aktPage",$params) ? $params["aktPage"] : 0;
		$miscAction=array_key_exists("miscAction",$params) ? $params["miscAction"] : "";
		$sliceArray=array_key_exists("sliceArray",$params) ? $params["sliceArray"] : false;		
		$n=0;	
		$totalRows=count($rows);
		$showPosts=true;
		if ($miscAction=="contactFilled") $showPosts=false;
		$templateName="single"; //default template name
		$excerpt=false;
		if ($totalRows>1 || $aktPage>0) { 
			$templateName="multi";
			$excerpt=true;
		}

		if ($custTitle != "majaxcounts") {
			if ($totalRows<1)	 {
				$this->sendBlankResponse();
			}
			$pagination=$this->getPagination($totalRows,$aktPage,$limit);
			if ($sliceArray) $rows=array_slice($rows,$aktPage*$limit,$limit);		
		}
		
		foreach ($rows as $row) {
			if ($custTitle=="majaxcounts") { 
				$row["title"]=$custTitle;
				//$this->logWrite("countitem ".json_encode($row));
				echo json_encode($row).PHP_EOL;								
			}
			else {
				 if ($n==0) { //first row
					 //buildinit - fields description and html template for posts
					echo json_encode($this->buildInit($templateName)).PHP_EOL;						
					if ($miscAction) { 
						if ($this->siteSettings["clickAction"]=="form") {
							//send form
							$mForm=new MajaxForm($this->getPostType(),$row["post_title"],$this->translating);
							if ($miscAction=="action") {
								$mForm->setTemplate($this->htmlElements,"defaultForm","form");								
								echo json_encode($mForm->renderForm()).PHP_EOL;	
							}
							if ($miscAction=="contactFilled") {	
								$mForm->setTemplate($this->htmlElements,"contactFormMessage");													
								echo json_encode($mForm->runForm()).PHP_EOL;	
							}						
						}						
					}					
				 }
				 if ($showPosts) {
					echo $this->buildItem($row,["templateName"=>$templateName],1,$excerpt).PHP_EOL;					
				 }
				 
				 if ($n==count($rows)-1) { 
					 //last row
					echo json_encode($pagination).PHP_EOL;						
				 }
				 
			} 
			flush();
			ob_flush();
			session_write_close();
			if ($delayBetweenPostsSeconds>0) usleep($delayBetweenPostsSeconds*1000000);	
			$n++;
		}	
		//exit;	
	}
	private function createResponse() {
		$response=new StdClass();
		return $response;
	}

	function sendBlankResponse() {
		$response=$this->createResponse();
		$response->title="empty";	
		$response->content="Sorry, no results.";
		echo json_encode($response).PHP_EOL;
		flush();
		ob_flush();		  
		exit;
	}
	public function showSearchBox() {
		$lastSearch=isset($_GET["mSearch"]) ? $_GET["mSearch"] : "";
		//$actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
		$actual_link="/";
		?>
		<form role="search" method="get" class="search-form" action="<?= $actual_link?>">
				<label>
					<span class="screen-reader-text">Vyhledávání</span>
					<input type="search" class="search-field" placeholder="Hledat …" value="<?= $lastSearch?>" name="mSearch">					
				</label>
				<input type="submit" class="search-submit" value="Hledat">
		</form>
		<?php
	}

	function logWrite($val,$file="log.txt") {
	 file_put_contents(plugin_dir_path( __FILE__ ) . $file,date("d-m-Y h:i:s")." ".$val."\n",FILE_APPEND | LOCK_EX);
	}
}