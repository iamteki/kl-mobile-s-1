<?php
// app/Livewire/CartIcon.php

namespace App\Livewire;

use Livewire\Component;
use App\Services\CartService;

class CartIcon extends Component
{
    public $itemCount = 0;
    public $totalAmount = 0;
    
    protected $listeners = [
        'cartUpdated' => 'updateCartInfo',
        'itemAddedToCart' => 'updateCartInfo',
        'itemRemovedFromCart' => 'updateCartInfo',
        'cartCleared' => 'updateCartInfo'
    ];
    
    public function mount()
    {
        $this->updateCartInfo();
    }
    
    public function updateCartInfo()
    {
        $cartService = app(CartService::class);
        $cartSummary = $cartService->getCartSummary();
        
        $this->itemCount = $cartSummary['item_count'];
        $this->totalAmount = $cartSummary['total_amount'];
    }
    
    public function render()
    {
        return view('livewire.cart-icon');
    }
}