<?php
/**
 * Test Improved AddressNormalizer
 * 
 * Tests the improved normalizer against previously failing addresses
 */

// Load the improved AddressNormalizer
require_once(__DIR__ . '/includes/Services/AddressNormalizer.php');

use Shift8\TREB\Services\AddressNormalizer;

echo "=== Testing Improved AddressNormalizer ===\n\n";

// Previously failing addresses
$failingAddresses = [
    '29 Nelson Street, Brant, ON L0R 2H6',
    '3328 Oriole Drive, London South, ON N6M 0K1, London South, ON',
    '1425 Gerrard Street E 2nd Flr, Toronto E01, ON M4L 1Z7, Toronto E01, ON',
    '10 Old Mill Trail 305, Toronto W08, ON M8X 2Y9, Toronto W08, ON',
    '123 Main Street Unit 456, Toronto, ON M5V 1A1',
    '321 Yonge Street #1001, Toronto, ON M5B 1R7',
    '654 Bay Street PH, Toronto, ON M5G 1M5',
    '258 Bloor Street W 3rd Floor, Toronto, ON M5V 2L1',
    '1900 Highway 7 East, Markham, ON L3R 1A3',
    '2200 The Esplanade Suite 1501, Toronto, ON M5E 1A6',
    'Unit 2600, 123 Main Street, Toronto, ON M5V 1A1',
    'Apt 2700 - 456 Queen Street, Toronto, ON M5V 2A5',
    '2800 Unknown Street, Toronto',
    '5400 Main Street W, Hamilton, ON L8S 1A8'
];

$normalizer = new AddressNormalizer();

echo "🔍 Testing " . count($failingAddresses) . " previously failing addresses...\n\n";

foreach ($failingAddresses as $index => $address) {
    echo sprintf("[%2d] Original: %s\n", $index + 1, $address);
    
    $variations = $normalizer->normalize($address);
    
    if (empty($variations)) {
        echo "     ❌ NO VARIATIONS GENERATED\n";
    } else {
        echo sprintf("     ✅ Generated %d variations:\n", count($variations));
        foreach ($variations as $i => $variation) {
            echo sprintf("       %d. %s\n", $i + 1, $variation);
        }
    }
    echo "\n";
}

echo "=== Analysis Complete ===\n";

// Test specific parsing issues
echo "\n=== Testing Specific Parsing Issues ===\n\n";

$testCases = [
    'Bloor Street W parsing' => '258 Bloor Street W 3rd Floor, Toronto, ON M5V 2L1',
    'Unit at start' => 'Unit 2600, 123 Main Street, Toronto, ON M5V 1A1',
    'Hash unit indicator' => '321 Yonge Street #1001, Toronto, ON M5B 1R7',
    'PH designation' => '654 Bay Street PH, Toronto, ON M5G 1M5',
    'Duplicate city names' => '3328 Oriole Drive, London South, ON N6M 0K1, London South, ON',
    'Toronto area code' => '1425 Gerrard Street E 2nd Flr, Toronto E01, ON M4L 1Z7, Toronto E01, ON',
    'Unit after trail' => '10 Old Mill Trail 305, Toronto W08, ON M8X 2Y9, Toronto W08, ON'
];

foreach ($testCases as $testName => $address) {
    echo "🧪 $testName:\n";
    echo "   Input:  $address\n";
    
    $variations = $normalizer->normalize($address);
    if (!empty($variations)) {
        echo "   Output: " . $variations[0] . "\n";
    } else {
        echo "   Output: ❌ NO VARIATIONS\n";
    }
    echo "\n";
}
