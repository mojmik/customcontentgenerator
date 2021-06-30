<?php
namespace CustomAjaxFilters\Admin;
use \CustomAjaxFilters\Majax\MajaxWP as MajaxWP;

class CJpages {
    private $customPostType;
    private $cjTools;
    public function __construct($customPostType,$cjTools) {
        $this->customPostType=$customPostType;
        $this->cjTools=$cjTools;
    }

    

    function createCatPages() {
        $mKeywords = $this->keywords;
        $dedTable=Settings::loadSetting("dedicatedTables-".$this->customPostType,"cptsettings");
        $childPages=[];        
        $currencyFormat=$this->cjTools->getParams("currencyFormat");

        $text[1] = __("Online shopping for");
        $text[2] = __("with great number of products and special prices");
        $text[3] = __("Offered by famous brands like");
        $text[4] = __("and many more products");
        $text[5] = __("such as");
        $text[6] = __("under ");        

        //vzit kategorie, ktery maji pres 100 postu
        $createdCatPages = 0;
        //$terms = $this->getCategories([["name"=>"counts","type" =>"%d", "value" => "100", "operator" => ">" ]]);
        $terms = $this->cjTools->getCats(null,null,[["name"=>"counts","type" =>"%d", "value" => "100", "operator" => ">" ]]);
        for ($n = 0; $n < count($terms); $n++) {
            if ($createdCatPages>10) break;
            if (empty($terms[$n]["path"]) || !$terms[$n]["path"]) continue;
            $posts_array = $this->cjTools->getPostsByCategory($terms[$n]["slug"],false,$dedTable,[$this->cjTools->getParams("metaNames","price"),$this->cjTools->getParams("metaNames","brand")]);
            $cntPosts = count($posts_array);

            if (is_array($mKeywords)) {
                $obsahujeKW = 0;
                $hledejKW = 1;
                for ($y = 0; $y < count($mKeywords); $y++) {
                    if (strpos(" ".strtolower($terms[$n]["name"]), strtolower($mKeywords[$y])) > 0) $obsahujeKW = 1;
                }
            }

            if (!$terms[$n]["parent"] && $createdCatPages <= 10 && $cntPosts > 200 && ((empty($hledejKW) || ($hledejKW && $obsahujeKW) ))) {
                echo "<br />cnt2: ".$terms[$n]["parent"].", ".$cntPosts." slug:".$terms[$n]["slug"]."-";
                //vytvorime stranky
                $cenaNejnizsi = 0;
                $cenaNejvyssi = 0;
                $prumer = 0;
                for ($y = 0; $y < $cntPosts; $y++) {
                    if ($dedTable) {
                        $cena = $posts_array[$y][$this->cjTools->getParams("metaNames","price")];
                    } else {
                        $postId = $posts_array[$y]["ID"];
                        $cenaArr = get_post_meta($postId, $this->cjTools->getParams("metaNames","price"));
                        $cena = $cenaArr[0];
                    }
                    
                    $cenyArr[$y] = $cena;
                    $prumer += $cena;
                    if ($cena < $cenaNejnizsi || $cenaNejnizsi == 0) $cenaNejnizsi = $cena;
                    if ($cena > $cenaNejvyssi || $cenaNejvyssi == 0) $cenaNejvyssi = $cena;
                }

                if ($cntPosts > 0) $prumer = $prumer / $cntPosts;
                $diff = $cenaNejvyssi - $cenaNejnizsi;

                $cenaArrB = array();
                $cenaArrB[0] = round($cenaNejnizsi + $diff / 4);
                $cenaArrB[1] = round($cenaNejnizsi + 2 * $diff / 4);
                $cenaArrB[2] = round($cenaNejnizsi + 3 * $diff / 4);
                $cenaArrB = $this->mGoRound($cenaArrB);

                $cntTitles = array();
                $postsTitles = array();
                $vendors = array();

                for ($y = 0; $y < $cntPosts; $y++) {
                    $cena = $cenyArr[$y];
                    for ($c = 0; $c < count($cenaArrB); $c++) {
                        $postsTitles[$c]="";
                        $cntTitles[$c]=0;
                        $vendors[$c]=array();
                    }
                    for ($c = 0; $c < count($cenaArrB); $c++) {
                        if ($cena < $cenaArrB[$c] && (empty($cntTitles[$c]) || $cntTitles[$c] < 5)) {
                            if ($postsTitles[$c]) $postsTitles[$c].= ", ";
                            $postsTitles[$c].= $posts_array[$y]["post_title"];
                            $cntTitles[$c]++;
                            if ($dedTable) {
                                $vendor = $posts_array[$y][$this->cjTools->getParams("metaNames","brand")];
                            } else {
                                $vendorArr = get_post_meta($postId, "brand");
                                $vendor = $vendorArr[0];
                            }
                            
                            if ($vendor) {
                                if (!in_array($vendor, $vendors[$c])) array_push($vendors[$c], $vendor);
                            }
                        }
                    }
                }


                //$addContent=getAjaxMore($catId,$taxTerms,$priceUnder=0);
                $addContent = $this->getAddContent($terms[$n]["slug"]."%");
                $parentTitle = $terms[$n]["path"];
                $post_details = array(
                    'post_title'    => $parentTitle,
                    'post_content'  => "<p>".$text[1]." ".$terms[$n]["path"]." ".$text[2].".</p>".$addContent,
                    'post_status'   => 'publish',
                    'post_author'   => 1,
                    'post_type' => 'page'
                );
                $parentExistuje = get_page_by_title($parentTitle);
                if ($parentExistuje) {
                    echo "<br />stranka uz existuje, preskakuju";
                    continue;
                }
                else {
                    echo "<br />stranka neexistuje, vytvarim";
                }

                $parentId = wp_insert_post($post_details);
                $createdCatPages++;
                //insertSpecPagesInfo($parentId,$parentTitle,0,$cntPosts);

                for ($c = 0; $c < count($cenaArrB); $c++) {
                    $brandy = "";
                    for ($b = 0; $b < count($vendors[$c]); $b++) {
                        if ($b > 0) $brandy.= ", ";
                        $brandy.= $vendors[$c][$b];
                    }
                    if ($brandy) $brandy = ". ".$text[3]." $brandy.";
                    $under = " ".$text[6].Mutils::formatField($cenaArrB[$c],$currencyFormat);
                    $pageTitle = $terms[$n]["path"].$under;
                    $priceFrom = 0;
                    if ($c > 0) $priceFrom = $cenaArrB[$c - 1];
                    $priceTo = $cenaArrB[$c];
                    $addContent = $this->getAddContent($terms[$n]["slug"]."%", $priceFrom, $priceTo);
                    if ($postsTitles[$c]) $postsTitles[$c] = "<p><strong>".$terms[$n]["path"]."</strong> $brandy ".$text[5]." ".$postsTitles[$c]." ..".$text[4].$under."</p>".$addContent;
                    $post_details = array(
                        'post_title'    => $pageTitle,
                        'post_content'  => $postsTitles[$c],
                        'post_status'   => 'publish',
                        'post_author'   => 1,
                        'post_parent'   => $parentId,
                        'post_type' => 'page'
                    );
                    $childId = wp_insert_post($post_details);
                    $childPages[]=["id" => $childId, "title" => $pageTitle, "parent" => $parentId];
                    //insertSpecPagesInfo($childId,$pageTitle,$parentId,$cntPosts);
                }
            }
        }

        /* todo: automaticke pridani podstranek do menu
        $menuLocations = get_nav_menu_locations();
        $menuID = $menuLocations['primary']; 
        $menuItems  = wp_get_nav_menu_items($menuID);
        foreach ($menuItems as $i) {
            $itemId=$i;
        }
        */
        //vzit ceny
        echo "done createcatpages";
    }
    function getAddContent($taxSlug,$priceFrom=0,$priceTo=0) {   
        /* 
        global $hpPrice;
        if (isCZ()) $more="Další..";
        else $more="More..";
        if ($priceTo>0) $out='[ajax_load_more id="6946518025" button_label="'.$more.'" loading_style="green" container_type="div" css_classes="mlistingajax" post_type="hp_listing" posts_per_page="6" taxonomy_terms="'.$taxTerms.'" taxonomy="hp_listing_category"  taxonomy_operator="IN"  meta_key="'.$hpPrice.'" meta_value="'.$priceFrom.','.$priceTo.'" meta_compare="BETWEEN" meta_type="DECIMAL" orderby="meta_value_num"]';
        else $out='<!-- wp:hivepress/listings {"number":"3","category":"'.$catId.'","order":"random"} /--><p>[ajax_load_more id="6946518025" button_label="'.$more.'" loading_style="green" container_type="div" css_classes="mlistingajax" post_type="hp_listing" posts_per_page="6" taxonomy_terms="'.$taxSlug.'" taxonomy="hp_listing_category"  taxonomy_operator="IN"]</p>';
        return $out;      
      */
      //not implemented 
      $price="";
      if ($priceTo>0) {
        $price="{$this->cjTools->getParams("metaNames","price")}=\"between;{$priceFrom}|{$priceTo}\" ";
      }
      $out="[majaxstaticcontent type=\"{$this->customPostType}\" cj=\"1\" {$price} {$this->cjTools->getParams("metaNames","type")}=\"{$taxSlug}\"]";
      return $out;
    }
    function mGoRound($prices) {
        foreach($prices as & $price) {
            if ($price > 10000) $price = round($price, -3);
            else if ($price > 1000) $price = round($price, -2);
            else if ($price > 100) $price = round($price,-1);
        }
        return $prices;
    }
}