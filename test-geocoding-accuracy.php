<?php
/**
 * Geocoding Accuracy Test
 * 
 * Tests actual geocoding accuracy by comparing:
 * 1. Current regex-based address cleaning + OpenStreetMap API
 * 2. New AddressNormalizer + OpenStreetMap API
 * 
 * This tests the REAL impact on geocoding success rates.
 */

// Load WordPress
require_once('/home/ck/git/shift8-projects/shift8.local/wp-content/plugins/shift8-treb/shift8-treb.php');

// Load the new AddressNormalizer
require_once(__DIR__ . '/includes/Services/AddressNormalizer.php');

use Shift8\TREB\Services\AddressNormalizer;

echo "=== Geocoding Accuracy Comparison Test ===\n\n";

// Default Toronto coordinates (used when geocoding fails)
const DEFAULT_LAT = 43.6532;
const DEFAULT_LNG = -79.3832;

// Test addresses - focusing on previously problematic ones
$testAddresses = [
    // Previously problematic addresses that failed geocoding
    '395 Dundas Street W 603, Oakville, ON L6M 5R8',
    '3328 Oriole Drive, London South, ON N6M 0K1, London South, ON',
    '1425 Gerrard Street E 2nd Flr, Toronto E01, ON M4L 1Z7, Toronto E01, ON',
    '10 Old Mill Trail 305, Toronto W08, ON M8X 2Y9, Toronto W08, ON',
    '12 Old Mill Trail 503, Toronto W08, ON M8X 2Z4',
    '103 The Queensway Avenue 2707, Toronto W01, ON M6S 5B3',
    
    // Current database addresses (should work with both)
    '127 South Lancelot Road, Huntsville, ON P0B 1M0',
    '700 Humberwood Boulevard PH21, Toronto W10, ON M9W 7J4',
    '282 Beta Street BSMT, Toronto W06, ON M8W 4J1',
    '29 Nelson Street, Brant, ON L0R 2H6',
    
    // Additional complex cases
    '55 East Liberty Street 1210, Toronto C01, ON M6K 3P9',
    '74 Upper Highlands Drive, Brampton, ON L6Z 4V9',
    '123 Main Street Unit 456, Toronto, ON M5V 1A1',
    '456 King Street E Suite 789, Toronto, ON M5A 1L9',
    '321 Yonge Street #1001, Toronto, ON M5B 1R7',
];

echo "Testing " . count($testAddresses) . " addresses with REAL OpenStreetMap API calls...\n";
echo "This will take a few minutes due to rate limiting (1 request per second).\n\n";

// Initialize services
$normalizer = new AddressNormalizer();

// Create a simplified version of the current regex cleaning for comparison
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
    $base_address = preg_replace('/(?<!\b[NSEW])\s+(\d+(?:st|nd|rd|th)\s+Flr?)(?:\s*,|\s*$)/i', '', $base_address);
    
    // Add Canada suffix
    if (!preg_match('/,\s*(Canada|CA)\s*$/i', $base_address)) {
        $base_address .= ', Canada';
    }
    
    $variations[] = trim($base_address);
    
    // Add a couple more variations (simplified version of current logic)
    $expanded = str_replace(array(' W,', ' E,', ' N,', ' S,'), array(' West,', ' East,', ' North,', ' South,'), $base_address);
    if (!in_array(trim($expanded), $variations)) {
        $variations[] = trim($expanded);
    }
    
    return array_unique($variations);
}

