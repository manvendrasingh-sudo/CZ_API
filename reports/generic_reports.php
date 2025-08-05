<?php
error_reporting(E_WARNING | E_PARSE | E_NOTICE);
session_start();
$reqUid = explode('.', uniqid('', true))[1];

if(file_exists("/var/www/html/base_path.php")){
    include_once("/var/www/html/base_path.php");
} else {
    $base_path = "/var/log/czentrix/";
}


// Define constants for paths and configuration.
define('CONFIG_PATH', '/var/www/html/apps/api_config.txt');
define('LOG_FILE_PATH', $base_path."/tp_integration.txt");

// Include all the Required Files.
foreach (glob('reports_classes/*.php') as $filename) {
    include_once $filename;
}

// Include performance configuration for large dataset handling
// include_once('reports_classes/performance_config.php');

// Getting data from request Body:-
$param = array();
$param = file_get_contents('php://input');
$param = json_decode($param, true);
prepare_log_report("URI Received", $param);

// Check for the Valid Request Body
if (json_last_error() !== JSON_ERROR_NONE) {
    errorResponse("Bad Request: Invalid JSON format", 400);
}

// Check for the Config File Existance.
if (!file_exists(CONFIG_PATH)) {
    errorResponse("Some Error Occured, try again after some time.", 500, "Config File Missing from location: /var/www/html/apps/api_config.txt");
}

// Including the Config File.
$json_config = file_get_contents(CONFIG_PATH);
$config_data = json_decode($json_config, true);

// Log Configurations:-
$config_log_depth = isset($config_data['log_depth']) ? $config_data['log_depth'] : 0;
$maximum_days_rep = 5;

if (isset($config_data['max_allowed_days'])) {
    $val = $config_data['max_allowed_days'];

    if (is_string($val)) {
        prepare_log_report("Provided 'max_allowed_days' in the config is a string. Converting to integer", $val, 1);
        $val = (int) $val;
    }

    if ($val !== 0) {
        $maximum_days_rep = $val;
    } else {
        prepare_log_report("'max_allowed_days' in the config is either 0 or invalid. Falling back to default value:", $maximum_days_rep, 1);

    }
} else {
    prepare_log_report("'max_allowed_days' not provided in config. Using default value", $maximum_days_rep, 1);
}

// Check for the Valid JSON Data from Config file.
if (json_last_error() !== JSON_ERROR_NONE) {
    errorResponse("Some Error Occured, try again after some time.", 500, "JSON is NOT VALID in the Config File: /var/www/html/apps/api_config.txt");
}

// Initialize DB Connection
$conn = "";
// $conn = DBconnection::mysqlConnection($config_data['mysql_config']);
$db = new DBconnection($config_data['mysql_config']);
$conn = $db->mysqlConnection();
if(!$conn){
    prepare_log_report("MYSQL Config Received", json_encode($config_data['mysql_config']));
    prepare_log_report("MYSQL ERROR", $db->getError());
    errorResponse("Some Error Occured, try again after some time.", 500, "Mysql Connection Failed check the Mysql Config at location: '/var/www/html/apps/api_config.txt'");
}

/**This is File where we call our API directly so this Block will be commented.**/
/** ============= Token Validation Block Starts ============= **/

//$accessToken = isset($_SERVER["HTTP_X_ACCESS_TOKEN"]) ? $_SERVER["HTTP_X_ACCESS_TOKEN"] : null;

// Check if Token is Not Present.
//(empty($accessToken)) && errorResponse("A token is required for authentication.", 401, "Missing Token in Request.");

// Decode the Token.
//$decodedToken = json_decode(getToken(base64_decode($accessToken)), true);
//prepare_log_report("Decoded Token Value", $decodedToken, 1);

// Check for errors returned from getToken Func if any.
//(!empty($decodedToken["error"])) && errorResponse("Invalid Token!", 401, $decodedToken["error"]);

// Extract token Data.
//$role_id = isset($decodedToken["role_ID"]) ? $decodedToken["role_ID"] : null;
//$user_id = isset($decodedToken["user_id"]) ? $decodedToken["user_id"] : null;

