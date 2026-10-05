<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\Filesystem\TenantFiles;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Jobs\RecordTenantJob;
use Tests\Support\TestingBroadcaster;

beforeEach(function (): void {
    $this->a = Tenant::factory()->create(['slug' => 'acme']);
    $this->b = Tenant::factory()->create(['slug' => 'globex']);
    inTenant($this->a, fn () => Project::factory()->create(['name' => 'A1']));
    inTenant($this->b, fn () => Project::factory()->create(['name' => 'B1']));
});

describe('filas', function (): void {
    it('grava o tenant no payload e restaura no worker, com dois tenants na mesma fila', function (): void {
        // Closures sem retorno: PendingDispatch enfileira no destrutor.
        inTenant($this->a, function (): void {
            RecordTenantJob::dispatch('a')->onConnection('redis');
        });
        inTenant($this->b, function (): void {
            RecordTenantJob::dispatch('b')->onConnection('redis');
        });
        RecordTenantJob::dispatch('central')->onConnection('redis');

        $payload = json_decode((string) Redis::connection('default')->lindex('queues:default', 0), true);
        expect($payload['tenant_id'])->toBe($this->a->id);

        $this->artisan('queue:work', ['connection' => 'redis', '--stop-when-empty' => true])->assertSuccessful();

        expect(Cache::store('central')->get('job:a'))->toMatchArray(['tenant' => $this->a->id, 'projects' => ['A1'], 'url' => 'http://acme.tenantly.test/x'])
            ->and(Cache::store('central')->get('job:b'))->toMatchArray(['tenant' => $this->b->id, 'projects' => ['B1'], 'url' => 'http://globex.tenantly.test/x'])
            ->and(Cache::store('central')->get('job:central'))->toMatchArray(['tenant' => null, 'projects' => null]);
    });

    it('restaura o contexto anterior depois de um job sync', function (): void {
        tenancy()->set($this->a);

        inTenant($this->b, fn () => RecordTenantJob::dispatchSync('sync'));

        expect(tenancy()->id())->toBe($this->a->id)
            ->and(Cache::store('central')->get('job:sync')['tenant'])->toBe($this->b->id);
    });

    it('falha o job cujo tenant não existe mais', function (): void {
        inTenant($this->a, function (): void {
            RecordTenantJob::dispatch('orfao')->onConnection('redis');
        });
        $this->a->delete();

        $this->artisan('queue:work', ['connection' => 'redis', '--stop-when-empty' => true, '--tries' => 1]);

        expect(Cache::store('central')->get('job:orfao'))->toBeNull();
    });
});

describe('cache', function (): void {
    it('isola chaves por tenant com o mesmo nome', function (): void {
        inTenant($this->a, fn () => Cache::put('contador', 'a'));
        inTenant($this->b, fn () => expect(Cache::get('contador'))->toBeNull());
        inTenant($this->b, fn () => Cache::put('contador', 'b'));

        inTenant($this->a, fn () => expect(Cache::get('contador'))->toBe('a'));
        expect(Cache::get('contador'))->toBeNull();

        $keys = Redis::connection('cache')->keys('*contador');
        expect(collect($keys)->filter(fn (string $k): bool => str_contains($k, 'tenant:'.$this->a->id.':contador')))->toHaveCount(1);
    });

    it('volta ao prefixo central ao sair do tenant', function (): void {
        inTenant($this->a, fn () => null);
        Cache::put('global', 1);

        inTenant($this->a, fn () => expect(Cache::get('global'))->toBeNull());
    });
});

describe('storage', function (): void {
    beforeEach(function (): void {
        $root = storage_path('framework/testing/disks/tenant-isolation');
        File::deleteDirectory($root);
        config()->set('filesystems.disks.local.root', $root);
        Storage::forgetDisk(['local', 'tenant']);
    });

    it('separa os arquivos em pastas por tenant', function (): void {
        inTenant($this->a, fn () => app(TenantFiles::class)->disk()->put('docs/contrato.txt', 'A'));

        inTenant($this->b, fn () => expect(app(TenantFiles::class)->disk()->exists('docs/contrato.txt'))->toBeFalse());
        expect(Storage::disk('local')->exists('tenants/'.$this->a->id.'/docs/contrato.txt'))->toBeTrue();
    });

    it('não monta o disco sem tenant ativo', function (): void {
        Storage::disk('tenant');
    })->throws(App\Tenancy\Exceptions\MissingTenantContext::class);

    it('serve arquivo por URL assinada só no host do tenant', function (): void {
        $url = inTenant($this->a, function (): string {
            app(TenantFiles::class)->disk()->put('docs/contrato.txt', 'conteudo A');

            return app(TenantFiles::class)->temporaryUrl('docs/contrato.txt');
        });

        expect($url)->toStartWith('http://acme.tenantly.test/files/docs/contrato.txt');

        $this->get($url)->assertOk()->assertStreamedContent('conteudo A');
        $this->get(str_replace('acme.', 'globex.', $url))->assertForbidden();
        $this->get(str_replace('contrato', 'outro', $url))->assertForbidden();
        $this->get(tenantUrl($this->a, 'files/docs/contrato.txt'))->assertForbidden();
    });

    it('impede path traversal para a pasta de outro tenant', function (): void {
        inTenant($this->b, fn () => app(TenantFiles::class)->disk()->put('segredo.txt', 'B'));

        $url = inTenant($this->a, fn () => app(TenantFiles::class)->temporaryUrl('../'.$this->b->id.'/segredo.txt'));

        $this->get($url)->assertNotFound();
    });
});

it('marca e-mails com o tenant e o nome da organização', function (): void {
    Mail::fake();
    $mailable = (new Mailable)->to('x@example.com')->subject('Oi')->html('ok');

    inTenant($this->a, fn () => Mail::send($mailable));

    Mail::assertSent(Mailable::class);
});

it('propaga o tenant_id para o contexto de logs', function (): void {
    inTenant($this->a, fn () => expect(Context::get('tenant_id'))->toBe($this->a->id));

    expect(Context::get('tenant_id'))->toBeNull();
});

it('só autoriza canais broadcast do tenant atual para membros', function (): void {
    Broadcast::extend('testing', fn () => new TestingBroadcaster);
    config()->set('broadcasting.connections.testing', ['driver' => 'testing']);
    config()->set('broadcasting.default', 'testing');
    require base_path('routes/channels.php'); // registra os canais no driver de teste

    $user = inTenant($this->a, function (): User {
        $user = User::factory()->create();
        Membership::factory()->for($user)->role(MembershipRole::Member)->create();

        return $user;
    });

    $auth = fn (Tenant $host, string $channel) => $this->actingAs($user)
        ->post('http://'.$host->subdomainHost().'/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1.1']);

    $auth($this->a, 'private-tenant.'.$this->a->id)->assertOk();
    $auth($this->a, 'private-tenant.'.$this->b->id)->assertForbidden();
    $auth($this->b, 'private-tenant.'.$this->b->id)->assertForbidden();
});
