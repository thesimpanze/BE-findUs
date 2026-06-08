<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Location;
use App\Models\LocationHistory;
use App\Services\GeocodingService;
use Illuminate\Support\Facades\Redis;

class ProcessReverseGeocoding implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $latitude;
    protected $longitude;
    protected $recordedAt;
    protected $isNewHistory;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, $latitude, $longitude, $recordedAt, $isNewHistory = false)
    {
        $this->user = $user;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->recordedAt = $recordedAt;
        $this->isNewHistory = $isNewHistory;
    }

    /**
     * Execute the job.
     */
    public function handle(GeocodingService $geocodingService): void
    {
        $address = $geocodingService->reverseGeocode($this->latitude, $this->longitude);

        if ($address) {
            // Update Location Table
            Location::where('user_id', $this->user->id)->update(['address' => $address]);

            // Update Location History Table if it was recorded in this request
            if ($this->isNewHistory) {
                LocationHistory::where('user_id', $this->user->id)
                    ->where('recorded_at', $this->recordedAt)
                    ->update(['address' => $address]);
            }

            // Update Redis Cache
            $redisKey = "user_location:{$this->user->id}";
            $locationData = Redis::get($redisKey);
            
            if ($locationData) {
                $data = json_decode($locationData, true);
                $data['address'] = $address;
                // keep the remaining TTL
                $ttl = Redis::ttl($redisKey);
                if ($ttl > 0) {
                    Redis::setex($redisKey, $ttl, json_encode($data));
                }
            }
        }
    }
}
