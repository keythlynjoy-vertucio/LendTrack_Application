<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        $logs = ActivityLog::query()
            ->with('user')
            ->latest()
            ->paginate(25);

        return view('activity-logs.index', compact('logs'));
    }
}
