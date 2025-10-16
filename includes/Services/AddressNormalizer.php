<?php
/**
 * Address Normalization Service
 *
 * Provides sophisticated address normalization and parsing capabilities
 * as a pure PHP alternative to libpostal for better geocoding accuracy.
 *
 * @package Shift8_TREB
 * @since 1.7.0
 */

namespace Shift8\TREB\Services;

/**
 * Address Normalization Service
 *
 * Normalizes and structures addresses for optimal geocoding results.
 * Uses intelligent parsing and standardization instead of regex-based cleaning.
 */
class AddressNormalizer {
    
    /**
     * Street type abbreviations and their full forms
     */
    private const STREET_TYPES = [
        'ST' => 'STREET',
        'AVE' => 'AVENUE',
        'BLVD' => 'BOULEVARD',
        'RD' => 'ROAD',
        'DR' => 'DRIVE',
        'CT' => 'COURT',
        'CIR' => 'CIRCLE',
        'LN' => 'LANE',
        'WAY' => 'WAY',
        'PL' => 'PLACE',
        'CRES' => 'CRESCENT',
        'TR' => 'TRAIL',
        'PKWY' => 'PARKWAY',
        'HWY' => 'HIGHWAY',
        'TER' => 'TERRACE',
        'GR' => 'GROVE',
        'GDNS' => 'GARDENS',
        'HTS' => 'HEIGHTS',
        'PT' => 'POINT',
        'MT' => 'MOUNT'
    ];
    
    /**
     * Directional abbreviations and their full forms
     */
    private const DIRECTIONS = [
        'N' => 'NORTH',
        'S' => 'SOUTH',
        'E' => 'EAST',
        'W' => 'WEST',
        'NE' => 'NORTHEAST',
        'NW' => 'NORTHWEST',
        'SE' => 'SOUTHEAST',
        'SW' => 'SOUTHWEST'
    ];
    
    /**
     * Unit type indicators to remove for building-level geocoding
     */
    private const UNIT_INDICATORS = [
        'APT', 'APARTMENT', 'UNIT', 'SUITE', 'STE', '#', 'BSMT', 'BASEMENT',
        'MAIN', 'UPPER', 'LOWER', 'FLR', 'FLOOR', 'FL'
    ];
    
    /**
     * Canadian province abbreviations
     */
    private const PROVINCES = [
        'ON' => 'ONTARIO',
        'BC' => 'BRITISH COLUMBIA',
        'AB' => 'ALBERTA',
        'SK' => 'SASKATCHEWAN',
        'MB' => 'MANITOBA',
        'QC' => 'QUEBEC',
        'NB' => 'NEW BRUNSWICK',
        'NS' => 'NOVA SCOTIA',
        'PE' => 'PRINCE EDWARD ISLAND',
        'NL' => 'NEWFOUNDLAND AND LABRADOR',
        'YT' => 'YUKON',
        'NT' => 'NORTHWEST TERRITORIES',
        'NU' => 'NUNAVUT'
    ];
    
    /**
     * Normalize a raw address for optimal geocoding
     *
     * @param string $rawAddress Raw address from AMPRE API
     * @param string $countryBias Country bias for geocoding (default: 'CA')
     * @return array Normalized address variations
     */
    public function normalize(string $rawAddress, string $countryBias = 'CA'): array {
        // Parse the address into components
        $components = $this->parseAddress($rawAddress);
        
        // Generate multiple normalized variations
        $variations = [];
        
        // Variation 1: Building-level address (most reliable for geocoding)
        $buildingAddress = $this->buildBuildingAddress($components, $countryBias);
        if ($buildingAddress) {
            $variations[] = $buildingAddress;
        }
        
        // Variation 2: Expanded abbreviations
        $expandedAddress = $this->buildExpandedAddress($components, $countryBias);
        if ($expandedAddress && !in_array($expandedAddress, $variations)) {
            $variations[] = $expandedAddress;
        }
        
        // Variation 3: Simplified city format
        $simplifiedAddress = $this->buildSimplifiedAddress($components, $countryBias);
        if ($simplifiedAddress && !in_array($simplifiedAddress, $variations)) {
            $variations[] = $simplifiedAddress;
        }
        
        // Variation 4: Street + city only (fallback)
        $basicAddress = $this->buildBasicAddress($components, $countryBias);
        if ($basicAddress && !in_array($basicAddress, $variations)) {
            $variations[] = $basicAddress;
        }
        
        return array_filter($variations);
    }
    
