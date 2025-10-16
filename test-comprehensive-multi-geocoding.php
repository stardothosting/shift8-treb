<?php
/**
 * Comprehensive Multi-Service Geocoding Test
 * 
 * Tests the new MultiGeocodingService against the full address database
 */

// Load WordPress
require_once('/home/ck/git/shift8-projects/shift8.local/wp-config.php');

// Load the MultiGeocodingService
require_once(__DIR__ . '/includes/Services/MultiGeocodingService.php');

use Shift8\TREB\Services\MultiGeocodingService;

echo "=== Comprehensive Multi-Service Geocoding Test ===\n\n";

// Get a large sample of addresses from the database
global $wpdb;

echo "🔍 Gathering test addresses from database...\n";

$db_query = "
    SELECT DISTINCT pm.meta_value as address 
    FROM {$wpdb->postmeta} pm 
    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
    WHERE pm.meta_key = 'shift8_treb_unparsed_address' 
    AND p.post_type = 'post' 
    AND pm.meta_value != '' 
    ORDER BY p.post_date DESC 
    LIMIT 50
";

$dbAddresses = $wpdb->get_col($db_query);

// Add the previously problematic addresses
$problematicAddresses = [
    '29 Nelson Street, Brant, ON L0R 2H6',
    '3328 Oriole Drive, London South, ON N6M 0K1, London South, ON',
    '1425 Gerrard Street E 2nd Flr, Toronto E01, ON M4L 1Z7, Toronto E01, ON',
    '10 Old Mill Trail 305, Toronto W08, ON M8X 2Y9, Toronto W08, ON',
    '321 Yonge Street #1001, Toronto, ON M5B 1R7',
    '1900 Highway 7 East, Markham, ON L3R 1A3',
    '2200 The Esplanade Suite 1501, Toronto, ON M5E 1A6',
    '395 Dundas Street W 603, Oakville, ON L6M 5R8',
    '258 Bloor Street W 3rd Floor, Toronto, ON M5V 2L1',
    '123 Main Street Unit 456, Toronto, ON M5V 1A1',
    '789 Queen Street W Apt 12, Toronto, ON M6J 1G1',
    '456 King Street E Suite 789, Toronto, ON M5A 1L9',
    '654 Bay Street PH, Toronto, ON M5G 1M5',
    '987 College Street Lower, Toronto, ON M6H 1A1',
    '147 Spadina Avenue Upper, Toronto, ON M5V 2L1',
    'Unit 2600, 123 Main Street, Toronto, ON M5V 1A1',
    'Apt 2700 - 456 Queen Street, Toronto, ON M5V 2A5',
    '5400 Main Street W, Hamilton, ON L8S 1A8'
];

// Combine and deduplicate
$testAddresses = array_unique(array_merge($dbAddresses, $problematicAddresses));

echo "✅ Collected " . count($testAddresses) . " unique test addresses\n\n";

if (count($testAddresses) < 30) {
    echo "⚠️  Warning: Only " . count($testAddresses) . " addresses available. Consider importing more listings first.\n\n";
}

$geocoder = new MultiGeocodingService();

echo "🧪 Starting comprehensive multi-service geocoding test...\n";
echo "⏱️  This will take approximately " . ceil(count($testAddresses) * 4 / 60) . " minutes due to rate limiting.\n";
echo str_repeat("=", 120) . "\n";

$results = [
    'total' => 0,
    'successful' => 0,
    'failed' => 0,
    'services_used' => [],
    'failed_examples' => []
];

$startTime = time();