// Validate token Data.
//(empty($role_id) || empty($user_id)) && errorResponse("Unauthorized Access", 401, "Invalid user or role in token.");

// Validate token expiration.
//$now = new DateTimeImmutable();
//($decodedToken["nbf"] > $now->getTimestamp() || $decodedToken["exp"] < $now->getTimestamp()) && errorResponse("Token Expired", 401);

// Function to Get/Check the Token.
// function getToken($token){
//     prepare_log_report(" getToken() is called with Token Value", $token, 1);
//     require_once('/var/www/html/apps/jwt.php');
//     $serverKey = "#czentrixapiC0nf!Gur@t!0n";
//     // Get our server-side secret key from a secure location.
//     try {
//         $payload = JWT::decode($token, $serverKey, array('HS256'));
//         $return = json_decode($payload, true);
//     } catch (Exception $e) {
//         $return = array('error' => $e->getMessage());
//     }
//     $jsonEncodedReturnArray = json_encode($return, JSON_PRETTY_PRINT);
//     return $jsonEncodedReturnArray;
// }
/** ============= Token Validation Block Ends ============= **/

// Getting All the Required Params.

/** ============ Validations Block For All the Params Start ============ **/
// Array To check the Valid Reports:-
$validReportsArr = [
    "agent_session_report",
    "abandon_calls",
    "agent_login_report",
    "campaign_call_summary",
    "agent_break_report",
    "agent_summary_report",
    "detail_call_report",
    "magic_call_report"
];

$sanitized_params = validateReqData($validReportsArr,$param);

$report_name      = $sanitized_params['report_name'];
$camp_name        = $sanitized_params['campaign_name'];
$department_name  = $sanitized_params['department'];
$startdate        = $sanitized_params['start_date'];
$enddate          = $sanitized_params['end_date'];
$starttime        = $sanitized_params['start_time'];
$endtime          = $sanitized_params['end_time'];
$list_name        = $sanitized_params['list_name'];
$campaign_type    = $sanitized_params['campaign_type'];
$report_user_name = $sanitized_params['user_name'];
$did              = $sanitized_params['did']; //GAURAV JAIN
$ivr_node_name    = $sanitized_params['ivr_node_name']; //GAURAV JAIN
$page             = $sanitized_params['page']; // Pagination support
$limit            = $sanitized_params['limit']; // Pagination support


// Function to check if the Provided Date is in correct Format.
function isDateFormatCorrect($date){
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
    list($year, $month, $day) = explode('-', $date);
    return checkdate((int)$month, (int)$day, (int)$year);
}

// Function to check if the Provided time is in Correct Format.
function isTimeFormatCorrect($time){
    if (!preg_match('/^(2[0-3]|[01][0-9]):[0-5][0-9]:[0-5][0-9]$/', $time)) return false;
    list($hours, $minutes, $seconds) = explode(':', $time);
    if (
        (int)$hours >= 0 && (int)$hours <= 23 &&
        (int)$minutes >= 0 && (int)$minutes <= 59 &&
        (int)$seconds >= 0 && (int)$seconds <= 59
    ) {
        return true;
    }
    return false;
}

