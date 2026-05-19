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
        
        // 4. Keluarkan user dari SEMUA circle yang ia ikuti saat ini (termasuk circle miliknya sendiri)
        // Hal ini menjamin bahwa user hanya aktif di satu circle pada satu waktu
        CircleMember::where('user_id', $user->id)->delete();
        
        // 5. Tambahkan user sebagai member baru di circle tujuan
        CircleMember::create([
            'circle_id' => $circle->id,
            'user_id'   => $user->id,
            'role'      => 'member',
            'status'    => 'active',
            'joined_at' => now(),
        ]);
        
        return response()->json([
            'message' => 'Berhasil bergabung ke Circle baru. Anda telah keluar dari Circle sebelumnya.',
            'data'    => $circle
        ], 200);
    }

    /**
     * Keluar dari circle orang lain dan otomatis kembali ke circle milik sendiri.
     */
    public function leave(Request $request)
    {
        $user = $request->user();
        
        // 1. Cari circle milik user ini sendiri
        $ownCircle = Circle::where('owner_id', $user->id)->first();
        
        if (!$ownCircle) {
            return response()->json([
                'message' => 'Anda tidak memiliki Circle sendiri untuk kembali. Silakan buat Circle terlebih dahulu.'
            ], 404);
        }

        // 2. Cek keanggotaan saat ini
        $currentMembership = CircleMember::where('user_id', $user->id)->first();

        if ($currentMembership && $currentMembership->circle_id === $ownCircle->id) {
            return response()->json([
                'message' => 'Anda sudah berada di dalam Circle Anda sendiri.'
            ], 400);
        }
        
        // 3. Hapus semua keanggotaan dari circle manapun (keluar dari circle orang lain)
        CircleMember::where('user_id', $user->id)->delete();
        
        // 4. Masukkan user kembali ke circlenya sendiri
        CircleMember::create([
            'circle_id' => $ownCircle->id,
            'user_id'   => $user->id,
            'role'      => 'ketua guild',
            'status'    => 'active',
            'joined_at' => now(),
        ]);
        
        return response()->json([
            'message' => 'Berhasil keluar dari Circle orang lain dan Anda telah otomatis masuk kembali ke Circle Anda sendiri.',
            'data'    => $ownCircle
        ], 200);
    }
}
