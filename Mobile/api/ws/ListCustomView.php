<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/

class Mobile_WS_ListCustomView extends Mobile_WS_Controller {
	function process(Mobile_API_Request $request) {
		return $this->processGetCustomView($request);
	}
	
	function processGetCustomView(Mobile_API_Request $request) {
		global $current_user; // Few core API assumes this variable availability
		$current_user = $this->getActiveUser();
		global $adb;
		$rest_data = $request->getRest_data();
		$module = $rest_data['module'];
		$sql0 = "SELECT `cvid` , `viewname` FROM `vtiger_customview` WHERE entitytype = '{$module}' ORDER BY cvid;";
		$result = $adb->query($sql0);
		$num_rows = $adb->num_rows($result);
		$viewArr = array();
		if($num_rows > 0){
			while($row = $adb->fetch_array($result)){
				$entries = array();
				$entries['cvid'] = $row['cvid'];
				$entries['viewname'] = $row['viewname'];
				//$entries['sequence_no'] = $row['sequence_no'];
				$viewArr[] = $entries;
			}
		}
		$response = new Mobile_API_Response();
		$response->setResult(array('viewArr'=>$viewArr));
		
		return $response;
	}
}
