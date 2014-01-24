<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
include_once dirname(__FILE__) . '/SaveRecord.php';

class Mobile_WS_AddRecordComment extends Mobile_WS_SaveRecord {
	
	function saveCommentToHelpDesk($commentcontent, $record, $user) {
		global $current_user;
		$current_user = $user;
		
		$targetModule = 'HelpDesk';
		$recordComponents = vtws_getIdComponents($record);
		
		$focus = CRMEntity::getInstance('HelpDesk');
		$focus->retrieve_entity_info($recordComponents[1], $targetModule);
		$focus->id = $recordComponents[1];
		$focus->mode = 'edit';
		$focus->column_fields['comments'] = $commentcontent;
		$focus->save($targetModule);
		return false;
	}
	
	function process(Mobile_API_Request $request) {
		$rest_data = $request->getRest_data();
		$relatedTo = $rest_data['record'];
		$commentContent = $rest_data['content'];
		$user = $this->getActiveUser();
		
		$targetModule = '';
		if (!empty($relatedTo) && Mobile_WS_Utils::detectModulenameFromRecordId($relatedTo) == 'HelpDesk') {
			$targetModule = 'HelpDesk';
		} else {
			$targetModule = 'ModComments';
		}
		$response = false;
		if ($targetModule == 'HelpDesk') {
			$response = $this->saveCommentToHelpDesk($commentContent, $relatedTo, $user);
		} else {
			if (vtlib_isModuleActive($targetModule)) {
				$assigned_user_id = sprintf('%sx%s', Mobile_WS_Utils::getEntityModuleWSId('Users'), $user->id);
				$select_fields="&related_to=".$relatedTo."&commentcontent=".$commentContent."&assigned_user_id=".$assigned_user_id;
				$request->set('rest_data',Zend_Json::encode(array('module'=>$targetModule,'select_fields'=>urlencode($select_fields),'record'=>'')));
				$response = parent::process($request);
			}
		}
		return $response;
	}
}