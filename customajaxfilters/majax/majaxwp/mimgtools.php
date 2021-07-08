<?php 
namespace CustomAjaxFilters\Majax\MajaxWP;

Class MimgTools {
	public static function getPath($type="rel",$ver="inplugin") {
		//mimgmain in uploads dir
		//$uploadsPath="../../../../../uploads/";		

		//mimgmain in plugin dir
		//$uploadsPath="./mimg";	

		if ($type=="rel") {
			if ($ver=="inplugin") return "./mimg";
			else return "../../../../../uploads/";
		} else {
			if ($ver=="inplugin") return CAF_PLUGIN_PATH."/customajaxfilters/majax/majaxwp/mimg/";
			else return wp_get_upload_dir()["basedir"];
		}
	}	
	public static function handleRequest() {
		$url=$_SERVER['REQUEST_URI'];
		$p=strpos($url,"mimgtools/");
		
		if ($p!==false) {
			$url=substr($url,$p+strlen("mimgtools/"),-1);			
			MimgTools::prepImage($url);	
		} 
	}
	static function streamImage($fileName) {
		$type = 'image/jpeg';
		//header('Content-Type: application/force-download');
		header('Content-Type:'.$type);				
		header('Content-Length: ' . filesize($fileName));
		readfile($fileName);		
	}
	static function prepImage($postId="") {
		//no htaccess or mimgmain in root dir
		//$uploadsPath="./wp-content/uploads";

		//mimgmain in uploads dir
		//$uploadsPath="../../../../../uploads/";		

		//mimgmain in plugin dir
		$uploadsPath=MimgTools::getPath();	

		if (isset($_REQUEST["debug"])) {
			if ($handle = opendir($uploadsPath)) {
				while (false !== ($entry = readdir($handle))) {
					if ($entry != "." && $entry != "..") {
						echo "$entry\n";
					}
				}
				closedir($handle);
			}
			exit;
		}
		
		if (!$postId) return "";
		$filenameNfo = "$uploadsPath/mimgnfo-$postId";
		$filenameImg = "$uploadsPath/mimg2-$postId.jpg";				
		if (file_exists($filenameImg)) {
			//already have image			
			MimgTools::streamImage($filenameImg);
			die();
		}

		if (file_exists($filenameNfo)) {			
			$url=file_get_contents($filenameNfo);		
			$image = ImageCreateFromString(file_get_contents($url));  
			if ($image) {
				$height=true;
				$width=600;
				$height = $height === true ? (ImageSY($image) * $width / ImageSX($image)) : $height;
				
				// create image 
				$output = ImageCreateTrueColor($width, $height);
				ImageCopyResampled($output, $image, 0, 0, 0, 0, $width, $height, ImageSX($image), ImageSY($image));
				// save image
				
				ImageJPEG($output, $filenameImg, 95); 
				// return resized image	  
				MimgTools::streamImage($filenameImg);
				die();
			}
		}		
		die();		
	}

}
?>