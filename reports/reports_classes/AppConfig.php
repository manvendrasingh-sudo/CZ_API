<?php
/**
 * Application Configuration Class
 * Handles all configuration settings and constants
 */
class AppConfig {
    
    // Application constants
    const CONFIG_PATH = '/var/www/html/apps/api_config.txt';
    const BASE_PATH = '/var/www/html/base_path.php';
    const DEFAULT_LOG_PATH = '/var/log/czentrix';
    const DEFAULT_MAX_DAYS = 5;
    const DEFAULT_MEMORY_LIMIT = '512M';
    const DEFAULT_EXECUTION_TIME = 300;
    
    // Error reporting levels
    const ERROR_REPORTING_LEVEL = E_WARNING | E_PARSE | E_NOTICE;
    
    // Pagination defaults
    const DEFAULT_PAGE_SIZE = 1000;
    const MAX_PAGE_SIZE = 5000;
    const MIN_PAGE_SIZE = 100;
    
    // Memory and performance settings
    const MAX_MEMORY_USAGE = 256 * 1024 * 1024; // 256MB
    const CHUNK_SIZE = 500;
    const GARBAGE_COLLECTION_INTERVAL = 500;
    
    private static $instance = null;
    private $config = [];
    private $logPath;
    
    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        $this->initializeConfig();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize configuration
     */
    private function initializeConfig() {
        // Set error reporting
        error_reporting(self::ERROR_REPORTING_LEVEL);
        
        // Set memory and execution limits
        ini_set('memory_limit', self::DEFAULT_MEMORY_LIMIT);
        set_time_limit(self::DEFAULT_EXECUTION_TIME);
        
        // Enable garbage collection
        gc_enable();
        
        // Set log path
        $this->setLogPath();
        
        // Load configuration file
        $this->loadConfigFile();
    }
    
    /**
     * Set log path based on available paths
     */
    private function setLogPath() {
        if (file_exists(self::BASE_PATH)) {
            include_once(self::BASE_PATH);
            $this->logPath = defined('$czbar_path') ? $czbar_path : self::DEFAULT_LOG_PATH;
        } else {
            $this->logPath = self::DEFAULT_LOG_PATH;
        }
    }
    
    /**
     * Load configuration file
     */
    private function loadConfigFile() {
        if (!file_exists(self::CONFIG_PATH)) {
            throw new Exception("Configuration file not found at: " . self::CONFIG_PATH);
        }
        
        $jsonConfig = file_get_contents(self::CONFIG_PATH);
        $this->config = json_decode($jsonConfig, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in configuration file");
        }
    }
    
    /**
     * Get configuration value
     */
    public function get($key, $default = null) {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Get MySQL configuration
     */
    public function getMySQLConfig() {
        return $this->config['mysql_config'] ?? [];
    }
    
    /**
     * Get Redis configuration
     */
    public function getRedisConfig() {
        return $this->config['redis_config'] ?? [];
    }
    
    /**
     * Get maximum allowed days for reports
     */
    public function getMaxAllowedDays() {
        $val = $this->get('max_allowed_days', self::DEFAULT_MAX_DAYS);
        
        if (is_string($val)) {
            $val = (int) $val;
        }
        
        return ($val !== 0) ? $val : self::DEFAULT_MAX_DAYS;
    }
    
    /**
     * Get log depth configuration
     */
    public function getLogDepth() {
        return $this->get('log_depth', 0);
    }
    
    /**
     * Get log file path
     */
    public function getLogFilePath() {
        return $this->logPath . '/tp_integration.txt';
    }
    
    /**
     * Validate pagination parameters
     */
    public static function validatePagination($page, $limit) {
        $page = max(1, (int) $page);
        $limit = max(self::MIN_PAGE_SIZE, min(self::MAX_PAGE_SIZE, (int) $limit));
        
        return [$page, $limit];
    }
    
    /**
     * Check memory usage
     */
    public static function checkMemoryUsage() {
        $memoryUsage = memory_get_usage(true);
        return $memoryUsage < self::MAX_MEMORY_USAGE;
    }
    
    /**
     * Force garbage collection
     */
    public static function forceGarbageCollection() {
        gc_collect_cycles();
    }
    
    /**
     * Get performance statistics
     */
    public static function getPerformanceStats() {
        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ];
    }
    
    /**
     * Log performance issue
     */
    public static function logPerformanceIssue($message, $data = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message,
            'data' => $data,
            'stats' => self::getPerformanceStats()
        ];
        
        error_log("Performance Issue: " . json_encode($logData));
    }
}
?> 