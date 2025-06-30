<?php
// app/Services/BookingService.php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Package;
use App\Models\Payment;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Services\AvailabilityService;
use App\Services\InventoryService;
use App\Services\NotificationService;
use App\Services\PricingService;
use Carbon\Carbon;
use DB;
use Exception;

class BookingService
{
    protected $availabilityService;
    protected $inventoryService;
    protected $notificationService;
    protected $pricingService;
    
    public function __construct(
        AvailabilityService $availabilityService,
        InventoryService $inventoryService,
        NotificationService $notificationService,
        PricingService $pricingService
    ) {
        $this->availabilityService = $availabilityService;
        $this->inventoryService = $inventoryService;
        $this->notificationService = $notificationService;
        $this->pricingService = $pricingService;
    }
    
    /**
     * Create a new booking
     */
    public function createBooking(Customer $customer, array $data, array $items)
    {
        DB::beginTransaction();
        
        try {
            // Create booking
            $booking = Booking::create([
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'booking_number' => $this->generateBookingNumber(),
                'event_date' => $data['event_date'],
                'event_type' => $data['event_type'],
                'venue' => $data['venue'],
                'venue_address' => $data['venue_address'],
                'number_of_pax' => $data['number_of_pax'],
                'installation_date' => $data['installation_date'],
                'installation_time' => $data['installation_time'],
                'event_start_time' => $data['event_start_time'],
                'dismantle_date' => $data['dismantle_date'],
                'dismantle_time' => $data['dismantle_time'],
                'booking_status' => BookingStatus::PENDING,
                'payment_status' => PaymentStatus::PENDING,
                'subtotal' => 0,
                'tax_amount' => 0,
                'deposit_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 0,
                'notes' => $data['notes'] ?? null,
                'special_requests' => $data['special_requests'] ?? null,
            ]);
            
            $subtotal = 0;
            
            // Add items to booking
            foreach ($items as $item) {
                $bookingItem = $this->addBookingItem($booking, $item);
                $subtotal += $bookingItem->total_price;
            }
            
            // Calculate pricing
            $pricing = $this->pricingService->calculateBookingTotal($booking, $subtotal);
            
            // Update booking totals
            $booking->update([
                'subtotal' => $pricing['subtotal'],
                'tax_amount' => $pricing['tax_amount'],
                'deposit_amount' => $pricing['deposit_amount'],
                'discount_amount' => $pricing['discount_amount'],
                'total_amount' => $pricing['total_amount'],
            ]);
            
            // Reserve inventory for all items
            foreach ($booking->items as $bookingItem) {
                $this->inventoryService->reserveInventory($bookingItem);
            }
            
            // Send confirmation notification
            $this->notificationService->sendBookingConfirmation($booking);
            
            DB::commit();
            
            return $booking;
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Add item to booking
     */
    protected function addBookingItem(Booking $booking, array $item)
    {
        // Determine rental days
        $rentalDays = Carbon::parse($booking->installation_date)->diffInDays(Carbon::parse($booking->dismantle_date)) + 1;
        
        // Get unit price based on item type
        if (!empty($item['product_id'])) {
            $product = Product::findOrFail($item['product_id']);
            $unitPrice = $this->pricingService->getProductPrice($product, $item['variation_id'] ?? null, $rentalDays);
            
            return $booking->items()->create([
                'product_id' => $product->id,
                'variation_id' => $item['variation_id'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice * $item['quantity'],
                'notes' => $item['notes'] ?? null,
            ]);
        } elseif (!empty($item['package_id'])) {
            $package = Package::findOrFail($item['package_id']);
            $packagePrice = $this->pricingService->getPackagePrice($package, $rentalDays);
            
            return $booking->items()->create([
                'package_id' => $package->id,
                'quantity' => $item['quantity'],
                'unit_price' => $packagePrice,
                'total_price' => $packagePrice * $item['quantity'],
                'notes' => $item['notes'] ?? null,
            ]);
        }
    }
    
    /**
     * Update booking status
     */
    public function updateBookingStatus(Booking $booking, string $status, string $notes = null)
    {
        $previousStatus = $booking->booking_status;
        
        $booking->update([
            'booking_status' => $status,
            'status_notes' => $notes
        ]);
        
        // Handle status-specific actions
        switch ($status) {
            case BookingStatus::CONFIRMED:
                $this->handleBookingConfirmed($booking);
                break;
                
            case BookingStatus::CANCELLED:
                $this->handleBookingCancelled($booking);
                break;
                
            case BookingStatus::DELIVERED:
                $this->handleBookingDelivered($booking);
                break;
                
            case BookingStatus::COMPLETED:
                $this->handleBookingCompleted($booking);
                break;
        }
        
        // Log status change
        activity()
            ->performedOn($booking)
            ->withProperties([
                'previous_status' => $previousStatus,
                'new_status' => $status,
                'notes' => $notes
            ])
            ->log('Booking status updated');
            
        return $booking;
    }
    
    /**
     * Handle confirmed booking
     */
    protected function handleBookingConfirmed(Booking $booking)
    {
        $booking->update(['confirmed_at' => now()]);
        $this->notificationService->sendBookingConfirmedNotification($booking);
    }
    
    /**
     * Handle cancelled booking
     */
    protected function handleBookingCancelled(Booking $booking)
    {
        DB::transaction(function () use ($booking) {
            // Release inventory
            foreach ($booking->items as $item) {
                $this->inventoryService->releaseInventory($item);
            }
            
            // Update booking
            $booking->update([
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id()
            ]);
            
            // Process refund if payment exists
            if ($booking->payments()->where('status', PaymentStatus::COMPLETED)->exists()) {
                $this->processRefund($booking);
            }
            
            // Send cancellation notification
            $this->notificationService->sendBookingCancelledNotification($booking);
        });
    }
    
    /**
     * Handle delivered booking
     */
    protected function handleBookingDelivered(Booking $booking)
    {
        $booking->update(['delivered_at' => now()]);
        
        // Mark items as delivered
        $booking->items()->update([
            'is_delivered' => true,
            'delivered_at' => now()
        ]);
        
        // Update inventory status
        foreach ($booking->items as $item) {
            $this->inventoryService->markAsDelivered($item);
        }
    }
    
    /**
     * Handle completed booking
     */
    protected function handleBookingCompleted(Booking $booking)
    {
        $booking->update(['completed_at' => now()]);
        
        // Mark items as returned
        $booking->items()->update([
            'is_returned' => true,
            'returned_at' => now()
        ]);
        
        // Release inventory
        foreach ($booking->items as $item) {
            $this->inventoryService->releaseInventory($item);
        }
        
        // Update customer stats
        $booking->customer->increment('total_bookings');
        $booking->customer->increment('total_spent', $booking->total_amount);
    }
    
    /**
     * Process payment for booking
     */
    public function processPayment(Booking $booking, array $paymentData)
    {
        $payment = $booking->payments()->create([
            'payment_method' => $paymentData['payment_method'],
            'amount' => $paymentData['amount'],
            'status' => PaymentStatus::PENDING,
            'transaction_id' => $paymentData['transaction_id'] ?? null,
            'gateway_response' => $paymentData['gateway_response'] ?? null,
        ]);
        
        if ($paymentData['status'] === 'succeeded') {
            $payment->update([
                'status' => PaymentStatus::COMPLETED,
                'paid_at' => now()
            ]);
            
            // Update booking payment status
            $this->updateBookingPaymentStatus($booking);
            
            // Send payment confirmation
            $this->notificationService->sendPaymentConfirmation($booking, $payment);
        }
        
        return $payment;
    }
    
    /**
     * Update booking payment status
     */
    protected function updateBookingPaymentStatus(Booking $booking)
    {
        $totalPaid = $booking->payments()
            ->where('status', PaymentStatus::COMPLETED)
            ->sum('amount');
            
        if ($totalPaid >= $booking->total_amount) {
            $booking->update(['payment_status' => PaymentStatus::PAID]);
        } elseif ($totalPaid >= $booking->deposit_amount) {
            $booking->update(['payment_status' => PaymentStatus::PARTIAL]);
        }
    }
    
    /**
     * Process refund
     */
    public function processRefund(Booking $booking, float $amount = null)
    {
        $refundAmount = $amount ?? $booking->total_paid;
        
        // Create refund payment record
        $refund = $booking->payments()->create([
            'payment_method' => 'refund',
            'amount' => -$refundAmount,
            'status' => PaymentStatus::PENDING,
            'notes' => 'Refund for cancelled booking'
        ]);
        
        // Process refund through payment gateway
        // This would integrate with Stripe or other payment processor
        
        $refund->update([
            'status' => PaymentStatus::COMPLETED,
            'paid_at' => now()
        ]);
        
        $booking->update(['payment_status' => PaymentStatus::REFUNDED]);
        
        return $refund;
    }
    
    /**
     * Check if booking can be modified
     */
    public function canModifyBooking(Booking $booking): bool
    {
        // Cannot modify if already delivered or completed
        if (in_array($booking->booking_status, [BookingStatus::DELIVERED, BookingStatus::COMPLETED, BookingStatus::CANCELLED])) {
            return false;
        }
        
        // Cannot modify if event is within 48 hours
        $hoursUntilEvent = now()->diffInHours($booking->event_date, false);
        if ($hoursUntilEvent <= 48) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if booking can be cancelled
     */
    public function canCancelBooking(Booking $booking): bool
    {
        // Cannot cancel if already cancelled or completed
        if (in_array($booking->booking_status, [BookingStatus::CANCELLED, BookingStatus::COMPLETED])) {
            return false;
        }
        
        // Cannot cancel if already delivered
        if ($booking->booking_status === BookingStatus::DELIVERED) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get cancellation charges
     */
    public function getCancellationCharges(Booking $booking): float
    {
        $hoursUntilEvent = now()->diffInHours($booking->event_date, false);
        
        // Less than 24 hours: 100% charge
        if ($hoursUntilEvent <= 24) {
            return $booking->total_amount;
        }
        
        // 24-48 hours: 50% charge
        if ($hoursUntilEvent <= 48) {
            return $booking->total_amount * 0.5;
        }
        
        // 48-72 hours: 25% charge
        if ($hoursUntilEvent <= 72) {
            return $booking->total_amount * 0.25;
        }
        
        // More than 72 hours: No charge
        return 0;
    }
    
    /**
     * Generate unique booking number
     */
    protected function generateBookingNumber(): string
    {
        $prefix = 'KLM';
        $year = date('y');
        $month = date('m');
        
        // Get the last booking number for this month
        $lastBooking = Booking::whereYear('created_at', date('Y'))
            ->whereMonth('created_at', date('m'))
            ->orderBy('id', 'desc')
            ->first();
            
        if ($lastBooking) {
            // Extract the sequence number from the last booking
            $lastNumber = intval(substr($lastBooking->booking_number, -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return sprintf('%s%s%s%04d', $prefix, $year, $month, $newNumber);
    }
    
    /**
     * Get booking statistics
     */
    public function getBookingStats($startDate = null, $endDate = null)
    {
        $query = Booking::query();
        
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }
        
        return [
            'total_bookings' => $query->count(),
            'confirmed_bookings' => $query->where('booking_status', BookingStatus::CONFIRMED)->count(),
            'pending_bookings' => $query->where('booking_status', BookingStatus::PENDING)->count(),
            'cancelled_bookings' => $query->where('booking_status', BookingStatus::CANCELLED)->count(),
            'total_revenue' => $query->where('payment_status', PaymentStatus::PAID)->sum('total_amount'),
            'average_booking_value' => $query->where('payment_status', PaymentStatus::PAID)->avg('total_amount'),
        ];
    }
}