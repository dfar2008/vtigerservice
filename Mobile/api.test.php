<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
chdir('../../');

header('Content-type: text/plain');

include_once 'vtlib/Vtiger/Net/Client.php';
include_once 'include/Zend/Json.php';

$mobileAPITestController = new Mobile_API_TestController();
//$mobileAPITestController->doLoginAndFetchModules('admin','admin');

//$mobileAPITestController->doListModuleRecords('Contacts');
$mobileAPITestController->doLogin('xiaoyang', '123456');
//$mobileAPITestController->doListCustomView('Accounts');
//$mobileAPITestController->doListModuleRecords('Potentials');
//
//$mobileAPITestController->doFetchModuleFilters('Accounts');
//$mobileAPITestController->doFilterDetailsWithCount('1');
//$mobileAPITestController->doFetchAllAlerts();
//$mobileAPITestController->doAlertDetailsWithMessage(5);
//$mobileAPITestController->doListModuleRecords('Potentials');
//$mobileAPITestController->doRecordComment('11x67');
//$mobileAPITestController->doAddRecordComment('11x67','aasdsadsadsa');
//$mobileAPITestController->doModuleFilter();
//$mobileAPITestController->doSalesFunnel(1);

//$mobileAPITestController->doFetchRecord('13x121', true);
//$mobileAPITestController->doEditRecord('11x2');
$mobileAPITestController->doCreateRecord('Potentials','');
//$mobileAPITestController->doDescribe('Accounts');
//$mobileAPITestController->doSave('Accounts', '', array('accountname'=>'上海aaa 瑞策1'));
//$mobileAPITestController->doSave('Potentials', '', array('potentialname'=>'上海aaa 瑞策1','related_to'=>'11x17','closingdate'=>'2013-11-27','sales_stage'=>'Prospecting'));
//$mobileAPITestController->doSync('HelpDesk');//, 0, 1277646523, 'public');//, 0, 1277234885);// 1271240542);
//$mobileAPITestController->doScanImage();
//$mobileAPITestController->doFetchRecordsWithGrouping('Accounts', 'alertid', '4');
//$mobileAPITestController->doQuery('Contacts', "SELECT firstname,lastname,account_id FROM Contacts LIMIT 1,2;");
//$mobileAPITestController->doQuery('Contacts', "SELECT * FROM Contacts;", 0, true);
//$mobileAPITestController->doRelatedRecordsWithGrouping('11x67', 'Contacts', 0);
//$mobileAPITestController->doDeleteRecords(array('1x198', '18x198'));

//$mobileAPITestController->doHistory('Home');

//$mobileAPITestController->doFetchRecord('16x196', false);

class Mobile_API_TestController {
	
	private $URL = 'http://192.168.1.112/vtigercrm2/service/Mobile/api.php';
	private $userid;
	private $session;
	private $listing;
	
	function doPost($parameters, $printResponse = false) {
		$client = new Vtiger_Net_Client($this->URL);
		$response = $client->doPost($parameters);
		if($printResponse) echo $response;
		$responseJSON = Zend_Json::decode($response);
		return $responseJSON;
	}
	
	//登陆并匹配模块
	function doLoginAndFetchModules($username, $password) {
		$responseJSON = $this->doPost(array(
				'_operation' => 'loginAndFetchModules',
				'rest_data'=> Zend_Json::encode(array('username'=>$username,'password' => $password))
			));
			
		$modules = array();
			
		if($responseJSON['success']) {
			$result = $responseJSON['result'];
			
			$this->userid = $result['login']['userid'];
			$this->session= $result['login']['session'];
			$this->listing= $result['modules'];
			
			echo sprintf("Login success - User ID: %s - Session %s\n", $this->userid, $this->session);
			echo "Accessible modules\n";
			foreach($this->listing as $moduleinfo) {
				echo sprintf("  %s - %s\n", $moduleinfo['id'], $moduleinfo['name']);
				$modules[] = $moduleinfo['name'];
			}
			
		} else {
			$error = $responseJSON['error'];
			echo sprintf("Login failed - %s: %s\n", $error['code'], $error['message']);
		}
		return $modules;
	}
	