    /**
     * Parse raw address into structured components
     *
     * @param string $rawAddress Raw address string
     * @return array Parsed address components
     */
    private function parseAddress(string $rawAddress): array {
        $address = trim($rawAddress);
        
        // Initialize components
        $components = [
            'house_number' => '',
            'street_name' => '',
            'street_type' => '',
            'direction' => '',
            'unit' => '',
            'unit_type' => '',
            'city' => '',
            'province' => '',
            'postal_code' => '',
            'country' => ''
        ];
        
        // Handle edge cases where address format is unusual
        if (empty($address)) {
            return $components; // Return empty components for invalid addresses
        }
        
        // Handle addresses that start with unit designations
        // Handle dash format first: "Apt 2700 - 456 Queen Street"
        if (preg_match('/^(Unit|Apt|Apartment)\s+\d+\s*-\s*(.+)/', $address, $matches)) {
            $address = trim($matches[2]);
        }
        // Handle comma format: "Unit 2600, 123 Main Street"
        elseif (preg_match('/^(Unit|Apt|Apartment)\s+\d+,?\s*(.+)/', $address, $matches)) {
            $address = trim($matches[2]);
        }
        
        // Extract postal code (Canadian format: A1A 1A1)
        if (preg_match('/\b([A-Z]\d[A-Z]\s+\d[A-Z]\d)\b/i', $address, $matches)) {
            $components['postal_code'] = strtoupper($matches[1]);
            $address = str_replace($matches[0], '', $address);
        }
        
        // Split by commas to get major components
        $parts = array_map('trim', explode(',', $address));
        $parts = array_filter($parts); // Remove empty parts
        $parts = array_values($parts); // Reindex
        
        // Last part is usually country (if present)
        if (count($parts) > 1 && in_array(strtoupper(end($parts)), ['CANADA', 'CA'])) {
            $components['country'] = 'CANADA';
            array_pop($parts);
        }
        
        // Second to last is usually province
        if (count($parts) > 1) {
            $lastPart = strtoupper(trim(end($parts)));
            if (isset(self::PROVINCES[$lastPart]) || in_array($lastPart, self::PROVINCES)) {
                $components['province'] = $lastPart;
                array_pop($parts);
            }
        }
        
        // Next part is usually city (may include area codes like "Toronto C01")
        if (count($parts) > 1) {
            $cityPart = trim(end($parts));
            // Remove Toronto area codes (e.g., "Toronto C01" -> "Toronto")
            $cityPart = preg_replace('/\s+[A-Z]\d{2}$/', '', $cityPart);
            // Handle duplicate city names (e.g., "London South, ON N6M 0K1, London South, ON")
            $cityPart = $this->cleanDuplicateCityNames($cityPart);
            $components['city'] = $cityPart;
            array_pop($parts);
        }
        
        // Remaining part(s) should be the street address
        if (!empty($parts)) {
            $streetAddress = implode(', ', $parts);
            $this->parseStreetAddress($streetAddress, $components);
        }
        
        return $components;
    }
    
