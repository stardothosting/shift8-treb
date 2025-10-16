<?php
/**
 * Comprehensive Address Normalization Test
 * 
 * Tests the new AddressNormalizer against a comprehensive set of problematic
 * TREB address patterns that we've encountered in production.
 */

// Load the new AddressNormalizer
require_once(__DIR__ . '/includes/Services/AddressNormalizer.php');

use Shift8\TREB\Services\AddressNormalizer;

echo "=== Comprehensive TREB Address Normalization Test ===\n\n";

// Test addresses including all the problematic ones we've encountered
$testAddresses = [
    // Current database addresses
    '127 South Lancelot Road, Huntsville, ON P0B 1M0',
    '700 Humberwood Boulevard PH21, Toronto W10, ON M9W 7J4',
    '282 Beta Street BSMT, Toronto W06, ON M8W 4J1',
    '29 Nelson Street, Brant, ON L0R 2H6',
    '389 Burnhamthorpe Road, Toronto W08, ON M9B 2A7',
    '49 Albert Avenue, Toronto W06, ON M8V 2L6',
    '84 North Heights Road, Toronto W08, ON M9B 2T8',
    
    // Previously problematic addresses from our debugging
    '395 Dundas Street W 603, Oakville, ON L6M 5R8',
    '3328 Oriole Drive, London South, ON N6M 0K1, London South, ON',
    '1425 Gerrard Street E 2nd Flr, Toronto E01, ON M4L 1Z7, Toronto E01, ON',
    '10 Old Mill Trail 305, Toronto W08, ON M8X 2Y9, Toronto W08, ON',
    '12 Old Mill Trail 503, Toronto W08, ON M8X 2Z4',
    '103 The Queensway Avenue 2707, Toronto W01, ON M6S 5B3',
    
    // Additional complex patterns
    '55 East Liberty Street 1210, Toronto C01, ON M6K 3P9',
    '74 Upper Highlands Drive, Brampton, ON L6Z 4V9',
    '123 Main Street Unit 456, Toronto, ON M5V 1A1',
    '789 Queen Street W Apt 12, Toronto, ON M6J 1G1',
    '456 King Street E Suite 789, Toronto, ON M5A 1L9',
    '321 Yonge Street #1001, Toronto, ON M5B 1R7',
    '654 Bay Street PH, Toronto, ON M5G 1M5',
    '987 College Street Lower, Toronto, ON M6H 1A1',
    '147 Spadina Avenue Upper, Toronto, ON M5V 2L1',
    '258 Bloor Street W 3rd Floor, Toronto, ON M5S 1V8',
    
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
    'Unit 2600, 123 Main Street, Toronto, ON M5V 1A1',
    'Apt 2700 - 456 Queen Street, Toronto, ON M5V 2A5',
    
    // Missing components
    '2800 Unknown Street, Toronto',
    '2900 Test Road',
    'Toronto, ON M5V 1A1',
    '3000',
    '',
    
    // International format variations
    '3100 Maple Street, Toronto, Ontario, Canada',
    '3200 Oak Avenue, Toronto, ON, Canada',
    '3300 Pine Road, Toronto, Canada',
];

echo "Testing " . count($testAddresses) . " addresses including problematic patterns...\n\n";

// Initialize the normalizer
$normalizer = new AddressNormalizer();

// Test results tracking
$results = [
    'total' => 0,
    'successful_parse' => 0,
    'multiple_variations' => 0,
    'failed_parse' => 0,
    'problematic_cases' => [],
    'excellent_cases' => []
];

echo "Testing address normalization...\n";
echo str_repeat("=", 100) . "\n";

