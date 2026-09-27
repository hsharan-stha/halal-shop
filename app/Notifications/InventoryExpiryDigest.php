<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InventoryExpiryDigest extends Notification implements ShouldQueue
{
    use Queueable;

    private const MAIL_LINES = 20;

    /**
     * @param  list<array{batch_id: int, batch_number: string, product: string, variant: ?string, sku: ?string, quantity: int, expires_at: string, days_left: int}>  $batches
     */
    public function __construct(public array $batches) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('admin.inventory.alerts.expiry_subject', ['count' => count($this->batches)]))
            ->line(__('admin.inventory.alerts.expiry_intro'));

        foreach (array_slice($this->batches, 0, self::MAIL_LINES) as $batch) {
            $message->line($this->describe($batch));
        }

        if (count($this->batches) > self::MAIL_LINES) {
            $message->line(__('admin.inventory.alerts.and_more', ['count' => count($this->batches) - self::MAIL_LINES]));
        }

        return $message->action(__('admin.inventory.alerts.expiry_action'), route('admin.batches.index', ['expiry' => 'expiring']));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'inventory_expiry',
            'count' => count($this->batches),
            'expired' => count(array_filter($this->batches, fn (array $batch) => $batch['days_left'] < 0)),
            'batch_ids' => array_column($this->batches, 'batch_id'),
            'url' => route('admin.batches.index', ['expiry' => 'expiring']),
        ];
    }

    /**
     * @param  array{batch_number: string, product: string, variant: ?string, quantity: int, expires_at: string, days_left: int}  $batch
     */
    private function describe(array $batch): string
    {
        $replace = [
            'product' => $batch['product'].($batch['variant'] ? ' '.$batch['variant'] : ''),
            'batch' => $batch['batch_number'],
            'quantity' => $batch['quantity'],
            'date' => local_date($batch['expires_at']),
            'days' => max(0, $batch['days_left']),
        ];

        return __($batch['days_left'] < 0 ? 'admin.inventory.alerts.expired_line' : 'admin.inventory.alerts.expiring_line', $replace);
    }
}
