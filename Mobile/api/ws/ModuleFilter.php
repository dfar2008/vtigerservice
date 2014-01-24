<?php
/***********************************************************************************
 * 查看范围下拉框
 * Creat at 2013-12-3 for c3crm by xiao
 ************************************************************************************/
class Mobile_WS_ModuleFilter extends Mobile_WS_Controller {

	function process(Mobile_API_Request $request) {
		global $current_user, $adb;
		$current_user = $this->getActiveUser();
		$shtml = '<option value="'.$current_user->id.'" >'.vtranslate('LBL_MINE').'</option>';
		$shtml .= '<option value="all">'.vtranslate('LBL_ALL').'</option>';
		$response = new Mobile_API_Response();
		$response->setResult(array('options'=>$shtml,'selected'=>$current_user->id));
		return $response;
	}
}