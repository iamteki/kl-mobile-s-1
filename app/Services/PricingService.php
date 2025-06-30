<?php
// app/Services/PricingService.php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Package;
use App\Models\Booking;
use App\Models\Customer;
use Carbon\Carbon;

class PricingService
{
    /**
     * Get product price based on rental duration and variation
     */
    public function getProductPrice(Product $product, ?int $variationId = null, int $rentalDays = 1): float
    {
        $basePrice = $product->base_price;
        
        // Apply variation pricing if applicable
        if ($variationId) {
            $variation = ProductVariation::find($variationId);
            if ($variation) {
                if ($variation->price) {
                    // Variation has its own price
                    $basePrice = $variation->price;
                } elseif ($variation->price_modifier) {
                    // Apply modifier to base price
                    if ($variation->price_modifier_type === 'percentage') {
                        $basePrice += ($basePrice * $variation->price_modifier / 100);
                    } else {
                        $basePrice += $variation->price_modifier;
                    }
                }
            }
        }
        
        // Calculate price based on rental duration
        return $this->calculateRentalPrice($product, $basePrice, $rentalDays);
    }
    
    /**
     * Calculate rental price based on duration
     */
    protected function calculateRentalPrice(Product $product, float $basePrice, int $rentalDays): float
    {
        // If product has specific pricing tiers
        if ($product->price_per_day && $rentalDays == 1) {
            return $product->price_per_day;
        } elseif ($product->price_per_week && $rentalDays >= 7 && $rentalDays < 30) {
            $weeks = ceil($rentalDays / 7);
            return $product->price_per_week * $weeks;
        } elseif ($product->price_per_month && $rentalDays >= 30) {
            $months = ceil($rentalDays / 30);
            return $product->price_per_month * $months;
        }
        
        // Default daily pricing with discounts for longer rentals
        $dailyRate = $basePrice;
        
        if ($rentalDays >= 30) {
            // 20% discount for monthly rentals
            $dailyRate = $basePrice * 0.8;
        } elseif ($rentalDays >= 7) {
            // 10% discount for weekly rentals
            $dailyRate = $basePrice * 0.9;
        } elseif ($rentalDays >= 3) {
            // 5% discount for 3+ day rentals
            $dailyRate = $basePrice * 0.95;
        }
        
        return $dailyRate * $rentalDays;
    }
    
    /**
     * Get package price
     */
    public function getPackagePrice(Package $package, int $rentalDays = 1): float
    {
        // Packages typically have fixed pricing
        $basePrice = $package->price;
        
        // Apply duration-based pricing if configured
        if ($package->price_type === 'per_day') {
            return $basePrice * $rentalDays;
        }
        
        // Fixed price regardless of duration
        return $basePrice;
    }
    
