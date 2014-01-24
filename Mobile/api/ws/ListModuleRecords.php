<?php
/*+**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 ************************************************************************************/
include_once dirname(__FILE__) . '/models/Alert.php';
include_once dirname(__FILE__) . '/models/SearchFilter.php';
include_once dirname(__FILE__) . '/models/Paging.php';
include_once 'include/Webservices/Query.php';

class Mobile_WS_ListModuleRecords extends Mobile_WS_Controller {
	private $module = false;
	protected $resolvedValueCache = array();
	
	protected function detectModuleName($recordid) {
		if($this->module === false) {
			$this->module = Mobile_WS_Utils::detectModulenameFromRecordId($recordid);
		}
		return $this->module;
	}

	function isCalendarModule($module) {
		return ($module == 'Events' || $module == 'Calendar');
	}
	
	function getSearchFilterModel($module, $search) {
		return Mobile_WS_SearchFilterModel::modelWithCriterias($module, Zend_JSON::decode($search));
	}
	
	function getPagingModel(Mobile_API_Request $request) {
		$page = $request->get('page', 0);
		return Mobile_WS_PagingModel::modelWithPageStart($page);
	}
	
	function process(Mobile_API_Request $request) {
		return $this->processSearchRecordLabel($request);
	}
	
	function processSearchRecordLabel(Mobile_API_Request $request) {
		global $current_user; // Few core API assumes this variable availability
		$current_user = $this->getActiveUser();
		$rest_data = $request->getRest_data();
		$module = $rest_data['module'];
		$filterid = $rest_data['filterid'];
		$search_text = $rest_data['search_text'];

		$filterOrAlertInstance = false;
		$filterOrAlertInstance = Mobile_WS_FilterModel::modelWithId($module, $filterid);

		if($filterOrAlertInstance && strcmp($module, $filterOrAlertInstance->moduleName)) {
			$response = new Mobile_API_Response();
			$response->setError(1001, 'Mistached module information.');
			return $response;
		}

		// Initialize with more information
		if($filterOrAlertInstance) {
			$filterOrAlertInstance->setUser($current_user);
		}
		 if(!empty($search_text)) {
			$filterOrAlertInstance->setSearchText($search_text);
		}
		$page = $rest_data['page'];
		$pagingModel = Mobile_WS_PagingModel::modelWithPageStart($page-1);

		if($this->isCalendarModule($module)) {
			return $this->processSearchRecordLabelForCalendar($request, $pagingModel);
		}
		$module_records = $this->fetchRecordLabelsForModule($module, $current_user, array(), $filterOrAlertInstance, $pagingModel);
		
		$modifiedRecords = array();
		foreach($module_records['records'] as $record) {
			if ($record instanceof SqlResultIteratorRow) {
				$record = $record->data;
				// Remove all integer indexed mappings
				for($index = count($record); $index > -1; --$index) {
					if(isset($record[$index])) {
						unset($record[$index]);
					}
				}
			}
			
			$values = array_values($record);

			$modifiedRecords[] = $record;
		}
		$lastpg=ceil($module_records['count']/$pagingModel->limit());
		$lastpg = ($lastpg>0)?$lastpg:1;
		
		$response = new Mobile_API_Response();
		$response->setResult(array('records'=>$modifiedRecords, 'lastpg'=>$lastpg,'module'=>$module));
		
		return $response;
	}
	
	function processSearchRecordLabelForCalendar(Mobile_API_Request $request, $pagingModel = false) {
		$current_user = $this->getActiveUser();
		
		// Fetch both Calendar (Todo) and Event information
		$moreMetaFields = array('date_start', 'time_start', 'activitytype', 'location');
		$eventsRecords = $this->fetchRecordLabelsForModule('Events', $current_user, $moreMetaFields, false, $pagingModel);
		$calendarRecords=$this->fetchRecordLabelsForModule('Calendar', $current_user, $moreMetaFields, false, $pagingModel);

		// Merge the Calendar & Events information
		$records = array_merge($eventsRecords, $calendarRecords);
		
		$modifiedRecords = array();
		foreach($records as $record) {
			$modifiedRecord = array();
			$modifiedRecord['id'] = $record['id'];                      unset($record['id']);
			$modifiedRecord['eventstartdate'] = $record['date_start'];  unset($record['date_start']);
			$modifiedRecord['eventstarttime'] = $record['time_start'];  unset($record['time_start']);
			$modifiedRecord['eventtype'] = $record['activitytype'];     unset($record['activitytype']);
			$modifiedRecord['eventlocation'] = $record['location'];     unset($record['location']);
			
			$modifiedRecord['label'] = implode(' ',array_values($record));
			
			$modifiedRecords[] = $modifiedRecord;
		}
		
		$response = new Mobile_API_Response();
		$response->setResult(array('records' =>$modifiedRecords, 'module'=>'Calendar'));
		
		return $response;
	}
	
