<?php
/**
 * Test Address Normalization Script
 * 
 * Tests the new AddressNormalizer against real TREB listing addresses
 * to validate effectiveness before full integration.
 */

// Load WordPress
require_once('/home/ck/git/shift8-projects/shift8.local/wp-config.php');

// Load the new AddressNormalizer
require_once(__DIR__ . '/includes/Services/AddressNormalizer.php');

use Shift8\TREB\Services\AddressNormalizer;

echo "=== TREB Address Normalization Test ===\n\n";

// Get 100 recent listing addresses from the database
global $wpdb;

$query = "
    SELECT DISTINCT pm.meta_value as address 
    FROM {$wpdb->postmeta} pm 
    INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
    WHERE pm.meta_key = 'shift8_treb_unparsed_address' 
    AND p.post_type = 'post' 
    AND pm.meta_value != '' 
    ORDER BY p.post_date DESC 
    LIMIT 100
";

$addresses = $wpdb->get_col($query);

if (empty($addresses)) {
    echo "❌ No TREB listing addresses found in database.\n";
    echo "Please run a sync first to import some listings.\n";
    exit(1);
}

echo "✅ Found " . count($addresses) . " listing addresses to test\n\n";

// Initialize the normalizer
$normalizer = new AddressNormalizer();

// Test results tracking
$results = [
    'total' => 0,
    'successful_parse' => 0,
    'multiple_variations' => 0,
    'failed_parse' => 0,
    'examples' => []
];

echo "Testing address normalization...\n";
echo str_repeat("=", 80) . "\n";

foreach ($addresses as $index => $rawAddress) {
    $results['total']++;
    
    echo sprintf("[%3d/%3d] Testing: %s\n", $index + 1, count($addresses), $rawAddress);
    
    try {
        // Test the new normalization
        $variations = $normalizer->normalize($rawAddress);
        
        if (empty($variations)) {
            $results['failed_parse']++;
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
            
            // Store some examples for detailed analysis
            if (count($results['examples']) < 10) {
                $results['examples'][] = [
                    'original' => $rawAddress,
                    'variations' => $variations
                ];
            }
        }
        
    } catch (Exception $e) {
        $results['failed_parse']++;
        echo "   ❌ ERROR: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // Add a small delay to avoid overwhelming output
    if ($index % 10 === 9) {
        echo "--- Progress: " . ($index + 1) . "/" . count($addresses) . " addresses processed ---\n\n";
    }
}

// Display summary results
echo str_repeat("=", 80) . "\n";
echo "SUMMARY RESULTS\n";
echo str_repeat("=", 80) . "\n";

$successRate = ($results['successful_parse'] / $results['total']) * 100;
$multiVariationRate = ($results['multiple_variations'] / $results['total']) * 100;

echo sprintf("Total addresses tested: %d\n", $results['total']);
echo sprintf("Successfully parsed: %d (%.1f%%)\n", $results['successful_parse'], $successRate);
echo sprintf("Failed to parse: %d (%.1f%%)\n", $results['failed_parse'], 100 - $successRate);
echo sprintf("Generated multiple variations: %d (%.1f%%)\n", $results['multiple_variations'], $multiVariationRate);

echo "\n" . str_repeat("=", 80) . "\n";
echo "DETAILED EXAMPLES\n";
echo str_repeat("=", 80) . "\n";

foreach ($results['examples'] as $i => $example) {
    echo sprintf("\nExample %d:\n", $i + 1);
    echo "Original: " . $example['original'] . "\n";
    echo "Normalized variations:\n";
    
    foreach ($example['variations'] as $j => $variation) {
        echo sprintf("  %d. %s\n", $j + 1, $variation);
    }
}

// Performance assessment
echo "\n" . str_repeat("=", 80) . "\n";
echo "ASSESSMENT\n";
echo str_repeat("=", 80) . "\n";

if ($successRate >= 95) {
    echo "🎉 EXCELLENT: Success rate >= 95% - Ready for production!\n";
} elseif ($successRate >= 90) {
    echo "✅ GOOD: Success rate >= 90% - Should improve current system\n";
} elseif ($successRate >= 80) {
    echo "⚠️  FAIR: Success rate >= 80% - May need refinement\n";
} else {
    echo "❌ POOR: Success rate < 80% - Needs significant improvement\n";
}

echo "\nNext steps:\n";
if ($successRate >= 90) {
    echo "- Proceed with integration into the plugin\n";
    echo "- Update tests to use new normalization\n";
    echo "- Test geocoding accuracy with real API calls\n";
} else {
    echo "- Analyze failed cases and improve parsing logic\n";
    echo "- Add more address patterns and edge cases\n";
    echo "- Consider hybrid approach with current regex fallback\n";
}

echo "\n=== Test Complete ===\n";
