# Coding Standards and Best Practices

## Overview
This document outlines the coding standards and best practices for the Reports API project. Following these standards ensures code consistency, maintainability, and reliability.

## File Structure

### Directory Organization
```
reports/
├── reports_classes/          # Core classes and utilities
│   ├── AppConfig.php         # Application configuration
│   ├── RequestHandler.php    # Request processing
│   ├── ResponseHandler.php   # Response formatting
│   ├── filter_value.php      # Filter logic
│   ├── reportoutput.php      # Report generation
│   └── mysql_connection.php  # Database connection
├── cz_generic_reports.php    # Main API endpoint (legacy)
├── generic_reports.php       # Generic reports endpoint (legacy)
├── cz_generic_reports_standardized.php  # Standardized version
└── CODING_STANDARDS.md       # This file
```

## PHP Coding Standards

### 1. File Naming
- Use PascalCase for class files: `AppConfig.php`
- Use snake_case for function files: `mysql_connection.php`
- Use descriptive names that indicate purpose

### 2. Class Naming
```php
// ✅ Good
class AppConfig
class RequestHandler
class ResponseHandler

// ❌ Bad
class appconfig
class request_handler
class responsehandler
```

### 3. Method Naming
```php
// ✅ Good
public function getMySQLConfig()
public function validateRequest()
public function sendSuccessResponse()

// ❌ Bad
public function get_mysql_config()
public function validate_request()
public function sendSuccess()
```

### 4. Variable Naming
```php
// ✅ Good
$config = AppConfig::getInstance();
$requestHandler = new RequestHandler();
$userInfo = validateTokenAndGetUserInfo($conn);

// ❌ Bad
$cfg = AppConfig::getInstance();
$req = new RequestHandler();
$user = validateTokenAndGetUserInfo($conn);
```

### 5. Constants
```php
// ✅ Good
const CONFIG_PATH = '/var/www/html/apps/api_config.txt';
const DEFAULT_PAGE_SIZE = 1000;
const MAX_MEMORY_USAGE = 256 * 1024 * 1024;

// ❌ Bad
define('CONFIG_PATH', '/var/www/html/apps/api_config.txt');
```

## Code Structure

### 1. Class Structure
```php
<?php
/**
 * Class Description
 * Brief description of what this class does
 */
class ClassName {
    
    // Constants first
    const CONSTANT_NAME = 'value';
    
    // Private properties
    private $propertyName;
    
    // Public properties (if any)
    public $publicProperty;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialization code
    }
    
    /**
     * Public methods
     */
    public function publicMethod() {
        // Implementation
    }
    
    /**
     * Private methods
     */
    private function privateMethod() {
        // Implementation
    }
}
?>
```

### 2. Function Structure
```php
/**
 * Function description
 * 
 * @param string $param1 Description of parameter
 * @param int $param2 Description of parameter
 * @return array Description of return value
 * @throws Exception Description of when exception is thrown
 */
function functionName($param1, $param2) {
    // Input validation
    if (empty($param1)) {
        throw new Exception("Parameter 1 is required");
    }
    
    // Main logic
    $result = processData($param1, $param2);
    
    // Return result
    return $result;
}
```

## Error Handling

### 1. Exception Handling
```php
try {
    // Risky operation
    $result = performRiskyOperation();
    
} catch (DatabaseException $e) {
    // Handle database-specific errors
    logError("Database error: " . $e->getMessage());
    throw new Exception("Database operation failed");
    
} catch (Exception $e) {
    // Handle general errors
    logError("General error: " . $e->getMessage());
    throw new Exception("Operation failed");
}
```

### 2. Input Validation
```php
/**
 * Validate required parameters
 */
private function validateRequiredFields() {
    $requiredFields = ['report_name', 'campaign_name'];
    
    foreach ($requiredFields as $field) {
        if (empty($this->requestData[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
}
```

### 3. Error Logging
```php
/**
 * Log error with context
 */
private function logError($message, $details = '') {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => $message,
        'details' => $details,
        'request_data' => $this->requestData
    ];
    
    error_log("API Error: " . json_encode($logData));
}
```

