<?php
namespace CustomAjaxFilters\Admin;
use \CustomAjaxFilters\Majax\MajaxWP as MajaxWP;


class ImportCSV {
	public $fieldsList=array();
	private $customPostType;	
    private $settings=array();	 
	
	public function __construct($cpt="") {
		$this->customPostType=$cpt;
		$this->settings=[		
		 "createpost" => true,
		 "createmeta" => true,
		 "createcat" => false
		];
		/*
			mapping template: text %1 %2 %3 text2|csv field1|csv field2|csv field3
			no | => string
		*/
		$this->mapping=[
		 "post" => [
			 "post_title" => "auto %1 %2|Běžné číslo|Vozidlo - model",
			 "post_content" => "%1|Popis modelu"			 	 
		 ],
		 "meta" => [
			 "mauta_znacka" => "%1|Vozidlo - značka",
			 "mauta_automat" => "%1|Automatická převodovka",
			 "mauta_cenaden" => "100"
		 ],
		 "replaceglobally" => [
			"Ano" => "1",
			"Ne" => "0"
		 ]
		];
		$this->initDefaults();	
	}	
	public function setWPmapping($mapping) {
		$this->mapping=$mapping;
	}
	private function initDefaults() {
		$this->params["separator"]=";";
		$this->params["encoding"]="";
		$this->params["enclosure"]="";
		$this->params["emptyFirst"]=false;
		$this->params["skipCols"]=[];
		$this->params["createTable"]=false;
		$this->params["colsOnFirstLine"]=true;
		$this->params["mColNames"]=[];
		$this->params["tableName"]="csvtab";
		$this->params["cj"]=null;

	}
	public function setParam($name,$val) {
		$this->params[$name]=$val;
		return $this;
	}	

