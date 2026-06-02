<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class LogSuccessfulLogout
{
    public function handle(Logout $event)
    {
        AuditLog::create([
            'user_id' => $event->user->id ?? null,
            'action' => 'logout',
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
