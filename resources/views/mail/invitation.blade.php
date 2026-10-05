<x-mail::message>
# Você foi convidado para {{ $tenant->name }}

Papel: **{{ $invitation->role->label() }}**

<x-mail::button :url="$acceptUrl">
Aceitar convite
</x-mail::button>

O link expira em {{ $invitation->expires_at->format('d/m/Y H:i') }}.
</x-mail::message>