    /**
     * Calculate booking total with tax and deposits
     */
    public function calculateBookingTotal(Booking $booking, float $subtotal): array
    {
        // Get tax rate from settings
        $taxRate = config('settings.tax_rate', 6) / 100;
        $taxAmount = $subtotal * $taxRate;
        
        // Calculate any applicable discounts
        $discountAmount = $this->calculateDiscounts($booking, $subtotal);
        
        // Calculate total after tax and discount
        $totalAmount = $subtotal + $taxAmount - $discountAmount;
        
        // Calculate deposit amount
        $depositPercentage = config('settings.deposit_percentage', 30) / 100;
        $depositAmount = $totalAmount * $depositPercentage;
        
        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'discount_amount' => round($discountAmount, 2),
            'total_amount' => round($totalAmount, 2),
            'deposit_amount' => round($depositAmount, 2),
            'balance_due' => round($totalAmount - $depositAmount, 2)
        ];
    }
    
    /**
     * Calculate applicable discounts
     */
    protected function calculateDiscounts(Booking $booking, float $subtotal): float
    {
        $discountAmount = 0;
        
        // Early bird discount (booking 30+ days in advance)
        $daysInAdvance = now()->diffInDays($booking->event_date, false);
        if ($daysInAdvance >= 30) {
            $discountAmount += $subtotal * 0.05; // 5% early bird discount
        }
        
        // Loyalty discount based on customer booking history
        if ($booking->customer) {
            $customerDiscount = $this->getCustomerLoyaltyDiscount($booking->customer, $subtotal);
            $discountAmount += $customerDiscount;
        }
        
        // Bulk discount based on order value
        if ($subtotal >= 10000) {
            $discountAmount += $subtotal * 0.10; // 10% for orders over 10,000
        } elseif ($subtotal >= 5000) {
            $discountAmount += $subtotal * 0.05; // 5% for orders over 5,000
        }
        
        // Apply any coupon discount
        if ($booking->coupon_code) {
            $couponDiscount = $this->applyCouponDiscount($booking->coupon_code, $subtotal);
            $discountAmount += $couponDiscount;
        }
        
        // Cap discount at 25% of subtotal
        return min($discountAmount, $subtotal * 0.25);
    }
    
    /**
     * Get customer loyalty discount
     */
    protected function getCustomerLoyaltyDiscount(Customer $customer, float $subtotal): float
    {
        $totalBookings = $customer->total_bookings;
        $discountPercentage = 0;
        
        if ($totalBookings >= 20) {
            $discountPercentage = 0.10; // 10% for VIP customers
        } elseif ($totalBookings >= 10) {
            $discountPercentage = 0.07; // 7% for regular customers
        } elseif ($totalBookings >= 5) {
            $discountPercentage = 0.05; // 5% for returning customers
        } elseif ($totalBookings >= 2) {
            $discountPercentage = 0.03; // 3% for second-time customers
        }
        
        return $subtotal * $discountPercentage;
    }
    
    /**
     * Apply coupon discount
     */
    protected function applyCouponDiscount(string $couponCode, float $subtotal): float
    {
        // This would typically check against a coupons table
        // For now, implementing some example coupon codes
        
        $coupons = [
            'WELCOME10' => ['type' => 'percentage', 'value' => 10],
            'SAVE50' => ['type' => 'fixed', 'value' => 50],
            'SUMMER20' => ['type' => 'percentage', 'value' => 20],
        ];
        
        $couponCode = strtoupper($couponCode);
        
        if (!isset($coupons[$couponCode])) {
            return 0;
        }
        
        $coupon = $coupons[$couponCode];
        
        if ($coupon['type'] === 'percentage') {
            return $subtotal * ($coupon['value'] / 100);
        } else {
            return min($coupon['value'], $subtotal); // Fixed amount, but not more than subtotal
        }
    }
    
    /**
     * Calculate additional charges
     */
    public function calculateAdditionalCharges(Booking $booking): array
    {
        $charges = [];
        
        // Delivery charges based on distance
        $deliveryCharge = $this->calculateDeliveryCharge($booking->venue_address);
        if ($deliveryCharge > 0) {
            $charges['delivery'] = [
                'description' => 'Delivery and pickup',
                'amount' => $deliveryCharge
            ];
        }
        
        // Setup charges for items requiring operators
        $setupCharge = $this->calculateSetupCharge($booking);
        if ($setupCharge > 0) {
            $charges['setup'] = [
                'description' => 'Professional setup and operation',
                'amount' => $setupCharge
            ];
        }
        
        // Late hours surcharge (events after 10 PM)
        $eventTime = Carbon::parse($booking->event_date . ' ' . $booking->event_start_time);
        if ($eventTime->hour >= 22) {
            $charges['late_hours'] = [
                'description' => 'Late hours surcharge',
                'amount' => 500 // Fixed surcharge
            ];
        }
        
        // Weekend surcharge
        if ($eventTime->isWeekend()) {
            $charges['weekend'] = [
                'description' => 'Weekend surcharge',
                'amount' => $booking->subtotal * 0.1 // 10% weekend surcharge
            ];
        }
        
        return $charges;
    }
    
    /**
     * Calculate delivery charge based on distance
     */
    protected function calculateDeliveryCharge(string $address): float
    {
        // This would typically integrate with a mapping API to calculate distance
        // For now, using a simplified zone-based pricing
        
        $zones = [
            'colombo' => 0, // Free delivery within Colombo
            'suburbs' => 1000, // Suburbs
            'outstation' => 2500, // Outstation
        ];
        
        // Simple keyword matching for demo
        $addressLower = strtolower($address);
        
        if (str_contains($addressLower, 'colombo')) {
            return $zones['colombo'];
        } elseif (str_contains($addressLower, 'gampaha') || str_contains($addressLower, 'kalutara')) {
            return $zones['suburbs'];
        } else {
            return $zones['outstation'];
        }
    }
    
    /**
     * Calculate setup charges
     */
    protected function calculateSetupCharge(Booking $booking): float
    {
        $setupCharge = 0;
        
        foreach ($booking->items as $item) {
            if ($item->product && $item->product->requires_operator) {
                $setupHours = $item->product->setup_time_hours ?? 2;
                $hourlyRate = 1500; // Hourly rate for technicians
                $setupCharge += $setupHours * $hourlyRate * $item->quantity;
            }
        }
        
        return $setupCharge;
    }
    
    /**
     * Get price breakdown for display
     */
    public function getPriceBreakdown(float $subtotal, array $additionalCharges = []): array
    {
        $breakdown = [
            'subtotal' => $subtotal,
            'additional_charges' => $additionalCharges,
            'additional_total' => array_sum(array_column($additionalCharges, 'amount')),
        ];
        
        $subtotalWithCharges = $breakdown['subtotal'] + $breakdown['additional_total'];
        
        // Calculate tax
        $taxRate = config('settings.tax_rate', 6) / 100;
        $breakdown['tax_rate'] = $taxRate * 100;
        $breakdown['tax_amount'] = $subtotalWithCharges * $taxRate;
        
        // Calculate total
        $breakdown['total'] = $subtotalWithCharges + $breakdown['tax_amount'];
        
        // Calculate deposit
        $depositPercentage = config('settings.deposit_percentage', 30) / 100;
        $breakdown['deposit_percentage'] = $depositPercentage * 100;
        $breakdown['deposit_amount'] = $breakdown['total'] * $depositPercentage;
        $breakdown['balance_due'] = $breakdown['total'] - $breakdown['deposit_amount'];
        
        return $breakdown;
    }
    
    /**
     * Format price for display
     */
    public function formatPrice(float $amount): string
    {
        return 'LKR ' . number_format($amount, 2);
    }
}