    /**
     * Parse street address portion into components
     *
     * @param string $streetAddress Street address portion
     * @param array &$components Components array to populate
     */
    private function parseStreetAddress(string $streetAddress, array &$components): void {
        $address = trim($streetAddress);
        
        // First, extract house number if present at the start
        $houseNumber = '';
        $remaining = $address;
        
        if (preg_match('/^(\d+[A-Z]?)\s+(.+)/', $address, $matches)) {
            $houseNumber = $matches[1];
            $remaining = $matches[2];
        }
        
        // Clean the remaining address for unit/apartment removal
        $cleanAddress = $this->removeUnitDesignations($remaining);
        
        // If we have a house number, use it; otherwise try to extract from cleaned address
        if (!empty($houseNumber)) {
            $components['house_number'] = $houseNumber;
            $this->parseStreetNameAndType($cleanAddress, $components);
        } else {
            // Try to extract house number from cleaned address
            if (preg_match('/^(\d+[A-Z]?)\s+(.+)/', $cleanAddress, $matches)) {
                $components['house_number'] = $matches[1];
                $this->parseStreetNameAndType($matches[2], $components);
            } else {
                // No house number found, treat entire string as street name
                $this->parseStreetNameAndType($cleanAddress, $components);
            }
        }
    }
    
    /**
     * Remove unit designations from address for building-level geocoding
     *
     * @param string $address Address to clean
     * @return string Cleaned address
     */
    private function removeUnitDesignations(string $address): string {
        $cleaned = $address;
        
        // Remove floor designations first (2nd Flr, 3rd Floor, etc.)
        // This must come before other patterns to avoid conflicts
        $cleaned = preg_replace('/\s+\d+(?:st|nd|rd|th)\s+(?:Flr?|Floor)\b/i', '', $cleaned);
        
        // Remove unit numbers after street names with directional indicators
        // e.g., "Gerrard Street E 2nd" -> "Gerrard Street E" (after floor removal above)
        $cleaned = preg_replace('/(\b(?:Street|St|Avenue|Ave|Road|Rd|Drive|Dr|Boulevard|Blvd|Crescent|Cres|Circle|Cir|Court|Ct|Lane|Ln|Way|Place|Pl|Trail|Tr)\s+[NSEW])\s+\d+/i', '$1', $cleaned);
        
        // Remove unit numbers directly after street types (without directional)
        // e.g., "Dundas Street 603" -> "Dundas Street"
        $cleaned = preg_replace('/(\b(?:Street|St|Avenue|Ave|Road|Rd|Drive|Dr|Boulevard|Blvd|Crescent|Cres|Circle|Cir|Court|Ct|Lane|Ln|Way|Place|Pl|Trail|Tr))\s+\d+/i', '$1', $cleaned);
        
        // Remove common apartment/unit designations
        $unitPattern = '/\s+(?:' . implode('|', self::UNIT_INDICATORS) . ')\s*[A-Z0-9]*(?:\s|$)/i';
        $cleaned = preg_replace($unitPattern, ' ', $cleaned);
        
        // Remove unit numbers at the end of addresses
        // e.g., "Old Mill Trail 305" -> "Old Mill Trail"
        $cleaned = preg_replace('/\s+\d+\s*$/', '', $cleaned);
        
        // Remove hash/pound unit indicators
        // e.g., "Yonge Street #1001" -> "Yonge Street"
        $cleaned = preg_replace('/\s*#\s*\d+/', '', $cleaned);
        
        // Remove PH (Penthouse) designations
        $cleaned = preg_replace('/\s+PH\s*\d*(?:\s|$)/i', ' ', $cleaned);
        
        // Clean up multiple spaces and trim
        $cleaned = preg_replace('/\s+/', ' ', trim($cleaned));
        
        return $cleaned;
    }
    
    /**
     * Parse street name, type, and direction
     *
     * @param string $streetPart Street name portion
     * @param array &$components Components array to populate
     */
    private function parseStreetNameAndType(string $streetPart, array &$components): void {
        $words = explode(' ', trim($streetPart));
        $words = array_filter($words); // Remove empty elements
        $words = array_values($words); // Reindex array
        
        if (empty($words)) {
            return;
        }
        
        // Handle special cases like "Bloor Street W" where W is direction, not part of street type
        $direction = '';
        $streetType = '';
        $streetName = '';
        
        // Look for direction at the end (single letter directions like W, E, N, S)
        $lastWord = strtoupper(end($words));
        if (isset(self::DIRECTIONS[$lastWord]) || in_array($lastWord, self::DIRECTIONS)) {
            $direction = $lastWord;
            array_pop($words);
        }
        
        // Look for street type (second to last if direction was found, or last if no direction)
        if (!empty($words)) {
            $lastWord = strtoupper(end($words));
            if (isset(self::STREET_TYPES[$lastWord]) || in_array($lastWord, self::STREET_TYPES)) {
                $streetType = $lastWord;
                array_pop($words);
            }
        }
        
        // Remaining words are the street name
        if (!empty($words)) {
            $streetName = implode(' ', $words);
        }
        
        // Validate that we have at least a street name
        if (empty($streetName) && !empty($streetType)) {
            // If we only have a street type, it might be misidentified
            // Put it back as street name
            $streetName = $streetType;
            $streetType = '';
        }
        
        // Set components
        $components['street_name'] = $streetName;
        $components['street_type'] = $streetType;
        $components['direction'] = $direction;
    }
    
