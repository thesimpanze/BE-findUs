<?php

namespace App\Http\Controllers;

use App\Models\Circle;
use App\Models\CircleMember;
use Illuminate\Http\Request;
use App\Http\Requests\JoinCircleRequest;

class CircleController extends Controller
{
    /**
     * Bergabung ke sebuah Circle menggunakan kode referal.
     * Mengeluarkan user dari Circle manapun sebelumnya (termasuk miliknya sendiri).
     */
    public function join(JoinCircleRequest $request)
    {
        $user = $request->user();
        $referalCode = $request->referal_code;
        
        // 1. Cari circle berdasarkan referal_code
        $circle = Circle::where('referal_code', $referalCode)->first();
        
        if (!$circle) {
            return response()->json([
                'message' => 'Circle tidak ditemukan dengan kode referal tersebut.'
            ], 404);
        }
        
        // 2. Cek apakah user adalah owner dari circle tersebut
        if ($circle->owner_id === $user->id) {
            return response()->json([
                'message' => 'Anda adalah pemilik dari Circle ini. Anda tidak perlu bergabung menggunakan kode referal.'
            ], 400);
        }
        
        // 3. Cek apakah user sudah menjadi member di circle tersebut
        $isAlreadyMember = CircleMember::where('circle_id', $circle->id)
                                        ->where('user_id', $user->id)
                                        ->exists();
                                        
        if ($isAlreadyMember) {
            return response()->json([
                'message' => 'Anda sudah menjadi anggota di Circle ini.'
            ], 400);
        }
        
        // 4. Tambahkan user sebagai member baru di circle tujuan
        CircleMember::create([
            'circle_id' => $circle->id,
            'user_id'   => $user->id,
            'role'      => 'member',
            'status'    => 'active',
            'joined_at' => now(),
        ]);
        
        return response()->json([
            'message' => 'Berhasil bergabung ke Circle.',
            'data'    => $circle
        ], 200);
    }

    /**
     * Keluar dari circle.
     */
    public function leave(Request $request, Circle $circle)
    {
        $user = $request->user();
        
        // Cek apakah user adalah owner dari circle tersebut
        if ($circle->owner_id === $user->id) {
            return response()->json([
                'message' => 'Anda adalah pemilik dari Circle ini. Anda tidak dapat keluar dari Circle milik sendiri.'
            ], 400);
        }

        // Cek keanggotaan saat ini
        $currentMembership = CircleMember::where('user_id', $user->id)
                                         ->where('circle_id', $circle->id)
                                         ->first();

        if (!$currentMembership) {
            return response()->json([
                'message' => 'Anda tidak berada di dalam Circle ini.'
            ], 400);
        }
        
        // Hapus keanggotaan dari circle tersebut
        $currentMembership->delete();
        
        return response()->json([
            'message' => 'Berhasil keluar dari Circle.',
        ], 200);
    }

    /**
     * Update nama circle.
     */
    public function update(\App\Http\Requests\UpdateCircleRequest $request, Circle $circle)
    {
        $user = $request->user();

        // Hanya owner yang bisa mengubah nama circle
        if ($circle->owner_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only the owner can update the circle name.'
            ], 403);
        }

        $circle->update([
            'name' => $request->name
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Berhasil memperbarui nama circle.',
            'data'    => $circle
        ], 200);
    }

    /**
     * Dapatkan daftar anggota dari sebuah Circle.
     */
    public function members(Request $request, Circle $circle)
    {
        $user = $request->user();
        
        // 1. Otorisasi: Pastikan user yang me-request adalah anggota atau owner dari circle tersebut
        $isMember = $circle->members()->where('user_id', $user->id)->exists();
        
        if (!$isMember && $circle->owner_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You are not a member of this circle.'
            ], 403);
        }
        
        // 2. Ambil data anggota beserta data user-nya
        $members = $circle->members()->with('user')->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil daftar anggota circle',
            'data'    => $members
        ], 200);
    }
}
