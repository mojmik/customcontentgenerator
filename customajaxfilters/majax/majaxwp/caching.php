<?php
namespace CustomAjaxFilters\Majax\MajaxWP;

use stdClass;

Class Caching {	    
    public static $cacheMap = array();
    private static $customPostType;
    private static $cachePath;    
    private static $compressJson=0;    
    private static $recreateCache=false;   
    static function checkPath($cpt="") {
        $path=Caching::getCachePath($cpt);
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        } 
    }
    static function checkPruneCacheNeeded($cpts) {
        if (!is_array($cpts)) {
            $cptsArr[]=$cpts;
            $cpts=$cptsArr;
        } 
        $fn=wp_upload_dir()["basedir"]."/deletecache.txt";
        if (file_exists($fn)) {
            foreach ($cpts as $cpt) {
                //$cachePath=plugin_dir_path( __FILE__ ) ."cache/$cpt/";  
                Caching::setPostType($cpt);
                Caching::pruneCache(true);
            }
            Caching::$recreateCache=true;
            unlink($fn);
        }   
    }
    static function setPostType($cpt) {
        Caching::$cachePath="";
        Caching::$customPostType=$cpt;
    }
    static function getCachePath($cpt="") {
        if ($cpt) {
            return plugin_dir_path( __FILE__ ) ."cache/".$cpt."/";
        }
        if (!Caching::$cachePath) { 
            if (Caching::$customPostType) Caching::$cachePath=plugin_dir_path( __FILE__ ) ."cache/".Caching::$customPostType."/";
            else Caching::$cachePath=plugin_dir_path( __FILE__ ) ."cache/";
        }
        return Caching::$cachePath;
    }
    static function pruneCache($all=false,$cpt="") {
        $files = glob(Caching::getCachePath($cpt) . "*");
        $now   = time();
      
        foreach ($files as $file) {
          if (is_file($file)) {
            if ( ($now - filemtime($file) >= 60 * 60 * 24 * 7) || $all) { // 1 days or all
              unlink($file);
            }
          }
        }
        Caching::logWrite("cache pruned for ".Caching::getCachePath($cpt));
    }

    
    static function addCache($query,$rows,$fnId="") {
        //add query into cachemap and write rows        
        if (!count($rows)) {
            $fnId="empty";
        } 
        if (!$fnId) {
            $fnId=date("d-m-y-h-i-s").rand(10000,99999).".txt";            
        }        
        Caching::$cacheMap[] = ["query" => $query, "fnId" => $fnId];
        file_put_contents(Caching::getCachePath() . "cachemap.txt",$query."|".$fnId."^",FILE_APPEND | LOCK_EX);
        Caching::cacheWrite($fnId,$rows);
        Caching::logWrite("$query added to cache");
    } 
    static function cacheWrite($name,$rows) {
        //add rows into cache        
        if (!count($rows)) {
            $name="empty";
        } 
        $path=Caching::getCachePath() . "$name.json";
        if (file_exists($path)) return; //empty rows
        if (Caching::$compressJson) 
         file_put_contents($path,gzcompress(json_encode($rows)));
        else 
         file_put_contents($path,json_encode($rows));
    }
    static function cacheRead($name) {
       if (Caching::$recreateCache) return false;
       $txt=@file_get_contents(Caching::getCachePath() . "$name.json");
       if ($txt===false) return false;
       if (Caching::$compressJson) $rows=json_decode(gzuncompress($txt),1);
       else $rows=json_decode($txt,1);
       return $rows;
    }
    static function getUseCache($param) {
        if ($param=="single") return false;        
        return true;
    }
    static function getCachedFn($query) {
        if (empty(Caching::$cacheMap)) {
            Caching::loadCacheMap();
        }         
        foreach (Caching::$cacheMap as $row) {
            if (isset($row["query"]) && $row["query"] == $query) return $row["fnId"];
        }
        return false;
    }
    static function getCachedRows($query) {
       global $wpdb;
       $fnName=Caching::getCachedFn($query);
       if ($fnName == false) {
        if ($wpdb) {
            $rows=$wpdb->get_results($query,ARRAY_A);
        }
        else $rows=MikDb::getRows($query);
        Caching::addCache($query,$rows);
        Caching::logWrite("$query added to cache");
        return $rows;
       }
       Caching::logWrite("$query loaded from cache");
       return Caching::cacheRead($fnName);
    }
    static function getCachedJson($query) {
        $fnName=Caching::getCachedFn($query);  
        if ($fnName == false) {
         Caching::logWrite("-$query json not exist in cache-");
         return false;
        }
        Caching::logWrite("$query json loaded from cache");        
        return Caching::cacheRead($fnName);
    }
    
    static function loadCacheMap() {
        if (Caching::$recreateCache) return false;
        Caching::checkPath();        
        Caching::$cacheMap=array();
        $txt=@file_get_contents(Caching::getCachePath() . "cachemap.txt");
        if ($txt === false) return false;
        Caching::$cacheMap=[];
        $rows=explode("^",$txt);
        foreach ($rows as $row) {
            $ex=explode("|",$row);            
            if (!empty($ex[0]) && !empty($ex[1])) Caching::$cacheMap[]= ["query" => $ex[0], "fnId" => $ex[1]];
        }
    }
    static function logWrite($val,$fn="caching.txt") {
        file_put_contents(Caching::getCachePath() . $fn,date("d-m-Y h:i:s")." ".$val."\n",FILE_APPEND | LOCK_EX);
    }
}