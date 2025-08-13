<?php
/**
 * Response Handler Class
 * Handles API response formatting and sending
 */
class ResponseHandler {
    
    private $config;
    
    public function __construct() {
        $this->config = AppConfig::getInstance();
    }
    
    /**
     * Send success response
     */
    public function sendSuccess($data, $message = 'Success', $statusCode = 200) {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s'),
            'status_code' => $statusCode
        ];
        
        $this->sendResponse($response, $statusCode);
    }
    
    /**
     * Send error response
     */
    public function sendError($message, $statusCode = 400, $details = null) {
        $response = [
            'success' => false,
            'message' => $message,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s'),
            'status_code' => $statusCode
        ];
        
        $this->sendResponse($response, $statusCode);
    }
    
    /**
     * Send paginated response
     */
    public function sendPaginatedResponse($data, $pagination, $message = 'Success') {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => $pagination,
            'timestamp' => date('Y-m-d H:i:s'),
            'status_code' => 200
        ];
        
        $this->sendResponse($response, 200);
    }
    
    /**
     * Send raw JSON response
     */
    public function sendRawResponse($data) {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Send response with proper headers
     */
    private function sendResponse($response, $statusCode) {
        // Set HTTP status code
        http_response_code($statusCode);
        
        // Set headers
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        
        // Log response for debugging
        $this->logResponse($response, $statusCode);
        
        // Send response
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Log response for debugging
     */
    private function logResponse($response, $statusCode) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'status_code' => $statusCode,
            'response_size' => strlen(json_encode($response)),
            'memory_usage' => memory_get_usage(true)
        ];
        
        if ($statusCode >= 400) {
            error_log("API Error Response: " . json_encode($logData));
        }
    }
    
    /**
     * Send download response for CSV files
     */
    public function sendDownloadResponse($filePath, $fileName) {
        if (!file_exists($filePath)) {
            $this->sendError("File not found", 404);
        }
        
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        readfile($filePath);
        exit;
    }
    
    /**
     * Format pagination data
     */
    public function formatPagination($currentPage, $totalRecords, $limit, $offset) {
        $totalPages = ceil($totalRecords / $limit);
        
        return [
            'current_page' => $currentPage,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'has_more' => $currentPage < $totalPages,
            'limit' => $limit,
            'offset' => $offset,
            'has_previous' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages
        ];
    }
}
?> 