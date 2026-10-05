<?php

declare(strict_types=1);

namespace App\Audit;

use App\Models\AuditLog;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Grava "quem fez o quê, em qual tenant". O tenant vem sempre do contexto,
 * nunca do chamador.
 */
final readonly class AuditLogger
{
    public function __construct(
        private Application $container,
        private AuthFactory $auth,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(string $action, array $properties = [], ?Model $subject = null): AuditLog
    {
        [$actorType, $actorId] = $this->actor();
        $request = $this->request();

        $subjectKey = $subject?->getKey();

        return AuditLog::query()->create([
            'tenant_id' => $this->container->make(TenantContext::class)->id(),
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'impersonator_id' => $this->impersonatorId($request),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => is_string($subjectKey) ? $subjectKey : null,
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => $request?->ip(),
            'user_agent' => $request !== null ? mb_substr((string) $request->userAgent(), 0, 1000) : null,
        ]);
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private function actor(): array
    {
        foreach (['admin' => 'admin', 'web' => 'user', 'sanctum' => 'user'] as $guard => $type) {
            if (config("auth.guards.{$guard}") === null) {
                continue;
            }

            $user = $this->auth->guard($guard)->user();

            if ($user !== null) {
                $id = $user->getAuthIdentifier();

                return [$type, is_string($id) ? $id : null];
            }
        }

        return ['system', null];
    }

    private function request(): ?Request
    {
        if ($this->container->runningInConsole() && ! $this->container->runningUnitTests()) {
            return null;
        }

        return $this->container->bound('request') ? $this->container->make(Request::class) : null;
    }

    private function impersonatorId(?Request $request): ?string
    {
        if ($request === null || ! $request->hasSession()) {
            return null;
        }

        $id = $request->session()->get('impersonator_id');

        return is_string($id) ? $id : null;
    }
}
