<?php

namespace App\Http\Controllers;

use App\Models\Circle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Http\Requests\UpdateLocationRequest;

class LocationController extends Controller
{
    /**
     * Update lokasi terkini user ke Redis.
     * Endpoint ini dipanggil secara berkala (high-frequency) oleh aplikasi client.
     */
    public function updateLocation(UpdateLocationRequest $request)
    {
        $user = $request->user();
        
        // Buat array payload data lokasi dan battery
        $payload = [
            'latitude'   => $request->latitude,
            'longitude'  => $request->longitude,
            'battery'    => $request->battery,
            'updated_at' => now()->toIso8601String(),
        ];
        
        // Tentukan key Redis berdasarkan ID user
        $redisKey = "user_location:{$user->id}";
        
        // Simpan data ke Redis dengan TTL 300 detik (5 menit)
        // setex (Set with Expiration): (key, ttl_in_seconds, value)
        // Jika dalam 5 menit tidak ada update, key akan otomatis dihapus (user dianggap offline)
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
        
        // 3. Looping semua user_id untuk mengambil data lokasi dari Redis
        foreach ($memberIds as $memberId) {
            $redisKey = "user_location:{$memberId}";
            $locationData = Redis::get($redisKey);
            
            if ($locationData) {
                // Jika data ditemukan di Redis (berarti user aktif/online dalam 5 menit terakhir)
                $data = json_decode($locationData, true);
                
                $locations[] = [
                    'user_id'      => $memberId,
                    'status'       => 'online',
                    'latitude'     => (float) $data['latitude'],
                    'longitude'    => (float) $data['longitude'],
                    'battery'      => $data['battery'] ?? null,
                    'last_updated' => $data['updated_at'],
                ];
            } else {
                // Jika data null (karena sudah melewati TTL 5 menit atau tidak pernah di-set)
                // Kembalikan status offline tanpa titik koordinat
                $locations[] = [
                    'user_id' => $memberId,
                    'status'  => 'offline',
                ];
            }
        }
        
        return response()->json([
            'circle_id' => $circle->id,
            'data'      => $locations
        ]);
    }
}
