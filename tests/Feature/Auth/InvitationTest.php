<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Mail\InvitationMail;
use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();
    $this->acme = Tenant::factory()->create(['slug' => 'acme']);
    $this->owner = memberOf($this->acme);
});

function inviteAndCaptureUrl(object $test, string $email, string $role = 'member'): string
{
    $test->actingAs($test->owner)->post(tenantUrl($test->acme, 'invitations'), ['email' => $email, 'role' => $role])->assertSessionHasNoErrors();

    $url = null;
    Mail::assertQueued(InvitationMail::class, function (InvitationMail $mail) use (&$url): bool {
        $url = $mail->acceptUrl;

        return true;
    });

    auth()->logout();

    return $url;
}

it('convida por e-mail com link assinado e cria a conta ao aceitar', function (): void {
    $url = inviteAndCaptureUrl($this, 'nova@exemplo.com');

    expect($url)->toStartWith('http://acme.tenantly.test/invitations/');
    $this->get($url)->assertOk();

    $this->post($url, ['name' => 'Nova', 'password' => 'senha-forte-123'])->assertRedirect('/');

    $user = inTenant($this->acme, fn () => User::query()->where('email', 'nova@exemplo.com')->first());
    expect($user)->not->toBeNull()
        ->and(inTenant($this->acme, fn () => $user->membership()?->role))->toBe(MembershipRole::Member);
    $this->assertAuthenticatedAs($user);
});

it('exige a senha da conta existente em outra organização', function (): void {
    $globex = Tenant::factory()->create();
    memberOf($globex, attributes: ['email' => 'bob@exemplo.com', 'password' => 'senha-do-bob-1']);

    $url = inviteAndCaptureUrl($this, 'bob@exemplo.com');

    $this->post($url, ['password' => 'errada-123456'])->assertSessionHasErrors('password');
    $this->post($url, ['password' => 'senha-do-bob-1'])->assertRedirect('/');

    expect(inTenant($this->acme, fn () => Membership::query()->count()))->toBe(2);
});

it('rejeita link adulterado, expirado ou já usado', function (): void {
    $url = inviteAndCaptureUrl($this, 'x@exemplo.com');

    $this->get(str_replace('token=', 'token=x', $url))->assertForbidden();

    $this->post($url, ['name' => 'X', 'password' => 'senha-forte-123'])->assertRedirect();
    auth()->logout();
    $this->post($url, ['name' => 'X', 'password' => 'senha-forte-123'])->assertSessionHasErrors('token');

    $url2 = inviteAndCaptureUrl($this, 'y@exemplo.com');
    $this->travel(InviteMemberDays() + 1)->days();
    $this->get($url2)->assertForbidden();
});

it('não aceita convite de um tenant no host de outro', function (): void {
    $globex = Tenant::factory()->create(['slug' => 'globex']);
    $url = inviteAndCaptureUrl($this, 'z@exemplo.com');

    expect($this->get(str_replace('acme.', 'globex.', $url))->status())->toBeIn([403, 404]);
});

function InviteMemberDays(): int
{
    return App\Actions\Members\InviteMember::EXPIRES_IN_DAYS;
}
