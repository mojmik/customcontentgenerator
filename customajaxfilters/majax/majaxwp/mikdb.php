<?php
namespace CustomAjaxFilters\Majax\MajaxWP;

use stdClass;

class MikDb {	

	private static $pdo;
	private static $dbsettings = array(
		\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
		\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
		\PDO::ATTR_EMULATE_PREPARES => false,
	);
	private static $dbConnSettings=[];
	public static function getConnection() {
        // initialize $pdo on first call
        if (empty(self::$pdo)) {
            self::connect();
        }

        // now we should have a $pdo, whether it was initialized on this call or a previous one
        // but it could have experienced a disconnection
        try {            
            $old_errlevel = error_reporting(0);
            self::$pdo->query("SELECT 1");
        } catch (\PDOException $e) {
            echo "Connection failed, reinitializing...\n";
            self::connect();
        }
        error_reporting($old_errlevel);

        return self::$pdo;
	}
	public static function init($host, $user, $pwd, $database) {
		self::$dbConnSettings["host"]=$host;
		self::$dbConnSettings["user"]=$user;
		self::$dbConnSettings["pwd"]=$pwd;
		self::$dbConnSettings["database"]=$database;
		self::connect();
	}

	public static function connect() {
		if (!isset(self::$dbConnSettings["host"])) die("dbconn settings error");
		if (!isset(self::$dbConnSettings["user"])) die("dbconn settings error");
		if (!isset(self::$dbConnSettings["pwd"])) die("dbconn settings error");
		if (!isset(self::$dbConnSettings["database"])) die("dbconn settings error");
		$host=self::$dbConnSettings["host"];
		$user=self::$dbConnSettings["user"];
		$pwd=self::$dbConnSettings["pwd"];
		$database=self::$dbConnSettings["database"];

		if (!isset(self::$pdo))
		{
			self::$pdo = @new \PDO(
				"mysql:host=$host;dbname=$database",
				$user,
				$pwd,
				self::$dbsettings
			);
		}
	}
	public static function getRows($query, $params = array())	{
		$out = self::$pdo->prepare($query);
		$out->execute($params);
		return $out->fetchAll(\PDO::FETCH_ASSOC);
	}
	public static function getRow($query, $params = array())	{
		$out = self::$pdo->prepare($query);
		$out->execute($params);
		return $out->fetch();
	}
	public static function getInsertSql($table,$fields) {
        $out="INSERT INTO `$table` ";
        $n=0;
        $out.="(";
        foreach ($fields as $f => $val) {
            if ($n>0) $out.=", ";
            $out.="`$f`";
            $n++;
        }
        $out.=")";
        $out.=" VALUES ";
		$out.="(";
		$n=0;
        foreach ($fields as $f => $val) {
            if ($n>0) $out.=", ";
            $out.="'".$val."'";
            $n++;
        }
        $out.=");";
        return $out;
	}	
	public static function getUpdateSql($table,$setFields,$whereFields) {
        $out="UPDATE `$table` SET ";
        $n=0;
        foreach ($setFields as $f => $val) {
            if ($n>0) $out.=", ";
            $out.="`$f`='".$val."'";
            $n++;
		}
		$out.=" WHERE ";
		$n=0;
		foreach ($whereFields as $f => $val) {
            if ($n>0) $out.=" AND ";
            $out.="`$f`='".$val."'";
            $n++;
		}
        $out.=";";
        return $out;
	}	
	public static function createTableIfNotExists($tableName,$fieldsDef,$args=[]) {
		global $wpdb;
		if($wpdb->get_var("SHOW TABLES LIKE '{$tableName}'") == $tableName) {            
            return true;
        }
      	MikDb::createTable($tableName,$fieldsDef,$args);
	}
	public static function dropTable($tableName) {
		global $wpdb;			
		$wpdb->query( "DROP TABLE IF EXISTS {$tableName}");
	}
    public static function createTable($tableName,$fieldsDef,$args=[]) {
        global $wpdb;			
		
        $charset_collate = $wpdb->get_charset_collate();
        if (!empty($args["drop"])) MikDb::dropTable($tableName);
        //check table exists

        $sql = "CREATE TABLE `{$tableName}` (";
        $n=0;
        $primary="";
        foreach ($fieldsDef as $f => $def) {
		 $f="`{$f}`";
		 if ($n>0) $sql.=", ";
		 if (!empty($def["sql"])) {
			$sql.="$f ".$def["sql"];
		 } else {
			$sql.=$f." ".(empty($def["type"]) ? "" : $def["type"]);
			$sql.=(empty($def["notnull"])) ? "" : " NOT NULL";
			$sql.=(empty($def["autoinc"])) ? "" : " AUTO_INCREMENT";
		 }
		 
         if (!empty($def["primary"])) { 
             if ($primary) $primary.=",";
             $primary=$f;
         }
         $n++;
        }
        $sql.=", PRIMARY KEY ($primary)";
		$sql.=") $charset_collate;";
		if (!empty($args["debug"])) echo "<br />sql debug: ".$sql;
        else { 
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}
	}	

