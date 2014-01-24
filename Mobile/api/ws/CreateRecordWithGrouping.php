<?php
/*+**********************************************************************************
* 新增记录
* Creat at 2013-11-30 for c3crm by xiao
************************************************************************************/
include_once 'include/Webservices/Retrieve.php';
include_once dirname(__FILE__) . '/FetchRecord.php';
include_once 'include/Webservices/DescribeObject.php';
include_once 'modules/Vtiger/models/Module.php';
include_once 'modules/Vtiger/models/Field.php';
include_once 'modules/Users/models/Privileges.php';
include_once 'include/utils/UserInfoUtil.php';
class Mobile_WS_CreateRecordWithGrouping extends Mobile_WS_FetchRecord {
	
	function process(Mobile_API_Request $request) {
		return $this->processWithGrouping($request);
	}
	
	protected function processWithGrouping(Mobile_API_Request $request) {
		global $current_user,$adb;
		$rest_data = $request->getRest_data();
		$module = $rest_data['module'];
		$setype = $rest_data['setype'];
		$record = $rest_data['record'];
		$modifiedResult = array();
		if($setype == 'Account' && $record!=0){
			$account = array();
			$account['id'] = $record;
			$record=preg_replace("/([0-9]+)x/",'',$record);
			$result = $adb->query("select accountname from vtiger_account where accountid =".$record);
			$account['name'] = $adb->query_result($result, 0, 'accountname');
			$modifiedResult['account'] = $account;
		}
		$current_user = $this->getActiveUser();
		$moduleFieldGroups = Mobile_WS_Utils::gatherModuleFieldGroupInfo($module);
		//print_R($moduleFieldGroups);die;
		
		$fieldModule = Vtiger_Module_Model::getInstance($module);
		$textBox = array('1','2','7','9','11','55','71','72','85','86','87','88','89','255','1004');
		$comboBox = array('15','16','111');
		$linkBox = array('10','13','50','51','57','58','59','69','73','75','76','78','80','81','1010');
		$textArea = array('19','20','21','22','24');
		$dateBox = array('5','6','23');
		$switchBox = array('56');
		$allBox = array_merge($textBox,$comboBox,$linkBox,$textArea,$dateBox,$switchBox);
		$blocks = array(); $labelFields = false;
		foreach($moduleFieldGroups as $blocklabel => $fieldgroups) {
			$fields = array();
			foreach($fieldgroups as $fieldname => $fieldinfo) {
				// Pickup field if its part of the result
				$field = array(
					'name'  => $fieldname,
					'label' => $fieldinfo['label'],
					'mandatory' => $fieldinfo['mandatory'],
					'fieldtype' => "text",
					'uitype'=> $fieldinfo['uitype'] 
				);
				if(!in_array($field['uitype'],$allBox))continue;
				// Fix the assigned to uitype
				if ($field['uitype'] == '53') {
					$field['type']['defaultValue'] = array('value' => "19x{$current_user->id}", 'label' => $current_user->column_fields['last_name']);
				} else if($field['uitype'] == '117') {
					$field['type']['defaultValue'] = $field['value'];
				}
				// END
				
				if(in_array($field['uitype'],$comboBox)){
					$field['fieldtype'] = "opts";
					$fieldModel = Vtiger_Field_Model::getInstance($fieldname,$fieldModule);
					$field['opts'] = $fieldModel->getPicklistValues();
				}else if(in_array($field['uitype'],$linkBox)){
					if($field['label'] == 'Related To'|| $field['label'] == 'Organization Name'){
						$field['fieldtype'] = "accountid";
					}elseif($field['name'] == 'contact_id'){
						$field['fieldtype'] = "contactid";
					}elseif($field['name'] == 'potential_id'){
						$field['fieldtype'] = "potentialid";
					}else{
						continue;
					}
				}else if(in_array($field['uitype'],$textArea)){
					$field['fieldtype'] = "textarea";
				}else if(in_array($field['uitype'],$dateBox)){
					$field['fieldtype'] = "date";
				}else if(in_array($field['uitype'],$switchBox)){
					$field['fieldtype'] = "switch";
				}
				$fields[] = $field;
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
		if($labelFields) $modifiedResult['labelFields'] = $labelFields;

		$response = new Mobile_API_Response();
		$response->setResult($modifiedResult);
		return $response;
	}
}