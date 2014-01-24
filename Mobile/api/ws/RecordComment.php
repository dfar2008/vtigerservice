<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
include_once dirname(__FILE__) . '/models/Paging.php';
class Mobile_WS_RecordComment extends Mobile_WS_Controller {
	
	function process(Mobile_API_Request $request) {
		global $current_user, $adb;
		$current_user = $this->getActiveUser();
		
		$rest_data = $request->getRest_data();
		$record = $rest_data['record'];
		$recordId = vtws_getIdComponents($record);
		$parentId = $recordId[1];
		$recentComments = $this->getRecentComments($parentId);
		$response = new Mobile_API_Response();
		$response->setResult(array('records'=>$recentComments));
		return $response;
	}

	public function getRecentComments($parentId){
		global $adb;
		$listView = Vtiger_ListView_Model::getInstance('ModComments');
		$queryGenerator = $listView->get('query_generator');
		$queryGenerator->setFields(array('parent_comments', 'createdtime', 'modifiedtime', 'related_to',
									'assigned_user_id', 'commentcontent', 'creator', 'id', 'customer', 'reasontoedit', 'userid'));

		$query = $queryGenerator->getQuery();
		
		$query = $query ." AND related_to = ? ORDER BY vtiger_crmentity.createdtime DESC";
		$result = $adb->pquery($query, array($parentId));
		$rows = $adb->num_rows($result);
		$records = array();
		$seq=$rows;
		for ($i=0; $i<$rows; $i++) {
			$row = array();
			//$row = $adb->query_result_rowdata($result, $i);
			$row['content'] = $adb->query_result($result, $i, 'commentcontent');
			$row['createdtime'] = $adb->query_result($result, $i, 'createdtime');
			$row['smownerid'] = $adb->query_result($result, $i, 'smownerid');
			$row['customer'] = $adb->query_result($result, $i, 'customer');
			$row['reasontoedit'] = $adb->query_result($result, $i, 'reasontoedit');
			$commentor = $this->getCommentedByModel($row['customer'],$row['smownerid']);
			$row['username'] = $commentor->getName();
			$row['seq'] = $seq;
			$row['userPhoto'] = vimage_path('DefaultUserIcon.png');
			if($commentor) {
				if (!empty($row['customer'])) {
					$row['userPhoto'] = 'CustomerPortal.png';
				} else {
					$imagePath = $commentor->getImageDetails();
					if (!empty($imagePath[0]['name'])) {
						$row['userPhoto'] = '../' . $imagePath[0]['path'] . '_' . $imagePath[0]['name'];
					}
				}
			}
			$records[] = $row;
			$seq--;
		}
		//print_r($records);
		return $records;
	}

	/**
	 * Function returns the commentor Model (Users Model)
	 * @return <Vtiger_Record_Model>
	 */
	public function getCommentedByModel($customer,$commentedBy) {
		if(!empty($customer)) {
			return Vtiger_Record_Model::getInstanceById($customer, 'Contacts');
		} else {
			if($commentedBy)
			return Vtiger_Record_Model::getInstanceById($commentedBy, 'Users');
		}
		return false;
	}
}