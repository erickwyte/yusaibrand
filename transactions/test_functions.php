<?php
// check_functions.php
require_once 'db.php';

echo "<h2>Checking Current Function Names</h2>";

// Get all user-defined functions
$functions = get_defined_functions()['user'];

echo "Total functions: " . count($functions) . "<br><br>";

// Show all functions that might be related
echo "Database-related functions:<br>";
foreach ($functions as $func) {
    if (stripos($func, 'product') !== false || 
        stripos($func, 'order') !== false || 
        stripos($func, 'transaction') !== false ||
        stripos($func, 'user') !== false) {
        echo " - $func<br>";
    }
}

// Specifically check for getProductFromAnyTable
echo "<br>Checking for specific functions:<br>";
echo "getProductFromAnyTable exists: " . (function_exists('getProductFromAnyTable') ? 'YES' : 'NO') . "<br>";
echo "getproductfromanytable exists: " . (function_exists('getproductfromanytable') ? 'YES' : 'NO') . "<br>";
echo "createOrder exists: " . (function_exists('createOrder') ? 'YES' : 'NO') . "<br>";
echo "createorder exists: " . (function_exists('createorder') ? 'YES' : 'NO') . "<br>";
echo "createOrderItem exists: " . (function_exists('createOrderItem') ? 'YES' : 'NO') . "<br>";
echo "createorderitem exists: " . (function_exists('createorderitem') ? 'YES' : 'NO') . "<br>";
?>