<?php
/**
 * Standalone Geocoding Accuracy Test
 * 
 * Tests geocoding accuracy without WordPress dependencies
 */

// Load the new AddressNormalizer
require_once(__DIR__ . '/includes/Services/AddressNormalizer.php');

use Shift8\TREB\Services\AddressNormalizer;

echo "=== Standalone Geocoding Accuracy Test ===\n\n";

// Default Toronto coordinates (used when geocoding fails)
const DEFAULT_LAT = 43.6532;
const DEFAULT_LNG = -79.3832;

// Test addresses - focusing on previously problematic ones
$testAddresses = [
    '395 Dundas Street W 603, Oakville, ON L6M 5R8',
    '3328 Oriole Drive, London South, ON N6M 0K1, London South, ON',
    '1425 Gerrard Street E 2nd Flr, Toronto E01, ON M4L 1Z7, Toronto E01, ON',
    '10 Old Mill Trail 305, Toronto W08, ON M8X 2Y9, Toronto W08, ON',
    '103 The Queensway Avenue 2707, Toronto W01, ON M6S 5B3',
    '127 South Lancelot Road, Huntsville, ON P0B 1M0',
    '282 Beta Street BSMT, Toronto W06, ON M8W 4J1',
    '55 East Liberty Street 1210, Toronto C01, ON M6K 3P9',
];

echo "Testing " . count($testAddresses) . " addresses with REAL OpenStreetMap API calls...\n";
echo "Rate limited to 1 request per second - this will take about " . (count($testAddresses) * 2) . " seconds.\n\n";

// Initialize services
$normalizer = new AddressNormalizer();

// Create a simplified version of the current regex cleaning
function clean_address_regex($address) {
    $variations = array();
    
    // Base cleaning - remove Toronto area codes
    $base_address = preg_replace('/,\s*Toronto\s+[A-Z]\d{2}(?:,\s*ON)?/i', ', Toronto, ON', $address);
    
    // Remove duplicate city names
    $base_address = preg_replace('/,\s*([^,]+),\s*ON\s+([A-Z]\d[A-Z]\s+\d[A-Z]\d),\s*\1,\s*ON/i', ', $1, ON $2', $base_address);
    
    // Remove unit/apartment designations
    $base_address = preg_replace('/(\b(?:Street|St|Avenue|Ave|Road|Rd|Drive|Dr|Boulevard|Blvd|Crescent|Cres|Circle|Cir|Court|Ct|Lane|Ln|Way|Place|Pl|Trail|Tr)\s+[NSEW])\s+\d+(?:st|nd|rd|th)?(?:\s+Flr?)?/i', '$1', $base_address);
    $base_address = preg_replace('/(\b(?:Street|St|Avenue|Ave|Road|Rd|Drive|Dr|Boulevard|Blvd|Crescent|Cres|Circle|Cir|Court|Ct|Lane|Ln|Way|Place|Pl|Trail|Tr))\s+\d+/i', '$1', $base_address);
    $base_address = preg_replace('/\s+(BSMT|MAIN|APT\s*\d+|UNIT\s*\d+|#\d+)(?:\s*,|\s*$)/i', '', $base_address);
    
    // Add Canada suffix
    if (!preg_match('/,\s*(Canada|CA)\s*$/i', $base_address)) {
        $base_address .= ', Canada';
    }
    
    $variations[] = trim($base_address);
    
    // Add expanded directional variation
    $expanded = str_replace(array(' W,', ' E,', ' N,', ' S,'), array(' West,', ' East,', ' North,', ' South,'), $base_address);
    if (!in_array(trim($expanded), $variations)) {
        $variations[] = trim($expanded);
    }
    
    return array_unique($variations);
}

// Rate limiting tracker
$lastRequest = 0;

// Geocoding function
function test_geocode($addressVariations, $method) {
    global $lastRequest;
    
    foreach ($addressVariations as $address) {
        // Rate limiting - respect 1 request per second
        $timeSince = time() - $lastRequest;
        if ($timeSince < 1) {
            echo "      ⏱️  Rate limiting: sleeping " . (1 - $timeSince) . " seconds...\n";
            sleep(1 - $timeSince);
        }
        $lastRequest = time();
        
        $encoded_address = urlencode($address);
        $url = "https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=ca&q={$encoded_address}";
        
        echo "      🌐 Trying: " . $address . "\n";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => "User-Agent: WordPress TREB Plugin Test/1.0\r\n"
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            echo "      ❌ HTTP request failed\n";
            continue;
        }
        
        $data = json_decode($response, true);
        
        if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
            return array(
                'lat' => floatval($data[0]['lat']),
                'lng' => floatval($data[0]['lon']),
                'successful_variation' => $address,
                'display_name' => $data[0]['display_name'] ?? 'N/A'
            );
        } else {
            echo "      ❌ No results for this variation\n";
        }
    }
    
    return false;
}

// Test results
$results = [
    'total' => 0,
    'regex_success' => 0,
    'normalizer_success' => 0,
    'both_success' => 0,
    'both_failed' => 0,
    'normalizer_better' => 0,
    'regex_better' => 0,
    'details' => []
];

