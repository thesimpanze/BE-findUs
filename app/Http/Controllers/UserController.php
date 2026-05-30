<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * Update the user's profile photo.
     */
    public function updatePhoto(Request $request)
    {
        $request->validate([
            'photo' => User::$rules['photo'] ?? 'required|image|mimes:jpg,jpeg,png|max:3072',
        ]);

        $user = $request->user();

        // Check if user already has a photo and delete it from storage
        if ($user->photo && Storage::disk('public')->exists($user->photo)) {
            Storage::disk('public')->delete($user->photo);
        }

        // Store the new photo
        $path = $request->file('photo')->store('profile_photos', 'public');

        // Update user record
        $user->update([
            'photo' => $path,
        ]);

        return response()->json([
            'message' => 'Profile photo updated successfully',
            'photo_url' => asset('storage/' . $path),
            'user' => $user
        ]);
    }

    /**
     * Get the authenticated user's profile with relations.
     */
    public function getUser(Request $request)
    {
        $user = $request->user()->load(['circle', 'memberships.circle']);

        // Check if user is premium
        $user->is_premium = $user->isPremium();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil data user',
            'data' => $user
        ]);
    }
}
