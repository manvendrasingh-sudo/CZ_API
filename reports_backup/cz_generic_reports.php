<?php

error_reporting(E_WARNING | E_PARSE | E_NOTICE);
session_start();
foreach (glob('reports_classes/*.php') as $filename) {
    include_once $filename;
}

$campArr  = array();
$skillArr = array();
$listArr  = array();
$agentArr = array();
$campaign_id;
$list_condition;
$skill_condition;
$magic_call=0;
$header_name = $_SERVER['HTTP_HOST'];
$param = array();
$param = file_get_contents('php://input');
$param = json_decode($param, true);
if (is_array($param) && count($param) > 0) {
    if (isset($param[0]) && count($param[0]) > 1) {
        $param = $param[0];
    }
} else {
    $param = []; // Set an empty array or default value
}
$camp_name = (isset($param['campaign_name']) ? $param['campaign_name'] : '');
$report_name = (isset($param['report_name']) ? $param['report_name'] : ''); // request value of report name
$department_name = (isset($param['department']) ? $param['department'] : ''); 
$json_config    =       file_get_contents("/var/www/html/apps/api_config.txt");
$config_data    =       json_decode($json_config,true);
$resFormat=3;
$conn      = DBconnection::mysqlConnection();

function prepare_log_report($event, $data) {
        $fp = fopen("/var/log/czentrix/tp_integration.txt", "a+");
        fwrite($fp, "\n--Report log- " . $event . ":". date("Y-m-d H:i:s"));
        fwrite($fp, "\n" . $data . "\n----- END -----\n\n");
        fclose($fp);
}

$returnArray = ["error" => 0];

if($_SERVER["HTTP_X_ACCESS_TOKEN"]){
    $token = base64_decode($_SERVER["HTTP_X_ACCESS_TOKEN"]);
	$token_validation = getToken($token);
	$now = new DateTimeImmutable();
	$json_token = json_decode($token_validation,true);
	$json_token["error"] = isset($json_token["error"])?$json_token["error"]:'';
	if($json_token["error"])
	{
		$returnArray = array('msg' => "Token Invalid",'error'=>"1","status_code"=>"401"); // JWT token changed
	}
	else if ($json_token["nbf"] > $now->getTimestamp() || $json_token["exp"] < $now->getTimestamp()){
		$returnArray = array('msg' => "Your token has been expired",'error'=>"1","status_code"=>"401"); // JWT token expired
    }
	else if (!empty($role_id) || !empty($user_id)){
		$returnArray = array('msg' => "Incorrect username and password",'error'=>"1","status_code"=>"401");
		
    }
    $role_id = $json_token["role_ID"];
	$user_id = $json_token["user_id"];
}elseif(empty($camp_name) && $returnArray['error']==0){

	$returnArray = array('msg' => "A token is required for authentication.",'error'=>"1","status_code"=>"401");
}
function getToken($token){
	if (!empty($token)) 
	{
		//print $token;
		require_once('/var/www/html/apps/jwt.php');
		$serverKey = "#czentrixapiC0nf!Gur@t!0n";
		// Get our server-side secret key from a secure location.
		try {
			$payload = JWT::decode($token, $serverKey, array('HS256'));
			$returnArray = json_decode($payload,true);
		}
		catch(Exception $e) {
			$returnArray = array('error' => $e->getMessage());
		}
	} 
	else {
		$returnArray = array('error' => 'Token is not valid.');
	}
	$jsonEncodedReturnArray = json_encode($returnArray, JSON_PRETTY_PRINT);
   //  print_r($returnArray);exit;
	return $jsonEncodedReturnArray;

}