echo str_repeat("=", 100) . "\n";

foreach ($testAddresses as $index => $rawAddress) {
    $results['total']++;
    
    echo sprintf("[%d/%d] Testing: %s\n", $index + 1, count($testAddresses), $rawAddress);
    
    // Test 1: Current regex method
    echo "   🔄 Testing current regex method...\n";
    $regexVariations = clean_address_regex($rawAddress);
    echo "      Generated " . count($regexVariations) . " variations\n";
    $regexResult = test_geocode($regexVariations, 'regex');
    
    $regexSuccess = $regexResult && 
                   abs($regexResult['lat'] - DEFAULT_LAT) > 0.01 && 
                   abs($regexResult['lng'] - DEFAULT_LNG) > 0.01;
    
    if ($regexSuccess) {
        $results['regex_success']++;
        echo "   ✅ REGEX SUCCESS: " . $regexResult['lat'] . ", " . $regexResult['lng'] . "\n";
        echo "      Location: " . substr($regexResult['display_name'], 0, 80) . "...\n";
    } else {
        echo "   ❌ REGEX FAILED: No valid coordinates found\n";
    }
    
    // Test 2: New normalizer method
    echo "   🔄 Testing new normalizer method...\n";
    $normalizerVariations = $normalizer->normalize($rawAddress);
    echo "      Generated " . count($normalizerVariations) . " variations\n";
    $normalizerResult = test_geocode($normalizerVariations, 'normalizer');
    
    $normalizerSuccess = $normalizerResult && 
                        abs($normalizerResult['lat'] - DEFAULT_LAT) > 0.01 && 
                        abs($normalizerResult['lng'] - DEFAULT_LNG) > 0.01;
    
    if ($normalizerSuccess) {
        $results['normalizer_success']++;
        echo "   ✅ NORMALIZER SUCCESS: " . $normalizerResult['lat'] . ", " . $normalizerResult['lng'] . "\n";
        echo "      Location: " . substr($normalizerResult['display_name'], 0, 80) . "...\n";
    } else {
        echo "   ❌ NORMALIZER FAILED: No valid coordinates found\n";
    }
    
    // Compare results
    if ($regexSuccess && $normalizerSuccess) {
        $results['both_success']++;
        echo "   🎯 BOTH METHODS SUCCEEDED\n";
    } elseif (!$regexSuccess && !$normalizerSuccess) {
        $results['both_failed']++;
        echo "   💥 BOTH METHODS FAILED\n";
    } elseif ($normalizerSuccess && !$regexSuccess) {
        $results['normalizer_better']++;
        echo "   🚀 NORMALIZER BETTER (only normalizer succeeded)\n";
    } elseif ($regexSuccess && !$normalizerSuccess) {
        $results['regex_better']++;
        echo "   📉 REGEX BETTER (only regex succeeded)\n";
    }
    
    echo "\n";
}

// Final results
echo str_repeat("=", 100) . "\n";
echo "GEOCODING ACCURACY COMPARISON RESULTS\n";
echo str_repeat("=", 100) . "\n";

$regexSuccessRate = ($results['regex_success'] / $results['total']) * 100;
$normalizerSuccessRate = ($results['normalizer_success'] / $results['total']) * 100;

echo sprintf("Total addresses tested: %d\n", $results['total']);
echo sprintf("Current regex method success: %d/%d (%.1f%%)\n", $results['regex_success'], $results['total'], $regexSuccessRate);
echo sprintf("New normalizer method success: %d/%d (%.1f%%)\n", $results['normalizer_success'], $results['total'], $normalizerSuccessRate);
echo "\n";
echo sprintf("Both methods succeeded: %d (%.1f%%)\n", $results['both_success'], ($results['both_success'] / $results['total']) * 100);
echo sprintf("Both methods failed: %d (%.1f%%)\n", $results['both_failed'], ($results['both_failed'] / $results['total']) * 100);
echo sprintf("Only normalizer succeeded: %d (%.1f%%)\n", $results['normalizer_better'], ($results['normalizer_better'] / $results['total']) * 100);
echo sprintf("Only regex succeeded: %d (%.1f%%)\n", $results['regex_better'], ($results['regex_better'] / $results['total']) * 100);

$improvement = $normalizerSuccessRate - $regexSuccessRate;
echo "\n";
if ($improvement > 0) {
    echo sprintf("🎉 IMPROVEMENT: +%.1f%% success rate with normalizer!\n", $improvement);
    echo "✅ PROCEED: Replace regex with normalizer for better geocoding accuracy\n";
} elseif ($improvement < 0) {
    echo sprintf("📉 REGRESSION: %.1f%% lower success rate with normalizer\n", abs($improvement));
    echo "⚠️  CAUTION: Consider improvements or hybrid approach\n";
} else {
    echo "🤝 EQUAL: Same success rate - proceed for better maintainability\n";
}

echo "\n=== Test Complete ===\n";