## Database Operations

### 1. Connection Handling
```php
/**
 * Get database connection with error handling
 */
public function getConnection() {
    try {
        $conn = $this->db->mysqlConnection();
        
        if (!$conn) {
            throw new Exception("Database connection failed: " . $this->db->getError());
        }
        
        return $conn;
        
    } catch (Exception $e) {
        logError("Database connection error", $e->getMessage());
        throw new Exception("Database connection failed");
    }
}
```

### 2. Query Execution
```php
/**
 * Execute query with error handling
 */
private function executeQuery($query) {
    $result = mysqli_query($this->conn, $query);
    
    if (!$result) {
        $error = mysqli_error($this->conn);
        logError("Query execution failed", [
            'query' => $query,
            'error' => $error
        ]);
        throw new Exception("Query execution failed");
    }
    
    return $result;
}
```

## Security Best Practices

### 1. Input Sanitization
```php
/**
 * Sanitize input parameters
 */
private function sanitizeInput($input) {
    if (is_string($input)) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    if (is_array($input)) {
        return array_map([$this, 'sanitizeInput'], $input);
    }
    
    return $input;
}
```

### 2. SQL Injection Prevention
```php
/**
 * Use prepared statements
 */
private function getCampaignData($campaignId) {
    $stmt = $this->conn->prepare("SELECT * FROM campaign WHERE campaign_id = ?");
    $stmt->bind_param("i", $campaignId);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_assoc();
}
```

### 3. Token Validation
```php
/**
 * Validate JWT token
 */
private function validateToken($token) {
    try {
        $decoded = JWT::decode($token, $this->secretKey, ['HS256']);
        
        // Check expiration
        if ($decoded->exp < time()) {
            throw new Exception("Token has expired");
        }
        
        return $decoded;
        
    } catch (Exception $e) {
        throw new Exception("Invalid token");
    }
}
```

## Performance Optimization

### 1. Memory Management
```php
/**
 * Process large datasets in chunks
 */
private function processLargeDataset($data, $chunkSize = 1000) {
    $chunks = array_chunk($data, $chunkSize);
    
    foreach ($chunks as $chunk) {
        // Process chunk
        $this->processChunk($chunk);
        
        // Force garbage collection
        gc_collect_cycles();
        
        // Check memory usage
        if (!AppConfig::checkMemoryUsage()) {
            throw new Exception("Memory limit exceeded");
        }
    }
}
```

### 2. Pagination
```php
/**
 * Implement pagination for large datasets
 */
private function getPaginatedData($query, $page, $limit) {
    $offset = ($page - 1) * $limit;
    $pagedQuery = $query . " LIMIT $limit OFFSET $offset";
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM ($query) as count_table";
    $totalRecords = $this->getCount($countQuery);
    
    // Get paginated data
    $data = $this->executeQuery($pagedQuery);
    
    return [
        'data' => $data,
        'pagination' => $this->formatPagination($page, $totalRecords, $limit, $offset)
    ];
}
```

## API Response Standards

### 1. Success Response
```json
{
    "success": true,
    "message": "Report generated successfully",
    "data": [...],
    "timestamp": "2024-01-01 12:00:00",
    "status_code": 200
}
```

### 2. Error Response
```json
{
    "success": false,
    "message": "Invalid request parameters",
    "details": {
        "field": "campaign_name",
        "error": "Campaign name is required"
    },
    "timestamp": "2024-01-01 12:00:00",
    "status_code": 400
}
```

### 3. Paginated Response
```json
{
    "success": true,
    "message": "Report generated successfully",
    "data": [...],
    "pagination": {
        "current_page": 1,
        "total_records": 50000,
        "total_pages": 50,
        "has_more": true,
        "limit": 1000,
        "offset": 0
    },
    "timestamp": "2024-01-01 12:00:00",
    "status_code": 200
}
```

## Testing Standards