$permissible_campaign='';
function check_validation($json_data_arr){
	GLOBAL $role_id,$user_id,$permissible_camps,$permissible_campaign,$camp_name,$config_data,$permissible_departments,$conn,$report_name,$department_name;
	
	$role_flag=0;
	$redis = new Redis(); 
	$redis->connect($config_data["REDIS_IP"],$config_data["REDIS_PORT"]); 	
	$rediskey= "CAMP#TVT#".$role_id."#TVT#".$user_id;	
 	$permissible_camps = $redis->get($rediskey);
	if ($report_name == "magic_call_report") {
	
		$countD=0;
		if (!$permissible_departments) {
				 $d1="SELECT admin.username,system_roles.permissible_camps,admin.user_id,permissible_departments FROM admin LEFT JOIN system_roles ON admin.role_ID=system_roles.role_id WHERE admin.role_ID=$role_id and admin.user_id=$user_id";
				$qryDep = mysqli_query($conn,$d1);

				if ($qry_res_user1 = mysqli_fetch_object($qryDep)) {
					
										$redis = new Redis();
										
										
										$redis->connect($config_data["REDIS_IP"],$config_data["REDIS_PORT"]);
										$rediskey= "CAMP#TVT#".$role_id."#TVT#".$user_id;
										$rediskeyDept= "DEPT#TVT#".$role_id."#TVT#".$user_id;
										$redis->set($rediskey, $qry_res_user1->permissible_camps);
										$redis->set($rediskeyDept, $qry_res_user1->permissible_departments);
										$permissible_departments = $qry_res_user1->permissible_departments;
								 		$permissible_camps = $qry_res_user1->permissible_camps;

				}

		}
		if ($permissible_departments) {
					//print 	$q4 ="select count(*) as countD from department where department_id in ($permissible_departments) and department_type='MAGIC' and department_name='".$department_name."'";
					 	$q4 ="select count(*) as countD from department where department_id in ($permissible_departments)  and department_name='".$department_name."'";
						$qry2 = mysqli_query($conn,$q4);
						$qry_res2 = mysqli_fetch_object($qry2);
						$countD = $qry_res2->countD;
						$role_flag =1;
						
		}
		
		if(!$countD and $user_id!=1){
			
				$returnArray = array('error' => '1',"msg"=>"Unauthorized Access","status_code"=>"401");
		}else{
			
				$returnArray = array('error' => '0',"msg"=>"Success","status_code"=>"201");
		}

	}else{
			if(empty($permissible_camps)) {
				$qry = mysqli_query($conn,"SELECT admin.username,system_roles.permissible_camps,admin.user_id FROM admin LEFT JOIN system_roles ON admin.role_ID=system_roles.role_id WHERE admin.role_ID=$role_id and admin.user_id=$user_id");
				if ($qry_res = mysqli_fetch_object($qry)) {
						$permissible_camps = $qry_res->permissible_camps;
						// $user_name = $qry_res->user_name;
				}
			}

			if(($permissible_camps || $user_id==1) && $camp_name){
				if($permissible_camps){
				$qry1 = mysqli_query($conn,"select campaign_name from campaign as a where a.campaign_id in ($permissible_camps) and campaign_name ='$camp_name'");
				}
				else if($user_id==1){
					$qry1 = mysqli_query($conn,"select campaign_name from campaign as a where campaign_name ='$camp_name'");
				}
				if (($qry_res1 = mysqli_fetch_object($qry1)) || $user_id==1) {
					$permissible_campaign = $qry_res1->campaign_name;
					$returnArray = array('error' => '0',"msg"=>"Varified User","status_code"=>"201");
				}else{
					prepare_log_report("Request campaign line 110","campaign not valid");
					$returnArray = array('error' => '1',"msg"=>"Unauthorized Access","status_code"=>"401");
				}
			}else{
				prepare_log_report("Request campaign line 113","campaign is blank");
				$returnArray = array('error' => '1',"msg"=>"Unauthorized Access","status_code"=>"401");
			}
}
	
	return json_encode($returnArray);
}
$json_data_arr["transaction_id"]= "Reports";

if($returnArray["error"]==0){
    $validation = check_validation($json_data_arr); // check permission of user
	if($validation){
		$json_validaion = json_decode($validation,true);
		if($json_validaion["error"]==1)
		{
		   http_response_code($json_validaion["status_code"]);
           print '{"response": {"transaction_id": "Report" , "status": "FAILURE","Message":"' . $json_validaion["msg"] . '"}}';
		   exit;
		}	
	}
}else{

	http_response_code($returnArray["status_code"]);
	print '{"response": {"transaction_id": "Report" , "status": "FAILURE","Message":"' . $returnArray["msg"] . '"}}';exit;
}


if($returnArray["error"]!=0){
    http_response_code($returnArray["status_code"]);
	print '{"response": {"transaction_id": "Report" , "status": "FAILURE","Message":"' . $returnArray["msg"] . '"}}';exit;
}

if ($report_name == "magic_call_report") {
	$magic_call=1;
    $table_name = "department";
    $id_column = "department_id";
    $name_column = "department_name";
	$camp_name = $department_name;
} else {
    $table_name = "campaign";
    $id_column = "campaign_id";
    $name_column = "campaign_name";
	$camp_name = $camp_name;
}

$campQuery = mysqli_query($conn, "SELECT $name_column, $id_column FROM $table_name");

$campArr = [];
while ($row = mysqli_fetch_object($campQuery)) {
    $campArr[$row->$id_column] = $row->$name_column;
}


$campaign_id     = array_search($camp_name, $campArr);
//if(empty($campaign_id)){ print "Campaign does not exists !! "; exit();}

$query_condition = "where campaign_id=$campaign_id" ;
$listQuery       = mysqli_query($conn, "select list_name,list_id from list $query_condition");
while ($row = mysqli_fetch_object($listQuery)) {
    $listArr[$row->list_id] = $row->list_name;
}

$skillQuery = mysqli_query($conn, "select skill_name,skill_id from skills $query_condition");
while ($row = mysqli_fetch_object($skillQuery)) {
    $skillArr[$row->skill_id] = $row->skill_name;
}

$agentQuery = mysqli_query($conn, "select agent_name,agent_id from agent $query_condition");
while ($row = mysqli_fetch_object($agentQuery)) {
    $agentArr[$row->agent_id] = $row->agent_name;
}