	public function removePreviousPosts($brute=false) {
		$table=$this->params["tableName"];
		global $wpdb;	
		
		if ($brute) {
			$query="
				DELETE a,b,c
				FROM wp_posts a
				LEFT JOIN wp_term_relationships b ON ( a.ID = b.object_id )
				LEFT JOIN wp_postmeta c ON ( a.ID = c.post_id )
				WHERE a.post_title like 'neco%';
			";
			$result = $wpdb->get_results($query);  			
		}
		$results = $wpdb->get_results("SELECT * FROM $table");		
		foreach ($results as $r) {			
			$title=$this->processTemplate($this->mapping["post"]["post_title"],$r);
			echo "<br />deleted: ".$title;
			$query="
				DELETE a,b,c
				FROM wp_posts a
				LEFT JOIN wp_term_relationships b ON ( a.ID = b.object_id )
				LEFT JOIN wp_postmeta c ON ( a.ID = c.post_id )
				WHERE a.post_title like '".$title."';
			";
			$result = $wpdb->get_results($query);  			
		}
	}
	public function preInsertThumbs() {
		global $post;		

		//list all images
		$args = array(
			'post_type' => 'attachment',
			'post_status' => 'published',
			'posts_per_page' =>-1,			
			'numberposts' => null,
			'orderby' => 'modified',
            'order' => 'DESC',
		);		
		$attachments = get_posts($args);
		foreach ( $attachments as $pic ) {				
			//echo $pic->ID.$pic->post_name."<br />";
			$pics[]=["ID" => $pic->ID, "name" => $pic->post_name];
		}
		
		//insert thumbnail programatically			
		$args = array( 'posts_per_page' => -1, 'post_type' => $this->customPostType );
		$myposts = get_posts( $args );

		foreach ( $myposts as $post ) {	
			$thumbnail_id=0;		
			$arr = explode(' ',trim($post->post_content));
			$word=$arr[0];
			$word=strtolower($word);
			if (strlen($word)<4) continue;
			foreach ($pics as $pic)	 {
				if (strpos($pic["name"],$word)!==false) { 
					$thumbnail_id=$pic["ID"];
					echo "<br />".$word."found in ".$pic["name"];
					break;
				}
			}
			if ($thumbnail_id>0) { 
				update_post_meta( $post->ID, '_thumbnail_id', $thumbnail_id );
				//echo "<br />adding thumb ".$post->post_name;
			}
		}		
	}
	private function processTemplate($template,$r) {
		$templateEx=explode("|",$template);
		if (count($templateEx)<=1) { 
			$out = $template;				
		}
		else {
			$out = $templateEx[0];					
			for ($substCnt=1;$substCnt<count($templateEx);$substCnt++)	{																
				$out = str_replace("%{$substCnt}",$r->{$templateEx[$substCnt]},$out);
			}
		}
		if (!empty($this->mapping["replaceglobally"])) {
			foreach ($this->mapping["replaceglobally"] as $repl=>$for) {
				if ($out==$repl) $out=$for;
			}
		}		
		return $out;
	}
	public function removeExtraSpaces($c) {
		$c=preg_replace('!\s+!', ' ', $c);
		$c=trim($c);
		return $c;
	}
	public function createPostsFromTable($fields,$from=null,$to=null,$extras=[]) {
		global $wpdb;	
		$table=$this->params["tableName"];		
		$limit="";
		if (!empty($to)) {
			if (!$from) $from="0";
			$limit="LIMIT ".$from.",".($to-$from);
		}
		$results = $wpdb->get_results("SELECT * FROM $table $limit");	

		//get columns of csvtab
		//get columns of wp_mauta_fields
		$terms=[];
		foreach ($results as $r) {
			//create post
			$postArr=[];
			if ($this->settings["createpost"]) {
				

				foreach ($this->mapping["post"] as $key=>$template) {
				 $postArr[$key]=$this->processTemplate($template,$r);				 
				}								
				$postArr["post_status"]="publish";
				$postArr["post_type"]=$this->customPostType;
				$postId=wp_insert_post($postArr);
				//echo "<br />inserted {$postArr["post_title"]} $postId";
				//echo json_encode(["result"=>"inserted {$postArr["post_title"]} $postId"]).PHP_EOL;
				
				//create metas
				if ($this->settings["createmeta"]) {								
					foreach ($this->mapping["meta"] as $key=>$template) {						
					 add_post_meta($postId,$key,$this->processTemplate($template,$r));
					}					
				}
				
				//all mauta_fields detected in csvtab are loaded
				foreach ($fields as $f) {
					$name=$f->name;
					$title=$f->title;
					$metaValue=$r->$title;
					$createMeta=true;
					//extras-apply filters like trim to all values
					if (!empty($extras[$name])) {
						foreach ($extras[$name] as $operation) {							
								if (!empty($operation["removeExtraSpaces"])) $metaValue=$this->removeExtraSpaces($metaValue);															
						}
					}
					if (isset($metaValue) && $createMeta) {						
						add_post_meta($postId,$name,$metaValue);
					}
				}
						
				
				if ($this->settings["createcat"]) {
					//category?
					$nameKat="";
					$slugKat="";
					$parentKatId=0;
					$wpdocs_cat = array('taxonomy' => 'hp_listing_category', 'cat_name' => $nameKat, 'category_description' => $nameKat, 'category_nicename' => $slugKat, 'category_parent' => $parentKatId);	 
					$wpdocs_cat_id = wp_insert_category($wpdocs_cat,false);
				}
			}
		}
		
	}
	private function getcsvUTF8($str) {
		$separator=$this->params["separator"];
		$encoding=$this->params["encoding"];
		$enclosure=$this->params["enclosure"];
		if ($str !== false)  {
			$str = $this->autoUTF($str,$encoding);			
			return str_getcsv($str, $separator,$enclosure,"\\");
		}
		return false;	
	}
	private function fgetcsvUTF8(&$handle, $length) {
		$separator=$this->params["separator"];
		$encoding=$this->params["encoding"];
		$enclosure=$this->params["enclosure"];
		if (($buffer = fgets($handle, $length)) !== false)    {
			$buffer = $this->autoUTF($buffer,$encoding);			
			return str_getcsv($buffer, $separator,$enclosure,"\\");
		}
		return false;	
	}
	private function autoUTF($s,$encoding="") 	{
		if ($encoding=="cp1250") return iconv('WINDOWS-1250', 'UTF-8', $s);
		if ($encoding=="cp852") return iconv('CP852', 'UTF-8', $s);
		return $s;
	}
	public function loadCsvValuesFromColumn($file,$selectColNum) {
		//output values from selected column in csv file
		$colsOnFirstLine=$this->params["colsOnFirstLine"];
		$fh = fopen($file, "r"); 
		$lineNum=0;
		$out=[];	
		while ($line = $this->fgetcsvUTF8($fh, 8000)) {		
			$lineNum++;			
			//echo "line:".$line[1];
			if ($colsOnFirstLine && $lineNum===1) {		
			 $mCols=$line;
			}
			else {			 			
				$n=0;
				$out[]=$line[$selectColNum];
			}							 
		}		
		return $out;
	}
	public function loadCsvValuesFromColumnWithKey($file,$keyCol,$keyVal,$selectColNum) {
		//output values from selected column in csv file
		$colsOnFirstLine=$this->params["colsOnFirstLine"];
		$fh = fopen($file, "r"); 
		$lineNum=0;
		$out=[];	
		while ($line = $this->fgetcsvUTF8($fh, 8000)) {		
			$lineNum++;			
			//echo "line:".$line[1];
			if ($colsOnFirstLine && $lineNum===1) {		
			 $mCols=$line;
			}
			else {			 			
				if ($line[$keyCol]==$keyVal) $out[]=$line[$selectColNum];
			}							 
		}		
		return $out;
	}
	public function countLines($file) {
		$linecount = 0;
		$handle = fopen($file, "r");
		while(!feof($handle)){
		$line = fgets($handle);
		$linecount++;
		}
		fclose($handle);
		return $linecount;
	}
	public function clearImportTable() {
		$table=$this->params["tableName"];
		MajaxWP\MikDb::clearTable($table);
	}
	public function loadCsvFile($file,$lineFrom=0,$lineTo=0) {
		global $wpdb;		
		$table=$this->params["tableName"];
		$emptyFirst=$this->params["emptyFirst"];
		$skipCols=$this->params["skipCols"];
		$createTable=$this->params["createTable"];
		$colsOnFirstLine=$this->params["colsOnFirstLine"];
		$mCols=$this->params["mColNames"];
		$cj=$this->params["cj"];
		$fh = fopen($file, "r"); 
		$lineNum=0;
		$mInserted=0;		
		if ($emptyFirst) MajaxWP\MikDb::clearTable($table);
		while ($line = $this->fgetcsvUTF8($fh, 8000)) {		
			$lineNum++;			
			if ($colsOnFirstLine && $lineNum===1) {		
			 $mCols=$line;
			 foreach ($mCols as $n => $c) {
				 if (strtolower($c)=="id") $mCols[$n]="original_id_from_csv";				 
			 }
			 if ($createTable) $this->createTable($mCols);
			}
			else {			 			
				$n=0;
				foreach ($line as $mVal) {
					$colName=$mCols[$n];
					$mVal = str_replace("''", "^^^", $mVal); 
            		$mVal = str_replace("^^^", "'", $mVal); 
            		$mVal = str_replace("'", "''", $mVal); 
					$mRow[$colName]=$mVal;
					$n++;
				}		
				if (!empty($cj)) $mRow=$cj->produceRecord($mRow);			
				MajaxWP\MikDb::insertRow($table,$mRow,$skipCols);	
				$mInserted++;			 
			}			
				 
		}
		return $mInserted;		
	}
	public function loadCsvFileChunky($file,$lineFrom=0,$lineTo=0) {
		$table=$this->params["tableName"];
		$emptyFirst=$this->params["emptyFirst"];
		$skipCols=$this->params["skipCols"];
		$createTable=$this->params["createTable"];
		$colsOnFirstLine=$this->params["colsOnFirstLine"];
		$mCols=$this->params["mColNames"];
		$cj=$this->params["cj"];
		
		$fh = fopen($file, "r"); 
		$lineNum=0;
		$mInserted=0;		
		AutaPlugin::logWrite("about to load csv ".$file." into ".$table);
		//if ($emptyFirst) MajaxWP\MikDb::clearTable($table);
		while ($line = fgets($fh)) {		
			$lineNum++;	
			if ($colsOnFirstLine && ($lineNum)===1) {		
				$mCols=$this->getcsvUTF8($line);
				if ($createTable) $this->createTable($line);
			}

			if ($lineFrom>0 && $lineNum<=$lineFrom) { 
				continue;
			}
			if ($lineTo>0 && $lineNum>$lineTo) { 
				break;								
			}
			$line = $this->getcsvUTF8($line);
			
			if ($colsOnFirstLine && ($lineNum)===1) {		
			 $mCols=$line;
			 if ($createTable) $this->createTable($line);
			}
			else {			 			
				$n=0;
				foreach ($line as $mVal) {
					$colName=$mCols[$n];
					$mVal = str_replace("\\'", "'", $mVal); 
					$mVal = str_replace("''", "^^^", $mVal); 
            		$mVal = str_replace("^^^", "'", $mVal); 
            		$mVal = str_replace("'", "''", $mVal); 
					$mRow[$colName]=$mVal;
					$n++;
				}		
				if (!empty($cj)) $mRow=$cj->produceRecord($mRow);			
				MajaxWP\MikDb::insertRow($table,$mRow,$skipCols);	
				$mInserted++;			 
			}			
				 
		}
		fclose($fh);				
		$total=($colsOnFirstLine) ? ($lineNum-1) : $lineNum;
		AutaPlugin::logWrite("csv loaded ".$file." into ".$table." from: ".$lineFrom." total: ".$total);
		return $total;		
	}
	function createTable($mCols) {	
		global $wpdb;	
		$tabName=$this->params["tableName"];
		$charset_collate = $wpdb->get_charset_collate();
		$wpdb->query( "DROP TABLE IF EXISTS {$tabName}");
		$cols="";
		foreach ($mCols as $mCol) {
			$cols.="`$mCol` text,";
		}
		$sql = "CREATE TABLE $tabName (
		  id mediumint(9) NOT NULL AUTO_INCREMENT,
		  $cols
		  PRIMARY KEY  (id)
		) $charset_collate;";		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		AutaPlugin::logWrite($sql);
		dbDelta( $sql );
	}
	

	public function loadFileLoadData($file,$sep="^",$enc='"') {
		//tohle nepude, protoze to je zakazany kvuli security
		global $wpdb;
		$query="LOAD DATA LOCAL INFILE '{$file}' INTO TABLE sas FIELDS TERMINATED BY '{$sep}' ENCLOSED BY '{$enc}'
						IGNORE 1 LINES (@category,@temple) SET category = @category, temple = @temple;";
		echo "<div style='position:absolute;top:100px;left:600px;'>".$query."</div>";
						
		$wpdb->query(
                $wpdb->prepare(
                        "LOAD DATA LOCAL INFILE '{$file}' INTO TABLE sas FIELDS TERMINATED BY '{$sep}' ENCLOSED BY '{$enc}' 
						IGNORE 1 LINES (@category,@temple) SET category = @category, temple = @temple;"
                )
        );
	}
	public function gotUploadedFile() {
		if(isset($_FILES['mfilecsv']) && ($_FILES['mfilecsv']['size'] > 0)) return true;
		return false;
	}
	public function doImportCSVfromWP($fn="",$from=0,$to=0) {
		if ($fn) {
			if ($to>0) return $this->loadCsvFileChunky($fn,$from,$to);					  	  																				
			else return $this->loadCsvFile($fn,$from,$to);					  	  																				
		} else {
			$upload_overrides = array( 'test_form' => false ); 
			$uploaded_file = wp_handle_upload($_FILES['mfilecsv'], $upload_overrides);
			$fn = $uploaded_file['file'];
			if(isset($fn) && wp_check_filetype($uploaded_file['file'],["text/csv"])) {									
					if ($to>0) $this->loadCsvFileChunky($fn,$from,$to);		  	  																	
					else $this->loadCsvFile($fn,$from,$to);		  	  																	
					return "imported";
			}
		}				
	}
	public function showImportCSV($type="") {							
			?>
			<form method="post" enctype="multipart/form-data"> 
				<input type="file" name="mfilecsv" id="mfilecsv" />				
			<?php
				if ($type=="csv") {
				 ?>
					<input type="text" name="separator" value="^" />	 
					<input type="text" name="encoding" value="cp852" />	 
				 <?php
				}
			?>
			<input type="submit" name="html-upload" id="html-upload" class="button" value="Upload" />
			</form>
			<?php					
	}
	public function showMakePosts($type,$table,$total) {							
		?>
		<form id="mautaAddCSV" method="post"> 			
			<input type="hidden" name="totalRecords" value="<?= $total?>" />
			<input type="hidden" name="table" value="<?= $table?>" />
			<input type="hidden" name="csvtype" value="<?= $type?>" />
			<input type="hidden" name="doajax" value="makeposts" />
			<input type="submit" name="html-upload" id="html-upload" class="button" value="Process" />
		</form>
		<?php					
}
}
