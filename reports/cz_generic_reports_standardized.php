<?php
/**
 * Standardized Generic Reports API
 * Handles report generation with proper error handling and structure
 */

// Start session
session_start();

// Generate unique request ID
$requestId = uniqid('req_', true);

define('CONFIG_PATH', '/var/www/html/apps/api_config.txt');
if (!file_exists(CONFIG_PATH)) {
    errorResponse("Some Error Occured, try again after some time.", 500, "Config File Missing from location: /var/www/html/apps/api_config.txt");
}
$json_config = file_get_contents(CONFIG_PATH);
$config_data = json_decode($json_config, true);


try {
    // Include required files
    require_once 'reports_classes/AppConfig.php';
    require_once 'reports_classes/RequestHandler.php';
    require_once 'reports_classes/ResponseHandler.php';
    
    // Include helper where check_validation is defined
    // require_once 'reports_classes/PermissionHelper.php'; // <-- Add this line if check_validation is in this file
    
    // Include all report classes
    foreach (glob('reports_classes/*.php') as $filename) {
        if (!strpos($filename, 'AppConfig.php') && !strpos($filename, 'RequestHandler.php') && !strpos($filename, 'ResponseHandler.php')) {
            include_once $filename;
        }
    }
    
    // Initialize handlers
    $config = AppConfig::getInstance();
    $requestHandler = new RequestHandler();
    $responseHandler = new ResponseHandler();
    
    // Process request
    $params = $requestHandler->processRequest();
    
    // Initialize database connection
    $db = new DBconnection($config->getMySQLConfig());
    $conn = $db->mysqlConnection();
    
    if (!$conn) {
        // Log error and throw exception
        error_log("Database connection failed: " . $db->getError());
        throw new Exception("Database connection failed: " . $db->getError());
    }
    
    // Initialize Redis connection
    $redis = "";
    $redis = initRedis($config_data);
    // Validate token and get user info
    $userInfo = validateTokenAndGetUserInfo($conn);
    // Check user permissions
    $permissions = checkUserPermissions($conn, $redis, $config, $userInfo, $params);
    // Generate report
    $report = generateReport($conn, $params, $userInfo, $permissions);
    
    // Send response
    if (isset($report['pagination'])) {
        $responseHandler->sendPaginatedResponse($report['data'], $report['pagination'], 'Report generated successfully');
    } else {
        $responseHandler->sendSuccess($report, 'Report generated successfully');
    }
    
} catch (Exception $e) {
    // Log error
    error_log("API Error [$requestId]: " . $e->getMessage());
    
    // Send error response
    $responseHandler->sendError($e->getMessage(), 500, [
        'request_id' => $requestId,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

/**
 * Validate token and get user information
 */
function validateTokenAndGetUserInfo($conn) {
    $accessToken = $_SERVER["HTTP_X_ACCESS_TOKEN"] ?? null;
    
    if (empty($accessToken)) {
        throw new Exception("Access token is required");
    }
    
    // Decode token
    $decodedToken = json_decode(getToken(base64_decode($accessToken)), true);
    
    if (!empty($decodedToken["error"])) {
        throw new Exception("Invalid token: " . $decodedToken["error"]);
    }
    
    $roleId = $decodedToken["role_ID"] ?? null;
    $userId = $decodedToken["user_id"] ?? null;
    
    if (empty($roleId) || empty($userId)) {
        throw new Exception("Invalid user or role in token");
    }
    
    // Validate token expiration
    $now = new DateTimeImmutable();
    if ($decodedToken["nbf"] > $now->getTimestamp() || $decodedToken["exp"] < $now->getTimestamp()) {
        throw new Exception("Token has expired");
    }
    
    return [
        'role_id' => $roleId,
        'user_id' => $userId,
        'token_data' => $decodedToken
    ];
}

/**
 * Check user permissions
 */
function check_validation($redis, $role_id, $user_id, $report_name, $camp_name, $department_name, $config_data, $conn) {
    // prepare_log_report("check_validation() is called with Params", "Role Id: $role_id, User Id: $user_id, Report Name: $report_name, Department Name: $department_name", 1);
    $settings = [
        "default" => [
            "table_name" => "campaign",
            "select_val" => "permissible_camps",
            "search_val" => "campaign_id",
            "cond_col"   => "campaign_name",
            "redis_key"  => "CAMP#TVT#{$role_id}#TVT#{$user_id}",
        ],
        "magic_call_report" => [
            "table_name" => "department",
            "select_val" => "permissible_departments",
            "search_val" => "department_id",
            "cond_col"   => "department_name",
            "redis_key"  => "DEPT#TVT#{$role_id}#TVT#{$user_id}",
        ],
    ];
    $config = $settings[$report_name] ? $settings[$report_name] : $settings["default"];
    // $redis = initRedis($config_data);
    // Check for admin user
    if ($user_id == 1) {
        $condition_value = $report_name === "magic_call_report" ? $department_name : $camp_name;
        return checkAdminPermissions($conn, $config['table_name'], $config['cond_col'], $condition_value);
    }
    return checkUserPermissions($conn, $redis, $config, $role_id, $user_id, $camp_name, $department_name);
}
function checkUserPermissions($conn, $redis, $config, $userInfo, $params) {
    $reportName = $params['report_name'];
    $campName = $params['campaign_name'];
    $departmentName = $params['department'];
    $validationResult = check_validation(
        $redis, 
        $userInfo['role_id'], 
        $userInfo['user_id'], 
        $reportName, 
        $campName, 
        $departmentName, 
        $config->getRedisConfig(), 
        $conn
    );
    
    if (isset($validationResult['error']) && $validationResult['error']) {
        error_log("Permission check failed: " . json_encode($validationResult));
        throw new Exception(isset($validationResult['msg']) ? $validationResult['msg'] : 'Permission check failed');
    }
    
    return isset($validationResult['data']) ? $validationResult['data'] : [];
}
/**
 * Generate report based on parameters
 */
function generateReport($conn, $params, $userInfo, $permissions) {
    // Extract parameters
    $reportName = $params['report_name'];
    $campName = $params['campaign_name'];
    $departmentName = $params['department'];
    $startDate = $params['start_date'];
    $endDate = $params['end_date'];
    $startTime = $params['start_time'];
    $endTime = $params['end_time'];
    $listName = $params['list_name'];
    $campaignType = $params['campaign_type'];
    $userName = $params['user_name'];
    $did = $params['did'];
    $ivrNodeName = $params['ivr_node_name'];
    $page = $params['page'];
    $limit = $params['limit'];
    
    // Initialize variables
    $magicCall = 0;
    $ivrReport = 0;
    $tableName = null;
    $startDuration = "";
    $endDuration = "";
    $id = null;
    $agentId = "";
    $disposition = "";
    $dialerType = "";
    $custDisposition = "";
    $customerPh = "";
    $uniqueCalls = "";
    $downloadReportsFlag = "";
    
    // Get table details
    $tableDetails = getTableDetails($reportName, $campName, $departmentName);
    
    // Fetch data
    $dataArr = fetchData($conn, $tableDetails['table_name'], $tableDetails['id_column'], $tableDetails['name_column']);
    $id = array_search($tableDetails['value_name'], $dataArr);
    
    if ($id === false) {
        throw new Exception("Invalid campaign or department name");
    }
    
    // Set report type flags
    if ($reportName == "magic_call_report") {
        $magicCall = "1";
    } elseif ($reportName == "ivr_detail_call_report") {
        $ivrReport = "1";
    }
    
    // Determine table name
    if (empty($startDate) && empty($endDate)) {
        $tableName = "current_report";
    } else {
        $date = date('Y_m');
        $dateNew = date('Y_m', strtotime($startDate));
        $currentDate = ($date === $dateNew) ? true : false;
        $tableName = ($currentDate) ? $date : $dateNew;
    }
    
    // Set final table name
    $tableName = ($reportName === "magic_call_report" ? "magic_call_" . $tableName : $tableName);
    $tableName = ($ivrReport == "1" ? "ivr_report_" . $tableName : $tableName);
    
    // Initialize objects
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = "https://" . $host;
    
    $filterObj = new FilterClass();
    $db = new DBconnection(AppConfig::getInstance()->getMySQLConfig());
    $config = new Reportconfig($id, $tableName, $baseUrl, $db);
    $obj = new ACD_customer();
    $report = new Reportoutput($db);
    
    // Set timezone
    $timeZoneVal = campaignTimeZone($conn, $campName);
    $filterObj->setTimeZoneVariables($timeZoneVal);
    
    // Handle IVR permissions
    $permissibleIvr = "";
    if ($ivrReport == "1") {
        $permissibleIvr = getUserPermissionsFromDB($conn, ["select_val" => "permissible_ivr"], $userInfo['role_id'], $userInfo['user_id']);
        if (empty($permissibleIvr)) {
            throw new Exception("Unable to get IVR permissions");
        }
    }
    
    // Set filter conditions
    $filterObj->setFilterCondition(
        $currentDate, $startDate, $endDate, $startTime, $endTime,
        $startDuration, $endDuration, $id, $agentId, $listName,
        $disposition, $campaignType, $dialerType, $custDisposition,
        $customerPh, $uniqueCalls, $magicCall, $ivrNodeName, $did,
        $ivrReport, $permissibleIvr
    );
    
    // Generate report
    $reportData = $report->setReportFields(
        $obj, $config, $filterObj, $reportName, $currentDate,
        $downloadReportsFlag, $userName, $tableName, $page, $limit
    );
    
    return $reportData;
}

/**
 * Get token data
 */
function getToken($token) {
    require_once('/var/www/html/apps/jwt.php');
    $serverKey = "#czentrixapiC0nf!Gur@t!0n";
    
    try {
        $payload = JWT::decode($token, $serverKey, array('HS256'));
        $return = json_decode($payload, true);
    } catch (Exception $e) {
        $return = array('error' => $e->getMessage());
    }
    
    return json_encode($return, JSON_PRETTY_PRINT);
}

/**
 * Initialize Redis connection
 */

function initRedis($config_data) {
    if (!class_exists('Redis')) {
        error_log("Redis extension not loaded");
        errorResponse("Redis extension not loaded", 500);
        return null;
    }
    try {
        $redis = new Redis();
        $connected = $redis->connect($config_data['REDIS_IP'], (int)$config_data['REDIS_PORT']);
        if (!$connected) {
            error_log("Redis connect() failed");
            errorResponse("Redis Connection Failed", 500, "Could not connect to Redis at IP: ".$config_data['REDIS_IP']." and PORT: ".$config_data['REDIS_PORT']);
            return null;
        }
        if (!$redis->ping()) {
            error_log("Redis ping() failed");
            errorResponse("Redis Connection Failed", 500, "Ping failed for Redis at IP: ".$config_data['REDIS_IP']." and PORT: ".$config_data['REDIS_PORT']);
            return null;
        }
        return $redis;
    } catch (RedisException $e) {
        error_log("RedisException: " . $e->getMessage());
        errorResponse("Some Error Occured, Please Try again Later", 500, "Redis Connection Failed at IP: ".$config_data['REDIS_IP']." and PORT: ".$config_data['REDIS_PORT']);
        return null;
    } catch (Exception $e) {
        error_log("Exception: " . $e->getMessage());
        errorResponse("Redis Connection Failed", 500);
        return null;
    }
}

// Include existing helper functions
require_once 'cz_generic_reports.php';
?> 