	function fetchRecordLabelsForModule($module, $user, $morefields=array(), $filterOrAlertInstance=false, $pagingModel = false) {
		
		if($this->isCalendarModule($module)) {
			$fieldnames = Mobile_WS_Utils::getEntityFieldnames('Calendar');
		} else {
			$fieldnames = Mobile_WS_Utils::getEntityFieldnames($module);
		}
		
		if(!empty($morefields)) {
			foreach($morefields as $fieldname) $fieldnames[] = $fieldname;
		}
		if($filterOrAlertInstance === false) {
			$filterOrAlertInstance = Mobile_WS_SearchFilterModel::modelWithCriterias($module);
			$filterOrAlertInstance->setUser($user);
		}
		
		return $this->queryToSelectFilteredRecords($module, $fieldnames, $filterOrAlertInstance, $pagingModel);
	}
	
	function queryToSelectFilteredRecords($module, $fieldnames, $filterOrAlertInstance, $pagingModel) {
		global $adb;
		$current_user = $this->getActiveUser();
		// Build select clause similar to Webservice query
		$selectColumnClause = "vtiger_crmentity.crmid,";
		$querySEtype = "vtiger_crmentity.setype as setype";
		if ($module == 'Calendar') {
			$querySEtype = "vtiger_activity.activitytype as setype";
		}
		$selectColumnClause .= $querySEtype;
		
		$query = $filterOrAlertInstance->query();
		//return $query;
		$query = preg_replace("/SELECT.*FROM(.*)/i", "SELECT $selectColumnClause FROM $1", $query);
		$count_query = preg_replace("/SELECT.*FROM(.*)/i", "SELECT count(*) AS count FROM $1", $query);
		$whereClause = "";
		$orderClause = "ORDER BY modifiedtime desc";
		$groupClause = "";
		$limitClause = $pagingModel? " LIMIT {$pagingModel->currentCount()},{$pagingModel->limit()}" : "" ;
		//查询总记录数
		$count_query = $count_query . sprintf("%s %s;", $whereClause,$groupClause);
		$count_query = $adb->pquery($count_query, array());
		$count = $adb->query_result($count_query, 0, 'count');
		//查询当页记录
		$query .= sprintf("%s %s %s %s;", $whereClause, $orderClause, $groupClause, $limitClause);
		$relatedRecords = array();
		$queryResult = $adb->query($query);
		while($row = $adb->fetch_array($queryResult)) {
			$targetSEtype = $row['setype'];
			if ($module == 'Calendar') {
				if ($row['setype'] != 'Task' && $row['setype'] != 'Emails') {
					$targetSEtype = 'Events';
				} else {
					$targetSEtype = $module;
				}
			}
			$relatedRecords[] = sprintf("%sx%s", Mobile_WS_Utils::getEntityModuleWSId($targetSEtype), $row['crmid']);
		}
		$records = array();
		if(count($relatedRecords)>0){
			$selectColumnClause ='';
			$fieldnames = Mobile_WS_Utils::getEntityFieldnames($module);
			foreach($fieldnames as $fieldname) {
				$selectColumnClause .= sprintf("%s,", $fieldname);
			}
			$selectColumnClause = rtrim($selectColumnClause, ',');

			// Perform query to get record information with grouping
			
			$wsquery = sprintf("SELECT $selectColumnClause FROM %s WHERE id IN ('%s') ORDER BY modifiedtime desc;", $module, implode("','", $relatedRecords));
			$queryResult = vtws_query($wsquery, $current_user);
			
			if (!empty($queryResult)) {
				foreach($queryResult as $recordValues) {
					$records[] = $this->processQueryResultRecordnoLabel($recordValues, $current_user);
				}
			}
		}
		return array('records'=>$records,'count'=>$count);
	}
	
	function resolveRecordValues(&$record, $user, $ignoreUnsetFields=false) {
		if(empty($record)) return $record;
		
		$fieldnamesToResolve = Mobile_WS_Utils::detectFieldnamesToResolve(
			$this->detectModuleName($record['id']) );
		
		if(!empty($fieldnamesToResolve)) {
			foreach($fieldnamesToResolve as $resolveFieldname) {
				if ($ignoreUnsetFields === false || isset($record[$resolveFieldname])) {
					$fieldvalueid = $record[$resolveFieldname];
					$fieldvalue = $this->fetchRecordLabelForId($fieldvalueid, $user);
					$record[$resolveFieldname] = array('value' => $fieldvalueid, 'label'=>$fieldvalue);
				}
			}
		}
	}
	
	function fetchRecordLabelForId($id, $user) {
		$value = null;
		
		if (isset($this->resolvedValueCache[$id])) {
			$value = $this->resolvedValueCache[$id];
		} else if(!empty($id)) {
			$value = trim(vtws_getName($id, $user));
			$this->resolvedValueCache[$id] = $value;
		} else {
			$value = $id;
		}
		return $value;
	}

	function processQueryResultRecordnoLabel(&$record, $user) {
		$this->resolveRecordValues($record, $user);
		return $record;
	}
}
