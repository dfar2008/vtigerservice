<?php
/*+**********************************************************************************
* 查看详细
* Creat at 2013-11-30 for c3crm by xiao
************************************************************************************/
include_once 'include/Webservices/Retrieve.php';
include_once dirname(__FILE__) . '/FetchRecord.php';
include_once 'include/Webservices/DescribeObject.php';
include_once 'include/utils/UserInfoUtil.php';


class Mobile_WS_FetchRecordWithGrouping extends Mobile_WS_FetchRecord {
	
	private $_cachedDescribeInfo = false;
	private $_cachedDescribeFieldInfo = false;
	
	protected function cacheDescribeInfo($describeInfo) {
		$this->_cachedDescribeInfo = $describeInfo;
		$this->_cachedDescribeFieldInfo = array();
		if(!empty($describeInfo['fields'])) {
			foreach($describeInfo['fields'] as $describeFieldInfo) {
				$this->_cachedDescribeFieldInfo[$describeFieldInfo['name']] = $describeFieldInfo;
			}
		}
	}
	
	protected function cachedDescribeInfo() {
		return $this->_cachedDescribeInfo;
	}
	
	protected function cachedDescribeFieldInfo($fieldname) {
		if ($this->_cachedDescribeFieldInfo !== false) {
			if(isset($this->_cachedDescribeFieldInfo[$fieldname])) {
				return $this->_cachedDescribeFieldInfo[$fieldname];
			}
		}
		return false;
	}
	
	protected function cachedEntityFieldnames($module) {
		$describeInfo = $this->cachedDescribeInfo();
		$labelFields = $describeInfo['labelFields'];
		switch($module) {
			case 'HelpDesk': $labelFields = 'ticket_title'; break;
			case 'Documents': $labelFields = 'notes_title'; break;
		}
		return explode(',', $labelFields);
	}
	
	protected function isTemplateRecordRequest(Mobile_API_Request $request) {
		$rest_data = $request->getRest_data();
		$recordid = $rest_data['record'];
		//$recordid = $request->get('record');
		return (preg_match("/([0-9]+)x0/", $recordid));
	}
	
	protected function processRetrieve(Mobile_API_Request $request) {
		$rest_data = $request->getRest_data();
		$recordid = $rest_data['record'];
		//$recordid = $request->get('record');
		global $current_user;
		// Create a template record for use 
		if ($this->isTemplateRecordRequest($request)) {
			$current_user = $this->getActiveUser();
			
			$module = $this->detectModuleName($recordid);
		 	$describeInfo = vtws_describe($module, $current_user);
		 	Mobile_WS_Utils::fixDescribeFieldInfo($module, $describeInfo);

		 	$this->cacheDescribeInfo($describeInfo);

			$templateRecord = array();
			foreach($describeInfo['fields'] as $describeField) {
				$templateFieldValue = '';
				if (isset($describeField['type']) && isset($describeField['type']['defaultValue'])) {
					$templateFieldValue = $describeField['type']['defaultValue'];
				} else if (isset($describeField['default'])) {
					$templateFieldValue = $describeField['default'];
				}
				$templateRecord[$describeField['name']] = $templateFieldValue;
			}
			if (isset($templateRecord['assigned_user_id'])) {
				$templateRecord['assigned_user_id'] = sprintf("%sx%s", Mobile_WS_Utils::getEntityModuleWSId('Users'), $current_user->id);
			} 
			// Reset the record id
			$templateRecord['id'] = $recordid;
			
			return $templateRecord;
		}
		
		// Or else delgate the action to parent
		return parent::processRetrieve($request);
	}
	
	function process(Mobile_API_Request $request) {
		$response = parent::process($request);
		return $this->processWithGrouping($request, $response);
	}
	
	protected function processWithGrouping(Mobile_API_Request $request, $response) {
		$isTemplateRecord = $this->isTemplateRecordRequest($request);
		$result = $response->getResult();
		
		$resultRecord = $result['record'];
//$response->setResult(array('record' => $resultRecord));
//return $response;
		$module = $this->detectModuleName($resultRecord['id']);
		
		$modifiedRecord = $this->transformRecordWithGrouping($resultRecord, $module, $isTemplateRecord);
		$response->setResult(array('record' => $modifiedRecord));
		
		return $response;
	}
	
	protected function transformRecordWithGrouping($resultRecord, $module, $isTemplateRecord=false) {
		global $current_user;
		$current_user = $this->getActiveUser();

		$moduleFieldGroups = Mobile_WS_Utils::gatherModuleFieldGroupInfo($module);
		$modifiedResult = array();
		$recordid = preg_replace("/([0-9]+)x/",'',$resultRecord['id']);
		
		$islook = isPermitted($module,'DetailView',$recordid);
		
		if($islook == 'no'){
			$modifiedResult['islook'] = 'no';
			return $modifiedResult;
		}else{
			//return $module.$recordid.$current_user->id;
			$isedit = isPermitted($module,'EditView',$recordid);
			//return $module.$recordid.'---'.$isedit;
			$modifiedResult['isedit'] = $isedit;
		}
		
		$blocks = array(); $labelFields = false;
		foreach($moduleFieldGroups as $blocklabel => $fieldgroups) {
			$fields = array();
			foreach($fieldgroups as $fieldname => $fieldinfo) {
				// Pickup field if its part of the result
				if(isset($resultRecord[$fieldname])) {
					$field = array(
						'name'  => $fieldname,
						'value' => $resultRecord[$fieldname],
						'label' => $fieldinfo['label'],
						'uitype'=> $fieldinfo['uitype'] 
					);
					
					// Template record requested send more details if available
					if ($isTemplateRecord) {
						$describeFieldInfo = $this->cachedDescribeFieldInfo($fieldname);
						if ($describeFieldInfo) {
							foreach($describeFieldInfo as $k=>$v) {
								if (isset($field[$k])) continue;
								$field[$k] = $v;
							}
						}
						// Entity fieldnames
						$labelFields = $this->cachedEntityFieldnames($module);
					}
					// Fix the assigned to uitype
					if ($field['uitype'] == '53') {
						$field['type']['defaultValue'] = array('value' => "19x{$current_user->id}", 'label' => $current_user->column_fields['last_name']);
					} else if($field['uitype'] == '117') {
						$field['type']['defaultValue'] = $field['value'];
					}
					// END
					
					$fields[] = $field;
				}
			}
			$blocks[] = array( 'label' => $blocklabel, 'fields' => $fields );
		}
		$sections = array();
		$moduleFieldGroupKeys = array_keys($moduleFieldGroups);
		foreach($moduleFieldGroupKeys as $blocklabel) {
			// Eliminate empty blocks
			if(isset($groups[$blocklabel]) && !empty($groups[$blocklabel])) {
				$sections[] = array( 'label' => $blocklabel, 'count' => count($groups[$blocklabel]) );
			}
		}
		
		$modifiedResult['blocks'] = $blocks;
		$modifiedResult['id'] = $resultRecord['id'];
		if($labelFields) $modifiedResult['labelFields'] = $labelFields;
		
		if (isset($resultRecord['LineItems'])) {
			$modifiedResult['LineItems'] = $resultRecord['LineItems'];
		}
		
		return $modifiedResult;
	}
}