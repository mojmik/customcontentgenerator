<?php
namespace CustomAjaxFilters\Majax\MajaxWP;

Class MajaxItem {	
    private $metaFields=array();
    private $mainFields=array();  
    public function addMeta($metaKey,$metaValue) {
        $this->metaFields[$metaKey]=($metaValue);
        return $this;
    }    
    public function addField($fieldKey,$fieldValue) {
        $this->mainFields[$fieldKey]=($fieldValue);
        return $this;
    }
    public function expose($getJson=1) {        
        $arr=$this->mainFields;
        $arr["meta"]=$this->metaFields;
        if ($getJson) return json_encode($arr);        
        else return $arr;
    }
    public function shrinkField($fieldKey) {
      $val=self::excerpt_paragraph($this->mainFields[$fieldKey]);
      //$val=$this->mainFields[$fieldKey];
      $this->mainFields[$fieldKey]=$val;
    }

    public static function excerpt_paragraph($html, $max_char = 100, $trail='...' ) {
        // temp var to capture the p tag(s)
        $matches= array();
        if ( preg_match( '/<p>[^>]+<\/p>/', $html, $matches) )
        {
            // found <p></p>
            $p = strip_tags($matches[0]);
        } else {
            $p = strip_tags($html);
        }
        //shorten without cutting words
        $p = self::short_str($p, $max_char );

        // remove trailing comma, full stop, colon, semicolon, 'a', 'A', space
        $p = rtrim($p, ',.;: aA' );

        // return nothing if just spaces or too short
        if (ctype_space($p) || $p=='' || strlen($p)<10) { return ''; }

        return '<p>'.$p.$trail.'</p>';
    }
    /**
    * shorten string but not cut words
    * 
    **/
    public static function short_str( $str, $len, $cut = false )    {
        if ( strlen( $str ) <= $len ) { return $str; }
        $string = ( $cut ? substr( $str, 0, $len ) : substr( $str, 0, strrpos( substr( $str, 0, $len ), ' ' ) ) );
        return $string;
    }
}
