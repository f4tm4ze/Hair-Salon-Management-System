<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class LogSuccessfulLogin
{
    public function handle(Login $event)
    {
        AuditLog::create([
            'user_id' => $event->user->id,
            'action' => 'login',
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
