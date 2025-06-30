{{-- resources/views/livewire/availability-checker.blade.php --}}
<div class="availability-checker">
    <div class="availability-form">
        <h5 class="mb-3">Check Availability & Book</h5>
        
        <form wire:submit.prevent="checkAvailability">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Event Date</label>
                    <input 
                        type="date" 
                        wire:model="startDate" 
                        class="form-control @error('startDate') is-invalid @enderror"
                        min="{{ date('Y-m-d', strtotime('+1 day')) }}"
                    >
                    @error('startDate')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Return Date</label>
                    <input 
                        type="date" 
                        wire:model="endDate" 
                        class="form-control @error('endDate') is-invalid @enderror"
                        min="{{ date('Y-m-d', strtotime('+1 day')) }}"
                    >
                    @error('endDate')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Quantity</label>
                    <div class="input-group">
                        <button 
                            type="button"
                            class="btn btn-outline-secondary" 
                            wire:click="$set('quantity', max(1, $quantity - 1))"
                        >
                            <i class="fas fa-minus"></i>
                        </button>
                        <input 
                            type="number" 
                            wire:model="quantity" 
                            class="form-control text-center @error('quantity') is-invalid @enderror"
                            min="{{ $product->min_rental_quantity ?? 1 }}"
                            max="{{ $product->max_rental_quantity ?? 999 }}"
                        >
                        <button 
                            type="button"
                            class="btn btn-outline-secondary" 
                            wire:click="$set('quantity', $quantity + 1)"
                        >
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    @error('quantity')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    
                    @if($product->min_rental_quantity > 1)
                        <small class="text-muted">Minimum: {{ $product->min_rental_quantity }} units</small>
                    @endif
                </div>
                
                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Check Availability
                    </button>
                </div>
            </div>
        </form>
        
        <!-- Availability Result -->
        @if($isAvailable !== null)
            <div class="availability-result mt-4 p-3 rounded-3 {{ $isAvailable ? 'available' : 'unavailable' }}">
                @if($isAvailable)
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle text-success me-3 fa-2x"></i>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">Available!</h6>
                            <p class="mb-0">{{ $message }}</p>
                        </div>
                    </div>
                @else
                    <div class="d-flex align-items-center">
                        <i class="fas fa-times-circle text-danger me-3 fa-2x"></i>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">Not Available</h6>
                            <p class="mb-0">{{ $message }}</p>
                        </div>
                    </div>
                @endif
            </div>
        @endif
        
        <!-- Add to Cart Button -->
        @if($isAvailable)
            <button 
                wire:click="addToCart" 
                class="btn btn-success btn-lg w-100 mt-3"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="addToCart">
                    <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                </span>
                <span wire:loading wire:target="addToCart">
                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                    Adding to Cart...
                </span>
            </button>
        @endif
        
        <!-- Calendar Toggle -->
        <div class="mt-4">
            <button 
                type="button"
                wire:click="toggleCalendar" 
                class="btn btn-outline-secondary btn-sm"
            >
                <i class="fas fa-calendar-alt me-2"></i>
                {{ $showCalendar ? 'Hide' : 'View' }} Availability Calendar
            </button>
        </div>
        
        <!-- Availability Calendar -->
        @if($showCalendar)
            <div class="availability-calendar mt-3" wire:transition>
                <h6 class="mb-3">Availability Calendar</h6>
                <div class="calendar-grid">
                    <div class="calendar-legend mb-3">
                        <span class="legend-item">
                            <span class="legend-box available"></span> Available
                        </span>
                        <span class="legend-item">
                            <span class="legend-box limited"></span> Limited Stock
                        </span>
                        <span class="legend-item">
                            <span class="legend-box unavailable"></span> Not Available
                        </span>
                    </div>
                    
                    <div class="calendar-dates">
                        @foreach($calendarData as $date => $info)
                            <div class="calendar-date {{ $info['available'] ? ($info['quantity'] <= 5 ? 'limited' : 'available') : 'unavailable' }}" 
                                 title="{{ $info['quantity'] }} available">
                                <div class="date-number">{{ \Carbon\Carbon::parse($date)->format('d') }}</div>
                                <div class="date-qty">{{ $info['quantity'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

<style>
.availability-checker {
    background-color: var(--bg-card);
    border: 1px solid var(--border-dark);
    border-radius: 12px;
    padding: 25px;
}

.availability-form h5 {
    color: var(--primary-purple);
    font-weight: 600;
}

.availability-result {
    border: 2px solid;
    background-color: rgba(255, 255, 255, 0.02);
}

.availability-result.available {
    border-color: var(--success);
    background-color: rgba(40, 167, 69, 0.1);
}

.availability-result.unavailable {
    border-color: var(--danger);
    background-color: rgba(220, 53, 69, 0.1);
}

.calendar-grid {
    background-color: var(--bg-darker);
    border-radius: 8px;
    padding: 15px;
}

.calendar-legend {
    display: flex;
    gap: 20px;
    justify-content: center;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.875rem;
    color: var(--text-gray);
}

.legend-box {
    width: 20px;
    height: 20px;
    border-radius: 4px;
}

.legend-box.available {
    background-color: var(--success);
}

.legend-box.limited {
    background-color: var(--warning);
}

.legend-box.unavailable {
    background-color: var(--danger);
}

.calendar-dates {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 5px;
    margin-top: 15px;
}

.calendar-date {
    aspect-ratio: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.calendar-date:hover {
    transform: scale(1.05);
}

.calendar-date.available {
    background-color: rgba(40, 167, 69, 0.2);
    border: 1px solid var(--success);
}

.calendar-date.limited {
    background-color: rgba(255, 193, 7, 0.2);
    border: 1px solid var(--warning);
}

.calendar-date.unavailable {
    background-color: rgba(220, 53, 69, 0.2);
    border: 1px solid var(--danger);
    opacity: 0.6;
}

.date-number {
    font-weight: 600;
    font-size: 0.875rem;
}

.date-qty {
    font-size: 0.75rem;
    color: var(--text-gray);
}
</style>