    /**
     * Build building-level address (most reliable for geocoding)
     *
     * @param array $components Parsed address components
     * @param string $countryBias Country bias
     * @return string|null Normalized building address
     */
    private function buildBuildingAddress(array $components, string $countryBias): ?string {
        if (empty($components['house_number']) || empty($components['city'])) {
            return null;
        }
        
        $parts = [];
        
        // House number
        $parts[] = $components['house_number'];
        
        // Street name (proper case)
        if (!empty($components['street_name'])) {
            $parts[] = ucwords(strtolower($components['street_name']));
        }
        
        // Street type (proper case)
        if (!empty($components['street_type'])) {
            $streetType = strtoupper($components['street_type']);
            $parts[] = self::STREET_TYPES[$streetType] ?? ucwords(strtolower($streetType));
        }
        
        // Direction (proper case)
        if (!empty($components['direction'])) {
            $direction = strtoupper($components['direction']);
            $parts[] = self::DIRECTIONS[$direction] ?? ucwords(strtolower($direction));
        }
        
        $streetAddress = implode(' ', $parts);
        
        // City (proper case)
        $city = ucwords(strtolower($components['city']));
        
        // Province (uppercase)
        $province = !empty($components['province']) ? strtoupper($components['province']) : 'ON';
        
        // Postal code (uppercase)
        $postalCode = !empty($components['postal_code']) ? strtoupper($components['postal_code']) : '';
        
        // Country
        $country = $countryBias === 'CA' ? 'Canada' : $countryBias;
        
        // Build final address
        $addressParts = [$streetAddress, $city];
        
        if (!empty($province)) {
            $addressParts[] = $province;
        }
        
        if (!empty($postalCode)) {
            $addressParts[] = $postalCode;
        }
        
        $addressParts[] = $country;
        
        return implode(', ', $addressParts);
    }
    
    /**
     * Build address with expanded abbreviations
     *
     * @param array $components Parsed address components
     * @param string $countryBias Country bias
     * @return string|null Expanded address
     */
    private function buildExpandedAddress(array $components, string $countryBias): ?string {
        if (empty($components['house_number']) || empty($components['city'])) {
            return null;
        }
        
        $parts = [];
        
        // House number
        $parts[] = $components['house_number'];
        
        // Street name
        if (!empty($components['street_name'])) {
            $parts[] = $components['street_name'];
        }
        
        // Expand street type
        if (!empty($components['street_type'])) {
            $streetType = strtoupper($components['street_type']);
            $expandedType = self::STREET_TYPES[$streetType] ?? $streetType;
            $parts[] = $expandedType;
        }
        
        // Expand direction
        if (!empty($components['direction'])) {
            $direction = strtoupper($components['direction']);
            $expandedDirection = self::DIRECTIONS[$direction] ?? $direction;
            $parts[] = $expandedDirection;
        }
        
        $streetAddress = implode(' ', $parts);
        
        // Build with expanded components (proper case)
        $city = ucwords(strtolower($components['city']));
        $addressParts = [$streetAddress, $city];
        
        if (!empty($components['province'])) {
            $addressParts[] = strtoupper($components['province']);
        }
        
        if (!empty($components['postal_code'])) {
            $addressParts[] = strtoupper($components['postal_code']);
        }
        
        $country = $countryBias === 'CA' ? 'Canada' : $countryBias;
        $addressParts[] = $country;
        
        return implode(', ', $addressParts);
    }
    
