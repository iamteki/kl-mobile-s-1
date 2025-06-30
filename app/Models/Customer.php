<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'phone',
        'secondary_phone',
        'company',
        'address',
        'city',
        'state',
        'postcode',
        'country',
        'tax_id',
        'total_bookings',
        'total_spent',
        'notes',
        'status',
    ];

    protected $casts = [
        'total_bookings' => 'integer',
        'total_spent' => 'decimal:2',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Update statistics when a booking is created
        static::created(function ($customer) {
            if ($customer->user_id) {
                $user = User::find($customer->user_id);
                if ($user && !$user->hasRole('customer')) {
                    $user->assignRole('customer');
                }
            }
        });
    }

    /**
     * Get the user account associated with this customer.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all bookings for this customer.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get all carts for this customer.
     */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    /**
     * Scope to get only active customers.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get the customer's full address.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->postcode,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get the customer's display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->company ?: $this->name;
    }

    /**
     * Update customer statistics.
     */
    public function updateStatistics()
    {
        $bookings = $this->bookings()
            ->whereIn('status', ['completed', 'delivered'])
            ->get();

        $this->update([
            'total_bookings' => $bookings->count(),
            'total_spent' => $bookings->sum('total_amount'),
        ]);
    }

    /**
     * Check if customer can make bookings.
     */
    public function canMakeBookings(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get customer tier based on spending.
     */
    public function getTierAttribute(): string
    {
        if ($this->total_spent >= 1000000) {
            return 'platinum';
        } elseif ($this->total_spent >= 500000) {
            return 'gold';
        } elseif ($this->total_spent >= 100000) {
            return 'silver';
        }
        
        return 'bronze';
    }

    /**
     * Get discount percentage based on tier.
     */
    public function getTierDiscountAttribute(): int
    {
        return match($this->tier) {
            'platinum' => 10,
            'gold' => 7,
            'silver' => 5,
            default => 0,
        };
    }
}