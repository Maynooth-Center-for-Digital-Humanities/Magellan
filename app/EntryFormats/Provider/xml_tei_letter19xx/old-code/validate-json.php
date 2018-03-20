<?php

header('Content-Type: application/json; charset=utf-8');
// json directory
$json_dir = "json";

// set errors to custom file
ini_set("log_errors", 1);
ini_set("error_log", "errors/json-error-log.log");

// get xml files
if (is_dir($json_dir)){
	if ($dh = opendir($json_dir)){
		$i=0;
		while (($file = readdir($dh)) !== false) {
			if ($file!=="." && $file!==".." && $file!==".DS_Store") { 
				$json_file = $json_dir."/".$file;
				
				$json_content = file_get_contents($json_file,true);	
				
				if (validateJSON(trim($json_content))===false) {
					echo $json_file." JSON is Not Valid\n";
					$i++;
				}		
			}
			
		}
		closedir($dh);
	}
}
echo "\n\n".$i;

function validateJSON($data=NULL) {
	if (!empty($data)) {
		@json_decode($data);
		switch (json_last_error()) {
        case JSON_ERROR_NONE:
            //echo ' - No errors\n';
        break;
        case JSON_ERROR_DEPTH:
            echo " - Maximum stack depth exceeded\n";
        break;
        case JSON_ERROR_STATE_MISMATCH:
            echo " - Underflow or the modes mismatch\n";
        break;
        case JSON_ERROR_CTRL_CHAR:
            echo " - Unexpected control character found\n";
        break;
        case JSON_ERROR_SYNTAX:
            echo " - Syntax error, malformed JSON\n";
        break;
        case JSON_ERROR_UTF8:
            echo " - Malformed UTF-8 characters, possibly incorrectly encoded\n";
        break;
        default:
            echo " - Unknown error\n";
        break;
    }
		return (json_last_error() === JSON_ERROR_NONE);
    }
	return false;
}
?>