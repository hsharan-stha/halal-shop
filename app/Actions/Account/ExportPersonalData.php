<?php

namespace App\Actions\Account;

use App\Models\User;

/**
 * Builds a machine-readable export of the personal data held for a customer.
 */
class ExportPersonalData
{
    /**
     * @return array<string, mixed>
     */
    public function handle(User $user): array
    {
        $user->loadMissing(['profile', 'settings']);

        $export = [
            'generated_at' => now()->toIso8601String(),
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'locale' => $user->locale,
                'timezone' => $user->timezone,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
            'profile' => [
                'date_of_birth' => $user->profile->date_of_birth?->toDateString(),
            ],
            'preferences' => [
                'theme' => $user->settings->theme,
                'marketing_emails' => (bool) $user->settings->marketing_emails,
            ],
        ];

        foreach ($this->relations() as $key => $callback) {
            if (method_exists($user, $key)) {
                $export[$key] = $callback($user);
            }
        }

        return $export;
    }

    /**
     * Optional datasets added as the relevant modules exist.
     *
     * @return array<string, callable(User): mixed>
     */
    private function relations(): array
    {
        return [
            'addresses' => fn (User $user) => $user->addresses()->get()->map->only([
                'label', 'recipient_name', 'phone', 'postal_code', 'prefecture', 'city', 'ward', 'town', 'street', 'building', 'room',
            ])->all(),
            'orders' => fn (User $user) => $user->orders()->get()->map(fn ($order) => [
                'order_number' => $order->order_number,
                'status' => $order->status?->value,
                'total' => $order->total,
                'placed_at' => $order->placed_at?->toIso8601String(),
            ])->all(),
            'reviews' => fn (User $user) => $user->reviews()->get()->map->only(['rating', 'title', 'comment', 'created_at'])->all(),
        ];
    }
}
