<?php
namespace CustomAjaxFilters\Majax\MajaxWP;
use \CustomAjaxFilters\Admin as MajaxAdmin;

class MajaxForm {   
    private $postType;
    private $postedFields; //fields reporting in emails etc.
    private $inputFields; //fields appearing in forms
    private $htmlSrc;
    private $htmlElements;
    function __construct($type="",$title="") {            
        $this->postType=$type;
        $this->postTitle=$title;
        $this->addFields($type);      
    }
   public function setTemplate($htmlElements,$templateName,$templateType="") {
    //$mForm->setTemplate($this->htmlElements,"contactFormMessage");
    $this->htmlElements=$htmlElements;
    $this->htmlSrc=$this->htmlElements->getHtml($templateName,$templateType);
   }
   
   function checkCaptcha() {
       $captchaResponse=$_POST["captcha"];
       if (!$captchaResponse) { 
        $this->logWrite("CAPTCHA blank ","filledform.txt");
        return false;
       }               
       $secret=MajaxAdmin\Settings::loadSecret("captchakey");       
       //$verify=file_get_contents($url);

       $ch = curl_init();
       curl_setopt_array($ch, [
            CURLOPT_URL => 'https://www.google.com/recaptcha/api/siteverify',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'secret' => $secret,
                'response' => $captchaResponse,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ],
            CURLOPT_RETURNTRANSFER => true
        ]);
       $output = curl_exec($ch);
       curl_close($ch);
       $verify = json_decode($output);

       $this->logWrite("CAPTCHA response out -".$output."-","filledform.txt");           
        if ($verify->success==true) {
           $this->logWrite("CAPTCHA success ".$output,"filledform.txt");
          //This user is verified by recaptcha
        return true;
       }
       $this->logWrite("CAPTCHA fail ".$output,"filledform.txt");
       return false;
   }
   function addFields($fieldSetType="default") {
    $fields=[];
    $this->inputFields=[];
    $this->postedFields=[];    
    if ($fieldSetType=="dotaz") {
        $fields=[
            ["idName" => "fname", "inputType" => "latinletters", "mRequired" => true, "sameLikeName" => false, "caption" => "Jméno" ],
            ["idName" => "email", "inputType" => "email", "mRequired" => true, "sameLikeName" => false, "caption" => "Email" ],
            ["idName" => "msg", "inputType" => "textarea", "mRequired" => true, "sameLikeName" => false, "caption" => "Zpráva" ],
            ["idName" => "postTitle", "inputType" => "hidden", "mRequired" => false, "sameLikeName" => false, "caption" => "" ],            
            ["idName" => "postType", "inputType" => "hidden", "mRequired" => false, "sameLikeName" => false, "caption" => "" ]            
        ];

    } else {        
            $fields=[
                ["idName" => "fname", "inputType" => "latinletters", "mRequired" => true, "sameLikeName" => false, "caption" => "Jméno" ],
                ["idName" => "lname", "inputType" => "latinletters", "mRequired" => true, "sameLikeName" => false, "caption" => "Příjmení" ],
                ["idName" => "email", "inputType" => "email", "mRequired" => true, "sameLikeName" => false, "caption" => "Email" ],
                ["idName" => "cemail", "inputType" => "email", "mRequired" => true, "sameLikeName" => "email", "caption" => "Email" ],
                ["idName" => "start_date", "inputType" => "date", "mRequired" => true, "sameLikeName" => false, "caption" => "Začátek pronájmu" ],
                ["idName" => "end_date", "inputType" => "date", "mRequired" => true, "sameLikeName" => false, "caption" => "Konec pronájmu" ],
                ["idName" => "phone_no", "inputType" => "phone", "mRequired" => true, "sameLikeName" => false, "caption" => "Telefon" ],
                ["idName" => "expected_mileage", "inputType" => "number", "mRequired" => true, "sameLikeName" => false, "caption" => "Předpoklad km" ],
                ["idName" => "business", "inputType" => "checkbox", "mRequired" => false, "sameLikeName" => false, "caption" => "Již je firemní zákazník" ],
                ["idName" => "postTitle", "inputType" => "hidden", "mRequired" => false, "sameLikeName" => false, "caption" => "" ],            
                ["idName" => "postType", "inputType" => "hidden", "mRequired" => false, "sameLikeName" => false, "caption" => "" ]            
            ];        
    }
    foreach ($fields as $f) {
        $this->inputFields[]=array("idName"=>$f["idName"],"caption"=>$f["caption"]);
        $this->postedFields[]=array(
        "idName"=>$f["idName"],
        "inputType"=>$f["inputType"],
        "mRequired"=>$f["mRequired"],
        "sameLikeName"=>$f["sameLikeName"],
        "caption"=>$f["caption"]);
    }   
   }
   function renderForm() {
    $row=[];
    $row["title"]="action";
    $row["content"]="";
    $row["postTitle"]=$this->postTitle;
    $row["postType"]=$this->postType;
    $row["htmlSrc"]=(!empty($this->htmlSrc)) ? $this->htmlSrc : "";
    $row["name"]="majaxContactForm";
    $row["siteKey"]=MajaxAdmin\Settings::loadSecret("sitekey");
    $row["flag"]="form-show";
    //idName, inputType, mRequired=true, sameLikeName=false    
    $row["fields"]=$this->postedFields;
    //$row["fields"]=[["neco" => "cau", "neco2" => "as"],["aneco" => "cau", "aneco2" => "as"]];
    return $row;
   }
   function runForm() {		
        $row=[];                  
        if (!$this->checkCaptcha()) {
            $row["title"]="action";
            $row["flag"]="form-not-ok";
            $row["htmlSrc"]=$this->htmlSrc;
            $row["content"]="Antispam ověření selhalo, vraťte se zpět a zkuste znova."; 
            return $row;
        }
        $outHtml="";
        $outTxt="";
        foreach ($this->postedFields as $p) {
            if ($p["caption"]) {
                if ($outTxt) $outTxt.="<br />";            
                $varDump="";
                $formVal=$_POST[$p["idName"]];
                $caption=$p["caption"];
                $outTxt.="$varDump $caption - $formVal";	
                $outHtml.="<tr><td><b>$caption</b></td><td>".$formVal."</td></tr>";	
            }            
            if ($p["idName"]=="email") $replyTo=$formVal;
        }			
        $outHtml="<table>$outHtml</table>";			
        
        $to = MajaxAdmin\Settings::loadSecret("emailTo"); 
        $subject = 'objednavka z '.$_SERVER['SERVER_NAME'];
        $body = "<h1>Objednavka z webu</h1> <h3>Typ: {$this->postType}</h3> <br /><br />{$outHtml}";      	
        $headers = 'Content-Type: text/html; charset=UTF-8';	
        mail($to,$subject,$body,$headers);			
        $this->logWrite("".$outTxt." replyto $replyTo.","filledform.txt");
        $row["title"]="action";
        $row["flag"]="form-ok";        
        $htmlSrc=$this->htmlElements->processTemplate($this->htmlSrc,["content"=>"_(Thanks for submitting. We will contact you soon.)"]);        
        $row["htmlSrc"]=$htmlSrc;
        $row["content"]="";
        return $row;        
    }	
   
    function logWrite($val,$file="log.txt") {
        file_put_contents(plugin_dir_path( __FILE__ ) . $file,date("d-m-Y h:i:s")." ".$val."\n",FILE_APPEND | LOCK_EX);
       }
}