foreach ($testAddresses as $index => $rawAddress) {
    $results['total']++;
    
    echo sprintf("[%3d/%3d] Testing: %s\n", $index + 1, count($testAddresses), $rawAddress);
    
    try {
        // Test the new normalization
        $variations = $normalizer->normalize($rawAddress);
        
        if (empty($variations)) {
            $results['failed_parse']++;
            $results['problematic_cases'][] = [
                'address' => $rawAddress,
                'issue' => 'No variations generated'
            ];
            echo "   ❌ FAILED: No variations generated\n";
        } else {
            $results['successful_parse']++;
            
            if (count($variations) > 1) {
                $results['multiple_variations']++;
            }
            
            echo "   ✅ SUCCESS: " . count($variations) . " variation(s) generated\n";
            
            foreach ($variations as $i => $variation) {
                echo "      " . ($i + 1) . ". " . $variation . "\n";
            }
            
            // Track excellent cases (good variety of variations)
            if (count($variations) >= 3) {
                $results['excellent_cases'][] = [
                    'address' => $rawAddress,
                    'variations' => $variations
                ];
            }
        }
        
    } catch (Exception $e) {
        $results['failed_parse']++;
        $results['problematic_cases'][] = [
            'address' => $rawAddress,
            'issue' => 'Exception: ' . $e->getMessage()
        ];
        echo "   ❌ ERROR: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // Add progress markers
    if (($index + 1) % 10 === 0) {
        echo "--- Progress: " . ($index + 1) . "/" . count($testAddresses) . " addresses processed ---\n\n";
    }
}

// Display comprehensive results
echo str_repeat("=", 100) . "\n";
echo "COMPREHENSIVE TEST RESULTS\n";
echo str_repeat("=", 100) . "\n";

$successRate = ($results['successful_parse'] / $results['total']) * 100;
$multiVariationRate = ($results['multiple_variations'] / $results['total']) * 100;

echo sprintf("Total addresses tested: %d\n", $results['total']);
echo sprintf("Successfully parsed: %d (%.1f%%)\n", $results['successful_parse'], $successRate);
echo sprintf("Failed to parse: %d (%.1f%%)\n", $results['failed_parse'], 100 - $successRate);
echo sprintf("Generated multiple variations: %d (%.1f%%)\n", $results['multiple_variations'], $multiVariationRate);
echo sprintf("Excellent cases (3+ variations): %d (%.1f%%)\n", count($results['excellent_cases']), (count($results['excellent_cases']) / $results['total']) * 100);

// Show problematic cases
if (!empty($results['problematic_cases'])) {
    echo "\n" . str_repeat("=", 100) . "\n";
    echo "PROBLEMATIC CASES REQUIRING ATTENTION\n";
    echo str_repeat("=", 100) . "\n";
    
    foreach ($results['problematic_cases'] as $i => $case) {
        echo sprintf("%d. %s\n", $i + 1, $case['address']);
        echo "   Issue: " . $case['issue'] . "\n\n";
    }
}

// Show some excellent examples
if (!empty($results['excellent_cases'])) {
    echo "\n" . str_repeat("=", 100) . "\n";
    echo "EXCELLENT EXAMPLES (showing first 5)\n";
    echo str_repeat("=", 100) . "\n";
    
    $showCount = min(5, count($results['excellent_cases']));
    for ($i = 0; $i < $showCount; $i++) {
        $case = $results['excellent_cases'][$i];
        echo sprintf("\nExample %d: %s\n", $i + 1, $case['address']);
        foreach ($case['variations'] as $j => $variation) {
            echo sprintf("  %d. %s\n", $j + 1, $variation);
        }
    }
}

// Final assessment
echo "\n" . str_repeat("=", 100) . "\n";
echo "FINAL ASSESSMENT\n";
echo str_repeat("=", 100) . "\n";

if ($successRate >= 95) {
    echo "🎉 EXCELLENT: Success rate >= 95% - Ready for production integration!\n";
    $recommendation = "PROCEED";
} elseif ($successRate >= 90) {
    echo "✅ GOOD: Success rate >= 90% - Should significantly improve current system\n";
    $recommendation = "PROCEED_WITH_MONITORING";
} elseif ($successRate >= 80) {
    echo "⚠️  FAIR: Success rate >= 80% - May need some refinement but still better than regex\n";
    $recommendation = "PROCEED_WITH_IMPROVEMENTS";
} else {
    echo "❌ POOR: Success rate < 80% - Needs significant improvement before integration\n";
    $recommendation = "NEEDS_WORK";
}

echo "\nComparison with current regex approach:\n";
echo "- Current regex: ~90-95% success rate with complex, hard-to-maintain code\n";
echo sprintf("- New normalizer: %.1f%% success rate with clean, maintainable OOP structure\n", $successRate);

echo "\nRecommendation: " . $recommendation . "\n";

switch ($recommendation) {
    case "PROCEED":
        echo "\nNext steps:\n";
        echo "✅ Integrate AddressNormalizer into PostManager\n";
        echo "✅ Create comprehensive geocoding service\n";
        echo "✅ Update all tests\n";
        echo "✅ Test with real geocoding API calls\n";
        break;
        
    case "PROCEED_WITH_MONITORING":
        echo "\nNext steps:\n";
        echo "✅ Integrate AddressNormalizer with fallback to regex for failed cases\n";
        echo "⚠️  Monitor problematic cases and improve parsing\n";
        echo "✅ Update tests and validate with real geocoding\n";
        break;
        
    case "PROCEED_WITH_IMPROVEMENTS":
        echo "\nNext steps:\n";
        echo "⚠️  Fix problematic cases identified above\n";
        echo "✅ Add hybrid approach (normalizer + regex fallback)\n";
        echo "✅ Extensive testing before production deployment\n";
        break;
        
    case "NEEDS_WORK":
        echo "\nNext steps:\n";
        echo "❌ Analyze and fix major parsing issues\n";
        echo "❌ Add more comprehensive address patterns\n";
        echo "❌ Consider alternative approaches\n";
        break;
}

echo "\n=== Comprehensive Test Complete ===\n";
