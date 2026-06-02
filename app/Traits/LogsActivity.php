<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait LogsActivity
{
    public static function bootLogsActivity()
    {
        static::created(function ($model) {
            $model->logActivity('created', $model->getAttributes(), null);
        });

        static::updated(function ($model) {
            $model->logActivity('updated', $model->getChanges(), $model->getOriginal());
        });

        static::deleted(function ($model) {
            $model->logActivity('deleted', null, $model->getOriginal());
        });
    }

    protected function logActivity($action, $new = null, $old = null)
    {
        $userId = Auth::id();

        AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'model_type' => get_class($this),
            'model_id' => $this->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
