<?php

namespace Sonole\GovGr\Core\Audit;

use Sonole\GovGr\Core\Contracts\IdentityResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/** Identity for calls with no authenticated request: queued jobs, scheduled commands, console-triggered lookups. */
class SystemIdentityResolver implements IdentityResolver
{
    /** @throws RuntimeException */
    public function resolve(?Request $request): AuditIdentity
    {
        $email = config('govgr.audit.system_user_email');
        $ip = config('govgr.audit.system_ip');

        if (empty($email)) {
            throw new RuntimeException(
                'govgr.audit.system_user_email is not configured. Set GOVGR_AUDIT_SYSTEM_USER_EMAIL to the user this package should attribute calls with no authenticated request to (queued jobs, scheduled commands, console-triggered lookups).'
            );
        }

        if (empty($ip)) {
            throw new RuntimeException(
                'govgr.audit.system_ip is not configured. Set GOVGR_AUDIT_SYSTEM_IP to the auditUserIp to use for calls with no authenticated request (queued jobs, scheduled commands, console-triggered lookups).'
            );
        }

        return new AuditIdentity(
            email: $email,
            ip: $ip,
            userId: $this->resolveUserId($email),
        );
    }

    /**
     * Cached forever once found (per email)
     * @throws RuntimeException
     */
    private function resolveUserId(string $email): int
    {
        return Cache::rememberForever("greekgov:system-user-id:{$email}", function () use ($email) {
            $modelClass = config('govgr.audit.system_user_model') ?? config('auth.providers.users.model', 'App\\Models\\User');
            $column = config('govgr.audit.system_user_email_column', 'email');

            $user = $modelClass::where($column, $email)->first();

            if (! $user) {
                throw new RuntimeException(
                    "No user found with email [$email] (govgr.audit.system_user_email) to use as the system audit identity's userId."
                );
            }

            return (int) $user->getKey();
        });
    }
}