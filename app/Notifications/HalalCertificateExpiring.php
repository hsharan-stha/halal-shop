<?php

namespace App\Notifications;

use App\Models\HalalCertification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HalalCertificateExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public HalalCertification $certification, public int $daysLeft) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replace = $this->replacements();

        return (new MailMessage)
            ->subject($this->daysLeft < 0 ? __('admin.halal.alerts.expired_subject', $replace) : __('admin.halal.alerts.expiring_subject', $replace))
            ->line($this->daysLeft < 0 ? __('admin.halal.alerts.expired_line', $replace) : __('admin.halal.alerts.expiring_line', $replace))
            ->line(__('admin.halal.alerts.products_line', ['count' => $this->certification->products->count()]))
            ->action(__('admin.halal.alerts.action'), route('admin.halal-certifications.show', $this->certification));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'halal_certificate_expiring',
            'halal_certification_id' => $this->certification->id,
            'certifying_body' => $this->certification->certifying_body,
            'certificate_number' => $this->certification->certificate_number,
            'expires_at' => $this->certification->expires_at->toDateString(),
            'days_left' => $this->daysLeft,
            'url' => route('admin.halal-certifications.show', $this->certification),
        ];
    }

    /**
     * @return array<string, string|int>
     */
    private function replacements(): array
    {
        return [
            'body' => $this->certification->certifying_body,
            'number' => $this->certification->certificate_number,
            'date' => local_date($this->certification->expires_at),
            'days' => max(0, $this->daysLeft),
        ];
    }
}