### 1. Unit Tests
```php
/**
 * Test class for RequestHandler
 */
class RequestHandlerTest extends PHPUnit_Framework_TestCase {
    
    public function testValidRequest() {
        $handler = new RequestHandler();
        $params = $handler->processRequest();
        
        $this->assertArrayHasKey('report_name', $params);
        $this->assertNotEmpty($params['report_name']);
    }
    
    public function testInvalidReportName() {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid report name');
        
        $handler = new RequestHandler();
        $handler->processRequest();
    }
}
```

### 2. Integration Tests
```php
/**
 * Test API endpoints
 */
class ApiIntegrationTest extends PHPUnit_Framework_TestCase {
    
    public function testReportGeneration() {
        $response = $this->makeApiRequest([
            'report_name' => 'detail_call_report',
            'campaign_name' => 'TestCampaign',
            'start_date' => '2024-01-01',
            'end_date' => '2024-01-01'
        ]);
        
        $this->assertEquals(200, $response['status_code']);
        $this->assertTrue($response['success']);
    }
}
```

## Documentation Standards

### 1. Class Documentation
```php
/**
 * Application Configuration Class
 * 
 * This class handles all application configuration settings including
 * database connections, memory limits, and pagination settings.
 * 
 * @package Reports
 * @author Your Name
 * @version 1.0
 */
class AppConfig {
    // Implementation
}
```

### 2. Method Documentation
```php
/**
 * Get MySQL configuration
 * 
 * Retrieves the MySQL configuration from the config file
 * and returns it as an array.
 * 
 * @return array MySQL configuration array
 * @throws Exception If configuration is not found
 */
public function getMySQLConfig() {
    // Implementation
}
```

## Version Control

### 1. Commit Messages
```
feat: Add pagination support for large datasets
fix: Resolve timezone issue in generic reports
docs: Update API documentation
test: Add unit tests for RequestHandler
refactor: Standardize error handling across classes
```

### 2. Branch Naming
```
feature/pagination-support
bugfix/timezone-issue
hotfix/security-patch
release/v1.2.0
```

## Deployment Standards

### 1. Environment Configuration
```php
// config/environment.php
return [
    'development' => [
        'debug' => true,
        'log_level' => 'debug',
        'cache' => false
    ],
    'production' => [
        'debug' => false,
        'log_level' => 'error',
        'cache' => true
    ]
];
```

### 2. Health Checks
```php
/**
 * Health check endpoint
 */
public function healthCheck() {
    $checks = [
        'database' => $this->checkDatabaseConnection(),
        'redis' => $this->checkRedisConnection(),
        'memory' => $this->checkMemoryUsage()
    ];
    
    $allHealthy = array_reduce($checks, function($carry, $check) {
        return $carry && $check;
    }, true);
    
    return [
        'status' => $allHealthy ? 'healthy' : 'unhealthy',
        'checks' => $checks,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}
```

## Monitoring and Logging

### 1. Performance Monitoring
```php
/**
 * Monitor API performance
 */
private function monitorPerformance($operation, $startTime) {
    $endTime = microtime(true);
    $duration = $endTime - $startTime;
    
    $metrics = [
        'operation' => $operation,
        'duration' => $duration,
        'memory_usage' => memory_get_usage(true),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($duration > 5.0) { // 5 seconds threshold
        AppConfig::logPerformanceIssue("Slow operation detected", $metrics);
    }
}
```

### 2. Error Tracking
```php
/**
 * Track errors with context
 */
private function trackError($error, $context = []) {
    $errorData = [
        'error' => $error->getMessage(),
        'file' => $error->getFile(),
        'line' => $error->getLine(),
        'trace' => $error->getTraceAsString(),
        'context' => $context,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    error_log("Error tracked: " . json_encode($errorData));
}
```

## Conclusion

Following these coding standards ensures:
- **Consistency**: All code follows the same patterns and conventions
- **Maintainability**: Code is easy to understand and modify
- **Reliability**: Proper error handling and validation
- **Security**: Input sanitization and SQL injection prevention
- **Performance**: Optimized database queries and memory management
- **Testability**: Code is structured for easy testing

Remember to review and update these standards as the project evolves. 