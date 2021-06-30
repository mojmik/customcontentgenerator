<?php
namespace CustomAjaxFilters\Majax\MajaxWP;

use stdClass;

Class ImageCache {
    private static $imageCache;    
    public static function loadImageCache($postType) {
        ImageCache::$imageCache=array();
        ImageCache::$imageCache=Caching::getCachedJson("allimages".$postType);
        if (ImageCache::$imageCache===false) {     
            ImageCache::$imageCache=ImageCache::loadFromSQL($postType);
            Caching::addCache("allimages".$postType,ImageCache::$imageCache,"allimages".$postType);                         
        }
    }

    public static function getImageUrlFromId($id) {
        $url="";
        foreach (ImageCache::$imageCache as $image) {
            if ($image["thumbId"]==$id) return $image["thumbPath"];
        }		
		return $url;
    }
    public static function loadFromSQL($postType) {   
        global $wpdb;  
        $rows=array();        

        $wpPrefix=$wpdb->prefix;
        $query = "
        SELECT wp.id,post_type,wpm.meta_value as thumbId,wpm2.meta_value as thumbPath 
        FROM {$wpPrefix}posts wp
        INNER JOIN {$wpPrefix}postmeta wpm
        ON wp.post_type like '$postType' AND wp.id = wpm.post_id AND wpm.meta_key like '_thumbnail_id'
        INNER JOIN {$wpPrefix}postmeta wpm2
        ON (wpm.meta_value=wpm2.post_id AND wpm2.meta_key = '_wp_attached_file' AND wpm.meta_key = '_thumbnail_id')
        ";

        foreach( $wpdb->get_results($query) as $key => $row) {	
            $rows[] = ["thumbId" => $row->thumbId, "thumbPath" => "/wp-content/uploads/".$row->thumbPath];
        }	
        return $rows;                
      }
}