	//登陆
	function doLogin($username, $password) {
		$responseJSON = $this->doPost(array(
				'_operation' => 'login',
			    'rest_data'=> Zend_Json::encode(array('username'=>$username,'password' => $password))
			), true);
			
		$modules = array();
			
		if($responseJSON['success']) {
			$result = $responseJSON['result'];
			
			$this->userid = $result['login']['userid'];
			$this->session= $result['login']['session'];
			$this->listing= $result['modules'];
			
			echo sprintf("Login success - User ID: %s - Session %s\n", $this->userid, $this->session);
			
		} else {
			$error = $responseJSON['error'];
			echo sprintf("Login failed - %s: %s\n", $error['code'], $error['message']);
		}
		return $modules;
	}
	
	function doFetchModuleFilters($moduleName) {
		$responseJSON = $this->doPost(array(
				'_operation' => 'fetchModuleFilters',
				'_session'=> $this->session,
				'module' => $moduleName,
			), true);
		//print_r($responseJSON);
	}
	
	function doFilterDetailsWithCount($filterId) {
		$responseJSON = $this->doPost(array(
				'_operation' => 'filterDetailsWithCount',
				'_session'=> $this->session,
				'filterid' => $filterId,
			), true);
		//print_r($responseJSON);
	}
	
	function doFetchAllAlerts() {
		$responseJSON = $this->doPost(array(
				'_operation' => 'fetchAllAlerts',
				'_session'=> $this->session,
			), true);
		//print_r($responseJSON);
	}
	
	function doAlertDetailsWithMessage($alertid) {
		$responseJSON = $this->doPost(array(
				'_operation' => 'alertDetailsWithMessage',
				'_session'=> $this->session,
				'alertid' => $alertid,
			), true);
		//print_r($responseJSON);
	}
	
	//获取模块记录列表
	function doListModuleRecords($module) {
		$responseJSON = $this->doPost(array(
				'_operation' => 'listModuleRecords',
				'_session'=> $this->session,
				'rest_data'=> Zend_Json::encode(array('module' => $module,'filterid' => '4','page' => '1','search_text'=>''))
			), true);
		//print_r($responseJSON);
	}

	//获取模块视图列表
	function doListCustomView($module) {
		$responseJSON = $this->doPost(array(
				'_operation' => 'listCustomView',
				'_session'=> $this->session,
				'rest_data'=> Zend_Json::encode(array('module' => $module))
			), true);
		//print_r($responseJSON);
	}
	
	function doFetchRecord($recordid, $withGrouping = false) {
		$parameters = array(
				'_session' => $this->session,
				'_operation' => 'fetchRecord',
				'rest_data'=> Zend_Json::encode(array('record' => $recordid))
			);
			
		if($withGrouping) {
			$parameters['_operation'] = 'fetchRecordWithGrouping';
		}
		$responseJSON = $this->doPost($parameters, true);
	}

	function doEditRecord($recordid) {
		$parameters = array(
				'_session' => $this->session,
				'_operation' => 'editRecordWithGrouping',
				'rest_data'=> Zend_Json::encode(array('record' => $recordid))
			);
		$responseJSON = $this->doPost($parameters, true);
	}

	function doCreateRecord($module,$record) {
		$parameters = array(
				'_session' => $this->session,
				'_operation' => 'createRecordWithGrouping',
				'rest_data'=> Zend_Json::encode(array('module' => $module,'setype'=>'Account','record'=>$record))
			);
		$responseJSON = $this->doPost($parameters, true);
	}
	
	function doDescribe($module) {
		$responseJSON = $this->doPost(array(
				'_session' => $this->session,
				'_operation' => 'describe',
				'module' => $module
			), true);
	}
	