// Geocoding function (simplified version of current implementation)
function test_geocode($addressVariations, $method) {
    foreach ($addressVariations as $address) {
        $encoded_address = urlencode($address);
        $url = "https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=ca&q={$encoded_address}";
        
        // Rate limiting - respect 1 request per second
        static $lastRequest = 0;
        $timeSince = time() - $lastRequest;
        if ($timeSince < 1) {
            sleep(1 - $timeSince);
        }
        $lastRequest = time();
        
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'WordPress TREB Plugin Test/1.0'
            )
        ));
        
        if (is_wp_error($response)) {
            continue;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
            return array(
                'lat' => floatval($data[0]['lat']),
                'lng' => floatval($data[0]['lon']),
                'successful_variation' => $address,
                'display_name' => $data[0]['display_name'] ?? 'N/A'
            );
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

echo str_repeat("=", 120) . "\n";

foreach ($testAddresses as $index => $rawAddress) {
    $results['total']++;
    
    echo sprintf("[%2d/%2d] Testing: %s\n", $index + 1, count($testAddresses), $rawAddress);
    
    // Test 1: Current regex method
    echo "   🔄 Testing current regex method...\n";
    $regexVariations = clean_address_regex($rawAddress);
    $regexResult = test_geocode($regexVariations, 'regex');
    
    $regexSuccess = $regexResult && 
                   abs($regexResult['lat'] - DEFAULT_LAT) > 0.01 && 
                   abs($regexResult['lng'] - DEFAULT_LNG) > 0.01;
    
    if ($regexSuccess) {
        $results['regex_success']++;
        echo "   ✅ REGEX SUCCESS: " . $regexResult['lat'] . ", " . $regexResult['lng'] . "\n";
        echo "      Location: " . $regexResult['display_name'] . "\n";
        echo "      Used variation: " . $regexResult['successful_variation'] . "\n";
    } else {
        echo "   ❌ REGEX FAILED: No valid coordinates found\n";
    }
    
    // Test 2: New normalizer method
    echo "   🔄 Testing new normalizer method...\n";
    $normalizerVariations = $normalizer->normalize($rawAddress);
    $normalizerResult = test_geocode($normalizerVariations, 'normalizer');
    
    $normalizerSuccess = $normalizerResult && 
                        abs($normalizerResult['lat'] - DEFAULT_LAT) > 0.01 && 
                        abs($normalizerResult['lng'] - DEFAULT_LNG) > 0.01;
    
    if ($normalizerSuccess) {
        $results['normalizer_success']++;
        echo "   ✅ NORMALIZER SUCCESS: " . $normalizerResult['lat'] . ", " . $normalizerResult['lng'] . "\n";
        echo "      Location: " . $normalizerResult['display_name'] . "\n";
        echo "      Used variation: " . $normalizerResult['successful_variation'] . "\n";
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
    
    // Store detailed results
    $results['details'][] = [
        'address' => $rawAddress,
        'regex_success' => $regexSuccess,
        'normalizer_success' => $normalizerSuccess,
        'regex_result' => $regexResult,
        'normalizer_result' => $normalizerResult,
        'regex_variations' => $regexVariations,
        'normalizer_variations' => $normalizerVariations
    ];
    
    echo "\n";
}

// Final results
echo str_repeat("=", 120) . "\n";
echo "GEOCODING ACCURACY COMPARISON RESULTS\n";
echo str_repeat("=", 120) . "\n";

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
} elseif ($improvement < 0) {
    echo sprintf("📉 REGRESSION: %.1f%% lower success rate with normalizer\n", abs($improvement));
} else {
    echo "🤝 EQUAL: Same success rate for both methods\n";
}

// Show cases where normalizer was better
if ($results['normalizer_better'] > 0) {
    echo "\n" . str_repeat("=", 120) . "\n";
    echo "CASES WHERE NORMALIZER WAS BETTER\n";
    echo str_repeat("=", 120) . "\n";
    
    foreach ($results['details'] as $detail) {
        if ($detail['normalizer_success'] && !$detail['regex_success']) {
            echo "\nAddress: " . $detail['address'] . "\n";
            echo "Normalizer found: " . $detail['normalizer_result']['display_name'] . "\n";
            echo "Successful variation: " . $detail['normalizer_result']['successful_variation'] . "\n";
            echo "Regex variations tried: " . implode(' | ', $detail['regex_variations']) . "\n";
        }
    }
}

// Recommendation
echo "\n" . str_repeat("=", 120) . "\n";
echo "RECOMMENDATION\n";
echo str_repeat("=", 120) . "\n";

if ($improvement >= 5) {
    echo "✅ PROCEED: Significant improvement (+5% or more) - Replace regex with normalizer\n";
} elseif ($improvement >= 0) {
    echo "✅ PROCEED: Equal or better performance - Replace for better maintainability\n";
} else {
    echo "⚠️  CAUTION: Lower performance - Consider hybrid approach or improvements\n";
}

echo "\n=== Geocoding Accuracy Test Complete ===\n";
