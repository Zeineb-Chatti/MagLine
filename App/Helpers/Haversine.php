<?php
namespace App\Helpers;

/**
 * Geographic Distance Calculator using Haversine Formula
 * 
 * @package Helpers
 */

/**
 * Calculate distance between two coordinates (latitude/longitude) in kilometers
 * 
 * @param array $coord1 [latitude, longitude] in decimal degrees
 * @param array $coord2 [latitude, longitude] in decimal degrees
 * @return stdClass Object with distance in kilometers
 * @throws InvalidArgumentException If invalid coordinates provided
 */
function haversine(array $coord1, array $coord2): \stdClass
{
    // Validate coordinates
    if (count($coord1) !== 2 || count($coord2) !== 2) {
        throw new \InvalidArgumentException('Coordinates must be arrays of [lat, lon]');
    }

    [$lat1, $lon1] = $coord1;
    [$lat2, $lon2] = $coord2;

    if (!is_numeric($lat1) || !is_numeric($lon1) || !is_numeric($lat2) || !is_numeric($lon2)) {
        throw new \InvalidArgumentException('Latitude/longitude must be numeric values');
    }

    if ($lat1 < -90 || $lat1 > 90 || $lat2 < -90 || $lat2 > 90) {
        throw new \InvalidArgumentException('Latitude must be between -90 and 90 degrees');
    }

    if ($lon1 < -180 || $lon1 > 180 || $lon2 < -180 || $lon2 > 180) {
        throw new \InvalidArgumentException('Longitude must be between -180 and 180 degrees');
    }

    // Earth radius in kilometers
    $R = 6371.0;

    // Convert degrees to radians
    $lat1Rad = deg2rad($lat1);
    $lon1Rad = deg2rad($lon1);
    $lat2Rad = deg2rad($lat2);
    $lon2Rad = deg2rad($lon2);

    // Differences
    $dLat = $lat2Rad - $lat1Rad;
    $dLon = $lon2Rad - $lon1Rad;

    // Haversine formula
    $a = sin($dLat / 2) ** 2 + cos($lat1Rad) * cos($lat2Rad) * sin($dLon / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    $distanceKm = $R * $c;

    return (object)['km' => $distanceKm];
}