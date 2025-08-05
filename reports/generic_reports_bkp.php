<?php
// for include all directories files........
//ini_set('display_errors', 1);
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
$date = date('Y-m-d');
$camp_name = (isset($_REQUEST['campaign_name']) ? $_REQUEST['campaign_name'] : '');
$conn      = DBconnection::mysqlConnection();

$campQuery = mysqli_query($conn, "select campaign_name,campaign_id from campaign");
while ($row = mysqli_fetch_object($campQuery)) {
    $campArr[$row->campaign_id] = $row->campaign_name;
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

$report_name = (isset($_REQUEST['report_name']) ? $_REQUEST['report_name'] : ''); // request value of report name
//$report_name="call_summary";
$current_date_check = (isset($_REQUEST['current_month']) ? $_REQUEST['current_month'] : '');

$_SESSION['campaign_name'] = $camp_name;
if($report_name == 'campaign_call_summary'){
$campaign_type                 = (isset($_REQUEST['campaign_type']) ? $_REQUEST['campaign_type'] : '');
}
else {
$campaign_type = '';
}

//$dialer_type = (isset($_REQUEST['dialer_type']) ?$_REQUEST['dialer_type'] : '');
$dialer_type = "" ;
$list_name   = (isset($_REQUEST['list_name']) ? $_REQUEST['list_name'] : '');
//$agent_name=(isset($_REQUEST['agents_name'])?$_REQUEST['agents_name']:'');
//$skills                = (isset($_REQUEST['skills_name']) ? $_REQUEST['skills_name'] : '');
$skills ="";
//$disposition           = (isset($_REQUEST['dispositions']) ? $_REQUEST['dispositions'] : '');
$disposition  = "";
//$cust_disposition = (isset($_REQUEST['customise_dispositions']) ? $_REQUEST['customise_dispositions'] : '');
$cust_disposition = "";
$startdate             = (isset($_REQUEST['start_date']) &&  !preg_match("/undefined/i",$_REQUEST['start_date'])? $_REQUEST['start_date']: $date);
$enddate               = (isset($_REQUEST['end_date'])  &&  !preg_match("/undefined/i",$_REQUEST['end_date'])? $_REQUEST['end_date']: $date);
$datediff = ceil(strtotime($enddate) - strtotime($startdate)) / 86400;

if( ($datediff > 4) || ($datediff < 0)){
 print '[{"error":"You are not allowed to get more than 5 days records !\n"}]';
 die();
}


$start_date_new = date('Y-m-d',strtotime($startdate));
$end_date_new = date('Y-m-d',strtotime($enddate));
$current_date =((($date==$date_new && $datediff < 2) || ($date==$end_date_new && $datediff < 2))?true:false);

//$starttime             = (isset($_REQUEST['start_time']) ? $_REQUEST['start_time'] : '');
$starttime = "" ;
//$endtime               = (isset($_REQUEST['end_time']) ? $_REQUEST['end_time'] : '');
$endtime = "" ;
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
    $report_user_name = isset($_REQUEST['user_name']) ? $_REQUEST['user_name'] : 'adminrw';
}

if ($report_name == "") {
    print "Please Give me report name which you want !!";
} else {
    $table_name=($current_date)?"current_report":date("Y_m",strtotime($startdate));

    $filerObj = new FilterClass();
    $config   = new Reportconfig($campaign_id,$table_name);
    $obj      = new ACD_customer();
    $report   = new Reportoutput();
    $mysqlObj = new DBconnection();

// set where value (finixeddddd)..

    $filerObj->setFilterCondition($current_date, $startdate, $enddate, $starttime, $endtime, $startduration, $endduration, $campaign_id, $agent_id, $list_id, $disposition, $campaign_type, $dialer_type, $cust_disposition, $customer_ph, $unique_calls);

    $report->setReportFields($obj, $config, $filerObj, $report_name, $current_date, $dwnl_reports_flag, $report_user_name,$table_name);

    if ($dwnl_reports_flag == '' || $dwnl_reports_flag == false) {
        if(preg_match('/There are no/', $REPORT)){
            print $report->jsonStringOutput();
        }
    } else {
        print "";
    }
}