foreach ($testAddresses as $index => $address) {
    $results['total']++;
    
    echo sprintf("[%3d/%3d] Testing: %s\n", $index + 1, count($testAddresses), substr($address, 0, 80));
    
    $result = $geocoder->geocode($address);
    
    if ($result['success']) {
        $results['successful']++;
        echo "          ✅ SUCCESS: " . $result['lat'] . ", " . $result['lng'] . "\n";
        echo "          🎯 Service: " . $result['service_used'] . "\n";
        echo "          📍 Used: " . substr($result['address_used'], 0, 80) . "\n";
        
        // Track service usage
        if (!isset($results['services_used'][$result['service_used']])) {
            $results['services_used'][$result['service_used']] = 0;
        }
        $results['services_used'][$result['service_used']]++;
        
    } else {
        $results['failed']++;
        echo "          ❌ FAILED: " . ($result['error'] ?? 'Unknown error') . "\n";
        echo "          🎯 Service: " . $result['service_used'] . "\n";
        
        // Store failed example
        if (count($results['failed_examples']) < 10) {
            $results['failed_examples'][] = [
                'address' => $address,
                'error' => $result['error'] ?? 'Unknown error',
                'service' => $result['service_used']
            ];
        }
    }
    
    echo "\n";
    
    // Progress update every 20 addresses
    if (($index + 1) % 20 === 0) {
        $elapsed = time() - $startTime;
        $rate = ($index + 1) / $elapsed * 60; // addresses per minute
        $remaining = count($testAddresses) - ($index + 1);
        $eta = $remaining / $rate;
        
        echo sprintf("--- Progress: %d/%d (%.1f%%) | Rate: %.1f addr/min | ETA: %.1f min ---\n\n", 
                    $index + 1, count($testAddresses), 
                    (($index + 1) / count($testAddresses)) * 100,
                    $rate, $eta);
    }
}

$totalTime = time() - $startTime;
$successRate = ($results['successful'] / $results['total']) * 100;

// Comprehensive analysis results
echo str_repeat("=", 120) . "\n";
echo "COMPREHENSIVE MULTI-SERVICE GEOCODING RESULTS\n";
echo str_repeat("=", 120) . "\n";

echo sprintf("📊 OVERALL STATISTICS:\n");
echo sprintf("   Total addresses tested: %d\n", $results['total']);
echo sprintf("   Successful geocoding: %d (%.1f%%)\n", $results['successful'], $successRate);
echo sprintf("   Failed geocoding: %d (%.1f%%)\n", $results['failed'], 100 - $successRate);
echo sprintf("   Total time: %d minutes %d seconds\n", floor($totalTime / 60), $totalTime % 60);

echo "\n📈 SERVICE USAGE ANALYSIS:\n";
foreach ($results['services_used'] as $service => $count) {
    $percentage = ($count / $results['successful']) * 100;
    echo sprintf("   %s: %d successes (%.1f%% of successes)\n", ucwords(str_replace('_', ' ', $service)), $count, $percentage);
}

// Show detailed failure examples
if (!empty($results['failed_examples'])) {
    echo "\n" . str_repeat("=", 120) . "\n";
    echo "DETAILED FAILURE ANALYSIS\n";
    echo str_repeat("=", 120) . "\n";
    
    foreach ($results['failed_examples'] as $i => $failure) {
        echo sprintf("\n🔍 Failure %d:\n", $i + 1);
        echo sprintf("   Address: %s\n", $failure['address']);
        echo sprintf("   Error: %s\n", $failure['error']);
        echo sprintf("   Final Service: %s\n", $failure['service']);
    }
}

// Final assessment
echo "\n" . str_repeat("=", 120) . "\n";
echo "FINAL ASSESSMENT\n";
echo str_repeat("=", 120) . "\n";

if ($successRate >= 99) {
    echo "🎉 OUTSTANDING: 99%+ success rate achieved!\n";
    echo "✅ Ready for production deployment\n";
} elseif ($successRate >= 95) {
    echo "🌟 EXCELLENT: 95%+ success rate achieved\n";
    echo "✅ Significant improvement over previous system\n";
} elseif ($successRate >= 90) {
    echo "👍 VERY GOOD: 90%+ success rate achieved\n";
    echo "📈 Major improvement from 82.3% baseline\n";
} else {
    echo "⚠️  NEEDS IMPROVEMENT: Success rate below 90%\n";
}

echo "\n💡 NEXT STEPS:\n";
if ($successRate >= 95) {
    echo "   ✅ Integration complete - multi-service geocoding is working excellently\n";
    echo "   📝 Update unit tests to reflect new architecture\n";
    echo "   🚀 Deploy to production\n";
} else {
    echo "   🔧 Consider adding Google Maps Geocoding API for remaining failures\n";
    echo "   🔧 Implement MapBox geocoding as additional fallback\n";
    echo "   📊 Analyze failure patterns for further improvements\n";
}

echo "\n=== Comprehensive Test Complete ===\n";
