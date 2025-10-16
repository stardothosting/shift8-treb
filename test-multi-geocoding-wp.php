<?php
/**
 * Test Multi-Service Geocoding with WordPress
 * 
 * Tests the new MultiGeocodingService with WordPress functions available
 */

// Load WordPress
require_once('/home/ck/git/shift8-projects/shift8.local/wp-config.php');

// Load the MultiGeocodingService
require_once(__DIR__ . '/includes/Services/MultiGeocodingService.php');

use Shift8\TREB\Services\MultiGeocodingService;

echo "=== Testing Multi-Service Geocoding with WordPress ===\n\n";

// Previously failing addresses
$testAddresses = [
    '29 Nelson Street, Brant, ON L0R 2H6',
    '3328 Oriole Drive, London South, ON N6M 0K1, London South, ON',
    '1425 Gerrard Street E 2nd Flr, Toronto E01, ON M4L 1Z7, Toronto E01, ON',
    '10 Old Mill Trail 305, Toronto W08, ON M8X 2Y9, Toronto W08, ON',
    '321 Yonge Street #1001, Toronto, ON M5B 1R7',
    '1900 Highway 7 East, Markham, ON L3R 1A3',
    '2200 The Esplanade Suite 1501, Toronto, ON M5E 1A6',
    '2800 Unknown Street, Toronto',
    // Add some that should work
    '258 Bloor Street W 3rd Floor, Toronto, ON M5V 2L1',
    '123 Main Street Unit 456, Toronto, ON M5V 1A1',
    '456 Queen Street, Toronto, ON M5V 2A5'
];

$geocoder = new MultiGeocodingService();

echo "🧪 Testing " . count($testAddresses) . " addresses with multi-service geocoding...\n";
echo "⏱️  This will take approximately " . ceil(count($testAddresses) * 4 / 60) . " minutes due to rate limiting.\n\n";

$successCount = 0;
$totalCount = count($testAddresses);
$startTime = time();

foreach ($testAddresses as $index => $address) {
    echo sprintf("[%2d/%2d] Testing: %s\n", $index + 1, $totalCount, substr($address, 0, 60) . (strlen($address) > 60 ? '...' : ''));
    
    $result = $geocoder->geocode($address);
    
    if ($result['success']) {
        $successCount++;
        echo sprintf("         ✅ SUCCESS: %.6f, %.6f\n", $result['lat'], $result['lng']);
        echo sprintf("         🎯 Service: %s\n", $result['service_used']);
        echo sprintf("         📍 Used: %s\n", substr($result['address_used'], 0, 80));
        if (!empty($result['display_name'])) {
            echo sprintf("         🏠 Location: %s\n", substr($result['display_name'], 0, 80) . '...');
        }
    } else {
        echo sprintf("         ❌ FAILED: %s\n", $result['error'] ?? 'Unknown error');
        echo sprintf("         🎯 Service: %s\n", $result['service_used']);
        echo sprintf("         📍 Fallback: %s\n", $result['address_used']);
    }
    echo "\n";
    
    // Progress update every 5 addresses
    if (($index + 1) % 5 === 0) {
        $elapsed = time() - $startTime;
        $rate = ($index + 1) / $elapsed * 60; // addresses per minute
        $remaining = $totalCount - ($index + 1);
        $eta = $remaining / $rate;
        
        echo sprintf("--- Progress: %d/%d (%.1f%%) | Rate: %.1f addr/min | ETA: %.1f min ---\n\n", 
                    $index + 1, $totalCount, 
                    (($index + 1) / $totalCount) * 100,
                    $rate, $eta);
    }
}

$totalTime = time() - $startTime;
$successRate = ($successCount / $totalCount) * 100;

echo str_repeat("=", 80) . "\n";
echo "MULTI-SERVICE GEOCODING RESULTS\n";
echo str_repeat("=", 80) . "\n";
echo sprintf("📊 SUCCESS RATE: %d/%d (%.1f%%)\n", $successCount, $totalCount, $successRate);
echo sprintf("⏱️  Total time: %d minutes %d seconds\n", floor($totalTime / 60), $totalTime % 60);

if ($successRate >= 99) {
    echo "🎉 EXCELLENT: Achieved 99%+ success rate!\n";
} elseif ($successRate >= 95) {
    echo "✅ VERY GOOD: 95%+ success rate achieved\n";
} elseif ($successRate >= 90) {
    echo "👍 GOOD: 90%+ success rate achieved\n";
} else {
    echo "⚠️  NEEDS IMPROVEMENT: Success rate below 90%\n";
}

echo "\n💡 NEXT STEPS:\n";
if ($successRate < 99) {
    echo "   - Add Google Maps Geocoding API as secondary service\n";
    echo "   - Implement MapBox geocoding as tertiary service\n";
    echo "   - Add more intelligent address variations\n";
} else {
    echo "   - Integration complete - ready for production!\n";
    echo "   - Update unit tests\n";
    echo "   - Run comprehensive test with full address database\n";
}

echo "\n=== Test Complete ===\n";
