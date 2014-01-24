<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
require_once 'include/QueryGenerator/QueryGenerator.php';
include_once 'modules/CustomView/CustomView.php';

class Mobile_WS_FilterModel {
	
	var $filterid, $moduleName;
	var $user;
	var $search_text;
	protected $customView;
	
	function __construct($moduleName) {
		$this->moduleName = $moduleName;
		$this->customView = new CustomView($moduleName);
	}
	
	function setUser($userInstance) {
		$this->user = $userInstance;
	}

	
	function getUser() {
		return $this->user;
	}

	//设置查询内容
	function setSearchText($search_text) {
		$this->search_text = $search_text;
	}

	function getSearchText() {
		return $this->search_text;
	}
	
	function query() {
		
		global $current_user;
		$viewId = $this->filterid;
		$moduleName = $this->moduleName;
		$queryGenerator = new QueryGenerator($moduleName, $current_user);
		$customView = new CustomView();
			
		if (!empty($viewId) && $viewId != "0") {
			$queryGenerator->initForCustomViewById($viewId);

			//Used to set the viewid into the session which will be used to load the same filter when you refresh the page
			$viewId = $customView->getViewId($moduleName);
		} else {
			$viewId = $customView->getViewId($moduleName);
			if(!empty($viewId) && $viewId != 0) {
				$queryGenerator->initForDefaultCustomView();
			} else {
				$entityInstance = CRMEntity::getInstance($moduleName);
				$listFields = $entityInstance->list_fields_name;
				$listFields[] = 'id';
				$queryGenerator->setFields($listFields);
			}
		}
	
		if($this->search_text != ''){
			//获取查询字段
			$searchFields = $this->getSearchFields($moduleName);
			foreach($searchFields as $searchKey){
				$queryGenerator->addUserSearchConditions(array('search_field' => $searchKey, 'search_text' => $this->search_text, 'operator' => ''));
			}
		}
		
		$listquery = $queryGenerator->getQuery();
		$listquery = str_replace(') AND (',' OR ',$listquery);
		//$listquery = getListQuery($this->moduleName);
		//$query = $this->customView->getModifiedCvListQuery($this->filterid,$listquery,$this->moduleName);
		//echo $listquery;die;
		return $listquery;
	}

	function getSearchFields($module) {
		$fieldnames = array();
		switch($module) {
			case 'Accounts': $fieldnames = array('accountname','ship_city','bill_state','description'); break;
			case 'Contacts': $fieldnames = array('firstname','lastname','account_id','mailingcity','mailingstate','description'); break;
			case 'Potentials': $fieldnames = array('potentialname','related_to','description'); break;
		}
		return $fieldnames;
	}
	
	function queryParameters() {
		return false;
	}
	
	static function modelWithId($moduleName, $filterid="") {
		$model = new Mobile_WS_FilterModel($moduleName);
		global $adb;
		if($filterid==""){
			$query = "SELECT `cvid` FROM `vtiger_customview` WHERE entitytype = ? ORDER BY cvid limit 0,1";
			$result = $adb->pquery($query, array($moduleName));
			if ($result && $adb->num_rows($result)) {
				$filterid = $adb->query_result($result, 0, 'cvid');
			}
		}
		$model->filterid = $filterid;
		return $model;
	}
	
}