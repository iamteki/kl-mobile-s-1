<?php
// app/Http/Controllers/Frontend/CheckoutController.php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Services\BookingService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use DB;

class CheckoutController extends Controller
{
    protected $cartService;
    protected $bookingService;
    protected $paymentService;
    
    public function __construct(
        CartService $cartService,
        BookingService $bookingService,
        PaymentService $paymentService
    ) {
        $this->cartService = $cartService;
        $this->bookingService = $bookingService;
        $this->paymentService = $paymentService;
    }
    
    public function index()
    {
        $cart = $this->cartService->getCart();
        
        if ($cart->items->count() == 0) {
            return redirect()->route('cart.index')
                ->with('error', 'Your cart is empty');
        }
        
        $customer = auth()->user()->customer;
        
        return view('frontend.checkout.index', compact('cart', 'customer'));
    }
    
    public function process(Request $request)
    {
        $request->validate([
            'event_date' => 'required|date|after:today',
            'event_type' => 'required|string',
            'venue' => 'required|string',
            'venue_address' => 'required|string',
            'number_of_pax' => 'required|integer|min:1',
            'installation_date' => 'required|date',
            'installation_time' => 'required',
            'event_start_time' => 'required',
            'dismantle_date' => 'required|date|after_or_equal:event_date',
            'dismantle_time' => 'required',
            'customer_name' => 'required|string',
            'customer_email' => 'required|email',
            'customer_phone' => 'required|string',
            'payment_method' => 'required|in:stripe,bank_transfer'
        ]);
        
        DB::beginTransaction();
        
        try {
            // Create or update customer
            $customer = $this->createOrUpdateCustomer($request);
            
            // Create booking
            $booking = $this->bookingService->createBooking(
                $customer,
                $this->cartService->getCart(),
                $request->all()
            );
            
            // Process payment
            if ($request->payment_method == 'stripe') {
                $payment = $this->paymentService->processStripePayment(
                    $booking,
                    $request->stripe_payment_method_id
                );
                
                if ($payment->status != 'completed') {
                    throw new \Exception('Payment failed');
                }
            } else {
                // Bank transfer - create pending payment
                $payment = $this->paymentService->createPendingPayment($booking);
            }
            
            // Clear cart
            $this->cartService->clearCart();
            
            DB::commit();
            
            // Send confirmation email
            $booking->customer->notify(new BookingConfirmation($booking));
            
            return redirect()->route('checkout.success', $booking)
                ->with('success', 'Booking confirmed successfully!');
                
        } catch (\Exception $e) {
            DB::rollback();
            
            return back()->with('error', $e->getMessage());
        }
    }
    
    public function success($bookingNumber)
    {
        $booking = Booking::where('booking_number', $bookingNumber)
            ->where('customer_id', auth()->user()->customer->id)
            ->firstOrFail();
            
        return view('frontend.checkout.success', compact('booking'));
    }
    
    private function createOrUpdateCustomer($request)
    {
        $customerData = [
            'name' => $request->customer_name,
            'email' => $request->customer_email,
            'phone' => $request->customer_phone,
            'company' => $request->company,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'postcode' => $request->postcode
        ];
        
        if (auth()->check()) {
            $customer = auth()->user()->customer;
            if ($customer) {
                $customer->update($customerData);
            } else {
                $customerData['user_id'] = auth()->id();
                $customer = Customer::create($customerData);
            }
        } else {
            $customer = Customer::firstOrCreate(
                ['email' => $request->customer_email],
                $customerData
            );
        }
        
        return $customer;
    }
}