<?php
/**
 * Performance Configuration for Large Dataset Handling
 * This file contains settings to prevent 500 Bad Gateway errors
 * when fetching large datasets from reports
 */

class PerformanceConfig {
    
    // Default pagination settings
    const DEFAULT_PAGE_SIZE = 1;
    const MAX_PAGE_SIZE = 1;
    const MIN_PAGE_SIZE = 1;
    
    // Memory and timeout settings
    const MEMORY_LIMIT = '512M';
    const EXECUTION_TIME_LIMIT = 300; // 5 minutes
    const MAX_MEMORY_USAGE = 256 * 1024 * 1024; // 256MB
    
    // Query optimization settings
    const CHUNK_SIZE = 500; // Process records in chunks
    const GARBAGE_COLLECTION_INTERVAL = 500; // Force GC every N records
    
    /**
     * Apply performance settings for large dataset queries
     */
    public static function applySettings() {
        // Set memory limit
        ini_set('memory_limit', self::MEMORY_LIMIT);
        
        // Set execution time limit
        set_time_limit(self::EXECUTION_TIME_LIMIT);
        
        // Enable garbage collection
        gc_enable();
        
        // Set MySQL timeout settings
        self::setMySQLTimeout();
    }
    
    /**
     * Set MySQL timeout settings
     */
    private static function setMySQLTimeout() {
        global $conn;
        if ($conn) {
            // Set MySQL timeout to prevent connection drops
            mysqli_query($conn, "SET SESSION wait_timeout=300");
            mysqli_query($conn, "SET SESSION interactive_timeout=300");
            mysqli_query($conn, "SET SESSION net_read_timeout=300");
            mysqli_query($conn, "SET SESSION net_write_timeout=300");
        }
    }
    
    /**
     * Validate and sanitize pagination parameters
     */
    public static function validatePagination($page, $limit) {
        $page = max(1, (int)$page);
        $limit = max(self::MIN_PAGE_SIZE, min(self::MAX_PAGE_SIZE, (int)$limit));
        
        return [$page, $limit];
    }
    
    /**
     * Check if memory usage is approaching limits
     */
    public static function checkMemoryUsage() {
        $memoryUsage = memory_get_usage(true);
        return $memoryUsage < self::MAX_MEMORY_USAGE;
    }
    
    /**
     * Force garbage collection if needed
     */
    public static function forceGarbageCollection() {
        gc_collect_cycles();
    }
    
    /**
     * Optimize query for large datasets
     */
    public static function optimizeQuery($query) {
        // Add SQL_NO_CACHE hint for large queries to prevent memory issues
        if (stripos($query, 'SELECT') === 0) {
            $query = str_ireplace('SELECT', 'SELECT SQL_NO_CACHE', $query);
        }
        
        return $query;
    }
    
    /**
     * Get optimized query with proper indexing hints
     */
    public static function getOptimizedQuery($baseQuery, $page, $limit) {
        $offset = ($page - 1) * $limit;
        
        // Add FORCE INDEX hints for better performance on large tables
        $optimizedQuery = self::optimizeQuery($baseQuery);
        
        // Add pagination
        $optimizedQuery .= " LIMIT $limit OFFSET $offset";
        
        return $optimizedQuery;
    }
    
    /**
     * Create count query for pagination
     */
    public static function getCountQuery($baseQuery) {
        return "SELECT COUNT(*) as total FROM ($baseQuery) as count_table";
    }
    
    /**
     * Process large result sets in chunks
     */
    public static function processInChunks($result, $callback, $chunkSize = null) {
        if ($chunkSize === null) {
            $chunkSize = self::CHUNK_SIZE;
        }
        
        $processedCount = 0;
        $chunk = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $chunk[] = $row;
            $processedCount++;
            
            if ($processedCount % $chunkSize === 0) {
                // Process chunk
                $callback($chunk);
                $chunk = [];
                
                // Force garbage collection
                self::forceGarbageCollection();
                
                // Check memory usage
                if (!self::checkMemoryUsage()) {
                    error_log("Memory usage exceeded limit during chunk processing");
                    break;
                }
            }
        }
        
        // Process remaining records
        if (!empty($chunk)) {
            $callback($chunk);
        }
        
        return $processedCount;
    }
    
    /**
     * Get performance statistics
     */
    public static function getPerformanceStats() {
        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ];
    }
    
    /**
     * Log performance issues
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

// Apply settings when this file is included
PerformanceConfig::applySettings();
?> 
