<?php

class Mobile_WS_SaleFunnel extends Mobile_WS_Controller {

	function process(Mobile_API_Request $request) {
		global $current_user, $adb;
		$current_user = $this->getActiveUser();
		
		$rest_data = $request->getRest_data();
		$owner = $rest_data['owner'];
		//$moduleName =$rest_data['module'];
		$dates = $request->get('expectedclosedate');
		//Date conversion from user to database format
		if(!empty($dates)) {
			$dates['start'] = Vtiger_Date_UIType::getDBInsertedValue($dates['start']);
			$dates['end'] = Vtiger_Date_UIType::getDBInsertedValue($dates['end']);
		}
		//$moduleModel = Vtiger_Module_Model::getInstance($moduleName);
		$data = $this->getPotentialsCountBySalesStage($owner, $dates);
		$response = new Mobile_API_Response();
		$response->setResult($data);
		return $response;
	}

	/**
	 * Function returns number of Open Potentials in each of the sales stage
	 * @param <Integer> $owner - userid
	 * @return <Array>
	 */
	public function getPotentialsCountBySalesStage($owner, $dateFilter) {
		global $adb;
		if (!$owner) {
			$currenUserModel = Users_Record_Model::getCurrentUserModel();
			$owner = $currenUserModel->getId();
		} else if ($owner === 'all') {
			$owner = '';
		}

		$params = array();
		if(!empty($owner)) {
			$ownerSql =  ' AND smownerid = ? ';
			$params[] = $owner;
		}
		if(!empty($dateFilter)) {
			$dateFilterSql = ' AND closingdate BETWEEN ? AND ? ';
			$params[] = $dateFilter['start'];
			$params[] = $dateFilter['end'];
		}

		$result = $adb->pquery('SELECT COUNT(*) as count, sales_stage,sum(amount) as amount FROM vtiger_potential
						INNER JOIN vtiger_crmentity ON vtiger_potential.potentialid = vtiger_crmentity.crmid
						AND deleted = 0 '.Users_Privileges_Model::getNonAdminAccessControlQuery('Potentials'). $ownerSql . $dateFilterSql . ' AND sales_stage NOT IN ("Closed Won", "Closed Lost")
							GROUP BY sales_stage ORDER BY count desc', $params);
		
		$response = array();
		for($i=0; $i<$adb->num_rows($result); $i++) {
			$saleStage = $adb->query_result($result, $i, 'sales_stage');
			$response[$i]['count'] = $adb->query_result($result, $i, 'count');
			$response[$i]['amount'] = $adb->query_result($result, $i, 'amount');
			$response[$i]['sales_stage'] = vtranslate($saleStage, 'Potentials');
			//$response[$i]['sales_stage'] = $saleStage;
		}
		return $response;
	}
}