function validateReqData($validReportsArr,$param){
    global $maximum_days_rep;

    $report_name   = $param['report_name']    ?? '';
    $campaign_name = $param['campaign_name']  ?? '';
    $department    = $param['department']     ?? '';
    $start_date    = $param['start_date']     ?? '';
    $end_date      = $param['end_date']       ?? '';
    $start_time    = $param['start_time']     ?? '';
    $end_time      = $param['end_time']       ?? '';
    $list_name     = $param['list_name']      ?? '';
    $campaign_type = $param['campaign_type']  ?? '';
    $user_name     = $param['user_name']      ?? '';
    $did           = $param['did']            ?? ''; //Gaurav jain
    $ivr_node_name = $param['ivr_node_name']  ?? ''; //Gaurav jain
    $page          = $param['page']           ?? 1;  // Pagination support
    $limit         = $param['limit']          ?? PerformanceConfig::DEFAULT_PAGE_SIZE; // Pagination support
    
    // Validate and sanitize pagination parameters
    list($page, $limit) = PerformanceConfig::validatePagination($page, $limit);

    // Validate report name.
    (empty($report_name) || !in_array($report_name, $validReportsArr, true)) && errorResponse("Invalid or Missing Report Name", 400);
    
    // For Magic Call Report Department Name is Mandatory.
    ($report_name === "magic_call_report" && empty($department)) && errorResponse("Department is Mandatory!", 400);
    
    // For All other Reports Campaign Name is Mandatory.
    ($report_name !== "magic_call_report" && empty($campaign_name)) && errorResponse("Campaign Name is Mandatory!", 400);

    /** Date Validation Block Start */
    // If start date is provided, End date is mandatory.
    (!empty($start_date) && empty($end_date)) && errorResponse("End Date is Mandatory", 400);

    // If End date is provided start date is mandatory.
    (!empty($end_date) && empty($start_date)) && errorResponse("Start Date is Mandatory", 400);

    // Date format check.
    if (!empty($start_date) && !empty($end_date)) {
        (!isDateFormatCorrect($start_date) || !isDateFormatCorrect($end_date)) && errorResponse("Invalid Start Date or End Date.", 400);
        (explode("-",$start_date)[1] != explode("-",$end_date)[1]) && errorResponse("Different Months are not Allowed.", 400);
        // Start Date cannot be less then the End Date.
        (strtotime($start_date) > strtotime($end_date)) && errorResponse("Start Date cannot be greater than the End Date.", 400);
        
        // Date range check
        $datediff = ceil(abs(strtotime($end_date) - strtotime($start_date)) / 86400);
        ($datediff > ($maximum_days_rep - 1) || $datediff < 0) && errorResponse("You are not allowed to get more than $maximum_days_rep days records!", 400);
    }
    /** Date Validation Block Ends */

    /** Time Validation Block Start  */
    // If Start time is provided, End time is mandatory.
    (!empty($start_time) && empty($end_time)) && errorResponse("End Time is Mandatory", 400);
    (!empty($end_time) && empty($start_time)) && errorResponse("Start Time is Mandatory", 400);

    // Time format check.
    if (!empty($start_time) && !empty($end_time)) {
        (!isTimeFormatCorrect($start_time) || !isTimeFormatCorrect($end_time)) && errorResponse("Invalid Start Time or End Time.", 400);
        ($start_time === $end_time) && errorResponse("Start Time and End Time cannot be set same.", 400);

        // End Time cannot be less then the Start Time.
        if (empty($start_date) && empty($end_date) && strtotime($start_time) > strtotime($end_time)) {
            errorResponse("End Time cannot be less than Start Time.", 400);
        }
    }
    /** Time Validation Block Ends  */

    // Validation of the Fields that will come based on the Report Names:-
    switch ($report_name) {
        case 'campaign_call_summary':
            (!empty($campaign_type) && (!in_array($campaign_type, ['INBOUND', 'OUTBOUND', 'ALL'], true))) && errorResponse("Provided Camapign Type is not Valid, Allowed Values: 'INBOUND' or 'OUTBOUND' or 'ALL'.", 400);
            if(empty($campaign_type)) $campaign_type = "ALL";
        break;

        case 'agent_call_summary':
            if(empty($user_name)) $user_name = "adminrw";
        break;

        case "ivr_detail_call_report":
            if ((array_key_exists('did', $param) && trim($param['did']) === '') || !in_array($report_name, $validReportsArr, true)) {
                return errorResponse("Invalid or Empty did", 400);
            }

            if ((array_key_exists('ivr_node_name', $param) && trim($param['ivr_node_name']) === '') || !in_array($report_name, $validReportsArr, true)) {
                return errorResponse("Invalid or Empty ivr_node_name", 400);
            }

            // if(isset($param['ivr_node_name']) && !empty($param['ivr_node_name'])){
            //     $is_valid_permissions = checkUserPermissions($conn, $redis, ["table_name"=>"ivr_node","search_val"=>"ivr_node_id","cond_col"=>"ivr_node_name","select_val"=>"permissible_ivr","redis_key"=>"IVR#TVT#{$role_id}#TVT#{$user_id}"],$role_id, $user_id,"", "",$ivr_node_name);
            //     prepare_log_report("Is Valid Permissions for IVR Node Name", $is_valid_permissions, 1);
            //     if ($is_valid_permissions['error']) {
            //         return errorResponse($is_valid_permissions['msg'], $is_valid_permissions['status_code']);
            //     }
            // }
        break;
        //did and iver node name  Gaurav jain edit end

        default:
            # code...
        break;
    }
    return compact (
        "report_name", "campaign_name", "department",
        "start_date", "end_date", "start_time", "end_time",
        "list_name", "campaign_type" , "user_name",
        "did", "ivr_node_name", "page", "limit"
    );
}
/** ============ Validations Block For All the Params Ends ============ **/

