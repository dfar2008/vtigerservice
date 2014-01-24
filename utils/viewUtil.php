<?php
require_once('include/database/PearDatabase.php');
require_once('include/CustomFieldUtil.php');
require_once('include/utils/utils.php');
require_once('include/utils/UserInfoUtil.php');
require_once('modules/CustomView/CustomView.php');
class viewScope{
	public $isAdmin;
	private $groupid;
	private $roleid;
	private $userid;
	private $profileid;
	private $moduleAllFlag = true;
	private $isAdminFlag = false;
	private $currentuser_parentrole;
	private $profileTabsPermission;
	private $profileActionPermission;
	private $profileGlobalPermission;
	private $defaultOrgSharingPermission;
	private $subordinate_roles_users;
	private $subordinate_roles;

	function __construct() {
		global $current_language,$app_strings,$app_list_strings;
		$current_language = 'zh_cn';
		$app_strings = return_application_language($current_language);
		$app_list_strings = return_app_list_strings_language($current_language);
	}
	
	function getParentroleAndIsadmin($session) {  //获取角色信息
		global $adb;
		$query = "select is_admin,ec_role.parentrole,ec_users2group.groupid,ec_user2role.roleid,ec_role2profile.profileid from ec_users left join ec_user2role on ec_user2role.userid=ec_users.id left join ec_role on ec_role.roleid=ec_user2role.roleid left join ec_users2group on ec_users2group.userid=ec_users.id left join ec_role2profile on ec_role2profile.roleid=ec_user2role.roleid where deleted=0 and id=".$session;
		//return $query;
		$this->userid = $session;
		$result = $adb->query($query);
		$num_rows = $adb->num_rows($result);
		if($num_rows && $num_rows > 0){
			$this->isAdmin = $adb->query_result($result,0,"is_admin");
			//return $this->isAdmin;
			$this->groupid = $adb->query_result($result,0,"groupid");
			$this->roleid = $adb->query_result($result,0,"roleid");
			$this->profileid = $adb->query_result($result,0,"profileid");
			$this->currentuser_parentrole = $adb->query_result($result,0,"parentrole");
			if($this->isAdmin == 'on'){
				$this->isAdminFlag = true;
			}
		}
		return $this->isAdmin ;
	}

	function getPermissionOfModule($module,$session) {   //获取角色模块权限
		$tabid=getTabid($module);
		$this->profileTabsPermission = getCombinedUserTabsPermissions($session);
		$this->profileGlobalPermission = getCombinedUserGlobalPermissions($session);
		$this->profileActionPermission = getCombinedUserActionPermissions($session);
		$this->defaultOrgSharingPermission = getDefaultSharingAction();
		$this->subordinate_roles_users = getSubordinateRoleAndUsers($this->roleid);;
		$this->subordinate_roles = getRoleSubordinates($this->roleid);
		if($this->isAdmin != 'on' && $this->profileGlobalPermission[1] == 1 && $this->profileGlobalPermission[2] == 1 && $this->defaultOrgSharingPermission[$tabid] == 3){
			$this->moduleAllFlag = false;
		}
	}

	function hasGlobalPermission() {
		if($this->isAdminFlag || $this->profileGlobalPermission[1] == 0 || $this->profileGlobalPermission[2] == 0) {
			return true;
		} else {
			return false;
		}
	}

	function getFilterByViewId($module,$viewid){
		$oCustomView = new CustomView($module);
		$stdfiltersql = $oCustomView->getCVStdFilterSQL($viewid);
		$advfiltersql = $oCustomView->getCVAdvFilterSQL($viewid);
		$filter = '';
		if(isset($stdfiltersql) && $stdfiltersql != '' && $stdfiltersql != '()')
		{
			$filter .= ' and '.$stdfiltersql;
		}
		if(isset($advfiltersql) && $advfiltersql != '' && $advfiltersql != '()')
		{
			$filter .= ' and '.$advfiltersql;
		}
		return $filter;
	}

	function getListQuery($module,$session,$viewscope=""){
		global $adb,$log;
		$log->info("Entering getListQuery() method ...");  
		$sec_query = "";
		$key = "mobile_listquery_".$module."_".$viewscope."_".$session;
		$sec_query = getSqlCacheData($key);
		if(!$sec_query) {
			$this->getParentroleAndIsadmin($session);
			$this->getPermissionOfModule($module,$session);
			if(!$this->moduleAllFlag){
				if($viewscope == ''){
					$viewscope = "all_to_me";
				}
				$sec_query = $this->getListViewSecurityParameter($module,$session,$viewscope);
			}else if($viewscope != ''){
				$sec_query = $this->getListViewSecurityParameter($module,$session,$viewscope);
			}
			setSqlCacheData($key,$sec_query);
		}
		return $sec_query;
	}

