<?php

namespace App\Models;

use App\Enums\RoleSlug;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'password', 'locale', 'timezone'])]
#[Hidden(['password', 'remember_token', 'id'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, Notifiable, SoftDeletes;

    /**
     * @var list<string>|null
     */
    private ?array $permissionCache = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'suspended_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'is_staff' => 'boolean',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function preferredLocale(): string
    {
        return $this->locale ?: config('app.locale');
    }

    /**
     * @return HasOne<UserProfile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class)->withDefault();
    }

    /**
     * @return HasOne<UserSetting, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class)->withDefault();
    }

    /**
     * @return HasMany<WishlistItem, $this>
     */
    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    /**
     * @return HasOne<Cart, $this>
     */
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    /**
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * The halal shop this login manages. Null for customers and platform staff.
     *
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeCustomers(Builder $query): void
    {
        $query->where('is_staff', false);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeStaff(Builder $query): void
    {
        $query->where('is_staff', true);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isStaff(): bool
    {
        return $this->is_staff;
    }

    public function hasRole(RoleSlug|string $role): bool
    {
        $slug = $role instanceof RoleSlug ? $role->value : $role;

        return $this->roles->contains('slug', $slug);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleSlug::SuperAdmin);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($permission, $this->permissionSlugs(), true);
    }

    /**
     * @return list<string>
     */
    public function permissionSlugs(): array
    {
        if ($this->permissionCache !== null) {
            return $this->permissionCache;
        }

        $this->loadMissing('roles.permissions');

        return $this->permissionCache = $this->roles
            ->flatMap(fn (Role $role) => $role->permissions->pluck('slug'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Replace the user's roles. Role slugs are validated against the database,
     * never trusted from the request as-is.
     *
     * @param  list<string>  $roleSlugs
     */
    public function syncRoles(array $roleSlugs): void
    {
        $roles = Role::query()->whereIn('slug', $roleSlugs)->get();

        $this->roles()->sync($roles->pluck('id'));
        $this->forceFill([
            'is_staff' => $roles->contains(fn (Role $role) => $role->slug !== RoleSlug::Customer->value),
        ])->save();

        $this->unsetRelation('roles');
        $this->permissionCache = null;
    }
}