    /**
     * Build simplified address (remove subdivisions)
     *
     * @param array $components Parsed address components
     * @param string $countryBias Country bias
     * @return string|null Simplified address
     */
    private function buildSimplifiedAddress(array $components, string $countryBias): ?string {
        if (empty($components['house_number']) || empty($components['city'])) {
            return null;
        }
        
        // Simplify city name (remove subdivisions like "London South" -> "London")
        $city = $components['city'];
        $city = preg_replace('/\s+(North|South|East|West|Central)$/i', '', $city);
        
        $parts = [];
        
        // House number
        $parts[] = $components['house_number'];
        
        // Street name
        if (!empty($components['street_name'])) {
            $parts[] = $components['street_name'];
        }
        
        // Street type (expand abbreviations for this variation)
        if (!empty($components['street_type'])) {
            $streetType = strtoupper($components['street_type']);
            $expandedType = self::STREET_TYPES[$streetType] ?? $streetType;
            $parts[] = $expandedType;
        }
        
        // Direction (expand abbreviations for this variation)
        if (!empty($components['direction'])) {
            $direction = strtoupper($components['direction']);
            $expandedDirection = self::DIRECTIONS[$direction] ?? $direction;
            $parts[] = $expandedDirection;
        }
        
        $streetAddress = implode(' ', $parts);
        
        // Build simplified address
        $addressParts = [$streetAddress, $city];
        
        if (!empty($components['province'])) {
            $addressParts[] = $components['province'];
        }
        
        $country = $countryBias === 'CA' ? 'Canada' : $countryBias;
        $addressParts[] = $country;
        
        return implode(', ', $addressParts);
    }
    
    /**
     * Build basic address (street + city only)
     *
     * @param array $components Parsed address components
     * @param string $countryBias Country bias
     * @return string|null Basic address
     */
    private function buildBasicAddress(array $components, string $countryBias): ?string {
        if (empty($components['house_number']) || empty($components['city'])) {
            return null;
        }
        
        $parts = [];
        
        // House number
        $parts[] = $components['house_number'];
        
        // Street name
        if (!empty($components['street_name'])) {
            $parts[] = $components['street_name'];
        }
        
        // Street type
        if (!empty($components['street_type'])) {
            $parts[] = $components['street_type'];
        }
        
        // Direction
        if (!empty($components['direction'])) {
            $parts[] = $components['direction'];
        }
        
        $streetAddress = implode(' ', $parts);
        
        // Simplify city
        $city = preg_replace('/\s+(North|South|East|West|Central)$/i', '', $components['city']);
        
        $country = $countryBias === 'CA' ? 'Canada' : $countryBias;
        
        return implode(', ', [$streetAddress, $city, $country]);
    }
    
    /**
     * Clean duplicate city names from address
     *
     * @param string $cityString City string that may contain duplicates
     * @return string Cleaned city name
     */
    private function cleanDuplicateCityNames(string $cityString): string {
        // Handle cases like "London South, ON N6M 0K1, London South, ON"
        // or "Toronto E01, ON M4L 1Z7, Toronto E01, ON"
        
        // First, check if this is actually a complex city string with duplicates
        if (strpos($cityString, ',') === false) {
            return $cityString; // No commas, no duplicates to clean
        }
        
        $parts = array_map('trim', explode(',', $cityString));
        
        // Look for the actual city name (usually the first non-province, non-postal part)
        $cityName = '';
        foreach ($parts as $part) {
            // Skip province codes and postal codes
            if (!preg_match('/^[A-Z]{2}$/', $part) && !preg_match('/^[A-Z]\d[A-Z]\s+\d[A-Z]\d$/', $part)) {
                if (empty($cityName)) {
                    $cityName = $part;
                    break;
                }
            }
        }
        
        return !empty($cityName) ? $cityName : $cityString;
    }
}
