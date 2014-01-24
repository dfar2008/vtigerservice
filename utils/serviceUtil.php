<?php
/**
 * Decodes the given set of special character 
 * input values $string - string to be converted, $encode - flag to decode
 * returns the decoded value in string fromat
 */

function my_from_html($string, $encode=true){
	global $log;
	global $toHtml;
	$string = trim($string);
	//if($encode && is_string($string))$string = html_entity_decode($string, ENT_QUOTES);
	if($encode && is_string($string)){
			$string = str_replace(array_values($toHtml), array_keys($toHtml), $string);
	}
    return $string;
}

function getJSONObj() {
	static $json = null;
	if(!isset($json)) {
		require_once('JSON.php');
		$json = new JSON(JSON_LOOSE_TYPE);
	}
	return $json;
}
?>