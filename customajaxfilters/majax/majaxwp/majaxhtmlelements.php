<?php
namespace CustomAjaxFilters\Majax\MajaxWP;
use \CustomAjaxFilters\Admin as MajaxAdmin;
use stdClass;

Class MajaxHtmlElements {	
    private $templatePath;
    private $translating;
    private $postType;
    public function __construct($translating=null) {
        $this->templatePath=plugin_dir_path( __FILE__ ) ."templates/";
        $this->translating=$translating;
    }
    public function setPostType($postType) {
        $this->postType=$postType;
    }
    public function checkPath() {
        if (!file_exists($this->templatePath)) {
            mkdir($this->templatePath, 0744, true);
        } 
    }
    function showBackButton() {
        ?>
        <div style='display:none;' id="majaxback">
            <div id='goBackButton' class='mbutton btn btn-primary'>
                <a href='javascript: history.go(-1)'>zpátky</a>
            </div>
        </div>
        <?php  
    }
    function showMainPlaceHolder() {  
        $this->showBackButton();  
		?>
		<div id="majaxmain" class="majaxmain">
         <div></div>
		 <?php
		  //ajax content comes here
		 ?>
		</div> 
		<?php
    }
    
    function showIdSign() {
        ?>
        <input id='idSign' type='hidden' name='idSign' value='1' />
        <?php
    }
    function showMainPlaceHolderStatic($header=false,$postType="",$emptyDiv=true) {  
        if ($emptyDiv) $emptyDiv="<div></div>";
        if ($header) {
            $this->showBackButton();
            ?>
            <input type='hidden' name='type' value='<?= $postType?>' />
            <div id="majaxmain" class="majaxmain">
            <?= $emptyDiv?>
             <?php
        }
        //ajax content comes here
		else {
            ?>
            </div> 
            <?php
        }
    }
    function showFilters($postType,$allFields) {
		?>
		<form id="majaxform">
			<div class='majaxfiltercontainer'>			
					<input type='hidden' name='type' value='<?= $postType?>' />
				<?php		
				foreach ($allFields as $fields) {
				  ?> <div class='majaxfilterbox'> <?php  
							echo $fields->outFieldFilter();	
				  ?> </div> <?php
				}
				?>			
            </div>            
		</form>		       
		<?php
    }
      
    function showPost($id,$name,$title,$image="",$content="",$metas=[],$itemDetails="",$templateName="multi") {      
        //used for static content output   
        $metaOut=[];     
        $featuredText=[];
        for ($n=0;$n<5;$n++) {
            $metaOut[$n]="";
        }        
        foreach ($metas as $metaName => $metaMisc) {
            //iterate fields        
            if (empty($metaMisc["displayorder"])) continue;
            $metaIcon=$metaMisc["icon"];
            $displayOrder=$metaMisc["displayorder"];
            $fieldFormat=$metaMisc["fieldformat"];
            $metaVal=$itemDetails[$metaName];
            $htmlTemplate=$metaMisc["htmlTemplate"];
            
            if ($metaIcon) $metaIcon="<img src='$metaIcon' />";
            else $metaIcon="<span>{$metaMisc["title"]}</span>";	

            if (!empty($metaMisc["virtVal"])) { //virtual values; first character .. # - clone value from other field, otherwise fix value
                if (substr($metaMisc["virtVal"],0,1) == "#") { 
                    //clone from other field
                    $cloneVar=substr($metaMisc["virtVal"],1);
                    $metaVal=ceil($itemDetails[$cloneVar]*1.21);    
                    $displayOrder=($displayOrder) ? $displayOrder : $metas[$cloneVar]["displayorder"];
                    $htmlTemplate=($htmlTemplate) ? $htmlTemplate : $metas[$cloneVar]["htmlTemplate"];
                    $fieldFormat=($fieldFormat) ? $fieldFormat : $metas[$cloneVar]["fieldformat"];
                    $metaIcon=($metaIcon) ? $metaIcon : $metas[$cloneVar]["icon"];
                }
                else $metaVal=$metaMisc["virtVal"];
            }             
            if ($htmlTemplate) {                              
                $htmlTemplate=str_replace('${formattedVal}',MajaxAdmin\Mutils::formatField($metaVal,$fieldFormat),$htmlTemplate);
                $htmlTemplate=str_replace('${metaIcon}',$metaIcon,$htmlTemplate);                  
                $metaVal=$htmlTemplate;              
            } else {
                $metaVal=MajaxAdmin\Mutils::formatField($metaVal,$fieldFormat);
            }
            
           
           
            if ($displayOrder<20) {
                if (!$htmlTemplate) $metaOut[0]=$metaOut[0] . "<div class='col meta'>$metaIcon"."$metaVal</div>";
                else $metaOut[0]=$metaOut[0] . $metaVal;
            }
            if ($displayOrder>=20 && $displayOrder<=30) {
                //$metaOut[1]=$metaOut[1] . $metaVal;         
                if (!$htmlTemplate) $metaOut[1]=$metaOut[1] . "<div class='col meta'>$metaIcon"."$metaVal</div>";
                else $metaOut[1]=$metaOut[1] . $metaVal;       
            }
            if ($displayOrder>30 && $displayOrder<=40) {
                $propVal=$metaVal;
                if (!$propVal) $propVal="neuvedeno";
                $metaOut[3]=$metaOut[3] . "
                <div class='col-sm-3'>
                    <span>".$metaMisc["title"]."</span>
                    <div class='row'>
                        <span>
                         $propVal
                        </span>
                    </div> 
                </div>";
            }
            if ($displayOrder>40 && $displayOrder<=50) {
                $featuredText[]=$metaVal;
            }
            if ($displayOrder>50 && $displayOrder<=60) {
                $metaOut[$metaName]=$metaVal;
            }
        }
        
        $image=(empty($image)) ? "" : "<img class='' src='$image' />";
        
        $params=[];
        $n=1;
        foreach ($featuredText as $f) {        
          $params["featuredHtml"].="<div class='stripes stripe$n'>$f</div>";
            $n++;
        }
        $params["id"]=$id;
        $params["content"]=$content;
        $params["title"]=$title;
        $params["image"]=$image;
        $params["name"]=$name;
        $params["metaOut"]=$metaOut;
        $params["mainClass"]="majaxoutStatic";
        $html=$this->loadTemplate($templateName); 
        echo $this->processTemplate($html,$params);
        //echo $html;
    }    
   
    function loadTemplate($templateName) {        
        $html=file_get_contents($this->templatePath.$templateName.".html");        
        return $html;
    }
    function formTemplate($templateName,$params=[]) {
        $postType="";        
        if (isset($params["type"])) $postType=$params["type"];
        if (isset($params["title"])) $postTitle=$params["title"];
        else $postTitle=$postType;
        $html=$this->loadTemplate($templateName);   
        $requiredFields="
            <input type='hidden' name='postTitle' value='$postTitle' />
            <input type='hidden' name='postType' value='$postType' />
        ";  
        $html=str_replace("{contactFormRequired}",$requiredFields,$html);                 
        $html=$this->processTemplate($html);

        if ($templateName=="contactForm") {            
            echo $html;
            return "";
        }
        if ($templateName=="defaultForm") {
            return $html;
        }        
    }
    function getHtml($templateName,$type="post",$params=[],$process=false) {
        if ($type=="form") return $this->formTemplate($templateName,$params);        
        if ($process) return $this->processTemplate($this->loadTemplate($templateName),$params);
        return $this->loadTemplate($templateName);
    }
    public function showPagination($pages) {
        $n=0;
        $aktPage=0;
        $cntPage=0;
        foreach ($pages as $page) { 
            if ($page=="2") {
                $aktPage=$n;
            }
            if ($page!="pagination") $n++;
        }            
        $cntPage=$n;
        if ($cntPage==1) return false;
        $p=0;
        $url="";
        ?>
        <div class='mpagination'>
        <?php
        for ($p=0;$p<$cntPage;$p++) {
            if ($p==0 || $p==$cntPage-1 || ($p>$aktPage-3 && $p<$aktPage+3) || $p==$aktPage) {
                if ($p!=$aktPage) {
                    if ($p!=0) $url=add_query_arg("aktPage",$p);
                    else $url=add_query_arg("aktPage",null);
                    ?>                    
                    <span><a data-slug='pagination' data-page='<?= $p?>' href='<?= $url?>'><?= ($p+1)?></a></span>
                    <?php
                } else {
                    ?>
                    <span><?= ($p+1)?></span> 
                    <?php 
                }
            } 
            else {
                if (($p==$aktPage-3) || ($p==$aktPage+3)) { 
                    ?>
                    ..
                    <?php
                }                
            }
        }
        ?>
        </div>
        <?php             
    }
    function translateTemplate($htmlSrc) {
        preg_match_all('/_\((.*?)\)/s', $htmlSrc, $matches);
        for ($i = 0; $i < count($matches[1]); $i++) {
            $key = $matches[0][$i];
            $m = $matches[1][$i];            
            $repl=$this->translating->loadTranslation($m);            
            $htmlSrc=str_replace($key,$repl,$htmlSrc);
        }
        return $htmlSrc;
    }
    function processTemplate($htmlSrc,$params=[]) {
        //translate _(texts) 
        $matches=null;
        $htmlSrc=$this->translateTemplate($htmlSrc);
        //translate _(texts) values of params 
        /*
        $matches=null;
        foreach ($params as $paramKey => $value) {
            preg_match_all('/_\((.*?)\)/s', $value, $matches);
            for ($i = 0; $i < count($matches[1]); $i++) {
                $key = $matches[0][$i];
                $m = $matches[1][$i];
                $repl=MajaxHtmlElements::loadTranslation($m);            
                $params[$paramKey]=str_replace($key,$repl,$params[$paramKey]);
            }
        }
        */
        $params=$this->translating->translateArrayRecursive($params);

        //replace ${params}
        $matches=null;
        preg_match_all('/\${(.*?)}/s', $htmlSrc, $matches);
        for ($i = 0; $i < count($matches[1]); $i++) {
            $key = $matches[0][$i];
            $m = $matches[1][$i];
            $repl="";
            if (isset($params[$m])) $repl=$params[$m];
            $htmlSrc=str_replace($key,$repl,$htmlSrc);
        }
        //replace {params}
        $matches=null;
        preg_match_all('/{(.*?)}/s', $htmlSrc, $matches);
        for ($i = 0; $i < count($matches[1]); $i++) {            
            $key = $matches[0][$i];
            $m = $matches[1][$i];        
            $repl="";              
            if (preg_match_all('/(.*?)\[(.*?)\]/s', $m, $matchesArr)) { // there is a single dim array like something[index] in params
                if (!empty($params[$matchesArr[1][0]]["mauta_".$this->postType."_".$matchesArr[2][0]])) $repl=$params[$matchesArr[1][0]]["mauta_".$this->postType."_".$matchesArr[2][0]];
                else $repl=$params[$matchesArr[1][0]][$matchesArr[2][0]];
            }            
            else if (isset($params[$m])) $repl=$params[$m];            
            $htmlSrc=str_replace($key,$repl,$htmlSrc);
        }
        return $htmlSrc;
        
    }
    public function showFixFields($fields) {
        foreach ($fields as $f) {
         ?>
         <input data-group="majax-fields" type='hidden' name='<?= $f["name"]?>' value='<?= $f["filter"]?>' />
         <?php
        }
    }
    public function showInitValsForAjax($fields) {
        foreach ($fields as $f) {
         ?>
         <input data-group="majax-init" type='hidden' name='<?= $f["name"]?>' value='<?= $f["val"]?>' />
         <?php
        }
    }
}