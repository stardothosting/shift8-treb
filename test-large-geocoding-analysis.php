<?php
/**
 * Large-Scale Geocoding Analysis
 * 
 * Tests a large pool of addresses to identify patterns in geocoding failures
 * and achieve 99-100% success rate.
 */

// Load WordPress to get real addresses from database
require_once('/home/ck/git/shift8-projects/shift8.local/wp-config.php');

// Load the new AddressNormalizer
require_once(__DIR__ . '/includes/Services/AddressNormalizer.php');

use Shift8\TREB\Services\AddressNormalizer;

echo "=== Large-Scale Geocoding Analysis ===\n\n";

// Default Toronto coordinates (used when geocoding fails)
const DEFAULT_LAT = 43.6532;
const DEFAULT_LNG = -79.3832;

// Get a large sample of addresses from the database and add known problematic ones
global $wpdb;

echo "🔍 Gathering test addresses...\n";

// Get addresses from database
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

// Add comprehensive test addresses including known problematic patterns
$testAddresses = array_merge($dbAddresses, [
    // Previously problematic addresses
    '395 Dundas Street W 603, Oakville, ON L6M 5R8',
    '3328 Oriole Drive, London South, ON N6M 0K1, London South, ON',
    '1425 Gerrard Street E 2nd Flr, Toronto E01, ON M4L 1Z7, Toronto E01, ON',
    '10 Old Mill Trail 305, Toronto W08, ON M8X 2Y9, Toronto W08, ON',
    '12 Old Mill Trail 503, Toronto W08, ON M8X 2Z4',
    '103 The Queensway Avenue 2707, Toronto W01, ON M6S 5B3',
    
    // Unit/apartment variations
    '123 Main Street Unit 456, Toronto, ON M5V 1A1',
    '789 Queen Street W Apt 12, Toronto, ON M6J 1G1',
    '456 King Street E Suite 789, Toronto, ON M5A 1L9',
    '321 Yonge Street #1001, Toronto, ON M5B 1R7',
    '654 Bay Street PH, Toronto, ON M5G 1M5',
    '987 College Street Lower, Toronto, ON M6H 1A1',
    '147 Spadina Avenue Upper, Toronto, ON M5V 2L1',
    '258 Bloor Street W 3rd Floor, Toronto, ON M5V 2L1',
    
    // Directional street names
    '100 North York Boulevard, North York, ON M2J 1P8',
    '200 South Park Road, Markham, ON L3T 1Z1',
    '300 East Mall Crescent, Etobicoke, ON M9B 6K1',
    '400 West Hill Drive, Scarborough, ON M1E 2Z9',
    
    // Complex street names
    '500 Avenue Road, Toronto, ON M4V 2J2',
    '600 The Kingsway, Toronto, ON M8X 2T5',
    '700 The Queensway, Toronto, ON M8Y 1K8',
    '800 The Donway West, Toronto, ON M3C 2E9',
    
    // Abbreviated forms
    '900 Queen St E, Toronto, ON M4M 1J5',
    '1000 King St W, Toronto, ON M6K 3M2',
    '1100 Bloor St W, Toronto, ON M6H 1M1',
    '1200 Dundas St E, Toronto, ON M4M 1S2',
    
    // Different provinces
    '1300 Robson Street, Vancouver, BC V6E 1C5',
    '1400 17th Avenue SW, Calgary, AB T2T 0A1',
    '1500 Portage Avenue, Winnipeg, MB R3G 0W4',
    '1600 Water Street, St. John\'s, NL A1C 1A9',
    
    // Rural addresses
    '1700 County Road 42, Essex, ON N8M 2X5',
    '1800 Regional Road 25, Niagara Falls, ON L2E 6S6',
    '1900 Highway 7 East, Markham, ON L3R 1A3',
    
    // Condo/apartment complexes
    '2000 Islington Avenue Unit 1205, Toronto, ON M8V 4B8',
    '2100 Lake Shore Boulevard W Apt 807, Toronto, ON M8V 1A1',
    '2200 The Esplanade Suite 1501, Toronto, ON M5E 1A6',
    
    // Edge cases
    '2300 St. Clair Avenue W, Toronto, ON M6N 1K8',
    '2400 St. George Street, Toronto, ON M5S 3G3',
    '2500 Avenue Rd, Toronto, ON M4V 2J7',
    
    // Problematic formats
    'Unit 2600, 123 Main Street, Toronto, ON M5V 1A1',
    'Apt 2700 - 456 Queen Street, Toronto, ON M5V 2A5',
    '2800 Unknown Street, Toronto',
    
    // International format variations
    '3100 Maple Street, Toronto, Ontario, Canada',
    '3200 Oak Avenue, Toronto, ON, Canada',
    '3300 Pine Road, Toronto, Canada',
    
    // More complex Toronto area codes
    '3400 Yonge Street, Toronto C01, ON M4N 2L4',
    '3500 Bathurst Street, Toronto C02, ON M5T 2S8',
    '3600 Eglinton Avenue E, Toronto E01, ON M4P 1A6',
    '3700 Jane Street, Toronto W02, ON M3N 2K1',
    
    // Mississauga/GTA variations
    '3800 Hurontario Street, Mississauga, ON L5A 3Y8',
    '3900 Burnhamthorpe Road W, Mississauga, ON L5C 2E7',
    '4000 Dixie Road, Mississauga, ON L4Y 2A6',
    
    // Brampton variations
    '4100 Main Street N, Brampton, ON L6V 1P8',
    '4200 Queen Street E, Brampton, ON L6V 1C4',
    
    // Richmond Hill/Markham
    '4300 Yonge Street, Richmond Hill, ON L4C 1V4',
    '4400 Highway 7, Markham, ON L3R 1A3',
    
    // Oakville/Burlington
    '4500 Lakeshore Road W, Oakville, ON L6K 1G6',
    '4600 Brant Street, Burlington, ON L7R 2G6',
    
    // Ajax/Pickering/Whitby
    '4700 Kingston Road, Ajax, ON L1T 3G2',
    '4800 Brock Street, Whitby, ON L1N 4J3',
    
    // Oshawa
    '4900 King Street W, Oshawa, ON L1J 2K5',
    
    // London area
    '5000 Richmond Street, London, ON N6A 3K7',
    '5100 Oxford Street E, London, ON N5Y 3H7',
    '5200 Wonderland Road S, London, ON N6K 1M6',
    
    // Hamilton area
    '5300 King Street E, Hamilton, ON L8N 1B2',
    '5400 Main Street W, Hamilton, ON L8S 1A8',
    
    // Kitchener/Waterloo
    '5500 King Street N, Waterloo, ON N2J 2W9',
    '5600 Weber Street N, Waterloo, ON N2L 4E7',
    
    // Windsor area
    '5700 Ouellette Avenue, Windsor, ON N9A 1C7',
    '5800 Tecumseh Road E, Windsor, ON N8T 1E7',
    
    // Ottawa area
    '5900 Bank Street, Ottawa, ON K1S 3T4',
    '6000 Rideau Street, Ottawa, ON K1N 5Y4',
]);

