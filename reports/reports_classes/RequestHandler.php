<?php
/**
 * Request Handler Class
 * Handles API request processing and validation
 */
class RequestHandler {
    
    private $config;
    private $requestData;
    private $validReports;
    
    public function __construct() {
        $this->config = AppConfig::getInstance();
        $this->validReports = [
            "agent_session_report",
            "abandon_calls",
            "agent_login_report",
            "campaign_call_summary",
            "agent_break_report",
            "agent_summary_report",
            "detail_call_report",
            "magic_call_report",
            "ivr_detail_call_report"
        ];
    }
    
    /**
     * Process the incoming request
     */
    public function processRequest() {
        try {
            // Get request data
            $this->requestData = $this->getRequestData();
            
            // Validate request
            $this->validateRequest();
            
            // Return sanitized parameters
            return $this->getSanitizedParams();
            
        } catch (Exception $e) {
            $this->logError("Request processing failed", $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get request data from input
     */
    private function getRequestData() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON format in request body");
        }
        
        return $data;
    }
    
    /**
     * Validate the request data
     */
    private function validateRequest() {
        $this->validateRequiredFields();
        $this->validateReportName();
        $this->validateDateRange();
        $this->validateTimeRange();
        $this->validateReportSpecificFields();
    }
    
    /**
     * Validate required fields
     */
    private function validateRequiredFields() {
        $requiredFields = ['report_name'];
        
        foreach ($requiredFields as $field) {
            if (empty($this->requestData[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
    }
    
    /**
     * Validate report name
     */
    private function validateReportName() {
        $reportName = $this->requestData['report_name'];
        
        if (!in_array($reportName, $this->validReports, true)) {
            throw new Exception("Invalid report name: $reportName");
        }
    }
    
    /**
     * Validate date range
     */
    private function validateDateRange() {
        $startDate = $this->requestData['start_date'] ?? '';
        $endDate = $this->requestData['end_date'] ?? '';
        
        if (!empty($startDate) && empty($endDate)) {
            throw new Exception("End date is required when start date is provided");
        }
        
        if (!empty($endDate) && empty($startDate)) {
            throw new Exception("Start date is required when end date is provided");
        }
        
        if (!empty($startDate) && !empty($endDate)) {
            $this->validateDateFormat($startDate, "start_date");
            $this->validateDateFormat($endDate, "end_date");
            $this->validateDateRangeLogic($startDate, $endDate);
        }
    }
    
    /**
     * Validate time range
     */
    private function validateTimeRange() {
        $startTime = $this->requestData['start_time'] ?? '';
        $endTime = $this->requestData['end_time'] ?? '';
        
        if (!empty($startTime) && empty($endTime)) {
            throw new Exception("End time is required when start time is provided");
        }
        
        if (!empty($endTime) && empty($startTime)) {
            throw new Exception("Start time is required when end time is provided");
        }
        
        if (!empty($startTime) && !empty($endTime)) {
            $this->validateTimeFormat($startTime, "start_time");
            $this->validateTimeFormat($endTime, "end_time");
            
            if ($startTime === $endTime) {
                throw new Exception("Start time and end time cannot be the same");
            }
        }
    }
    
    /**
     * Validate report-specific fields
     */
    private function validateReportSpecificFields() {
        $reportName = $this->requestData['report_name'];
        
        switch ($reportName) {
            case 'magic_call_report':
                if (empty($this->requestData['department'])) {
                    throw new Exception("Department is required for magic call report");
                }
                break;
                
            case 'campaign_call_summary':
                $campaignType = $this->requestData['campaign_type'] ?? 'ALL';
                if (!in_array($campaignType, ['INBOUND', 'OUTBOUND', 'ALL'], true)) {
                    throw new Exception("Invalid campaign type. Allowed values: INBOUND, OUTBOUND, ALL");
                }
                break;
                
            case 'ivr_detail_call_report':
                if (empty($this->requestData['did'])) {
                    throw new Exception("DID is required for IVR detail call report");
                }
                if (empty($this->requestData['ivr_node_name'])) {
                    throw new Exception("IVR node name is required for IVR detail call report");
                }
                break;
                
            default:
                if (empty($this->requestData['campaign_name'])) {
                    throw new Exception("Campaign name is required for this report");
                }
                break;
        }
    }
    
    /**
     * Validate date format
     */
    private function validateDateFormat($date, $fieldName) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new Exception("Invalid date format for $fieldName. Expected: YYYY-MM-DD");
        }
        
        list($year, $month, $day) = explode('-', $date);
        if (!checkdate((int)$month, (int)$day, (int)$year)) {
            throw new Exception("Invalid date value for $fieldName");
        }
    }
    
    /**
     * Validate time format
     */
    private function validateTimeFormat($time, $fieldName) {
        if (!preg_match('/^(2[0-3]|[01][0-9]):[0-5][0-9]:[0-5][0-9]$/', $time)) {
            throw new Exception("Invalid time format for $fieldName. Expected: HH:MM:SS");
        }
    }
    
    /**
     * Validate date range logic
     */
    private function validateDateRangeLogic($startDate, $endDate) {
        $startMonth = explode("-", $startDate)[1];
        $endMonth = explode("-", $endDate)[1];
        
        if ($startMonth !== $endMonth) {
            throw new Exception("Different months are not allowed in date range");
        }
        
        if (strtotime($startDate) > strtotime($endDate)) {
            throw new Exception("Start date cannot be greater than end date");
        }
        
        $dateDiff = ceil(abs(strtotime($endDate) - strtotime($startDate)) / 86400);
        $maxDays = $this->config->getMaxAllowedDays();
        
        if ($dateDiff > ($maxDays - 1) || $dateDiff < 0) {
            throw new Exception("Date range cannot exceed $maxDays days");
        }
    }
    
    /**
     * Get sanitized parameters
     */
    private function getSanitizedParams() {
        $params = [
            'report_name' => $this->requestData['report_name'] ?? '',
            'campaign_name' => $this->requestData['campaign_name'] ?? '',
            'department' => $this->requestData['department'] ?? '',
            'start_date' => $this->requestData['start_date'] ?? '',
            'end_date' => $this->requestData['end_date'] ?? '',
            'start_time' => $this->requestData['start_time'] ?? '',
            'end_time' => $this->requestData['end_time'] ?? '',
            'list_name' => $this->requestData['list_name'] ?? '',
            'campaign_type' => $this->requestData['campaign_type'] ?? 'ALL',
            'user_name' => $this->requestData['user_name'] ?? '',
            'did' => $this->requestData['did'] ?? '',
            'ivr_node_name' => $this->requestData['ivr_node_name'] ?? '',
            'page' => $this->requestData['page'] ?? 1,
            'limit' => $this->requestData['limit'] ?? AppConfig::DEFAULT_PAGE_SIZE
        ];
        
        // Validate and sanitize pagination parameters
        list($params['page'], $params['limit']) = AppConfig::validatePagination(
            $params['page'], 
            $params['limit']
        );
        
        return $params;
    }
    
    /**
     * Log error
     */
    private function logError($message, $details = '') {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message,
            'details' => $details,
            'request_data' => $this->requestData
        ];
        
        error_log("Request Error: " . json_encode($logData));
    }
}
?> 