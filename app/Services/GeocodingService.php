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
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept-Language' => 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
                'Referer' => 'http://localhost'
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