/** ============ Main Code Start Block ============ **/
// $permissible_campaign    = "";
// $permissible_departments = "";

// Checking the Validation and get the Permissionable Data (Not Applicable if the API Directly Calls)
// $is_valid_permissions = check_validation($role_id, $user_id, $report_name, $camp_name, $department_name, $config_data, $conn);
// prepare_log_report("Is Valid Permissions", $is_valid_permissions, 1);
// if ($is_valid_permissions['error']) {
//     errorResponse($is_valid_permissions['msg'], $is_valid_permissions['status_code']);
// }

// if ($report_name === "magic_call_report") {
//     $permissible_departments = $is_valid_permissions['data'];
// } else {
//     $permissible_campaign = $is_valid_permissions['data'];
// }

// Initialize all the Variables
$magic_call       = 0;
$ivr_report       = 0;
$table_name       = null;
$startduration    = "";
$endduration      = "";
$id               = null;
$list_name        = "";
$agent_id         = "";
$disposition      = "";
$dialer_type      = "";
$cust_disposition = "";
$customer_ph      = "";
$unique_calls     = "";

$dwnl_reports_flag  = "";

$dataArr       = [];
$listArr       = [];
$skillArr      = [];
$agentArr      = [];

$resFormat     = 3;
$tableDetails = getTableDetails($report_name, $camp_name, $department_name);
prepare_log_report("Table Details", $tableDetails, 1);
// Fetch campaign or department data

$dataArr = fetchData($conn, $tableDetails['table_name'], $tableDetails['id_column'], $tableDetails['name_column']);
prepare_log_report("Data Array", $dataArr, 1);

// $id = array_search($value_name, $dataArr);
$id = array_search($tableDetails['value_name'], $dataArr);
if ($report_name !== "magic_call_report") {
    $listArr = fetchData($conn, 'list', 'list_id', 'list_name', "WHERE campaign_id=$id");
    $list_id = $list_name ? array_search($list_name, $listArr) : '';

    // $skillQuery = mysqli_query($conn, "select skill_name,skill_id from skills $query_condition");
    // while ($row = mysqli_fetch_object($skillQuery)) {
    //     $skillArr[$row->skill_id] = $row->skill_name;
    // }

    // $agentQuery = mysqli_query($conn, "select agent_name,agent_id from agent $query_condition");
    // while ($row = mysqli_fetch_object($agentQuery)) {
    //     $agentArr[$row->agent_id] = $row->agent_name;
    // }
}

// Determine table name for the report based on date
// Set the Table Name if some Date is Provided.
if (empty($startdate) && empty($enddate)) {
    $table_name = "current_report";
} else {
    $date = date('Y_m');
    $date_new = date('Y_m', trim(strtotime($startdate)));
    $current_date = ($date === $date_new) ? true : false;
    $table_name = ($current_date) ? $date : $date_new;
}

if($report_name == "magic_call_report"){
    $magic_call = "1";
} else if($report_name == "ivr_detail_call_report") {
    $ivr_report = "1";
}

// Final Table Name
$table_name = ($report_name === "magic_call_report" ? "magic_call_" . $table_name : $table_name);
$table_name = ($ivr_report  ==  "1" ? "ivr_report_" . $table_name : $table_name); //Gaurav jain edit
$host = $_SERVER['HTTP_HOST'];
$baseUrl = "https://" . $host;

$filterObj  = new FilterClass();
$config     = new Reportconfig($id, $table_name, $baseUrl, $db);
$obj        = new ACD_customer();
$report     = new Reportoutput($db);