// Remove duplicates and empty addresses
$testAddresses = array_unique(array_filter($testAddresses));

echo "✅ Collected " . count($testAddresses) . " unique test addresses\n\n";

if (count($testAddresses) < 50) {
    echo "⚠️  Warning: Only " . count($testAddresses) . " addresses available. Consider importing more listings first.\n\n";
}

// Initialize services
$normalizer = new AddressNormalizer();

// Rate limiting tracker
$lastRequest = 0;
$requestCount = 0;

// Geocoding function with detailed analysis
function analyze_geocode($addressVariations, $originalAddress) {
    global $lastRequest, $requestCount;
    
    $attempts = [];
    
    foreach ($addressVariations as $index => $address) {
        $requestCount++;
        
        // Rate limiting - respect 1 request per second
        $timeSince = time() - $lastRequest;
        if ($timeSince < 1) {
            sleep(1 - $timeSince);
        }
        $lastRequest = time();
        
        $encoded_address = urlencode($address);
        $url = "https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=ca&q={$encoded_address}";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'header' => "User-Agent: WordPress TREB Plugin Analysis/1.0\r\n"
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        $attempt = [
            'variation' => $address,
            'success' => false,
            'lat' => null,
            'lng' => null,
            'display_name' => null,
            'error' => null
        ];
        
        if ($response === false) {
            $attempt['error'] = 'HTTP request failed';
        } else {
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $attempt['error'] = 'JSON decode error: ' . json_last_error_msg();
            } elseif (empty($data)) {
                $attempt['error'] = 'No results returned';
            } elseif (!isset($data[0]['lat']) || !isset($data[0]['lon'])) {
                $attempt['error'] = 'Invalid response format';
            } else {
                $attempt['success'] = true;
                $attempt['lat'] = floatval($data[0]['lat']);
                $attempt['lng'] = floatval($data[0]['lon']);
                $attempt['display_name'] = $data[0]['display_name'] ?? 'N/A';
                
                // Check if it's not the default Toronto coordinates
                if (abs($attempt['lat'] - DEFAULT_LAT) > 0.01 || abs($attempt['lng'] - DEFAULT_LNG) > 0.01) {
                    $attempts[] = $attempt;
                    return [
                        'success' => true,
                        'result' => $attempt,
                        'attempts' => $attempts
                    ];
                } else {
                    $attempt['error'] = 'Returned default Toronto coordinates';
                }
            }
        }
        
        $attempts[] = $attempt;
    }
    
    return [
        'success' => false,
        'result' => null,
        'attempts' => $attempts
    ];
}

