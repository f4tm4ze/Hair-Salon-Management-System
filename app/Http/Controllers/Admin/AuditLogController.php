<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::with('user')
            ->when($request->filled('user'), fn($q) => $q->where('user_id', $request->user))
            ->when($request->filled('action'), fn($q) => $q->where('action', $request->action))
            ->when($request->filled('model'), fn($q) => $q->where('model_type', $request->model))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $users = User::pluck('name', 'id');
        $actions = AuditLog::distinct()->pluck('action');
        $models = AuditLog::whereNotNull('model_type')->distinct()->pluck('model_type');

        return view('admin.audit_logs.index', compact('logs', 'users', 'actions', 'models'));
    }
}