$timeZonVal = campaignTimeZone($conn,$camp_name);
$filterObj->setTimeZoneVariables($timeZonVal);

// Initialize permissible_ivr variable for IVR reports
$permissible_ivr = "";
if($ivr_report == "1") {
    $permissible_ivr = ""; // For generic reports, we don't need IVR permissions
}
$filterObj->setFilterCondition($current_date, $startdate, $enddate, $starttime, $endtime, $startduration, $endduration, $id, $agent_id, $list_id, $disposition, $campaign_type, $dialer_type, $cust_disposition, $customer_ph, $unique_calls, $magic_call, $ivr_node_name, $did, $ivr_report, $permissible_ivr);

$REPORT = $report->setReportFields($obj, $config, $filterObj, $report_name, $current_date, $dwnl_reports_flag, $report_user_name, $table_name, $page, $limit);

if ($dwnl_reports_flag == '' || $dwnl_reports_flag == false) {
    prepare_log_report("Response Returned SUCCESS", "Report Data Sent to the Client.");
    header('Content-Type: application/json');
    print json_encode($REPORT, JSON_PRETTY_PRINT);
    //$REPORT = "";
    //if (preg_match('/There are no/', $REPORT)) {
    //print $report->jsonStringOutput();
    //}
} else {
    prepare_log_report("Response Returned SUCCESS", "Report Data Sent to the Client.");
    header('Content-type: application/json');
    print "[]";
}

// DBconnection::mysqlClose();
$db->mysqlClose();

function campaignTimeZone($conn,$campaign_name){
    $defTZ = "0";
    if ($campaign_name != "" && $campaign_name != "ALL") {
            $resTimeZone1 = mysqli_fetch_object(mysqli_query($conn, "select agentui_timezone from campaign where campaign_name='$campaign_name'"));
            $resTimeZone2 = $resTimeZone1->agentui_timezone;
            $resTimeZone3 = mysqli_fetch_object(mysqli_query($conn, "select gmtdiff from gmttimediff where timezone='$resTimeZone2'"));
            $resTimeZone = $resTimeZone3->gmtdiff;
            $resTimeZone = substr_replace($resTimeZone, ":", 3, 0);
    } else {
            if ($_REQUEST['timezone'] == "None") {
                    $resTimeZone = "+05:30";
                    $defTZ = "1";
            } else {
                    $timezon = isset($_REQUEST['timezone']) ? $_REQUEST['timezone'] : "+05:30";
                    $timezone = new DateTimeZone($timezon);
                    $offsetSeconds = $timezone->getOffset(new DateTime());
                    $offsetHours = floor($offsetSeconds / 3600);
                    $offsetMinutes = floor(($offsetSeconds % 3600) / 60);
                    $resTimeZone = sprintf('%+03d:%02d', $offsetHours, $offsetMinutes);
                    $defTZ = "0";
            }
    }
    return $resTimeZone . "," . $defTZ;
}

// function check_validation($role_id, $user_id, $report_name, $camp_name, $department_name, $config_data, $conn) {
//     prepare_log_report("check_validation() is called with Params", "Role Id: $role_id, User Id: $user_id, Report Name: $report_name, Department Name: $department_name", 1);
//     $settings = [
//         "default" => [
//             "table_name" => "campaign",
//             "select_val" => "permissible_camps",
//             "search_val" => "campaign_id",
//             "cond_col"   => "campaign_name",
//             "redis_key"  => "CAMP#TVT#{$role_id}#TVT#{$user_id}",
//         ],
//         "magic_call_report" => [
//             "table_name" => "department",
//             "select_val" => "permissible_departments",
//             "search_val" => "department_id",
//             "cond_col"   => "department_name",
//             "redis_key"  => "DEPT#TVT#{$role_id}#TVT#{$user_id}",
//         ],
//     ];
//     $config = $settings[$report_name] ? $settings[$report_name] : $settings["default"];
//     $redis = initRedis($config_data);
//     // Check for admin user
//     if ($user_id == 1) {
//         $condition_value = $report_name === "magic_call_report" ? $department_name : $camp_name;
//         return checkAdminPermissions($conn, $config['table_name'], $config['cond_col'], $condition_value);
//     }
//     return checkUserPermissions($conn, $redis, $config, $role_id, $user_id, $camp_name, $department_name);
// }

