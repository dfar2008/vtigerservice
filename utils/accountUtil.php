<?php
require_once('include/database/PearDatabase.php');
require_once('include/CustomFieldUtil.php');
require_once('include/utils/utils.php');
require_once('include/utils/UserInfoUtil.php');
require_once('modules/CustomView/CustomView.php');
//获取客户信息
function getAccountInfo($accountid){
	global $adb;
	$query = "select * from ec_account where deleted=0 and accountid='$accountid'";
	$result = $adb->query($query);
	$num_rows = $adb->num_rows($result);
	$lists = array();
	if($num_rows > 0){
		while($row = $adb->fetch_array($result)){
			$lists = $row;
		}
	}
	return $lists;
}

//获取客户首要联系人id
function getKeyContactid($accountid){
	global $adb;
	$query = "select contactid from ec_contactdetails 
				where deleted = 0 and accountid = {$accountid} and ismain = 'Yes' ";
	$result = $adb->query($query);
	$num_rows = $adb->num_rows($result);
	$contactid = '';
	if($num_rows && $num_rows == 1){//首要联系人
		$contactid = $adb->query_result($result,0,"contactid");
	}
	return $contactid;
}

//得到客户的联系人信息 
function getAccountContactInfo($accountid,$session) {
	global $adb;
	$query = "select contactid,lastname from ec_contactdetails 
				where deleted = 0 and accountid = {$accountid}
				and ec_contactdetails.smownerid = '{$session}' ";
	$result = $adb->query($query);
	$num_rows = $adb->num_rows($result);
	$contactarr = array();
	if($num_rows && $num_rows > 0){
		while($row = $adb->fetch_array($result)){
			$contactarr[] = array("contactid"=>$row['contactid'],"lastname"=>$row["lastname"]);
		}
	}
	return $contactarr;
}

//得到联系人的客户id
function getContactAccountid($contactid) {
	global $adb;
	$query = "select accountid from ec_contactdetails 
				where deleted = 0 and contactid = {$contactid} ";
	$result = $adb->query($query);
	$num_rows = $adb->num_rows($result);
	$accountid = '';
	if($num_rows && $num_rows == 1){
		$accountid = $adb->query_result($result,0,"accountid");
	}
	return $accountid;
}

//得到联系记录的客户id
function getNoteAccountid($notesid) {
	global $adb;
	$query = "select accountid from ec_notes 
				where deleted = 0 and notesid = {$notesid} ";
	$result = $adb->query($query);
	$num_rows = $adb->num_rows($result);
	$accountid = '';
	if($num_rows && $num_rows == 1){
		$accountid = $adb->query_result($result,0,"accountid");
	}
	return $accountid;
}

//得到联系记录的联系人id
function getNoteContactid($notesid) {
	global $adb;
	$query = "select contact_id from ec_notes 
				where deleted = 0 and notesid = {$notesid} ";
	$result = $adb->query($query);
	$num_rows = $adb->num_rows($result);
	$contact_id = '';
	if($num_rows && $num_rows == 1){
		$contact_id = $adb->query_result($result,0,"contact_id");
	}
	return $contact_id;
}

//得到日程安排的客户id
function getCalendarAccountid($activityid) {
	global $adb;
	$query = "select accountid from ec_activity 
				where deleted = 0 and activityid = {$activityid} ";
	$result = $adb->query($query);
	$num_rows = $adb->num_rows($result);
	$accountid = '';
	if($num_rows && $num_rows == 1){
		$accountid = $adb->query_result($result,0,"accountid");
	}
	return $accountid;
}

//得到日程安排的联系人id
function getCalendarContactid($activityid) {
	global $adb;
	$query = "select contact_id from ec_activity 
				where deleted = 0 and activityid = {$activityid} ";
	$result = $adb->query($query);
	$num_rows = $adb->num_rows($result);
	$contact_id = '';
	if($num_rows && $num_rows == 1){
		$contact_id = $adb->query_result($result,0,"contact_id");
	}
	return $contact_id;
}

//得到销售机会的客户id
function getPotentialAccountid($potentialid) {
	global $adb;
	$query = "select accountid from ec_potential 
				where deleted = 0 and potentialid = {$potentialid} ";
	$result = $adb->query($query);
	$num_rows = $adb->num_rows($result);
	$accountid = '';
	if($num_rows && $num_rows == 1){
		$accountid = $adb->query_result($result,0,"accountid");
	}
	return $accountid;
}

//得到销售机会的联系人id
function getPotentialContactid($potentialid) {
	global $adb;
	$query = "select contact_id from ec_potential 
				where deleted = 0 and potentialid = {$potentialid} ";
	$result = $adb->query($query);
	$num_rows = $adb->num_rows($result);
	$contact_id = '';
	if($num_rows && $num_rows == 1){
		$contact_id = $adb->query_result($result,0,"contact_id");
	}
	return $contact_id;
}

//得到合同订单的客户id
function getSalesOrderAccountid($salesorderid) {
	global $adb;
	$query = "select accountid from ec_salesorder 
				where deleted = 0 and salesorderid = {$salesorderid} ";
	$result = $adb->query($query);
	$num_rows = $adb->num_rows($result);
	$accountid = '';
	if($num_rows && $num_rows == 1){
		$accountid = $adb->query_result($result,0,"accountid");
	}
	return $accountid;
}

//得到合同订单的联系人id
function getSalesOrderContactid($salesorderid) {
	global $adb;
	$query = "select contact_id from ec_salesorder 
				where deleted = 0 and salesorderid = {$salesorderid} ";
	$result = $adb->query($query);
	$num_rows = $adb->num_rows($result);
	$contact_id = '';
	if($num_rows && $num_rows == 1){
		$contact_id = $adb->query_result($result,0,"contact_id");
	}
	return $contact_id;
}

//得到费用报销的客户id
function getExpenseAccountid($expensesid) {
	global $adb;
	$query = "select accountid from ec_expenses 
				where deleted = 0 and expensesid = {$expensesid} ";
	$result = $adb->query($query);
	$num_rows = $adb->num_rows($result);
	$accountid = '';
	if($num_rows && $num_rows == 1){
		$accountid = $adb->query_result($result,0,"accountid");
	}
	return $accountid;
}

//得到费用报销的联系人id
function getExpenseContactid($expensesid) {
	global $adb;
	$query = "select contact_id from ec_expenses 
				where deleted = 0 and expensesid = {$expensesid} ";
	$result = $adb->query($query);
	$num_rows = $adb->num_rows($result);
	$contact_id = '';
	if($num_rows && $num_rows == 1){
		$contact_id = $adb->query_result($result,0,"contact_id");
	}
	return $contact_id;
}

/*
* 获取用户头像
*/
function getCurrentUserPhotoUrl($id){
	global $site_URL;
	global $adb;
	$query="select imagename from ec_users where id=$id";
	$result=$adb->query($query);
	$num_rows=$adb->num_rows($result);
	$url="";
	if($num_rows>0){
		$imagename=$adb->query_result($result,0,"imagename");
		if(!empty($imagename)){
			$url=$site_URL."/getpic.php?mode=show&attachmentsid=".$imagename;
			//$photo='<img src="'.$url.'" width="80" height="100" border="0" align="absmiddle">';
		}else{
			//$photo='<i class=\'icon-user\'></i>';
		}
	}
	return $url;
	//return $photo;
}

?>