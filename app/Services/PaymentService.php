<?php
// app/Services/PaymentService.php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Customer;
use App\Enums\PaymentStatus;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Customer as StripeCustomer;
use Stripe\PaymentMethod;
use Stripe\Refund;
use Stripe\Webhook;
use Exception;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    protected $stripe;
    
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }
    
    /**
     * Create payment intent for booking
     */
    public function createPaymentIntent(Booking $booking, float $amount = null)
    {
        try {
            $amount = $amount ?? $booking->total_amount;
            
            // Get or create Stripe customer
            $stripeCustomer = $this->getOrCreateStripeCustomer($booking->customer);
            
            // Create payment intent
            $paymentIntent = PaymentIntent::create([
                'amount' => $this->formatAmount($amount),
                'currency' => 'lkr',
                'customer' => $stripeCustomer->id,
                'description' => "Booking #{$booking->booking_number}",
                'metadata' => [
                    'booking_id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                    'customer_id' => $booking->customer_id
                ],
                'receipt_email' => $booking->customer->email,
                'setup_future_usage' => 'off_session', // Save card for future use
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);
            
            // Create payment record
            $payment = $booking->payments()->create([
                'payment_method' => 'stripe',
                'amount' => $amount,
                'status' => PaymentStatus::PENDING,
                'transaction_id' => $paymentIntent->id,
                'gateway_response' => [
                    'client_secret' => $paymentIntent->client_secret,
                    'status' => $paymentIntent->status
                ]
            ]);
            
            return [
                'success' => true,
                'payment_intent_id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret,
                'payment_id' => $payment->id
            ];
            
        } catch (Exception $e) {
            Log::error('Payment intent creation failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process payment confirmation
     */
    public function confirmPayment(string $paymentIntentId)
    {
        try {
            // Retrieve payment intent from Stripe
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
            
            // Find payment record
            $payment = Payment::where('transaction_id', $paymentIntentId)->firstOrFail();
            $booking = $payment->booking;
            
            if ($paymentIntent->status === 'succeeded') {
                // Update payment record
                $payment->update([
                    'status' => PaymentStatus::COMPLETED,
                    'paid_at' => now(),
                    'gateway_response' => array_merge($payment->gateway_response ?? [], [
                        'status' => $paymentIntent->status,
                        'payment_method' => $paymentIntent->payment_method,
                        'receipt_url' => $paymentIntent->charges->data[0]->receipt_url ?? null
                    ])
                ]);
                
                // Update booking payment status
                $this->updateBookingPaymentStatus($booking);
                
                // If full payment received, confirm booking
                if ($booking->payment_status === PaymentStatus::PAID) {
                    $booking->update(['booking_status' => 'confirmed']);
                }
                
                return [
                    'success' => true,
                    'payment' => $payment,
                    'booking' => $booking
                ];
            } else {
                // Payment failed or requires action
                $payment->update([
                    'status' => PaymentStatus::FAILED,
                    'gateway_response' => array_merge($payment->gateway_response ?? [], [
                        'status' => $paymentIntent->status,
                        'failure_reason' => $paymentIntent->last_payment_error?->message
                    ])
                ]);
                
                return [
                    'success' => false,
                    'error' => $paymentIntent->last_payment_error?->message ?? 'Payment failed'
                ];
            }
            
        } catch (Exception $e) {
            Log::error('Payment confirmation failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process refund
     */
    public function processRefund(Payment $payment, float $amount = null, string $reason = null)
    {
        try {
            if ($payment->status !== PaymentStatus::COMPLETED) {
                throw new Exception('Cannot refund a payment that is not completed');
            }
            
            $refundAmount = $amount ?? $payment->amount;
            
            // Create Stripe refund
            $refund = Refund::create([
                'payment_intent' => $payment->transaction_id,
                'amount' => $this->formatAmount($refundAmount),
                'reason' => $this->mapRefundReason($reason),
                'metadata' => [
                    'booking_id' => $payment->booking_id,
                    'payment_id' => $payment->id
                ]
            ]);
            
            // Create refund payment record
            $refundPayment = $payment->booking->payments()->create([
                'payment_method' => 'stripe_refund',
                'amount' => -$refundAmount,
                'status' => PaymentStatus::COMPLETED,
                'transaction_id' => $refund->id,
                'paid_at' => now(),
                'gateway_response' => [
                    'refund_id' => $refund->id,
                    'status' => $refund->status,
                    'reason' => $reason
                ],
                'notes' => "Refund for payment #{$payment->id}: " . $reason
            ]);
            
            // Update booking payment status
            $this->updateBookingPaymentStatus($payment->booking);
            
            return [
                'success' => true,
                'refund' => $refundPayment
            ];
            
        } catch (Exception $e) {
            Log::error('Refund processing failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Handle Stripe webhook
     */
    public function handleWebhook(array $payload, string $signature)
    {
        try {
            // Verify webhook signature
            $event = Webhook::constructEvent(
                json_encode($payload),
                $signature,
                config('services.stripe.webhook_secret')
            );
            
            // Handle the event
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentIntentSucceeded($event->data->object);
                    break;
                    
                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event->data->object);
                    break;
                    
                case 'charge.refunded':
                    $this->handleChargeRefunded($event->data->object);
                    break;
                    
                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                case 'customer.subscription.deleted':
                    // Handle subscription events if needed
                    break;
                    
                default:
                    Log::info('Unhandled webhook event type: ' . $event->type);
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            Log::error('Webhook processing failed: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Handle successful payment intent
     */
    protected function handlePaymentIntentSucceeded($paymentIntent)
    {
        $payment = Payment::where('transaction_id', $paymentIntent->id)->first();
        
        if ($payment && $payment->status !== PaymentStatus::COMPLETED) {
            $this->confirmPayment($paymentIntent->id);
        }
    }
    
    /**
     * Handle failed payment intent
     */
    protected function handlePaymentIntentFailed($paymentIntent)
    {
        $payment = Payment::where('transaction_id', $paymentIntent->id)->first();
        
        if ($payment) {
            $payment->update([
                'status' => PaymentStatus::FAILED,
                'gateway_response' => array_merge($payment->gateway_response ?? [], [
                    'failure_reason' => $paymentIntent->last_payment_error?->message
                ])
            ]);
        }
    }
    
    /**
     * Handle charge refunded
     */
    protected function handleChargeRefunded($charge)
    {
        // Log refund from Stripe
        Log::info('Charge refunded via Stripe dashboard', [
            'charge_id' => $charge->id,
            'amount_refunded' => $charge->amount_refunded
        ]);
    }
    
    /**
     * Get or create Stripe customer
     */
    protected function getOrCreateStripeCustomer(Customer $customer)
    {
        if ($customer->stripe_customer_id) {
            try {
                return StripeCustomer::retrieve($customer->stripe_customer_id);
            } catch (Exception $e) {
                // Customer not found, create new one
            }
        }
        
        $stripeCustomer = StripeCustomer::create([
            'email' => $customer->email,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'metadata' => [
                'customer_id' => $customer->id
            ]
        ]);
        
        $customer->update(['stripe_customer_id' => $stripeCustomer->id]);
        
        return $stripeCustomer;
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
        } elseif ($totalPaid >= $booking->deposit_amount && $totalPaid > 0) {
            $booking->update(['payment_status' => PaymentStatus::PARTIAL]);
        } elseif ($totalPaid < 0) {
            $booking->update(['payment_status' => PaymentStatus::REFUNDED]);
        }
    }
    
    /**
     * Format amount for Stripe (convert to cents)
     */
    protected function formatAmount(float $amount): int
    {
        return (int) round($amount * 100);
    }
    
    /**
     * Map refund reason
     */
    protected function mapRefundReason(?string $reason): string
    {
        $reasonMap = [
            'cancelled' => 'requested_by_customer',
            'duplicate' => 'duplicate',
            'fraudulent' => 'fraudulent',
            'other' => 'requested_by_customer'
        ];
        
        return $reasonMap[$reason] ?? 'requested_by_customer';
    }
    
    /**
     * Get payment methods for customer
     */
    public function getCustomerPaymentMethods(Customer $customer)
    {
        if (!$customer->stripe_customer_id) {
            return [];
        }
        
        try {
            $paymentMethods = PaymentMethod::all([
                'customer' => $customer->stripe_customer_id,
                'type' => 'card'
            ]);
            
            return array_map(function ($method) {
                return [
                    'id' => $method->id,
                    'brand' => $method->card->brand,
                    'last4' => $method->card->last4,
                    'exp_month' => $method->card->exp_month,
                    'exp_year' => $method->card->exp_year
                ];
            }, $paymentMethods->data);
            
        } catch (Exception $e) {
            Log::error('Failed to get customer payment methods: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create subscription for recurring rentals
     */
    public function createSubscription(Customer $customer, array $items, string $interval = 'month')
    {
        // Implementation for subscription-based rentals if needed
        // This would create recurring payment schedules for long-term rentals
    }
    
    /**
     * Get payment statistics
     */
    public function getPaymentStats($startDate = null, $endDate = null)
    {
        $query = Payment::where('status', PaymentStatus::COMPLETED)
            ->where('amount', '>', 0);
            
        if ($startDate && $endDate) {
            $query->whereBetween('paid_at', [$startDate, $endDate]);
        }
        
        return [
            'total_revenue' => $query->sum('amount'),
            'total_transactions' => $query->count(),
            'average_transaction' => $query->avg('amount'),
            'payment_methods' => $query->groupBy('payment_method')
                ->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as total')
                ->get()
        ];
    }
}