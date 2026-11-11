<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StaffApiController extends Controller
{
    public function store(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        $request->merge([
            'name' => trim((string) $request->input('name', '')),
            'email' => strtolower(trim((string) $request->input('email', ''))),
        ]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'max:72', 'confirmed'],
        ]);
        $user = DB::transaction(function () use ($request, $data) {
            $user = User::create([
                'name' => $data['name'], 'email' => $data['email'],
                'password' => Hash::make($data['password']), 'role' => 'staff',
            ]);
            ActivityLog::create([
                'user_id' => $request->user()->id, 'subject_type' => User::class,
                'subject_id' => $user->id, 'action' => 'created',
                'description' => 'Created staff account',
                'changes' => ['name' => $user->name, 'email' => $user->email, 'role' => 'staff'],
            ]);
            return $user;
        });
        return response()->json(['message' => 'Staff account created.',
            'data' => $user->only(['id', 'name', 'email', 'role'])], 201);
    }
}
