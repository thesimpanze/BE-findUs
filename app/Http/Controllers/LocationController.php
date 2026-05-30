<?php

namespace App\Http\Controllers;

use App\Models\Circle;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Http\Requests\UpdateLocationRequest;
use Carbon\Carbon;

class LocationController extends Controller
{
    /**
     * Hitung jarak dua titik dalam meter menggunakan formula Haversine.
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // in meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Update lokasi terkini user ke Redis dan DB.
     */
    public function updateLocation(UpdateLocationRequest $request)
    {
        $user = $request->user();
        
        $latitude = $request->latitude;
        $longitude = $request->longitude;
        $battery = $request->battery;
        $now = now();

        // 1. Simpan/Update Lokasi Permanen di tabel locations
        $location = $user->location()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'battery' => $battery,
            ]
        );

        // 2. Logic History Lokasi (Jarak > 20m ATAU waktu > 5 menit)
        $lastHistory = $user->locationHistories()->orderBy('recorded_at', 'desc')->first();
        
        $shouldSaveHistory = false;
        
        if (!$lastHistory) {
            $shouldSaveHistory = true;
        } else {
            $distance = $this->calculateDistance(
                $lastHistory->latitude, 
                $lastHistory->longitude, 
                $latitude, 
                $longitude
            );
            
            $timeDiffMinutes = $lastHistory->recorded_at->diffInMinutes($now);
            
            if ($distance > 20 || $timeDiffMinutes >= 5) {
                $shouldSaveHistory = true;
            }
        }

        if ($shouldSaveHistory) {
            $user->locationHistories()->create([
                'latitude' => $latitude,
                'longitude' => $longitude,
                'battery' => $battery,
                'recorded_at' => $now,
            ]);
        }

        // 3. Simpan ke Redis (Cache 5 Menit)
        $payload = [
            'latitude'   => $latitude,
            'longitude'  => $longitude,
            'battery'    => $battery,
            'updated_at' => $now->toIso8601String(),
        ];
        
        $redisKey = "user_location:{$user->id}";
        Redis::setex($redisKey, 300, json_encode($payload));
        
        return response()->json([
            'message' => 'Location updated successfully',
            'data'    => $payload
        ]);
    }

    /**
     * Dapatkan lokasi terkini dari semua anggota di dalam sebuah Circle.
     */
    public function getCircleLocations(Request $request, Circle $circle)
    {
        $user = $request->user();
        
        // 1. Otorisasi: Pastikan user yang me-request adalah anggota atau owner dari circle tersebut
        $isMember = $circle->members()->where('user_id', $user->id)->exists();
        
        if (!$isMember && $circle->owner_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized. You are not a member of this circle.'
            ], 403);
        }
        
        // 2. Kumpulkan semua user_id yang ada di circle ini (members + owner)
        $memberIds = $circle->members()->pluck('user_id')->toArray();
        if (!in_array($circle->owner_id, $memberIds)) {
            $memberIds[] = $circle->owner_id;
        }
        
        $locations = [];
        
        // Eager load locations untuk fallback
        $usersWithLocations = User::whereIn('id', $memberIds)->with('location')->get()->keyBy('id');

        // 3. Looping semua user_id untuk mengambil data lokasi dari Redis atau Database
        foreach ($memberIds as $memberId) {
            $userModel = $usersWithLocations->get($memberId);
            $name = $userModel ? $userModel->name : 'Unknown';
            $photo = $userModel ? $userModel->photo : null;
            
            $redisKey = "user_location:{$memberId}";
            $locationData = Redis::get($redisKey);
            
            if ($locationData) {
                // Jika data ditemukan di Redis (berarti user aktif/online dalam 5 menit terakhir)
                $data = json_decode($locationData, true);
                
                $locations[] = [
                    'name'         => $name,
                    'photo'        => $photo,
                    'status'       => 'online',
                    'latitude'     => (float) $data['latitude'],
                    'longitude'    => (float) $data['longitude'],
                    'battery'      => $data['battery'] ?? null,
                    'last_updated' => $data['updated_at'],
                ];
            } else {
                // Fallback: Ambil data dari PostgreSQL (last known location)
                $dbLocation = $userModel ? $userModel->location : null;
                
                if ($dbLocation) {
                    $locations[] = [
                        'name'         => $name,
                        'photo'        => $photo,
                        'status'       => 'offline',
                        'latitude'     => (float) $dbLocation->latitude,
                        'longitude'    => (float) $dbLocation->longitude,
                        'battery'      => $dbLocation->battery,
                        'last_updated' => $dbLocation->updated_at->toIso8601String(),
                    ];
                } else {
                    // Tidak ada di Redis dan tidak ada di DB
                    $locations[] = [
                        'name'   => $name,
                        'photo'  => $photo,
                        'status' => 'offline',
                    ];
                }
            }
        }
        
        return response()->json([
            'circle_id' => $circle->id,
            'data'      => $locations
        ]);
    }
}