function getTableDetails($report_name, $camp_name, $department_name) {
    return $report_name === "magic_call_report"
        ? ["magic_call" => 1, "table_name" => "department", "id_column" => "department_id", "name_column" => "department_name", "value_name" => $department_name]
        : ["magic_call" => 0, "table_name" => "campaign", "id_column" => "campaign_id", "name_column" => "campaign_name", "value_name" => $camp_name];
}


function fetchData($conn, $table, $idColumn, $nameColumn, $condition = "") {
    prepare_log_report("fetchData(), Params", "Table: $table, Id Column: $idColumn, Name Column: $nameColumn, Condition: $condition", 1);
    $result = [];
    
    prepare_log_report("fetchData(), Query", "SELECT $idColumn, $nameColumn FROM $table $condition", 2);
    $query = $conn->query("SELECT $idColumn, $nameColumn FROM $table $condition");
    while ($row = $query->fetch_object()) {
        $result[$row->$idColumn] = $row->$nameColumn;
    }
    prepare_log_report("fetchData(), Query Result", $result, 2);
    return $result;
}

// function getUserPermissionsFromDB($conn, $config, $role_id, $user_id) {
//     prepare_log_report("getUserPermissionsFromDB() is Called with Params","Role Id: $role_id, User Id: $user_id", 1);
//     prepare_log_report("getUserPermissionsFromDB() is Called with Config", $config, 1);
    
//     prepare_log_report("getUserPermissionsFromDB() Query Executed","SELECT system_roles.{$config['select_val']} FROM admin LEFT JOIN system_roles ON admin.role_ID = system_roles.role_id WHERE admin.role_ID = $role_id AND admin.user_id = $user_id", 2);

//     $result = mysqli_fetch_object(mysqli_query($conn, "SELECT system_roles.{$config['select_val']} FROM admin LEFT JOIN system_roles ON admin.role_ID = system_roles.role_id WHERE admin.role_ID = $role_id AND admin.user_id = $user_id"));
//     return $result ? $result->select_val : null;
// }

// function checkUserPermissions($conn, $redis, $config, $role_id, $user_id, $camp_name, $department_name) {
//     prepare_log_report("checkUserPermissions() is Called with Params","Role Id: $role_id, User Id: $user_id, Camp Name: $camp_name, Department Name: $department_name", 1);
//     prepare_log_report("checkUserPermissions() is Called with Config", $config, 1);

//     $condition_value = $config['table_name'] === "department" ? $department_name : $camp_name;
//     $req_permissions = $redis->get($config['redis_key']) ?: getUserPermissionsFromDB($conn, $config, $role_id, $user_id);
//     prepare_log_report("checkUserPermissions(), User ".$config['table_name']." Permissions",$req_permissions, 1);
//     if ($req_permissions) {
//         // If Permissions Found Set them in Redis.
//         if ($req_permissions) {
//             $redis->set($config['redis_key'], $req_permissions);
//         }
//         prepare_log_report("checkUserPermissions() Query Exexuted", "SELECT COUNT(*) as isPermited FROM {$config['table_name']} WHERE {$config['search_val']} IN ($req_permissions) AND {$config['cond_col']} = '$condition_value'", 2);
        
//         $result = mysqli_fetch_object(mysqli_query($conn,"SELECT COUNT(*) as isPermited FROM {$config['table_name']} WHERE {$config['search_val']} IN ($req_permissions) AND {$config['cond_col']} = '$condition_value'"));
//         prepare_log_report("checkUserPermissions() Query Result", $result, 2);
//         return $result->isPermited
//             ? ['error' => false, 'msg' => 'Verified User', 'status_code' => 201, 'data' => $req_permissions]
//             : ['error' => true, 'msg' => 'Unauthorized Access', 'status_code' => 401];
//     }
//     return ['error' => true, 'msg' => 'Unauthorized Access', 'status_code' => 401];
// }

