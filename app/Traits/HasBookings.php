<?php

namespace App\Traits;

use App\Models\Booking;
use App\Models\BookingItem;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

trait HasBookings
{
    /**
     * Get booking items for this product.
     */
    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class, 'product_id');
    }

    /**
     * Get bookings through booking items.
     */
    public function bookings(): HasManyThrough
    {
        return $this->hasManyThrough(
            Booking::class,
            BookingItem::class,
            'product_id',
            'id',
            'id',
            'booking_id'
        );
    }

    /**
     * Get active bookings.
     */
    public function activeBookings(): HasManyThrough
    {
        return $this->bookings()
            ->whereIn('bookings.status', ['confirmed', 'processing', 'delivered']);
    }

    /**
     * Get bookings for a specific date.
     */
    public function getBookingsForDate(Carbon $date)
    {
        return $this->bookings()
            ->whereDate('event_date', $date)
            ->get();
    }

    /**
     * Get bookings for a date range.
     */
    public function getBookingsForDateRange(Carbon $startDate, Carbon $endDate)
    {
        return $this->bookings()
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('event_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        // Include bookings that span across the date range
                        $q->where('event_date', '<', $startDate)
                          ->whereRaw('DATE_ADD(event_date, INTERVAL 
                              (SELECT MAX(rental_days) FROM booking_items WHERE booking_id = bookings.id) DAY
                          ) >= ?', [$startDate]);
                    });
            })
            ->whereIn('status', ['confirmed', 'processing', 'delivered'])
            ->get();
    }

    /**
     * Check if product is booked on a specific date.
     */
    public function isBookedOn(Carbon $date, ?int $variationId = null): bool
    {
        $query = $this->bookingItems()
            ->whereHas('booking', function ($q) use ($date) {
                $q->where('event_date', '<=', $date)
                  ->whereRaw('DATE_ADD(event_date, INTERVAL rental_days DAY) > ?', [$date])
                  ->whereIn('status', ['confirmed', 'processing', 'delivered']);
            });

        if ($variationId) {
            $query->where('product_variation_id', $variationId);
        }

        return $query->exists();
    }

    /**
     * Get booked quantity for a specific date.
     */
    public function getBookedQuantityForDate(Carbon $date, ?int $variationId = null): int
    {
        $query = $this->bookingItems()
            ->whereHas('booking', function ($q) use ($date) {
                $q->where('event_date', '<=', $date)
                  ->whereRaw('DATE_ADD(event_date, INTERVAL rental_days DAY) > ?', [$date])
                  ->whereIn('status', ['confirmed', 'processing', 'delivered']);
            });

        if ($variationId) {
            $query->where('product_variation_id', $variationId);
        }

        return $query->sum('quantity');
    }

    /**
     * Get booked quantity for a date range.
     */
    public function getBookedQuantityForDateRange(Carbon $startDate, Carbon $endDate, ?int $variationId = null): int
    {
        $query = $this->bookingItems()
            ->whereHas('booking', function ($q) use ($startDate, $endDate) {
                $q->where(function ($query) use ($startDate, $endDate) {
                    // Bookings that start within the range
                    $query->whereBetween('event_date', [$startDate, $endDate])
                        // Bookings that end within the range
                        ->orWhere(function ($q) use ($startDate, $endDate) {
                            $q->where('event_date', '<', $startDate)
                              ->whereRaw('DATE_ADD(event_date, INTERVAL rental_days DAY) BETWEEN ? AND ?', [$startDate, $endDate]);
                        })
                        // Bookings that span the entire range
                        ->orWhere(function ($q) use ($startDate, $endDate) {
                            $q->where('event_date', '<', $startDate)
                              ->whereRaw('DATE_ADD(event_date, INTERVAL rental_days DAY) > ?', [$endDate]);
                        });
                })
                ->whereIn('status', ['confirmed', 'processing', 'delivered']);
            });

        if ($variationId) {
            $query->where('product_variation_id', $variationId);
        }

        // Get the maximum quantity booked on any single day
        $maxBooked = 0;
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            $dayBooked = $this->getBookedQuantityForDate($currentDate, $variationId);
            $maxBooked = max($maxBooked, $dayBooked);
            $currentDate->addDay();
        }

        return $maxBooked;
    }

    /**
     * Get availability calendar for a month.
     */
    public function getAvailabilityCalendar(int $year, int $month, ?int $variationId = null): array
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        
        $calendar = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            $booked = $this->getBookedQuantityForDate($currentDate, $variationId);
            $total = $this->getTotalQuantity($variationId);
            $available = max(0, $total - $booked);
            
            $calendar[$currentDate->format('Y-m-d')] = [
                'date' => $currentDate->format('Y-m-d'),
                'available' => $available,
                'booked' => $booked,
                'total' => $total,
                'is_available' => $available > 0,
                'is_past' => $currentDate->isPast(),
            ];
            
            $currentDate->addDay();
        }
        
        return $calendar;
    }

    /**
     * Get next available date.
     */
    public function getNextAvailableDate(?int $variationId = null): ?Carbon
    {
        $date = Carbon::today();
        $maxDays = 365; // Check up to a year ahead
        
        for ($i = 0; $i < $maxDays; $i++) {
            $available = $this->getAvailableQuantity($variationId) - $this->getBookedQuantityForDate($date, $variationId);
            
            if ($available > 0) {
                return $date;
            }
            
            $date->addDay();
        }
        
        return null;
    }

    /**
     * Get booking statistics.
     */
    public function getBookingStats(): array
    {
        $stats = [
            'total_bookings' => $this->bookings()->count(),
            'confirmed_bookings' => $this->bookings()->where('status', 'confirmed')->count(),
            'completed_bookings' => $this->bookings()->where('status', 'completed')->count(),
            'cancelled_bookings' => $this->bookings()->where('status', 'cancelled')->count(),
            'total_revenue' => $this->bookingItems()->sum('total_price'),
            'total_quantity_booked' => $this->bookingItems()->sum('quantity'),
            'average_rental_days' => $this->bookingItems()->avg('rental_days'),
        ];

        // Most popular variation
        if ($this->hasVariations()) {
            $popularVariation = $this->bookingItems()
                ->select('product_variation_id')
                ->selectRaw('COUNT(*) as count')
                ->whereNotNull('product_variation_id')
                ->groupBy('product_variation_id')
                ->orderByDesc('count')
                ->first();
                
            if ($popularVariation) {
                $stats['most_popular_variation_id'] = $popularVariation->product_variation_id;
            }
        }

        return $stats;
    }

    /**
     * Get upcoming bookings.
     */
    public function getUpcomingBookings(int $limit = 5)
    {
        return $this->bookings()
            ->where('event_date', '>=', Carbon::today())
            ->whereIn('status', ['confirmed', 'processing'])
            ->orderBy('event_date')
            ->limit($limit)
            ->get();
    }

    /**
     * Calculate availability percentage for a date range.
     */
    public function calculateAvailabilityPercentage(Carbon $startDate, Carbon $endDate, ?int $variationId = null): float
    {
        $totalDays = $startDate->diffInDays($endDate) + 1;
        $availableDays = 0;
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            $available = $this->getAvailableQuantity($variationId) - $this->getBookedQuantityForDate($currentDate, $variationId);
            
            if ($available > 0) {
                $availableDays++;
            }
            
            $currentDate->addDay();
        }
        
        return round(($availableDays / $totalDays) * 100, 2);
    }
}