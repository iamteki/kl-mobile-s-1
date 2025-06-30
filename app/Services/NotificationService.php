<?php
// app/Services/NotificationService.php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Customer;
use App\Models\Inventory;
use App\Mail\BookingConfirmation;
use App\Mail\BookingReminder;
use App\Mail\PaymentReceipt;
use App\Mail\BookingCancelled;
use App\Mail\LowStockAlert;
use App\Mail\NewBookingAdmin;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

class NotificationService
{
    protected $smsService;
    
    public function __construct()
    {
        // Initialize SMS service if configured
        // $this->smsService = new SmsService();
    }
    
    /**
     * Send booking confirmation
     */
    public function sendBookingConfirmation(Booking $booking)
    {
        try {
            // Send email
            Mail::to($booking->customer->email)
                ->queue(new BookingConfirmation($booking));
            
            // Send SMS if enabled
            if ($this->shouldSendSms($booking->customer)) {
                $this->sendBookingConfirmationSms($booking);
            }
            
            // Notify admin
            $this->notifyAdminNewBooking($booking);
            
            Log::info('Booking confirmation sent', ['booking_id' => $booking->id]);
            
        } catch (Exception $e) {
            Log::error('Failed to send booking confirmation', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send booking confirmed notification
     */
    public function sendBookingConfirmedNotification(Booking $booking)
    {
        try {
            Mail::to($booking->customer->email)
                ->queue(new \App\Mail\BookingConfirmed($booking));
                
        } catch (Exception $e) {
            Log::error('Failed to send booking confirmed notification', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send payment confirmation
     */
    public function sendPaymentConfirmation(Booking $booking, Payment $payment)
    {
        try {
            Mail::to($booking->customer->email)
                ->queue(new PaymentReceipt($booking, $payment));
                
            if ($this->shouldSendSms($booking->customer)) {
                $this->sendPaymentConfirmationSms($booking, $payment);
            }
            
        } catch (Exception $e) {
            Log::error('Failed to send payment confirmation', [
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send booking reminder
     */
    public function sendBookingReminder(Booking $booking)
    {
        try {
            Mail::to($booking->customer->email)
                ->queue(new BookingReminder($booking));
                
            if ($this->shouldSendSms($booking->customer)) {
                $this->sendBookingReminderSms($booking);
            }
            
        } catch (Exception $e) {
            Log::error('Failed to send booking reminder', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send booking cancelled notification
     */
    public function sendBookingCancelledNotification(Booking $booking)
    {
        try {
            Mail::to($booking->customer->email)
                ->queue(new BookingCancelled($booking));
                
            if ($this->shouldSendSms($booking->customer)) {
                $this->sendBookingCancelledSms($booking);
            }
            
            // Notify admin
            $this->notifyAdminBookingCancelled($booking);
            
        } catch (Exception $e) {
            Log::error('Failed to send booking cancelled notification', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send low stock alert
     */
    public function sendLowStockAlert(Inventory $inventory)
    {
        try {
            $adminEmails = $this->getAdminEmails();
            
            Mail::to($adminEmails)
                ->queue(new LowStockAlert($inventory));
                
        } catch (Exception $e) {
            Log::error('Failed to send low stock alert', [
                'inventory_id' => $inventory->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Notify admin of new booking
     */
    protected function notifyAdminNewBooking(Booking $booking)
    {
        try {
            $adminEmails = $this->getAdminEmails();
            
            Mail::to($adminEmails)
                ->queue(new NewBookingAdmin($booking));
                
        } catch (Exception $e) {
            Log::error('Failed to notify admin of new booking', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Notify admin of cancelled booking
     */
    protected function notifyAdminBookingCancelled(Booking $booking)
    {
        try {
            $adminEmails = $this->getAdminEmails();
            
            Mail::to($adminEmails)
                ->queue(new \App\Mail\BookingCancelledAdmin($booking));
                
        } catch (Exception $e) {
            Log::error('Failed to notify admin of cancelled booking', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send booking confirmation SMS
     */
    protected function sendBookingConfirmationSms(Booking $booking)
    {
        $message = "Dear {$booking->customer->name}, your booking #{$booking->booking_number} " .
                  "for {$booking->event_date->format('M d, Y')} has been received. " .
                  "Total: LKR " . number_format($booking->total_amount, 2) . ". " .
                  "We'll contact you shortly. - KL Mobile Events";
                  
        $this->sendSms($booking->customer->phone, $message);
    }
    
    /**
     * Send payment confirmation SMS
     */
    protected function sendPaymentConfirmationSms(Booking $booking, Payment $payment)
    {
        $message = "Payment received! LKR " . number_format($payment->amount, 2) . 
                  " for booking #{$booking->booking_number}. " .
                  "Thank you! - KL Mobile Events";
                  
        $this->sendSms($booking->customer->phone, $message);
    }
    
    /**
     * Send booking reminder SMS
     */
    protected function sendBookingReminderSms(Booking $booking)
    {
        $message = "Reminder: Your event is on {$booking->event_date->format('M d')} " .
                  "at {$booking->venue}. " .
                  "Our team will arrive at {$booking->installation_time} for setup. " .
                  "- KL Mobile Events";
                  
        $this->sendSms($booking->customer->phone, $message);
    }
    
    /**
     * Send booking cancelled SMS
     */
    protected function sendBookingCancelledSms(Booking $booking)
    {
        $message = "Your booking #{$booking->booking_number} has been cancelled. " .
                  "If you have any questions, please contact us. " .
                  "- KL Mobile Events";
                  
        $this->sendSms($booking->customer->phone, $message);
    }
    
    /**
     * Send SMS
     */
    protected function sendSms(string $phone, string $message)
    {
        try {
            // Implement SMS sending logic here
            // This would integrate with an SMS gateway like Twilio, Nexmo, etc.
            
            Log::info('SMS sent', [
                'phone' => $phone,
                'message' => $message
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to send SMS', [
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Check if should send SMS
     */
    protected function shouldSendSms(Customer $customer): bool
    {
        // Check if customer has opted in for SMS notifications
        // and if SMS service is configured
        
        return !empty($customer->phone) && 
               $customer->sms_notifications_enabled ?? true;
    }
    
    /**
     * Get admin email addresses
     */
    protected function getAdminEmails(): array
    {
        // Get admin emails from settings or users table
        $adminEmails = [
            config('settings.admin_email', 'admin@klmobileevents.com')
        ];
        
        // Add emails of users with admin role
        $adminUsers = \App\Models\User::where('is_admin', true)
            ->pluck('email')
            ->toArray();
            
        return array_merge($adminEmails, $adminUsers);
    }
    
    /**
     * Send custom notification
     */
    public function sendCustomNotification(Customer $customer, string $subject, string $message, array $data = [])
    {
        try {
            Mail::to($customer->email)->send(new \App\Mail\CustomNotification(
                $subject,
                $message,
                $data
            ));
            
        } catch (Exception $e) {
            Log::error('Failed to send custom notification', [
                'customer_id' => $customer->id,
                'subject' => $subject,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Send bulk notification
     */
    public function sendBulkNotification(array $customerIds, string $subject, string $message)
    {
        $customers = Customer::whereIn('id', $customerIds)
            ->where('status', 'active')
            ->get();
            
        foreach ($customers as $customer) {
            $this->sendCustomNotification($customer, $subject, $message);
        }
    }
    
    /**
     * Get notification templates
     */
    public function getNotificationTemplates(): array
    {
        return [
            'booking_confirmation' => [
                'name' => 'Booking Confirmation',
                'subject' => 'Booking Confirmation - #{booking_number}',
                'variables' => ['booking_number', 'customer_name', 'event_date', 'total_amount']
            ],
            'payment_receipt' => [
                'name' => 'Payment Receipt',
                'subject' => 'Payment Receipt - #{booking_number}',
                'variables' => ['booking_number', 'payment_amount', 'payment_date']
            ],
            'booking_reminder' => [
                'name' => 'Booking Reminder',
                'subject' => 'Event Reminder - {event_date}',
                'variables' => ['event_date', 'venue', 'installation_time']
            ],
            'booking_cancelled' => [
                'name' => 'Booking Cancelled',
                'subject' => 'Booking Cancelled - #{booking_number}',
                'variables' => ['booking_number', 'cancellation_reason']
            ]
        ];
    }
}