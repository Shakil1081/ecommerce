<?php

declare(strict_types=1);

namespace App\Modules\Admin\Actions;

use App\Models\User;
use App\Modules\Admin\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class LogAuditTrailAction
{
    /**
     * Log an immutable audit trail entry.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>  $options
     */
    public function __invoke(
        string $event,
        string $description,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $options = []
    ): AuditLog {
        /** @var User|null $user */
        $user = $options['user'] ?? Auth::user();

        $ip = $options['ip_address'] ?? Request::ip();
        $userAgent = $options['user_agent'] ?? Request::userAgent();

        $auditableType = $auditable ? get_class($auditable) : ($options['auditable_type'] ?? null);
        $auditableId = $auditable ? (string) $auditable->getKey() : ($options['auditable_id'] ?? null);

        return AuditLog::create([
            'event' => $event,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'ip_address' => $ip,
            'user_agent' => $userAgent ? substr((string) $userAgent, 0, 500) : null,
        ]);
    }
}
