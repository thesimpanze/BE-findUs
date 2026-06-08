<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    /**
     * Reverse geocode a latitude and longitude to a formatted address using Nominatim.
     *
     * @param float $latitude
     * @param float $longitude
     * @return string|null
     */
    public function reverseGeocode($latitude, $longitude)
    {
        try {
            // API Nominatim (OpenStreetMap)
            $url = "https://nominatim.openstreetmap.org/reverse";
            
            $response = Http::withHeaders([
                'User-Agent' => 'FindMyBackend/1.0 (contact@example.com)'
            ])->get($url, [
                'lat' => $latitude,
                'lon' => $longitude,
                'format' => 'jsonv2',
                'zoom' => 18,
                'addressdetails' => 1
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['display_name'])) {
                    return $data['display_name'];
                }
            } else {
                Log::warning("Reverse geocoding failed", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'lat' => $latitude,
                    'lon' => $longitude
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Reverse geocoding exception", [
                'message' => $e->getMessage(),
                'lat' => $latitude,
                'lon' => $longitude
            ]);
        }

        return null;
    }
}
