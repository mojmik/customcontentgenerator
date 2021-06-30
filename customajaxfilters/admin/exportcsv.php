<?php
namespace CustomAjaxFilters\Admin;

class ExportCSV {
    public function exportTable($table,$enclosure="\"",$delim=";",$linesDelim="\n") {   
            global $wpdb; 
            $cols = $wpdb->get_results("SHOW COLUMNS FROM ".$table,ARRAY_A);                       
            //$wp_filename = "{$table}-export".date("d-m-y").".csv";
            header("Pragma: public");
            header("Expires: 0");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Cache-Control: private", false);
            header("Content-Type: application/octet-stream");
            header("Content-Disposition: attachment; filename=\"report.csv\";" );
            header("Content-Transfer-Encoding: binary");
            ob_end_clean ();
            //$wp_file = fopen($wp_filename,"w");
            $rows = $wpdb->get_results("SELECT * FROM `$table`",ARRAY_A);
            $r=0;
            $n=0;
            foreach ($cols as $col) {   
             //output table fields
             $field=$col["Field"];
             $type=$col["Type"];
             $null=$col["Null"];
             $key=$col["Key"];
             $default=$col["Default"];
             $extra=$col["Extra"];
             if ($n>0) echo $delim;
             echo $field;
             $n++;
            }
            echo $linesDelim;
            foreach ($rows as $row)  {
                $n=0;
                if ($r>0) echo $linesDelim;
                foreach ($cols as $col) {                   
                  $val=$row[$col["Field"]];
                  $outArr[$col]=$val;
                  if ($n>0) echo $delim;
                  //echo $row[$col];
                  $val=str_replace($enclosure,"\\".$enclosure,$val);
                  echo $enclosure.$val.$enclosure;
                  $n++;
                }
                $r++;
            }
            // Close file
            // download csv file
          
            
            exit;
          
    }
    
}