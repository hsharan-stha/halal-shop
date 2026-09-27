<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockDigest extends Notification implements ShouldQueue
{
    use Queueable;

    private const MAIL_LINES = 20;

    /**
     * @param  list<array{item_id: int, product: string, variant: ?string, sku: ?string, sellable: int, threshold: int}>  $items
     */
    public function __construct(public array $items) {}

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
            ->subject(__('admin.inventory.alerts.low_stock_subject', ['count' => count($this->items)]))
            ->line(__('admin.inventory.alerts.low_stock_intro'));

        foreach (array_slice($this->items, 0, self::MAIL_LINES) as $item) {
            $message->line(__($item['sellable'] > 0 ? 'admin.inventory.alerts.low_stock_line' : 'admin.inventory.alerts.out_of_stock_line', [
                'product' => $item['product'].($item['variant'] ? ' '.$item['variant'] : ''),
                'sku' => $item['sku'] ?? '—',
                'quantity' => $item['sellable'],
            ]));
        }

        if (count($this->items) > self::MAIL_LINES) {
            $message->line(__('admin.inventory.alerts.and_more', ['count' => count($this->items) - self::MAIL_LINES]));
        }

        return $message->action(__('admin.inventory.alerts.low_stock_action'), route('admin.inventory.index', ['stock' => 'low_stock']));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'low_stock',
            'count' => count($this->items),
            'out_of_stock' => count(array_filter($this->items, fn (array $item) => $item['sellable'] <= 0)),
            'item_ids' => array_column($this->items, 'item_id'),
            'url' => route('admin.inventory.index', ['stock' => 'low_stock']),
        ];
    }
}