// function checkAdminPermissions($conn, $table, $column, $value)
// {
//     prepare_log_report("checkAdminPermissions() is called with Params", "Table: $table, Column: $column, Value: $value", 1);
//     $result = mysqli_fetch_object(mysqli_query($conn,"SELECT COUNT(*) as countD FROM $table WHERE $column = '$value'"));
//     prepare_log_report("checkAdminPermissions() Query Executed", "SELECT COUNT(*) as countD FROM $table WHERE $column = '$value'", 2);
//     prepare_log_report("checkAdminPermissions() Query Result", $result, 2);

//     return $result->countD
//         ? ['error' => false, 'msg' => 'Verified User', 'status_code' => 201]
//         : ['error' => true, 'msg' => 'Invalid Campaign or Department', 'status_code' => 401];
// }

// function initRedis($config_data) {
//     try {
//         $redis = new Redis();
//         $redis->connect($config_data['REDIS_IP'], $config_data['REDIS_PORT']);
//         if (!$redis->ping()) {
//             throw new RedisException("Redis Connection Failed at".$config_data['REDIS_IP']." and ".$config_data['REDIS_PORT']);
//         }
//         return $redis;
//     } catch (RedisException $e) {
//         errorResponse("Some Error Occured, Please Try again Later", 500, "Redis Connection Failed at IP: ".$config_data['REDIS_IP']." and PORT: ".$config_data['REDIS_PORT']);
//     } catch (Exception $e) {
//         errorResponse("Redis Connection Failed", 500);
//     }
// }
/** ============  Main Code End Block  ============ **/


/** Response Handling Functions Block Start **/
function errorResponse($msg, $statusCode, $logMsg = null) {
    $finalMsg = empty($logMsg) ? $msg : $logMsg;
    prepare_log_report("Failure Reason", $finalMsg);
    $response = [
        "msg" => $msg,
        "error" => 1,
        "status_code" => $statusCode
    ];
    sendResponse($response);
    exit;
}

function sendResponse($response) {
    http_response_code($response["status_code"]);
    $status = ($response["error"] === 1) ? "FAILURE" : "SUCCESS";
    $resStr = json_encode(
        [
            "response" => [
                "transaction_id" => "Report",
                "status" => $status,
                "Message" => $response["msg"]
            ]
        ],
        JSON_UNESCAPED_UNICODE
    );

    prepare_log_report("Response Returned", $resStr);
    header('Content-Type: application/json');
    echo $resStr;
}
/**  Response Handling Functions Block Ends  **/

/** Log Handling Functions Block Starts **/
function prepare_log_report($event, $data, $local_log_depth = 0) {
    global $config_log_depth,$reqUid;

    // 1. **General Log** (Always logs)
    if($local_log_depth == 0 && ($config_log_depth == 0 || $config_log_depth == 1 || $config_log_depth == 2 || $config_log_depth == 3)){
        $logData = date("Y-m-d H:i:s") . " [DirectAPI] [$reqUid] :: [$event] :: " . (is_array($data) || is_object($data) ? json_encode($data, true) : $data) . "\n\n";
        file_put_contents(LOG_FILE_PATH, $logData, FILE_APPEND);
    }

    // 2. **Detail Log** (Logs if log depth is 1 or 3)
    if ($local_log_depth == 1 && ($config_log_depth == 1 || $config_log_depth == 3)) {
        $logData = date("Y-m-d H:i:s") . " [DirectAPI] [$reqUid] :: [$event] :: " . (is_array($data) || is_object($data) ? json_encode($data, true) : $data) . "\n\n";
        file_put_contents(LOG_FILE_PATH, $logData, FILE_APPEND);
    }

    // 3. **Query Log** (Logs if log depth is 2 or 3)
    if ($local_log_depth == 2 && ($config_log_depth == 2 || $config_log_depth == 3)) {
        $logData = date("Y-m-d H:i:s") . " [DirectAPI] [$reqUid] :: [$event] :: " . (is_array($data) || is_object($data) ? json_encode($data, true) : $data) . "\n\n";
        file_put_contents(LOG_FILE_PATH, $logData, FILE_APPEND);
    }
}
/** Log Handling Functions Block Ends **/
?>
