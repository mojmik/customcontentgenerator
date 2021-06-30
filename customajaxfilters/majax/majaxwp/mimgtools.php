<?php 
namespace CustomAjaxFilters\Majax\MajaxWP;

Class MimgTools {
	
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
		header('Content-Type:'.$type);
		header('Content-Length: ' . filesize($fileName));
		readfile($fileName);		
	}
	static function prepImage($postId="") {
		//no htaccess or mimgmain in root dir
		//$uploadsPath="./wp-content/uploads";

		//mimgmain in plugin dir
		$uploadsPath="../../../../../uploads/";		
		
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