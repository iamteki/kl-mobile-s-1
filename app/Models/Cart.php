<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'user_id',
        'customer_id',
        'event_date',
        'event_details',
        'subtotal',
        'expires_at',
    ];

    protected $casts = [
        'event_date' => 'date',
        'event_details' => 'array',
        'subtotal' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($cart) {
            // Set expiration time (24 hours from now)
            if (!$cart->expires_at) {
                $cart->expires_at = now()->addHours(24);
            }
        });
    }

    /**
     * Get the user this cart belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the customer this cart belongs to.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get all items in this cart.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Scope to get active carts (not expired).
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to get expired carts.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Get or create cart for session/user.
     */
    public static function getOrCreate($sessionId = null, $userId = null): self
    {
        $query = self::active();

        if ($userId) {
            $cart = $query->where('user_id', $userId)->first();
        } elseif ($sessionId) {
            $cart = $query->where('session_id', $sessionId)->first();
        } else {
            return new self();
        }

        if (!$cart) {
            $cart = self::create([
                'session_id' => $sessionId,
                'user_id' => $userId,
            ]);
        }

        // Extend expiration
        $cart->extendExpiration();

        return $cart;
    }

    /**
     * Merge guest cart with user cart.
     */
    public static function mergeCart(string $sessionId, int $userId): self
    {
        $guestCart = self::active()->where('session_id', $sessionId)->first();
        $userCart = self::active()->where('user_id', $userId)->first();

        if (!$guestCart) {
            return $userCart ?: self::create(['user_id' => $userId]);
        }

        if (!$userCart) {
            // Convert guest cart to user cart
            $guestCart->update([
                'user_id' => $userId,
                'session_id' => null,
            ]);
            return $guestCart;
        }

        // Merge items from guest cart to user cart
        foreach ($guestCart->items as $guestItem) {
            $existingItem = $userCart->items()
                ->where('item_type', $guestItem->item_type)
                ->where('product_id', $guestItem->product_id)
                ->where('product_variation_id', $guestItem->product_variation_id)
                ->where('package_id', $guestItem->package_id)
                ->first();

            if ($existingItem) {
                $existingItem->increment('quantity', $guestItem->quantity);
            } else {
                $guestItem->cart_id = $userCart->id;
                $guestItem->save();
            }
        }

        // Delete guest cart
        $guestCart->delete();

        $userCart->updateTotals();

        return $userCart;
    }

    /**
     * Add product to cart.
     */
    public function addProduct(Product $product, int $quantity = 1, ?ProductVariation $variation = null, ?Carbon $eventDate = null): CartItem
    {
        // Update event date if provided
        if ($eventDate && !$this->event_date) {
            $this->update(['event_date' => $eventDate]);
        }

        // Check for existing item
        $existingItem = $this->items()
            ->where('item_type', 'product')
            ->where('product_id', $product->id)
            ->where('product_variation_id', $variation?->id)
            ->first();

        if ($existingItem) {
            $existingItem->increment('quantity', $quantity);
            $this->updateTotals();
            return $existingItem;
        }

        // Calculate price
        $unitPrice = $product->getPriceWithVariation($variation);
        $rentalDays = 1; // Default rental period

        if ($this->event_date) {
            // You can implement logic to determine rental days based on event details
            $rentalDays = $this->event_details['rental_days'] ?? 1;
        }

        $item = $this->items()->create([
            'item_type' => 'product',
            'product_id' => $product->id,
            'product_variation_id' => $variation?->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * $quantity,
            'rental_days' => $rentalDays,
            'item_data' => [
                'name' => $product->name,
                'sku' => $variation ? $variation->sku : $product->sku,
                'image' => $product->primary_image,
            ],
        ]);

        $this->updateTotals();

        return $item;
    }

    /**
     * Add package to cart.
     */
    public function addPackage(Package $package, int $quantity = 1, ?Carbon $eventDate = null): CartItem
    {
        // Update event date if provided
        if ($eventDate && !$this->event_date) {
            $this->update(['event_date' => $eventDate]);
        }

        // Check for existing item
        $existingItem = $this->items()
            ->where('item_type', 'package')
            ->where('package_id', $package->id)
            ->first();

        if ($existingItem) {
            $existingItem->increment('quantity', $quantity);
            $this->updateTotals();
            return $existingItem;
        }

        $item = $this->items()->create([
            'item_type' => 'package',
            'package_id' => $package->id,
            'quantity' => $quantity,
            'unit_price' => $package->price,
            'total_price' => $package->price * $quantity,
            'rental_days' => 1,
            'item_data' => [
                'name' => $package->name,
                'image' => $package->image,
            ],
        ]);

        $this->updateTotals();

        return $item;
    }

    /**
     * Remove item from cart.
     */
    public function removeItem(CartItem $item): bool
    {
        $result = $item->delete();
        $this->updateTotals();
        return $result;
    }

    /**
     * Update item quantity.
     */
    public function updateItemQuantity(CartItem $item, int $quantity): bool
    {
        if ($quantity <= 0) {
            return $this->removeItem($item);
        }

        $item->update([
            'quantity' => $quantity,
            'total_price' => $item->unit_price * $quantity,
        ]);

        $this->updateTotals();

        return true;
    }

    /**
     * Clear all items from cart.
     */
    public function clear(): void
    {
        $this->items()->delete();
        $this->updateTotals();
    }

    /**
     * Update cart totals.
     */
    public function updateTotals(): void
    {
        $this->subtotal = $this->items->sum('total_price');
        $this->save();
    }

    /**
     * Check if cart is empty.
     */
    public function isEmpty(): bool
    {
        return $this->items->count() === 0;
    }

    /**
     * Get item count.
     */
    public function getItemCountAttribute(): int
    {
        return $this->items->sum('quantity');
    }

    /**
     * Check if cart has expired.
     */
    public function hasExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Extend cart expiration.
     */
    public function extendExpiration(int $hours = 24): void
    {
        $this->update(['expires_at' => now()->addHours($hours)]);
    }

    /**
     * Set event details.
     */
    public function setEventDetails(array $details): void
    {
        $this->event_details = array_merge($this->event_details ?? [], $details);
        
        if (isset($details['event_date'])) {
            $this->event_date = Carbon::parse($details['event_date']);
        }
        
        $this->save();
    }

    /**
     * Check availability for all items.
     */
    public function checkAvailability(?Carbon $date = null): array
    {
        $date = $date ?: $this->event_date;
        
        if (!$date) {
            return ['available' => false, 'message' => 'Event date not set'];
        }

        $unavailable = [];

        foreach ($this->items as $item) {
            if ($item->item_type === 'product' && $item->product) {
                $available = $item->product->getAvailabilityForDateRange(
                    $date,
                    $date,
                    $item->product_variation_id
                );

                if ($available < $item->quantity) {
                    $unavailable[] = [
                        'item' => $item,
                        'available' => $available,
                        'requested' => $item->quantity,
                    ];
                }
            } elseif ($item->item_type === 'package' && $item->package) {
                if (!$item->package->isAvailable($date, $item->quantity)) {
                    $unavailable[] = [
                        'item' => $item,
                        'available' => 0,
                        'requested' => $item->quantity,
                    ];
                }
            }
        }

        return [
            'available' => empty($unavailable),
            'unavailable_items' => $unavailable,
        ];
    }

    /**
     * Convert cart to booking.
     */
    public function convertToBooking(Customer $customer, array $eventDetails): ?Booking
    {
        if ($this->isEmpty()) {
            return null;
        }

        $booking = Booking::create([
            'customer_id' => $customer->id,
            'user_id' => $this->user_id,
            'event_date' => $this->event_date,
            'event_type' => $eventDetails['event_type'],
            'event_venue' => $eventDetails['event_venue'],
            'number_of_pax' => $eventDetails['number_of_pax'],
            'installation_time' => $eventDetails['installation_time'],
            'event_start_time' => $eventDetails['event_start_time'],
            'dismantle_time' => $eventDetails['dismantle_time'],
            'subtotal' => $this->subtotal,
            'total_amount' => $this->subtotal, // Will be recalculated with taxes, etc.
            'customer_notes' => $eventDetails['notes'] ?? null,
        ]);

        // Convert cart items to booking items
        foreach ($this->items as $cartItem) {
            $booking->items()->create([
                'item_type' => $cartItem->item_type,
                'product_id' => $cartItem->product_id,
                'product_variation_id' => $cartItem->product_variation_id,
                'package_id' => $cartItem->package_id,
                'quantity' => $cartItem->quantity,
                'unit_price' => $cartItem->unit_price,
                'total_price' => $cartItem->total_price,
                'rental_days' => $cartItem->rental_days,
            ]);
        }

        // Clear cart after conversion
        $this->clear();

        return $booking;
    }
}