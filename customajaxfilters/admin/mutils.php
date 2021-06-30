<?php
namespace CustomAjaxFilters\Admin;


class Mutils {
    public static function removeExtraSpaces($c) {
        $c = preg_replace('!\s+!', ' ', $c);
        $c = trim($c);
        return $c;
    }
    public static function removePriceFormat($val) {
        if (substr($val,-3)===".00") $val=substr($val,0,strlen($val)-3);
        //if (substr($val,-3,1)==".") $val=substr($val,0,strlen($val)-3)."#".substr($val,strlen($val)-2);
        return $val;
    }
    public static function ms_escape_string($data) {
        if (!isset($data) or empty($data) ) return '';
        if (is_numeric($data)) return $data;

        $non_displayables = array(
            '/%0[0-8bcef]/',            // url encoded 00-08, 11, 12, 14, 15
            '/%1[0-9a-f]/',             // url encoded 16-31
            '/[\x00-\x08]/',            // 00-08
            '/\x0b/',                   // 11
            '/\x0c/',                   // 12
            '/[\x0e-\x1f]/'             // 14-31
        );
        foreach($non_displayables as $regex)
        $data = preg_replace($regex, '', $data);
        $data = str_replace("'", "''", $data);
        return $data;
    }
    public static function titleCase($string, $delimiters = array(" ", "-", ".", "'", "O'", "Mc"), $exceptions = array("de", "da", "dos", "das", "do", "I", "II", "III", "IV", "V", "VI"))    {
        $string = mb_convert_case($string, MB_CASE_TITLE, "UTF-8");
        foreach ($delimiters as $dlnr => $delimiter) {
            $words = explode($delimiter, $string);
            $newwords = array();
            foreach ($words as $wordnr => $word) {
                if (in_array(mb_strtoupper($word, "UTF-8"), $exceptions)) {
                    // check exceptions list for any words that should be in upper case
                    $word = mb_strtoupper($word, "UTF-8");
                } elseif (in_array(mb_strtolower($word, "UTF-8"), $exceptions)) {
                    // check exceptions list for any words that should be in upper case
                    $word = mb_strtolower($word, "UTF-8");
                } elseif (!in_array($word, $exceptions)) {
                    // convert to uppercase (non-utf8 only)
                    $word = ucfirst($word);
                }
                array_push($newwords, $word);
            }
            $string = join($delimiter, $newwords);
       }
       return $string;
    }
    public static function containsWord($haystack,$needles) {
        //zjisti, jestli jedno ze slov v haystacku se nachazi v poli needles
        $haystack=explode(" ",$haystack);
        foreach ($haystack as $hay) {
         $hay=mb_strtolower($hay,'UTF-8');	 
         if (in_array($hay,$needles)) {
           return true;  
         }
        }
        return false;
    }
    public static function getImageFromImageUrl($html) {
        preg_match_all('/<img.*?src=[\'"](.*?)[\'"].*?>/i', $html, $matches);
        return (empty($matches[0][0])) ? false : $matches[0][0];
    }
    
    public static function formatField($field,$fieldFormat) {                
        if ($fieldFormat) {
            if ($fieldFormat=="%1,- Kč") { 
                if (strpos($field,".")) $field=str_replace(".",",",$field)."- Kč";
                else if (strpos($field,",")) $field=$field." Kč";
                else $field=$field.",- Kč";
            }
            else $field=str_replace("%1",$field,$fieldFormat);
        } 
        return $field;
    }  
}