// Test results tracking
$results = [
    'total' => 0,
    'successful' => 0,
    'failed' => 0,
    'failure_patterns' => [],
    'successful_examples' => [],
    'failed_examples' => []
];

echo "🧪 Starting comprehensive geocoding analysis...\n";
echo "⏱️  This will take approximately " . ceil(count($testAddresses) * 4 / 60) . " minutes due to rate limiting.\n";
echo str_repeat("=", 120) . "\n";

$startTime = time();

foreach ($testAddresses as $index => $rawAddress) {
    $results['total']++;
    
    echo sprintf("[%3d/%3d] Analyzing: %s\n", $index + 1, count($testAddresses), substr($rawAddress, 0, 80));
    
    // Generate address variations
    $variations = $normalizer->normalize($rawAddress);
    echo sprintf("          Generated %d variations\n", count($variations));
    
    // Test geocoding
    $geocodeResult = analyze_geocode($variations, $rawAddress);
    
    if ($geocodeResult['success']) {
        $results['successful']++;
        echo "          ✅ SUCCESS: " . $geocodeResult['result']['lat'] . ", " . $geocodeResult['result']['lng'] . "\n";
        echo "          📍 Location: " . substr($geocodeResult['result']['display_name'], 0, 80) . "...\n";
        echo "          🎯 Used variation: " . $geocodeResult['result']['variation'] . "\n";
        
        // Store successful example
        if (count($results['successful_examples']) < 10) {
            $results['successful_examples'][] = [
                'original' => $rawAddress,
                'successful_variation' => $geocodeResult['result']['variation'],
                'location' => $geocodeResult['result']['display_name']
            ];
        }
    } else {
        $results['failed']++;
        echo "          ❌ FAILED: All variations failed\n";
        
        // Analyze failure patterns
        $failureReasons = [];
        foreach ($geocodeResult['attempts'] as $attempt) {
            if (!$attempt['success']) {
                $failureReasons[] = $attempt['error'];
                echo "          💥 " . $attempt['variation'] . " -> " . $attempt['error'] . "\n";
            }
        }
        
        // Categorize failure
        $failureCategory = 'unknown';
        if (in_array('No results returned', $failureReasons)) {
            $failureCategory = 'no_results';
        } elseif (in_array('HTTP request failed', $failureReasons)) {
            $failureCategory = 'http_error';
        } elseif (in_array('Returned default Toronto coordinates', $failureReasons)) {
            $failureCategory = 'default_coordinates';
        }
        
        if (!isset($results['failure_patterns'][$failureCategory])) {
            $results['failure_patterns'][$failureCategory] = 0;
        }
        $results['failure_patterns'][$failureCategory]++;
        
        // Store failed example
        if (count($results['failed_examples']) < 20) {
            $results['failed_examples'][] = [
                'original' => $rawAddress,
                'variations' => array_column($geocodeResult['attempts'], 'variation'),
                'errors' => $failureReasons,
                'category' => $failureCategory
            ];
        }
    }
    
    echo "\n";
    
    // Progress update every 25 addresses
    if (($index + 1) % 25 === 0) {
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

// Comprehensive analysis results
echo str_repeat("=", 120) . "\n";
echo "COMPREHENSIVE GEOCODING ANALYSIS RESULTS\n";
echo str_repeat("=", 120) . "\n";

$successRate = ($results['successful'] / $results['total']) * 100;

echo sprintf("📊 OVERALL STATISTICS:\n");
echo sprintf("   Total addresses tested: %d\n", $results['total']);
echo sprintf("   Successful geocoding: %d (%.1f%%)\n", $results['successful'], $successRate);
echo sprintf("   Failed geocoding: %d (%.1f%%)\n", $results['failed'], 100 - $successRate);
echo sprintf("   Total API requests made: %d\n", $requestCount);
echo sprintf("   Total time: %d minutes %d seconds\n", floor($totalTime / 60), $totalTime % 60);

echo "\n📈 FAILURE PATTERN ANALYSIS:\n";
foreach ($results['failure_patterns'] as $pattern => $count) {
    $percentage = ($count / $results['failed']) * 100;
    echo sprintf("   %s: %d failures (%.1f%% of failures)\n", ucwords(str_replace('_', ' ', $pattern)), $count, $percentage);
}

// Show detailed failure examples
echo "\n" . str_repeat("=", 120) . "\n";
echo "DETAILED FAILURE ANALYSIS (First 10 failures)\n";
echo str_repeat("=", 120) . "\n";

$showCount = min(10, count($results['failed_examples']));
for ($i = 0; $i < $showCount; $i++) {
    $failure = $results['failed_examples'][$i];
    echo sprintf("\n🔍 Failure %d [%s]:\n", $i + 1, strtoupper($failure['category']));
    echo sprintf("   Original: %s\n", $failure['original']);
    echo sprintf("   Variations tried:\n");
    foreach ($failure['variations'] as $j => $variation) {
        echo sprintf("     %d. %s\n", $j + 1, $variation);
    }
    echo sprintf("   Errors: %s\n", implode(', ', array_unique($failure['errors'])));
}

// Recommendations
echo "\n" . str_repeat("=", 120) . "\n";
echo "RECOMMENDATIONS FOR IMPROVEMENT\n";
echo str_repeat("=", 120) . "\n";

if ($successRate < 90) {
    echo "🚨 CRITICAL: Success rate is below 90% - Major improvements needed!\n\n";
    
    echo "🔧 IMMEDIATE ACTIONS NEEDED:\n";
    
    if (isset($results['failure_patterns']['no_results']) && $results['failure_patterns']['no_results'] > 0) {
        echo "   1. ADDRESS NORMALIZATION: " . $results['failure_patterns']['no_results'] . " addresses returned no results\n";
        echo "      - Improve address parsing for complex formats\n";
        echo "      - Add more address variations (abbreviated forms, alternative spellings)\n";
        echo "      - Handle edge cases better (unit formats, street name variations)\n\n";
    }
    
    if (isset($results['failure_patterns']['http_error']) && $results['failure_patterns']['http_error'] > 0) {
        echo "   2. API RELIABILITY: " . $results['failure_patterns']['http_error'] . " HTTP errors occurred\n";
        echo "      - Implement retry logic with exponential backoff\n";
        echo "      - Add fallback geocoding services (Google Maps, MapBox)\n";
        echo "      - Improve error handling and timeout settings\n\n";
    }
    
    echo "   3. ALTERNATIVE GEOCODING SERVICES:\n";
    echo "      - Consider Google Maps Geocoding API for higher accuracy\n";
    echo "      - Implement MapBox geocoding as secondary fallback\n";
    echo "      - Use multiple services in cascade for maximum coverage\n\n";
    
} elseif ($successRate < 95) {
    echo "⚠️  GOOD: Success rate is 90-95% but can be improved\n\n";
    echo "🔧 OPTIMIZATION OPPORTUNITIES:\n";
    echo "   - Fine-tune address normalization for edge cases\n";
    echo "   - Add retry logic for failed requests\n";
    echo "   - Consider secondary geocoding service for failures\n\n";
} else {
    echo "✅ EXCELLENT: Success rate is 95%+ - Minor optimizations possible\n\n";
}

echo "💡 NEXT STEPS:\n";
echo "   1. Analyze the detailed failure examples above\n";
echo "   2. Improve AddressNormalizer based on failure patterns\n";
echo "   3. Add retry logic and fallback geocoding services\n";
echo "   4. Re-run this analysis to measure improvements\n";
echo "   5. Target 99%+ success rate for production deployment\n";

echo "\n=== Analysis Complete ===\n";
