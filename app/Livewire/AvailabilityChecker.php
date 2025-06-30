<?php
// app/Livewire/AvailabilityChecker.php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Product;
use App\Services\AvailabilityService;
use Carbon\Carbon;

class AvailabilityChecker extends Component
{
    public Product $product;
    public $variationId = null;
    public $startDate = '';
    public $endDate = '';
    public $quantity = 1;
    
    public $isAvailable = null;
    public $availableQuantity = null;
    public $message = '';
    public $showCalendar = false;
    public $calendarData = [];
    
    protected $rules = [
        'startDate' => 'required|date|after:today',
        'endDate' => 'required|date|after_or_equal:startDate',
        'quantity' => 'required|integer|min:1',
    ];
    
    protected $messages = [
        'startDate.required' => 'Please select a start date',
        'startDate.after' => 'Start date must be in the future',
        'endDate.required' => 'Please select an end date',
        'endDate.after_or_equal' => 'End date must be after or equal to start date',
        'quantity.min' => 'Quantity must be at least 1',
    ];
    
    public function mount(Product $product, $variationId = null)
    {
        $this->product = $product;
        $this->variationId = $variationId;
        
        // Set default dates
        $this->startDate = Carbon::tomorrow()->format('Y-m-d');
        $this->endDate = Carbon::tomorrow()->format('Y-m-d');
        
        // Set minimum quantity
        $this->quantity = $product->min_rental_quantity ?? 1;
    }
    
    public function updatedVariationId($value)
    {
        $this->isAvailable = null;
        $this->message = '';
    }
    
    public function checkAvailability()
    {
        $this->validate();
        
        $availabilityService = app(AvailabilityService::class);
        
        $result = $availabilityService->checkAvailability(
            $this->product,
            Carbon::parse($this->startDate),
            Carbon::parse($this->endDate),
            $this->quantity,
            $this->variationId
        );
        
        $this->isAvailable = $result['available'];
        $this->availableQuantity = $result['quantity'];
        $this->message = $result['message'] ?? '';
        
        if ($this->isAvailable) {
            $this->message = "✓ Available! {$this->availableQuantity} units in stock for your dates.";
        }
    }
    
    public function toggleCalendar()
    {
        $this->showCalendar = !$this->showCalendar;
        
        if ($this->showCalendar) {
            $this->loadCalendarData();
        }
    }
    
    protected function loadCalendarData()
    {
        $availabilityService = app(AvailabilityService::class);
        
        $month = Carbon::now()->month;
        $year = Carbon::now()->year;
        
        $this->calendarData = $availabilityService->getAvailabilityCalendar(
            $this->product,
            $month,
            $year,
            $this->variationId
        );
    }
    
    public function addToCart()
    {
        $this->validate();
        
        // First check availability again
        $this->checkAvailability();
        
        if (!$this->isAvailable) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Sorry, this product is not available for the selected dates.'
            ]);
            return;
        }
        
        try {
            $cartService = app(\App\Services\CartService::class);
            
            $cartService->addProduct(
                $this->product,
                $this->quantity,
                $this->variationId,
                Carbon::parse($this->startDate),
                Carbon::parse($this->endDate)
            );
            
            $this->dispatch('itemAddedToCart');
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Product added to cart successfully!'
            ]);
            
            // Reset form
            $this->quantity = $this->product->min_rental_quantity ?? 1;
            $this->isAvailable = null;
            $this->message = '';
            
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function render()
    {
        return view('livewire.availability-checker');
    }
}