	function getListViewSecurityParameter($module,$session,$viewscope="all_to_me")
	{
		global $adb,$log;
		$log->info("Entering getListViewSecurityParameter() method ...");  
		$sec_query = "";
		$tabid=getTabid($module);
		$entityArr = getEntityTable($module);
		$ec_crmentity = $entityArr["tablename"];
		$entityidfield = $entityArr["entityidfield"];
		$crmid = $ec_crmentity.".".$entityidfield;
		
		if($viewscope == "all_to_me") {
			$sec_query .= " ($ec_crmentity.smcreatorid in($session) or $ec_crmentity.smownerid in($session) or $ec_crmentity.smownerid in(select ec_user2role.userid from ec_user2role inner join ec_users on ec_users.id=ec_user2role.userid inner join ec_role on ec_role.roleid=ec_user2role.roleid where ec_role.parentrole like '%".$this->currentuser_parentrole."::%')) ";
			require_once('modules/Calendar/CalendarCommon.php');
			if($module == 'Calendar'){
				$shared_ids = getSharedCalendarId($session);
				if(isset($shared_ids) && $shared_ids != '') {			
					$sec_query = "( ".$sec_query." or $ec_crmentity.smownerid in($shared_ids) )";			
				}
				$sec_query = "( ".$sec_query." or ec_salesmanactivityrel.smid=$session)";
			}
			$sec_query = "(".$sec_query." or $crmid in (select crmid from ec_sharerecords where module='".$module."' and userid='".$session."')";
			//自定义共享
			$sec_query .= " or ({$ec_crmentity}.deleted = 0 and {$ec_crmentity}.smownerid in (select shared from ec_customsharings where tabid={$tabid} 
									and whoshared='".$session."' and sharingstype in (0,1,2,3))) ";
			$sec_query .= ")";
			//$sec_query .= " or ({$ec_crmentity}.deleted = 0 and {$ec_crmentity}.smownerid in (select shared from ec_customsharings where tabid={$tabid} and shared = '".$session."' and sharingstype in (0,1,2,3))) ";
		}elseif($viewscope == "current_user") {
			$sec_query .= " ($ec_crmentity.smownerid in($session)) ";
			if($module == 'Calendar')
			{
				$sec_query = "( ".$sec_query." or ec_salesmanactivityrel.smid=$session)";
			}
		} elseif($viewscope == "creator") {
			$sec_query .= " $ec_crmentity.smcreatorid in($session) ";
		}elseif($viewscope == "sub_user") {
			$sec_query .= " $ec_crmentity.smownerid in(select ec_user2role.userid from ec_user2role inner join ec_users on ec_users.id=ec_user2role.userid inner join ec_role on ec_role.roleid=ec_user2role.roleid where ec_role.parentrole like '%".$this->currentuser_parentrole."::%') ";
		}elseif($viewscope == "current_group") {
        //$sec_query .= " $ec_crmentity.smownerid in (0) ";
        //$sec_query .= "and ec_groups.groupid in".getCurrentUserGroupList()." ";
        
		} elseif($viewscope == "share_to_me") {
			if($module == 'Calendar'){
				require_once('modules/Calendar/CalendarCommon.php');
				$shared_ids = getSharedCalendarId($session);
				if(isset($shared_ids) && $shared_ids != '')
					$sec_query .= " $ec_crmentity.smownerid in($shared_ids)";						
			}
			if($sec_query != "") {
				$sec_query = "(".$sec_query." or $crmid in (select crmid from ec_sharerecords where module='".$module."' and userid='".$session."'))";
			} else {
				$sec_query = "($crmid in (select crmid from ec_sharerecords where module='".$module."' and userid='".$session."'))";
			}
		} elseif($viewscope == "share_of_me") {		
			$sec_query .= "($ec_crmentity.smownerid='".$session."' and $crmid in (select crmid from ec_sharerecords where module='".$module."'))";
		} else {
			global $is_showsubuserdata;
			if(empty($is_showsubuserdata) || !$is_showsubuserdata) {
				$sec_query .= " $ec_crmentity.smownerid=".$viewscope;			
			} else {
				//$sec_query .= getSpecUserSubUserQuery($viewscope);
				require('user_privileges/user_privileges_'.$viewscope.'.php');		
				require('user_privileges/sharing_privileges_'.$viewscope.'.php');
				if(!isset($current_user_parent_role_seq) || $current_user_parent_role_seq == "") {
					$current_user_parent_role_seq = fetchUserRole($viewscope);
				}
				$sec_query .= " ($ec_crmentity.smownerid=$viewscope or $ec_crmentity.smownerid in (select ec_user2role.userid from ec_user2role inner join ec_users on ec_users.id=ec_user2role.userid inner join ec_role on ec_role.roleid=ec_user2role.roleid where ec_role.parentrole like '%".$current_user_parent_role_seq."::%') ) ";
			}
			if($module == 'Calendar')
			{
				 $sec_query = "( ".$sec_query." or ec_salesmanactivityrel.smid=".$viewscope.")";    
			}
		}
		$log->info("Exiting getListViewSecurityParameter method ...");
		return $sec_query;
	}

	function getOptArr($opts){
		$optArr = array();
		$valArr = array_keys($opts);
		for($k=0;$k<count($valArr);$k++){
			$titArr = array_keys($opts[$valArr[$k]]);
			$optArr[] = array("value"=>$valArr[$k],"text"=>$titArr[0],"selected"=>$opts[$valArr[$k]][$titArr[0]]);
		}
		return $optArr;
	}

	function getBlocksByModule($module,$record) {
		global $site_URL;
		if($module == 'Calendar'){
			require_once("modules/{$module}/Activity.php");
			$focus = new Activity();
		}else{
			require_once("modules/{$module}/{$module}.php");
			$focus = new $module();
		}
		if(isset($record)) {
			$focus->retrieve_entity_info($record,$module);
			if($module == 'Announcements') {
				require_once('modules/Announcements/ModuleConfig.php');
  				$focus->id = $record;
  				$focus->name = $focus->column_fields['announcementname'];
   				$focus->column_fields['description'] = decode_html($focus->column_fields["description"]);
			}else{
				$focus->name = $focus->column_fields['subject'];		
			}
		}
		global $mod_strings,$app_strings;
		$mod_strings = return_module_language($current_language, $module);
		$theDetailBlocks = getBlocks($module,"detail_view",'',$focus->column_fields);
		$output_list = array();
		$count = count($focus->column_fields);
		if($count > 0){
			foreach($theDetailBlocks as $k=>$v){
				$blockObj = array();
				$blockObj['title'] = $k;
				$blockObj['field'] = array();
				$count1 = count($v);
				if($count1 > 0){
					for($i=0;$i<$count1;$i++) {
						foreach($v[$i] as $k1=>$v1){
							if($k1 != ''){
								$thev = '';
								$link = 0;
								$link_module = '';
								$link_modulename = '';
								$link_id = '';
								if($v1['link'] != ''){
									$linkArr = $this->getLinkModuleInfo($v1['link']);
									$link_module = $linkArr['module'];
									$link_modulename = $app_strings[$linkArr['module']];
									$link_id  = $linkArr['record'];
									if($link_id != ''){
										$link = 1;
									}
								}
								if($v1['value'] != NULL){
									$thev = $v1['value'];
								}
								if($k1 == '联系人照片'){
									$pattern="/<img.*?src=[\'|\"](.*?)[\'|\"].*?[\/]?>/"; 
									preg_match_all($pattern,$thev,$match); 
									//print_r($match);
									if(is_array($match) && isset($match[1][0]) &&$match[1][0] !='') {
						            //注意，上面的正则表达式说明src的值是放在数组的第三个中
						                $thev = $site_URL.$match[1][0];
						            }
								}
								$blockObj['field'][] = array('name'=>$k1,'value'=>$thev,'link'=>$link,'re_module'=>$link_module,'link_modulename'=>$link_modulename,'link_id'=>$link_id);
							}
						}
					}
				}
				$output_list[] = $blockObj;
			}
		}
		$other_list = array();
		$moduleArr = array('Quotes','Invoice','PurchaseOrder','SalesOrder','Deliverys','Tuihuos','Warehousetransfers');
		if(in_array($module,$moduleArr)){
			$products = array();
			$query="select ec_products.*,ec_inventoryproductrel.*,ec_products.productid as crmid,ec_catalog.catalogname,ec_vendor.vendorname from ec_inventoryproductrel inner join ec_products on ec_products.productid=ec_inventoryproductrel.productid left join ec_catalog on ec_catalog.catalogid=ec_products.catalogid left join ec_vendor on ec_vendor.vendorid=ec_products.vendor_id where ec_inventoryproductrel.id=".$record." ORDER BY sequence_no";
			global $adb;
			$result = $adb->query($query);
			$num_rows = $adb->num_rows($result);
			for($i=1;$i<=$num_rows;$i++){
				$productname=$adb->query_result($result,$i-1,'productname');
				$productcode=$adb->query_result($result,$i-1,'productcode');
				$serialno=$adb->query_result($result,$i-1,'serialno');
				$quantity=$adb->query_result($result,$i-1,'quantity');
				if($module == 'Deliverys'){
					$products[]=array("产品名称"=>$productname,"产品编码"=>$productcode,"型号"=>$serialno,"数量"=>$quantity);
				}else{
					$listprice=$adb->query_result($result,$i-1,'listprice');
					$comment=$adb->query_result($result,$i-1,'comment');
					$products[]=array("产品名称"=>$productname,"产品编码"=>$productcode,"型号"=>$serialno,"数量"=>$quantity,"价格"=>$listprice,"备注"=>$comment);	
				}
			}
			if(count($products)>0){
				$other_list['title'] = "产品详细信息";
				$other_list['field'] = $products;
			}
		}elseif($module == 'Expenses'){
			$expenses = array();
			$query = "select ec_expenselists.* from ec_expenselists 
				where expensesid = ".$record." ORDER BY sequence_no ";
			global $adb;
			$result = $adb->query($query);
			$num_rows=$adb->num_rows($result);
			for($i=1;$i<=$num_rows;$i++){
				$expensecategory = $adb->query_result($result,$i-1,'expensecategory');
				$expenselistname = $adb->query_result($result,$i-1,'expenselistname');
				$starttime = $adb->query_result($result,$i-1,'starttime');
				$lasttime = $adb->query_result($result,$i-1,'lasttime');
				$expensesite = $adb->query_result($result,$i-1,'expensesite');
				$expensetotal = $adb->query_result($result,$i-1,'expensetotal');
				$expensebill = $adb->query_result($result,$i-1,'expensebill');
				$descri = $adb->query_result($result,$i-1,'descri');
				$expenses[]=array("费用类别"=>$expensecategory,"费用用途"=>$expenselistname,"开始时间"=>$starttime,"结束时间"=>$lasttime,"地点"=>$expensesite,"金额"=>$expensetotal,"凭证"=>$expensebill,"备注"=>$descri);	
			}
			if(count($expenses)>0){
				$other_list['title'] = "费用明细";
				$other_list['field'] = $expenses;
			}
		}
		return array('entry_list'=>$output_list,'other_list'=>$other_list,"record"=>$record);
	}

	function getProductList($record) {
		$products = array();
		$query="select ec_products.*,ec_inventoryproductrel.*,ec_products.productid as crmid,ec_catalog.catalogname,ec_vendor.vendorname from ec_inventoryproductrel inner join ec_products on ec_products.productid=ec_inventoryproductrel.productid left join ec_catalog on ec_catalog.catalogid=ec_products.catalogid left join ec_vendor on ec_vendor.vendorid=ec_products.vendor_id where ec_inventoryproductrel.id=".$record." ORDER BY sequence_no";
		global $adb;
		$result = $adb->query($query);
		$num_rows = $adb->num_rows($result);
		for($i=1;$i<=$num_rows;$i++){
			$productid=$adb->query_result($result,$i-1,'crmid');
			$productname=$adb->query_result($result,$i-1,'productname');
			$productcode=$adb->query_result($result,$i-1,'productcode');
			$serialno=$adb->query_result($result,$i-1,'serialno');
			$quantity=$adb->query_result($result,$i-1,'quantity');
			$listprice=$adb->query_result($result,$i-1,'listprice');
			$products[]=array("productid"=>$productid,"productname"=>$productname,"productcode"=>$productcode,"serialno"=>$serialno,"quantity"=>$quantity,"unit_price"=>$listprice);	
		}
		return $products;
	}

	
	function getCreateBlocksByModule($module) {
		global $mod_strings,$app_strings;
		$mod_strings = return_module_language($current_language, $module);
		if($module == 'Calendar'){
			require_once("modules/{$module}/Activity.php");
			$focus = new Activity();
		}else{
			require_once("modules/{$module}/{$module}.php");
			$focus = new $module();
		}
		$theDetailBlocks = getBlocks($module,'create_view','',$focus->column_fields);
		//return $theDetailBlocks;
		//var_dump($focus->column_fields);die;
		$textBox = array('1','2','7','9','11','55','71','72','85','86','87','88','89','1004');
		$comboBox = array('15','16','53','111');
		$linkBox = array('13','50','51','57','58','59','69','73','75','76','78','80','81','1010');
		$textArea = array('19','20','21','22','24');
		$dateBox = array('5','6','23');
		$switchBox = array('56');
		$allBox = array_merge($textBox,$comboBox,$linkBox,$textArea,$dateBox,$switchBox);
		$linkoutput_list = array();
		$output_list = array();
		foreach($theDetailBlocks as $k=>$v){
			$fieldarr = array();
			for($i=0;$i<count($v);$i++){
				$theline = $v[$i];
				for($j=0;$j<count($theline);$j++){
					$thefield = $theline[$j];
					$nn = array();
					if(count($thefield)>3){
						$nn['value'] = $thefield[1][0];
						$nn['name'] = $thefield[2][0];
						$uitype = $thefield[0][0];
						$nn['mandatory'] = $thefield[0][2];
						if(!in_array($uitype,$allBox))continue;
						if(in_array($uitype,$comboBox)){
							$nn['fieldtype'] = "opts";
							$optArr = $this->getOptArr($thefield[3][0]);
							$output_list["0"][$nn['name']] = array("name"=>$nn['name'],"value"=>$optArr);
						}else if(in_array($uitype,$linkBox)){
							if($nn['name'] == 'account_id'){
								$nn['fieldtype'] = "accountid";
							}elseif($nn['name'] == 'contact_id'){
								$nn['fieldtype'] = "contactid";
							}elseif($nn['name'] == 'potential_id'){
								$nn['fieldtype'] = "potentialid";
							}elseif($nn['name'] == 'imagename'){
								$nn['fieldtype'] = "image";
							}else{
								continue;
							}
						}else if(in_array($uitype,$textArea)){
							$nn['fieldtype'] = "textarea";
						}else if(in_array($uitype,$dateBox)){
							$nn['fieldtype'] = "date";
						}else if(in_array($uitype,$switchBox)){
							$nn['fieldtype'] = "switch";
						}
						$fieldarr[] = $nn;
						$mm = array();
						if($module == 'Calendar' && $nn['value'] == "开始日期"){
							$mm['value'] = '开始时间';
							$mm['name'] = 'time_start';
							$mm['mandatory'] = 1;
							$mm['fieldtype'] = "time";
							$fieldarr[] = $mm;
						}
						if($module == 'Calendar' && $nn['value'] == "结束日期"){
							$mm['value'] = '结束时间';
							$mm['name'] = 'time_end';
							$mm['mandatory'] = 1;
							$mm['fieldtype'] = "time";
							$fieldarr[] = $mm;
						}
					}
				}
			}
			$linkoutput_list[] = array('title'=>$k,'field'=>$fieldarr);
		}
		//print_r($linkoutput_list);
		return array('entry_list'=>$output_list, 'relationship_list' => $linkoutput_list);
	}

	function getEditBlocksByModule($module,$record) {
		global $mod_strings,$app_strings;
		$mod_strings = return_module_language($current_language, $module);
		if($module == 'Calendar'){
			require_once("modules/{$module}/Activity.php");
			$focus = new Activity();
		}else{
			require_once("modules/{$module}/{$module}.php");
			$focus = new $module();
		}
		if(isset($record)) {
			$focus->retrieve_entity_info($record,$module);
			$focus->name = $focus->column_fields['subject'];		
		}
		$theDetailBlocks = getBlocks($module,'edit_view','',$focus->column_fields);
		$textBox = array('1','2','7','9','11','55','71','72','85','86','87','88','89','1004');
		$comboBox = array('15','16','53','111');
		$linkBox = array('13','50','51','57','58','59','69','73','75','76','78','80','81','1010');
		$textArea = array('19','20','21','22','24');
		$dateBox = array('5','6','23');
		$switchBox = array('56');
		$allBox = array_merge($textBox,$comboBox,$linkBox,$textArea,$dateBox,$switchBox);
		$linkoutput_list = array();
		$output_list = array();
		foreach($theDetailBlocks as $k=>$v){
			$fieldarr = array();
			for($i=0;$i<count($v);$i++){
				$theline = $v[$i];
				for($j=0;$j<count($theline);$j++){
					$thefield = $theline[$j];
					$nn = array();
					if(count($thefield)>3){
						$nn['value'] = $thefield[1][0];
						$nn['name'] = $thefield[2][0];
						$nn['fieldvalue'] = $thefield[3][0];
						$nn['fieldid'] = '';
						$uitype = $thefield[0][0];
						$nn['mandatory'] = $thefield[0][2];
						if(!in_array($uitype,$allBox))continue;
						if(in_array($uitype,$comboBox)){
							$nn['fieldtype'] = "opts";
							$optArr = $this->getOptArr($thefield[3][0]);
							$output_list["0"][$nn['name']] = array("name"=>$nn['name'],"value"=>$optArr);
						}else if(in_array($uitype,$linkBox)){
							if($nn['name'] == 'account_id'){
								$nn['fieldtype'] = "accountid";
								$nn['fieldid'] = $thefield[3][1];
							}elseif($nn['name'] == 'contact_id'){
								$nn['fieldtype'] = "contactid";
								$nn['fieldid'] = $thefield[3][1];
							}elseif($nn['name'] == 'potential_id'){
								$nn['fieldtype'] = "potentialid";
								$nn['fieldid'] = $thefield[3][1];
							}elseif($module == 'Contacts' && $nn['name'] == 'imagename'){
								$nn['fieldtype'] = "image";
								$nn['fieldsrc'] = "css/images/default.png";
								$nn['fieldvalue'] = "";
								global $adb;
								global $site_URL;
								$image_id = '';
								$sql = "select ec_attachments.* from ec_attachments inner join ec_seattachmentsrel on ec_seattachmentsrel.attachmentsid = ec_attachments.attachmentsid where (ec_attachments.type like '%image%' or ec_attachments.type like '%img%') and ec_seattachmentsrel.crmid='".$record."' order by ec_attachments.attachmentsid";
								$image_res = $adb->query($sql);
								$image_id = $adb->query_result($image_res,0,'attachmentsid');
								$imgpath = "getpic.php?mode=show&attachmentsid=".$image_id;
								if($image_id != ''){
									$nn['fieldsrc'] = $site_URL.$imgpath;
									$nn['fieldvalue'] = $adb->query_result($image_res,0,'name');
								}
							}else{
								continue;
							}
						}else if(in_array($uitype,$textArea)){
							$nn['fieldtype'] = "textarea";
						}else if(in_array($uitype,$dateBox)){
							$nn['fieldtype'] = "date";
							$titArr = array_keys($nn['fieldvalue']);
							$nn['fieldvalue'] = $titArr[0];
						}else if(in_array($uitype,$switchBox)){
							$nn['fieldtype'] = "switch";
						}
						$fieldarr[] = $nn;
						$mm = array();
						if($module == 'Calendar' && $nn['value'] == "开始日期"){
							$mm['value'] = '开始时间';
							$mm['name'] = 'time_start';
							$mm['mandatory'] = 1;
							$mm['fieldtype'] = "time";
							$mm['fieldvalue'] = $thefield[3][0][$nn['fieldvalue']];
							$fieldarr[] = $mm;
						}
						if($module == 'Calendar' && $nn['value'] == "结束日期"){
							$mm['value'] = '结束时间';
							$mm['name'] = 'time_end';
							$mm['mandatory'] = 1;
							$mm['fieldtype'] = "time";
							$mm['fieldvalue'] = $thefield[3][0][$nn['fieldvalue']];
							$fieldarr[] = $mm;
						}
					}
				}
			}
			$linkoutput_list[] = array('title'=>$k,'field'=>$fieldarr);
		}
		return array('entry_list'=>$output_list, 'relationship_list' => $linkoutput_list);
	}

	function getLinkModuleInfo($link) {
		$linkArr = array();
		$strArr = explode('?',$link);
		$selectfieldarr = explode("&",$strArr[1]);
		foreach($selectfieldarr as $val){
			if($val && !empty($val)){
				$col = explode("=",$val);
				$linkArr[$col[0]] = $col[1];
			}
		}
		return $linkArr;
	}

	function getSubordinateUsersNameList()
	{
		global $log;
		$log->debug("Entering getSubordinateUsersNameList() method ...");
		$key = "getsubordinateusersnamelist_".$this->userid;
		$user_array = getSqlCacheData($key);
		if(!$user_array) {   
			$user_array = Array();
			if(sizeof($this->subordinate_roles_users) > 0)
			{	
				foreach ($this->subordinate_roles_users as $roleid => $userArray)
				{
					foreach($userArray as $userid=>$username)
					{
						if(!in_array($userid,$user_array))
						{
							$user_array[$userid] = $username;//get_assigned_user_name($userid);
						}
					}
				}
			}
			setSqlCacheData($key,$user_array);
		}
		$log->debug("Exiting getSubordinateUsersNameList method ...");
		return $user_array;
	}
	
	function get_fanwei_list($session,$module,$selected="all_to_me") {
		global $app_strings;
		global $app_list_strings;
		$this->getParentroleAndIsadmin($session);
		$this->getPermissionOfModule($module,$session);
		if($module == "Funnels" || $module=='Salesobjects'){
			return $this->getUserIDFilterHTML2($module,$selected);
		}else{
			if(!$this->moduleAllFlag){
				$sub_userlist = $this->getSubordinateUsersNameList();
				$users_combo = get_select_options_with_id($sub_userlist, $selected);
			}
			else
			{
				$user_array = get_user_array(FALSE, "Active", $selected);
				$users_combo = get_select_options_with_id($user_array, $selected);
			}
			if("all_to_me" == $selected)
			{
				$shtml .= "<option selected value=\"all_to_me\">".$app_strings['LBL_ALL_TO_ME'].$app_list_strings['moduleList'][$module]."</option>";
			}else
			{
				$shtml .= "<option value=\"all_to_me\">".$app_strings['LBL_ALL_TO_ME'].$app_list_strings['moduleList'][$module]."</option>";
			}
			if("current_user" == $selected)
			{
				$shtml .= "<option selected value=\"current_user\">".$app_strings['LBL_CURRENT_USER'].$app_list_strings['moduleList'][$module]."</option>";
			}else
			{
				$shtml .= "<option value=\"current_user\">".$app_strings['LBL_CURRENT_USER'].$app_list_strings['moduleList'][$module]."</option>";
			}
			if("creator" == $selected)
			{
				$shtml .= "<option selected value=\"creator\">".$app_strings['LBL_CREATOR'].$app_list_strings['moduleList'][$module]."</option>";
			}else
			{
				$shtml .= "<option value=\"creator\">".$app_strings['LBL_CREATOR'].$app_list_strings['moduleList'][$module]."</option>";
			}

			if("sub_user" == $selected)
			{
				$shtml .= "<option selected value=\"sub_user\">".$app_strings['LBL_SUB_USER'].$app_list_strings['moduleList'][$module]."</option>";
			}else
			{
				$shtml .= "<option value=\"sub_user\">".$app_strings['LBL_SUB_USER'].$app_list_strings['moduleList'][$module]."</option>";
			}
			$shtml .= $users_combo ;
			if("share_to_me" == $selected)
			{
				$shtml .= "<option selected value=\"share_to_me\">".$app_strings['LBL_SHARE_TO_ME'].$app_list_strings['moduleList'][$module]."</option>";
			}else
			{
				$shtml .= "<option value=\"share_to_me\">".$app_strings['LBL_SHARE_TO_ME'].$app_list_strings['moduleList'][$module]."</option>";
			}
			if("share_of_me" == $selected)
			{
				$shtml .= "<option selected value=\"share_of_me\">".$app_strings['LBL_SHARE_OF_ME'].$app_list_strings['moduleList'][$module]."</option>";
			}else
			{
				$shtml .= "<option value=\"share_of_me\">".$app_strings['LBL_SHARE_OF_ME'].$app_list_strings['moduleList'][$module]."</option>";
			}
		}
		return $shtml;
	}

	function getUserIDFilterHTML2($module,$selected="all_to_me")
	{
		global $app_strings;
		global $app_list_strings;
		$shtml = "";
		
		if($this->isAdminFlag == false)
		{
			$users_combo = $this->getSubusergroupOpts($selected);
		}
		else
		{
			//$users_combo =getSubusergroupOpts($selected);
		   $users_combo = $this->getGroupArrOpts($selected);
		}
		if("all_to_me" == $selected)
		{
			$shtml .= "<option selected value=\"all_to_me\">".$app_strings['LBL_ALL_TO_ME'].$app_list_strings['moduleList'][$module]."</option>";
		}else
		{
			$shtml .= "<option value=\"all_to_me\">".$app_strings['LBL_ALL_TO_ME'].$app_list_strings['moduleList'][$module]."</option>";
		}
		if("sub_user" == $selected)
		{
			$shtml .= "<option selected value=\"sub_user\">".$app_strings['LBL_SUB_USER'].$app_list_strings['moduleList'][$module]."</option>";
		}else
		{
			$shtml .= "<option value=\"sub_user\">".$app_strings['LBL_SUB_USER'].$app_list_strings['moduleList'][$module]."</option>";
		}
		if("current_user" == $selected)
		{
			$shtml .= "<option selected value=\"current_user\">".$app_strings['LBL_CURRENT_USER'].$app_list_strings['moduleList'][$module]."</option>";
		}else
		{
			$shtml .= "<option value=\"current_user\">".$app_strings['LBL_CURRENT_USER'].$app_list_strings['moduleList'][$module]."</option>";
		}
		$shtml .= $users_combo ;
		
		return $shtml;
	}


	function getSubusergroupOpts($selected) {
		global $app_strings;
		global $app_list_strings;
		global $adb;
		$optstr="";
		$optsarr=array();

		$sql="select ec_groups.*,ec_users.id,ec_users.user_name from ec_groups inner join ec_users2group on ec_groups.groupid=ec_users2group.groupid inner join ec_users on ec_users.id=ec_users2group.userid inner join ec_user2role on ec_users.id=ec_user2role.userid inner join ec_role on ec_user2role.roleid=ec_role.roleid where ec_users.status='Active' and ec_users.deleted=0 and (ec_role.parentrole like '%".$this->currentuser_parentrole."::%' or ec_users.id={$this->userid})";
		$result=$adb->query($sql);
		while($row=$adb->fetch_array($result)){
			$groupid=$row['groupid'];
			$groupname=$row['groupname'];
			$userid=$row['id'];
			$username=$row['user_name'];
			if(!isset($optsarr[$groupid])){
				$optsarr[$groupid]=array('groupname'=>$groupname,'users'=>array());
				$optsarr[$groupid]['users'][]=array("G::$groupid","所有{$groupname}用户");
				$optsarr[$groupid]['users'][]=array("U::$userid",$username);
			}else{
				$optsarr[$groupid]['users'][]=array("U::$userid",$username);
			}
		}

		foreach($optsarr as $groupid=>$groupinf){
			$groupname=$groupinf['groupname'];
			$optstr.="<optgroup label='$groupname'>";
			foreach($groupinf['users'] as $userinf){
				$selectedval="";
				if($selected==$userinf[0]) $selectedval='selected';
				$optstr.="<option $selectedval value='{$userinf[0]}'>{$userinf[1]}</option>";
			}
			$optstr.="</optgroup>";
		}
		return $optstr;
	}

	function getGroupArrOpts($selected) {
		global $app_strings;
		global $app_list_strings;
		global $adb;
		$optstr="";
		$optsarr=array();

		$sql="select ec_groups.*,ec_users.id,ec_users.user_name from ec_groups inner join ec_users2group on ec_groups.groupid=ec_users2group.groupid inner join ec_users on ec_users.id=ec_users2group.userid where ec_users.status='Active' and ec_users.deleted=0 ";
		$result=$adb->query($sql);
		while($row=$adb->fetch_array($result)){
			$groupid=$row['groupid'];
			$groupname=$row['groupname'];
			$userid=$row['id'];
			$username=$row['user_name'];
			if(!isset($optsarr[$groupid])){
				$optsarr[$groupid]=array('groupname'=>$groupname,'users'=>array());
				$optsarr[$groupid]['users'][]=array("G::$groupid","所有{$groupname}用户");
				$optsarr[$groupid]['users'][]=array("U::$userid",$username);
			}else{
				$optsarr[$groupid]['users'][]=array("U::$userid",$username);
			}
		}

		foreach($optsarr as $groupid=>$groupinf){
			$groupname=$groupinf['groupname'];
			$optstr.="<optgroup label='$groupname'>";
			foreach($groupinf['users'] as $userinf){
				$selectedval="";
				if($selected==$userinf[0]) $selectedval='selected';
				$optstr.="<option $selectedval value='{$userinf[0]}'>{$userinf[1]}</option>";
			}
			$optstr.="</optgroup>";
		}
		return $optstr;
	}

	function getUserIDS2($session,$viewscope="all_to_me")
	{
		global $adb;
		global $log;
		$log->debug("Entering getUserIDS() method ...");    
		$sec_query = "";
		$userIDS = '';
		$this->getParentroleAndIsadmin($session);
	
		if($viewscope == "all_to_me") {
			if(!$this->isAdminFlag) {
				//sub_user
				$sec_query = "select ec_user2role.userid from ec_user2role inner join ec_users on ec_users.id=ec_user2role.userid inner join ec_role on ec_role.roleid=ec_user2role.roleid where ec_role.parentrole like '".$this->currentuser_parentrole."::%'";
				//current_group
				//$sec_query .= "(select ec_users2group.userid from ec_users2group where ec_users2group.groupid in ".getCurrentUserGroupList().")";
				//$log->info($sec_query);
			} else {
				//all_users
				$sec_query = "select id as userid from ec_users where status='Active'";
			}
			$result = $adb->query($sec_query);
			$userIDS .='(';
			$i=0;
			while($row = $adb->fetch_array($result)) {
				$userid = $row['userid'];
				if($i != 0)
				{
					$userIDS .= ', ';
				}
				$userIDS .= $userid;
				$i++;
			}
			if($userIDS != '(') {
				$userIDS .= ', '.$this->userid;
			} else {
				$userIDS .= $this->userid;
			}
			$userIDS .=')';
		} 
		elseif($viewscope == "sub_user") {
			$sec_query = "select ec_user2role.userid from ec_user2role inner join ec_users on ec_users.id=ec_user2role.userid inner join ec_role on ec_role.roleid=ec_user2role.roleid where ec_role.parentrole like '%".$this->currentuser_parentrole."::%'";
			$result = $adb->query($sec_query);
			$userIDS .='(';
			$i=0;
			while($row = $adb->fetch_array($result)) {
				$userid = $row['userid'];
				if($i != 0)
				{
					$userIDS .= ', ';
				}
				$userIDS .= $userid;
				$i++;
			}
			$userIDS .=')';
		} elseif($viewscope == "current_user") {		
				
				$userIDS .='('.$this->userid;
				
				$userIDS .=')';
		} elseif($viewscope == "current_group") {		
				$sec_query .= "select ec_users2group.userid from ec_users2group where ec_users2group.groupid= ".$this->groupid."";
				$result = $adb->query($sec_query);
				$userIDS .='(';
				$i=0;
				while($row = $adb->fetch_array($result)) {
					$userid = $row['userid'];
					if($i != 0)
					{
						$userIDS .= ', ';
					}
					$userIDS .= $userid;
					$i++;
				}
				$userIDS .=')';
			
		} else {
			if(strpos($viewscope,"U::")===0){
				$userIDS .= '('.str_replace("U::","",$viewscope).')';
			}elseif(strpos($viewscope,"G::")===0){
				$groupid=str_replace("G::","",$viewscope);
				if(!$this->isAdminFlag) {
					$sec_query .= "select ec_users2group.userid from ec_users2group inner join ec_users on ec_users.id=ec_users2group.userid inner join ec_user2role on ec_users.id=ec_user2role.userid inner join ec_role on ec_user2role.roleid=ec_role.roleid where ec_users2group.groupid = $groupid and (ec_role.parentrole like '".$this->currentuser_parentrole."::%' or ec_users.id={$this->userid})";
				}else{
					$sec_query .= "select ec_users2group.userid from ec_users2group inner join ec_users on ec_users.id=ec_users2group.userid inner join ec_user2role on ec_users.id=ec_user2role.userid inner join ec_role on ec_user2role.roleid=ec_role.roleid where ec_users2group.groupid = $groupid ";
				}
				$result = $adb->query($sec_query);
				$userIDS .='(';
				$i=0;
				while($row = $adb->fetch_array($result)) {
					$userid = $row['userid'];
					if($i != 0)
					{
						$userIDS .= ', ';
					}
					$userIDS .= $userid;
					$i++;
				}
				$userIDS .=')';
				//$userIDS .= '('.str_replace("U::","",$viewscope).')';
			}
			
		}
		
		$log->debug("Exiting getUserIDS method ...");
		return $userIDS;
	}

	/** Function to check if the currently logged in user is permitted to perform the specified action  
	  * @param $module -- Module Name:: Type varchar
	  * @param $actionname -- Action Name:: Type varchar
	  * @param $recordid -- Record Id:: Type integer
	  * @returns yes or no. If Yes means this action is allowed for the currently logged in user. If no means this action is not allowed for the currently logged in user 
	  *
	 */
	function isPermitted($module,$actionname,$record_id='')
	{
		global $log;
		$log->debug("Entering isPermitted() method ...");
		global $adb;
		if($actionname == 'Edit'){
			$actionname = 'EditView';
		}
		$permission = "no";
		if($this->isAdminFlag==true){
			$permission ="yes";
			$log->debug("Exiting isPermitted method ...");
			return $permission;
		}
		if($module == 'Home' || $module == 'uploads'){
			$permission = "yes";;
			$log->debug("Exiting isPermitted method ...");
			return $permission;
		}
		
		//Checking the Access for the Settings Module
		if($module == 'Settings' || $module == 'Users')
		{
			if($this->isAdminFlag==false)
			{
				$permission = "no";
			}else{
				$permission = "yes";
			}
			$log->debug("Exiting isPermitted method ...");
			return $permission;
		}

		//changed by dingjianting on 2008-07-17 for deyang lixiansheng 's bug about share when create invoice based on salesorder
		if($record_id != "") {
			$query = "select setype from ec_crmentity where crmid='".$record_id."'";
			$result = $adb->query($query);
			$rownum = $adb->num_rows($result);
			if($rownum > 0) {
				$setype = $adb->query_result($result,0,'setype');
				if($setype != $module) {
					$record_id = "";
				}
			}
		}
		//Retreiving the Tabid and Action Id
		$tabid = getTabid($module);
		$actionid=getActionid($actionname);
		//If no actionid, then allow action is ec_tab permission is available	
		if($actionid == '')
		{
			if($this->profileTabsPermission[$tabid] ==0)
			{	
					$permission = "yes";
					$log->debug("Exiting isPermitted method ...");
			}
			else
			{
					$permission ="no";
			}
			return $permission;
			
		}
		if($actionid == 0)
		{
			$permission ="yes";
			$log->debug("Exiting isPermitted method ...");
			return $permission;
		}
		//Checking for ec_tab permission
		if($this->profileTabsPermission[$tabid] !=0)
		{
			$permission = "no";
			$log->debug("Exiting isPermitted method ...");
			return $permission;
		}

		//Checking for view all permission
		if($this->profileGlobalPermission[1] ==0 || $this->profileGlobalPermission[2] ==0)
		{	
			if($actionid == 3 || $actionid == 4)
			{
				$permission = "yes";
				$log->debug("Exiting isPermitted method ...");
				return $permission;

			}
		}

		//Checking for edit all permission
		if($this->profileGlobalPermission[2] ==0)
		{	
			if($actionid == 3 || $actionid == 4 || $actionid ==0 || $actionid ==1)
			{
				$permission = "yes";
				$log->debug("Exiting isPermitted method ...");
				return $permission;

			}
		}
		
		if(strlen($this->profileActionPermission[$tabid][$actionid]) <  1 && $this->profileActionPermission[$tabid][$actionid] == '')
		{
			$permission = "yes";
			$log->debug("Exiting isPermitted method ...");
			return $permission;
		}	
		if($this->profileActionPermission[$tabid][$actionid] != 0 && $this->profileActionPermission[$tabid][$actionid] != '')
		{
			$permission = "no";
			$log->debug("Exiting isPermitted method ...");
			return $permission;
		}
		//Checking and returning true if recorid is null
		if($record_id == '')
		{
			$permission = "yes";
			$log->debug("Exiting isPermitted method ...");
			return $permission;
		}

		//If modules is Notes,Products,Vendors,Faq,PriceBook then no sharing			
		if($record_id != '')
		{
			//if($module == 'Notes' || $module == 'Products' || $module == 'Faq' || $module == 'Vendors'  || $module == 'PriceBooks')
			//notes module is the same with others module
			//changed by dingjianting on 2007-3-5 for permittion problem
			if($module == 'Products' || $module == 'Faq' || $module == 'PriceBooks')
			{
				$permission = "yes";
				$log->debug("Exiting isPermitted method ...");
				return $permission;			
			}
		}
		
		//Retreiving the RecordCreatorId
		$recCreatorId = getRecordCreatorId($module,$record_id);
		if($this->userid == $recCreatorId)
		{
			$permission = "yes";
			$log->debug("Exiting isPermitted method ...");
			return $permission;
		}
		
		//Retreiving the RecordOwnerId
		$recOwnId=getRecordOwnerId($module,$record_id);
		
		//Retreiving the default Organisation sharing Access	
		$others_permission_id = $this->defaultOrgSharingPermission[$tabid];

		//Checking if the Record Owner is the current User
		if($this->userid == $recOwnId)
		{
			$permission = "yes";
			$log->debug("Exiting isPermitted method ...");
			return $permission;
		}
		//Checking if the Record Owner is the Subordinate User
		foreach($this->subordinate_roles_users as $roleid=>$userids)
		{
			if(in_array($recOwnId,$userids))
			{
				$permission='yes';
				$log->debug("Exiting isPermitted method ...");
				return $permission;
			}
		}

		//changed by dingjianting on 2007-11-23 for sharerecords
		//$module,$tabid,$actionid,$record_id
		$query = "select crmid from ec_sharerecords where crmid='".$record_id."' and action='".$actionname."' and module='".$module."'";
		$result = $adb->query($query);
		$rownum = $adb->num_rows($result);
		if($rownum > 0) {
			$permission = "yes";
			$log->debug("Exiting isPermitted method ...");
			return $permission;
		}

		if($actionname == "DetailView") { 
			$query = "select * from ec_relationships where childmodule='".$module."'";
			$result = $adb->query($query);
			$rownum = $adb->num_rows($result);
			for($i=0;$i<$rownum;$i++) {
				$childtable = $adb->query_result($result,$i,'childtable');
				$childid = $adb->query_result($result,$i,'childid');
				$childparentid = $adb->query_result($result,$i,'childparentid');
				$parentmodule = $adb->query_result($result,$i,'parentmodule');
				$parentid = $adb->query_result($result,$i,'parentid');
				$parenttable = $adb->query_result($result,$i,'parenttable');
				$relationtype = $adb->query_result($result,$i,'relationtype');
				if($relationtype == "1") {
					$sharequery = "select crmid from ec_sharerecords where action='DetailView' and module='".$parentmodule."' and crmid in (select ".$childparentid." from ".$childtable." where ".$childid."=".$record_id.")";
					//echo "sharequery:".$sharequery."<br>";
					
				} elseif ($relationtype == "n") {
					$sharequery = "select crmid from ec_sharerecords where action='DetailView' and module='".$parentmodule."' and crmid in (select ec_moduleentityrel.crmid from ec_moduleentityrel where ec_moduleentityrel.relcrmid=".$record_id.")";
					//echo "sharequery:".$sharequery."<br>";
				} else {
					//$relationtype == "-1" when potential be shared , account in potential module , not related module can be shared too.
					$sharequery = "select crmid from ec_sharerecords where action='DetailView' and module='".$parentmodule."' and crmid in (select ".$parentid." from ".$parenttable." where ".$childparentid."=".$record_id.")";
					//echo "sharequery:".$sharequery."<br>";
				}
				$shareresult = $adb->query($sharequery);
				$sharerownum = $adb->num_rows($shareresult);
				if($sharerownum > 0) {
					$permission = "yes";
					$log->debug("Exiting isPermitted method ...");
					return $permission;
				}
			}
		}
		//共享过来的 数据
		$checked_b = $this->checkedSharingsInfo($module,$actionname,$record_id);
		if($checked_b == '1'){
			$permission = "yes";
			$log->debug("Exiting isPermitted method ...");
			return $permission;
		}
		
		//Checking for Default Org Sharing permission
		if($others_permission_id == 0)
		{
			if($actionid == 1 || $actionid == 0)
			{

				if($module == 'Calendar')
				{
					if($recOwnType == 'Users')
					{
						$permission = $this->isCalendarPermittedBySharing($record_id);
					}
					else
					{
						$permission='no'; 
					}		
				}
				else
				{
					$permission = isReadWritePermittedBySharing($module,$tabid,$actionid,$record_id);
				}		
				$log->debug("Exiting isPermitted method ...");
				return $permission;	
			}
			elseif($actionid == 2)
			{
				$permission = "no";
				$log->debug("Exiting isPermitted method ...");
				return $permission;
			}
			else
			{
				$permission = "yes";
				$log->debug("Exiting isPermitted method ...");
				return $permission;
			}
		}
		elseif($others_permission_id == 1)
		{
			if($actionid == 2)
			{
				$permission = "no";
				$log->debug("Exiting isPermitted method ...");
				return $permission;
			}
			else
			{
				$permission = "yes";
				$log->debug("Exiting isPermitted method ...");
				return $permission;
			}
		}
		elseif($others_permission_id == 2)
		{

			$permission = "yes";
			$log->debug("Exiting isPermitted method ...");
			return $permission;
		}
		elseif($others_permission_id == 3)
		{
			if($actionid == 3 || $actionid == 4)
			{
				if($module == 'Calendar')
				{

					if($recOwnType == 'Users')
					{
						$permission = $this->isCalendarPermittedBySharing($record_id);
					}
					else
					{
						$permission='no'; 
					}		
				}
				else
				{
					$permission = isReadPermittedBySharing($module,$tabid,$actionid,$record_id);
				}	
				$log->debug("Exiting isPermitted method ...");
				return $permission;	
			}
			elseif($actionid ==0 || $actionid ==1)
			{
				if($module == 'Calendar')
				{

					if($recOwnType == 'Users')
					{
						//changed by dingjianting on 2007-06-28 for chinese calendar
						//$permission = $this->isCalendarPermittedBySharing($record_id);
						$permission='no';
					}
					else
					{
						$permission='no'; 
					}		
				}
				else
				{
					$permission = isReadWritePermittedBySharing($module,$tabid,$actionid,$record_id);
				}	
				$log->debug("Exiting isPermitted method ...");
				return $permission;	
			}
			elseif($actionid ==2)
			{
					$permission ="no";
					return $permission;	
			}		
			else
			{
				$permission = "yes";
				$log->debug("Exiting isPermitted method ...");
				return $permission;
			}
		}
		else
		{
			$permission = "yes";	
		}

		$log->debug("Exiting isPermitted method ...");
		return $permission;
	}

	/**
	 * 判断当前用户有没有当前页面的访问权限
	 */
	function checkedSharingsInfo($module,$action,$record_id){
		global $adb;
		$tabid = getTabid($module);
		$entityArr = getEntityTable($module);
		$ec_crmentity = $entityArr["tablename"];
		$entityidfield = $entityArr["entityidfield"];
		$crmid = $ec_crmentity.".".$entityidfield;
		if($action == 'index'){
			$action = 'ListView';
		}
		$actionarr = array("ListView"=>"0,1,2,3","DetailView"=>"1,2,3","EditView"=>"2,3","Delete"=>"3");
		$query = "select {$crmid} from {$ec_crmentity} where 
					{$ec_crmentity}.deleted = 0 and {$ec_crmentity}.smownerid in (
						select shared from ec_customsharings where tabid = '{$tabid}' 
						and whoshared = '{$this->userid}' 
						and sharingstype in ({$actionarr[$action]}) 
					) ";
		$query .= "and $crmid = '{$record_id}' ";
		$result = $adb->query($query);
		$num_rows = $adb->num_rows($result);
		if($num_rows > 0){
			return '1';	
		}
		return '';
	}

	function isCalendarPermittedBySharing($recordId)
	{
		global $adb;
		$permission = 'no';
		$query = "select * from ec_sharedcalendar where userid in(select smownerid from ec_activity where activityid=".$recordId." and smownerid !=0) and sharedid=".$this->userid;
		$result=$adb->query($query);
		if($adb->num_rows($result) >0)
		{
			$permission = 'yes';
		} else {
			$query = "select * from ec_salesmanactivityrel where activityid=".$recordId." and smid=".$this->userid;
			$result_invite = $adb->query($query);
			if($adb->num_rows($result_invite) > 0){
				$permission = 'yes';
			}
		}
		return $permission;	
	}	

	/**
	 * 得到下拉框值
	 */
	function getPickListOpts($opts,$setype = 'array'){
		global $adb;
		$query = "select colvalue from ec_picklist where colname = '{$opts}' order by sequence ";
		$result = $adb->query($query);
		$num_rows = $adb->num_rows($result);
		if($num_rows && $num_rows > 0){
			$optsarr = array();
			while($row = $adb->fetch_array($result)){
				if($setype == 'array'){
					$optsarr[] = $row["colvalue"];
				}else if($setype == 'opts'){
					$optsarr .= "<option value='{$row['colvalue']}'>{$row['colvalue']}</option>";
				}
			}
		}
		if($optsarr && !empty($optsarr)){
			return $optsarr;
		}
		return "";
	}

	function __destruct() {

	}
}
?>