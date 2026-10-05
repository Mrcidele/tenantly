<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DataExport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class DataExportReady extends Notification
{
    use Queueable;

    public function __construct(public readonly DataExport $export) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // URL gerada no host do tenant (UrlBootstrapper) e exige login + permissão.
        return (new MailMessage)
            ->subject('Sua exportação de dados está pronta')
            ->line('O pacote com os dados da organização foi gerado.')
            ->action('Baixar', url('/settings/data/exports/'.$this->export->id))
            ->line('O arquivo fica disponível por '.DataExport::RETENTION_DAYS.' dias.');
    }
}