	function doSave($module, $record, $values) {
		$select_fields = '';
		foreach($values as $key=>$value){
			$select_fields .= "&".$key."=".$value;
		}
		echo $select_fields;
		$parameters = array(
				'_session' => $this->session,
				'_operation' => 'saveRecord',
				'rest_data'=> Zend_Json::encode(array('module' => $module,'select_fields'=>urlencode($select_fields),'record'=>$record))
			);
		$responseJSON = $this->doPost($parameters, true);
//		$parameters = array(
//				'_session' => $this->session,
//				'_operation' => 'saveRecord',
//				'rest_data'=> Zend_Json::encode(array('module' => $module,'record' => $record,'select_fields' => $values))
//				
//			);
//			
//		$responseJSON = $this->doPost($parameters, true);
	}
	
	function doSync($module, $page=false, $lastSyncTime = false, $mode='PUBLIC') {
		$parameters = array(
				'_session' => $this->session,
				'_operation' => 'syncModuleRecords',
				'module' => $module,
			);
		if ($page !== false) {
			$parameters['page'] = $page;
		}
		if ($lastSyncTime !== false) {
			$parameters['syncToken'] = $lastSyncTime;
		}
		$parameters['mode'] = $mode;
			
		$responseJSON = $this->doPost($parameters, true);
	}
	
	function doScanImage($module) {
		$parameters = array(
				'_session' => $this->session,
				'_operation' => 'scanImage'
			);
			
		$responseJSON = $this->doPost($parameters, true);
	}
	
	function doFetchRecordsWithGrouping($module, $key, $value) {
		$parameters = array(
				'_session' => $this->session,
				'_operation' => 'fetchRecordsWithGrouping',
				'module' => $module,
				$key => $value
			);
			
		$responseJSON = $this->doPost($parameters, true);
	}
	
	function doQuery($module, $query, $page=0, $withGrouping = false) {
		$parameters = array(
				'_session' => $this->session,
				'_operation' => ($withGrouping? 'queryWithGrouping' : 'query'),
				'module' => $module,
				'page' => $page,
				'query' => $query
			);
			
		$responseJSON = $this->doPost($parameters, true);
	}
	
	function doRelatedRecordsWithGrouping($record, $relatedmodule, $page=0) {
		$parameters = array(
				'_session' => $this->session,
				'_operation' => 'relatedRecordsWithGrouping',
				'rest_data'=> Zend_Json::encode(array('relatedmodule' => $relatedmodule,'page'=>$page,'record'=>$record))
			);
			
		$responseJSON = $this->doPost($parameters, true);
	}

	function doModuleFilter() {
		$parameters = array(
				'_session' => $this->session,
				'_operation' => 'moduleFilter'
			);
			
		$responseJSON = $this->doPost($parameters, true);
	}

	function doSalesFunnel($owner) {
		$parameters = array(
				'_session' => $this->session,
				'_operation' => 'salesFunnel',
				'rest_data'=> Zend_Json::encode(array('owner'=>$owner))
			);
			
		$responseJSON = $this->doPost($parameters, true);
	}

	function doRecordComment($record) {
		$parameters = array(
				'_session' => $this->session,
				'_operation' => 'recordComment',
				'rest_data'=> Zend_Json::encode(array('record'=>$record))
			);
			
		$responseJSON = $this->doPost($parameters, true);
	}

	function doAddRecordComment($record,$content) {
		$parameters = array(
				'_session' => $this->session,
				'_operation' => 'addRecordComment',
				'rest_data'=> Zend_Json::encode(array('record'=>$record,'content'=>$content))
			);
			
		$responseJSON = $this->doPost($parameters, true);
	}
	
	function doDeleteRecords($recordids) {
		$key = 'record'; $value = $recordids;
		if (is_array($recordids)) {
			$key = 'records';
			$value = Zend_Json::encode($recordids);
		}
		$parameters = array(
				'_session' => $this->session,
				'_operation' => 'deleteRecords',
				$key => $value
			);
			
		$responseJSON = $this->doPost($parameters, true);
	}
	
	function doHistory($module, $record='') {
		$parameters = array(
			'_session' => $this->session,
			'_operation' => 'history',
			'module' => empty($record) ? $module : '',
			'record' => $record,
			'mode'   => 'All' , // Private (not supported yet)
		);
		$responseJSON = $this->doPost($parameters, true);
	}
	
}