	public static function wpdbTableEmpty($tableName,$where="1") {
		global $wpdb;	
		$count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tableName} WHERE %s",$where)); 
		if ($count == 0) return true;
		return false;
	}
	public static function wpdbTableCount($tableName,$where="1") {
		global $wpdb;	
		$count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tableName} WHERE %s",$where)); 
		return $count;
	}
	public static function wpdbGetRows($tableNames,$cols="*",$where=[],$useCache=false) {
		//MikDb::wpdbGetRows($this->params["cjCatsTable"],["id","path","counts"],[["name"=>"parent","type" =>"%s", "operator" => ">", "value" => $parentId ]]);
		global $wpdb;	
		$values=[];
		if (is_array($cols)) $cols=implode(",",$cols);	
		if (is_array($tableNames)) $tableNames=implode(",",$tableNames);			

		if (!empty($where)) {		  			  
			  $where1="";
			  for ($n=0;$n<count($where);$n++) {
				$operator=(empty($where[$n]["operator"])) ? "=" : $where[$n]["operator"];
				if ($operator=="LIKE") $operator=" LIKE ";
				$type=(empty($where[$n]["type"])) ? "%s" : $where[$n]["type"];
				if ($n>0) $where1.=" AND ";
				$where1.="`".$where[$n]["name"]."`".$operator.$type;
				$values[]=$where[$n]["value"];
			  }
			if ($useCache) return Caching::getCachedRows($wpdb->prepare("SELECT $cols FROM {$tableNames} WHERE $where1 ",$values)); 
		  	else return $wpdb->get_results($wpdb->prepare("SELECT $cols FROM {$tableNames} WHERE $where1 ",$values),ARRAY_A); 
		} else {
			if ($useCache) return Caching::getCachedRows("SELECT $cols FROM {$tableNames} "); 
	      	else return $wpdb->get_results("SELECT $cols FROM {$tableNames} ",ARRAY_A); 
		}
	}
	public static function wpDbGetRowsPrepared($query,$useCache=false) {
		global $wpdb;	
		if ($useCache) return Caching::getCachedRows($query); 
		else { 
			if ($wpdb) {
				return $wpdb->get_results($query,ARRAY_A); 
			} else {
				return MikDb::getRows($query);
			}
			
		}
	}
	public static function wpdbGetRowsAdvanced($params) {		
		global $wpdb;	
		if (!empty($params["cols"])) $cols=$params["cols"];
		if (!empty($params["tableNames"])) $tableNames=$params["tableNames"];
		if (!empty($params["orderDir"])) $orderDir=$params["orderDir"];
		if (!empty($params["useCache"])) $useCache=$params["useCache"];
		if (!empty($params["order"])) $order=$params["order"];
		if (!empty($params["limit"])) $limit=$params["limit"];
		if (!empty($params["where"])) $where=$params["where"];


		$values=[];
		if (is_array($cols)) $cols=implode(",",$cols);	
		if (is_array($tableNames)) $tableNames=implode(",",$tableNames);	
		$orderStr="";
		if (!empty($order))	{
			$orderStr.="ORDER BY ";
			$n=0;
			foreach ($order as $o) {
				if ($n>0) $orderStr.=",";
				$orderStr.=$o;
				$n++;
			}
			$orderStr.=" ".$orderDir;
		}
		$limitStr="";
		if (!empty($limit)) {
			$limitStr.="LIMIT ";
			$n=0;
			foreach ($limit as $l) {
				if ($n>0) $limitStr.=",";
				$limitStr.=$l;
				$n++;
			}
		}

		if (!empty($where)) {		  			  
			  $where1="";
			  for ($n=0;$n<count($where);$n++) {
				$operator=(empty($where[$n]["operator"])) ? "=" : $where[$n]["operator"];
				$type=(empty($where[$n]["type"])) ? "%s" : $where[$n]["type"];
				if ($n>0) $where1.=" AND ";
				$where1.="`".$where[$n]["name"]."` ".$operator." ".$type;
				$values[]=$where[$n]["value"];
			  }
			$query=$wpdb->prepare("SELECT $cols FROM {$tableNames} WHERE $where1 $orderStr $limitStr",$values);
			$query=str_replace("'NULL'","NULL",$query);
			if ($useCache) return Caching::getCachedRows($query); 
		  	else return $wpdb->get_results($query,ARRAY_A); 
		} else {
			if ($useCache) return Caching::getCachedRows("SELECT $cols FROM {$tableNames} $orderStr $limitStr"); 
	      	else return $wpdb->get_results("SELECT $cols FROM {$tableNames} $orderStr $limitStr",ARRAY_A); 
		}
	}
	
	public static function wpdbUpdateRows($tableName,$fields=[],$where=[]) {
		//MikDb::wpdbGetRows($this->params["cjCatsTable"],["id","path","counts"],["name"=>"parent","type" =>"%s", "value" => $parentId ]);
		global $wpdb;	
		$sql="UPDATE `$tableName` SET ";	
		$params=[];
		for ($n=0;$n<count($fields);$n++) {
			$type=(empty($fields[$n]["type"])) ? "%s" : $fields[$n]["type"];
			if ($n>0) $sql.=",";
			$sql.="`".$fields[$n]["name"]."` = ".$type;
			$params[]=$fields[$n]["value"];
		}
		$sql.=" WHERE ";
		for ($n=0;$n<count($where);$n++) {
			$type=(empty($where[$n]["type"])) ? "%s" : $where[$n]["type"];
			if ($n>0) $sql.=",";
			$sql.="`".$where[$n]["name"]."` = ".$type;
			$params[]=$where[$n]["value"];
		}
		$sql=$wpdb->prepare($sql,$params);
		return $wpdb->get_results($sql,ARRAY_A); 
	}
	public static function getWPprefix() {
		global $wpdb;	
		if (!empty($wpdb)) return $wpdb->prefix;
		return "";
	}
	public static function getTablePrefix() {		
		$fixPrefix="mauta_";
		return MikDb::getWPprefix().$fixPrefix;
	}
	public static function  getInsertQueryFromArray($table,$mArr,$skipCols=[]) {
		$query="INSERT INTO `$table` SET ";
		$n=0;
		foreach ($mArr as $colName => $mVal) {   
		  //echo "<br />colname:$colName value:$mVal";
		  if (!in_array($colName,$skipCols)) {
			if ($n>0) $query.=",";   
			$query.="`$colName`='$mVal'";
			$n++;
		 }
		}
		return $query;
	}
	public static function insertRow($table,$mArr,$skipCols=[]) {
		global $wpdb;
		$sql=MikDb::getInsertQueryFromArray($table,$mArr,$skipCols);
		$wpdb->get_results($sql);
		return $wpdb->insert_id;
	}
	public static function clearTable($table,$where=[]) {
		global $wpdb;
		if (count($where)<1) $wpdb->query("TRUNCATE TABLE `$table`");
		else {
			$sql="DELETE FROM `$table` WHERE ";
			$n=0;
			foreach ($where as $w) {
				if ($n>0) $sql.=" AND ";
				$sql.=$w;
				$n++;
			}
			$wpdb->query($sql);
		}
	}	
	public static function makeWhere($whereArr=[]) {
		$where="";
		$n=0;
		foreach ($whereArr as $w) {
			if ($w) {				
				if ($n>0) $where.=" AND ".$w;
				else $where.=$w;
				$n++;
			}			
		}
		if ($where) $where=" WHERE ".$where;
		return $where;
	}	
}