//$report_name="call_summary";
$current_date_check = (isset($param['current_month']) ? $param['current_month'] : '');

$_SESSION['campaign_name'] = $camp_name;
if($report_name == 'campaign_call_summary'){
$campaign_type                 = (isset($param['campaign_type']) ? $param['campaign_type'] : '');
}
else {
$campaign_type = '';
}

//$dialer_type = (isset($_REQUEST['dialer_type']) ?$_REQUEST['dialer_type'] : '');
$dialer_type = "" ;
$list_name   = (isset($param['list_name']) ? $param['list_name'] : '');
//$agent_name=(isset($_REQUEST['agents_name'])?$_REQUEST['agents_name']:'');
//$skills                = (isset($_REQUEST['skills_name']) ? $_REQUEST['skills_name'] : '');
$skills ="";
//$disposition           = (isset($_REQUEST['dispositions']) ? $_REQUEST['dispositions'] : '');
$disposition  = "";
//$cust_disposition = (isset($_REQUEST['customise_dispositions']) ? $_REQUEST['customise_dispositions'] : '');
$cust_disposition = "";
$startdate             = (isset($param['start_date']) ? $param['start_date'] : '');
$enddate               = (isset($param['end_date']) ? $param['end_date'] : '');

$datediff = ceil(abs(strtotime($enddate) - strtotime($startdate)) / 86400);

if( ($datediff > 4) || ($datediff < 0)){
 print "You are not allowed to get more than 5 days records !\n";
 die();
}

$date = date('Y-m');
$date_new = date('Y-m',strtotime($startdate));


$current_date =($date==$date_new)?true:false;



$starttime             = (isset($param['start_time']) ? $param['start_time'] : '');
//$starttime = "" ;
$endtime               = (isset($param['end_time']) ? $param['end_time'] : '');
//$endtime = "" ;
//$current_date          = "";
//$startduration         = (isset($_REQUEST['start_duration']) ? $_REQUEST['start_duration'] : '');
$startduration = "";
//$endduration           = (isset($_REQUEST['end_duration']) ? $_REQUEST['end_duration'] : '');
$endduration = "";
//$customer_phone        = (isset($_REQUEST['customer_phone']) ? $_REQUEST['customer_phone'] : '');
$customer_phone = "";
//$unique_call           = (isset($_REQUEST['unique_call']) ? $_REQUEST['unique_call'] : '');
$unique_call = "" ;
//$dwnl_reports_flag     = (isset($_REQUEST['dwnd_reports']) ? $_REQUEST['dwnd_reports'] : '');
$dwnl_reports_flag  = "";
//$report_name           = (isset($_REQUEST['report_name']) ? $_REQUEST['report_name'] : '');
//$agent_name            = (isset($_REQUEST['agent_name']) ? $_REQUEST['agent_name'] : '');
$agent_name ="";
//$list_name             = (isset($_REQUEST['list_name']) ? $_REQUEST['list_name'] : '');
$list_name = "";
//$customer_ph           = (isset($_REQUEST['customer_phone']) ? $_REQUEST['customer_phone'] : '');
$customer_ph = "";
//$unique_calls          = (isset($_REQUEST['unique_calls']) ? $_REQUEST['unique_calls'] : '');
$unique_calls = "";
//$agent_id         = ($agent_name != "") ? array_search($agent_name, $agentArr) : '';
$agent_id  = "";
$list_id          = ($list_name != "") ? array_search($list_name, $listArr) : '';
$report_user_name = "";

if ($report_name == "agent_call_summary") {
    $report_user_name = isset($param['user_name']) ? $param['user_name'] : 'adminrw';
}

if ($report_name == "") {
    print "Please Give me report name which you want !!";
} else {
    $table_name=($current_date)?date("Y_m"):"current_report";
	if ($report_name == "magic_call_report") {
		$table_name = "magic_call_" . $table_name;
	}
    $filerObj = new FilterClass();
    $config   = new Reportconfig($campaign_id,$table_name);
    $obj      = new ACD_customer();
    $report   = new Reportoutput();
    $mysqlObj = new DBconnection();

// set where value (finixeddddd)..

    $filerObj->setFilterCondition($current_date, $startdate, $enddate, $starttime, $endtime, $startduration, $endduration, $campaign_id, $agent_id, $list_id, $disposition, $campaign_type, $dialer_type, $cust_disposition, $customer_ph, $unique_calls,$magic_call);

    $report->setReportFields($obj, $config, $filerObj, $report_name, $current_date, $dwnl_reports_flag, $report_user_name,$table_name);

    // header('Content-type: application/json');
    if ($dwnl_reports_flag == '' || $dwnl_reports_flag == false) {
		$REPORT = "";
		if(preg_match('/There are no/', $REPORT)){
            print $report->jsonStringOutput();
        } else {
			header('Content-type: application/json');
			print "[]";
		}
    } else {
		header('Content-type: application/json');
        print "[]";
    }
}
?>