<?php
// Simple test script to verify timezone and large dataset fixes
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once('reports_classes/filter_value.php');
include_once('reports_classes/performance_config.php');

echo "=== SIMPLE TEST: TIMEZONE + LARGE DATASET FIXES ===\n\n";

// Test 1: Timezone functionality
echo "TEST 1: Timezone Functionality\n";
$filterObj = new FilterClass();
$filterObj->setTimeZoneVariables("+05:30,0");
$filterObj->setFilterCondition(true, '2024-01-01', '2024-01-01', '00:00:00', '23:59:59', '', '', '1', '', '', '', 'ALL', '', '', '', '', '0', '', '', '0', '');
$condition = $filterObj->getFilterCondition();
$hasTimezone = strpos($condition, 'convert_tz') !== false;
echo "Timezone working: " . ($hasTimezone ? "✓" : "✗") . "\n\n";

// Test 2: Pagination validation
echo "TEST 2: Pagination Validation\n";
list($page, $limit) = PerformanceConfig::validatePagination(2, 1500);
echo "Page: $page, Limit: $limit\n";
echo "Pagination working: " . (($page == 2 && $limit == 1500) ? "✓" : "✗") . "\n\n";

// Test 3: Memory settings
echo "TEST 3: Memory Settings\n";
echo "Memory limit: " . ini_get('memory_limit') . "\n";
echo "Execution time: " . ini_get('max_execution_time') . " seconds\n";
echo "Settings applied: " . (ini_get('memory_limit') == '512M' ? "✓" : "✗") . "\n\n";

echo "=== SUMMARY ===\n";
echo "Timezone fix: " . ($hasTimezone ? "✓" : "✗") . "\n";
echo "Large dataset fix: " . (ini_get('memory_limit') == '512M' ? "✓" : "✗") . "\n";
echo "Pagination fix: " . (($page == 2 && $limit == 1500) ? "✓" : "✗") . "\n";
?> 