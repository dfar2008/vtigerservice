<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
include_once dirname(__FILE__) . '/FetchRecordWithGrouping.php';

include_once 'include/Webservices/Create.php';
include_once 'include/Webservices/Update.php';

class Mobile_WS_SaveRecord extends Mobile_WS_FetchRecordWithGrouping {
	protected $recordValues = false;
	
	// Avoid retrieve and return the value obtained after Create or Update
	protected function processRetrieve(Mobile_API_Request $request) {
		return $this->recordValues;
	}
	
	function process(Mobile_API_Request $request) {
		global $current_user; // Required for vtws_update API
		$current_user = $this->getActiveUser();
		$rest_data = $request->getRest_data();
		$module = $rest_data['module'];
		//$module = $request->get('module');
		$recordid = $rest_data['record'];
		//$valuesJSONString =  $request->get('values');
		$selectfieldarr =  explode("&",urldecode($rest_data['select_fields']));
		$values = array();

		foreach($selectfieldarr as $val){
			if($val && !empty($val)){
				$col = explode("=",$val);
				if($col[0] == "accountname"){ 
					$accountname = $col[1];
				}
				$col[1] = mysql_real_escape_string($col[1]);
				$values[$col[0]] = $col[1];
			}
		}
//		if(!empty($valuesJSONString) && is_string($valuesJSONString)) {
//			$values = Zend_Json::decode($valuesJSONString);
//		} else {
//			$values = $valuesJSONString; // Either empty or already decoded.
//		}
		//print_r($values);die;
		$response = new Mobile_API_Response();
			
		if (empty($values)) {
			$response->setError(1501, "Values cannot be empty!");
			return $response;
		}
		
		try {
			// Retrieve or Initalize
			if (!empty($recordid) && !$this->isTemplateRecordRequest($request)) {
				$this->recordValues = vtws_retrieve($recordid, $current_user);
			} else {
				$this->recordValues = array();
			}

			// Set the modified values
			foreach($values as $name => $value) {
				$this->recordValues[$name] = $value;
			}
			
			// Update or Create
			if (isset($this->recordValues['id'])) {
				$this->recordValues = vtws_update($this->recordValues, $current_user);
			} else {

				$currenUserModel = Users_Record_Model::getCurrentUserModel();
				$Users = $currenUserModel->getAccessibleUsersForModule($module);
				$Groups = $currenUserModel->getAccessibleGroupForModule($module);
				$assigned_user_id = vtws_getWebserviceEntityId('Users', $current_user->id);
				if(is_array($Users)&&count($Users)>0){
					
				}else if(is_array($Groups)&&count($Groups)>0){
					//$Groups_key = array_keys($Groups);
					//$assigned_user_id = '20x'.$Groups_key[0];
				}
				$this->recordValues['assigned_user_id'] = $assigned_user_id;
				// Set right target module name for Calendar/Event record
				if ($module == 'Calendar') {
					if (!empty($this->recordValues['eventstatus']) && $this->recordValues['activitytype'] != 'Task') {
						$module = 'Events';
					}
				}
				$this->recordValues = vtws_create($module, $this->recordValues, $current_user);
			}
			
			// Update the record id
			$request->set('record', $this->recordValues['id']);
			
			// Gather response with full details
			$response = parent::process($request);
			
		} catch(Exception $e) {
			if($e->getMessage() == 'LBL_DATABASE_QUERY_ERROR'){
				$request->set('record', $this->recordValues['id']);
				// Gather response with full details
				$response = parent::process($request);
			}else{
				$response->setError($e->getCode(), $e->getMessage());
			}
		}
		return $response;
	}
	
}