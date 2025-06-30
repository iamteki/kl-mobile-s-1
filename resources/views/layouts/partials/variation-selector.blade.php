{{-- resources/views/frontend/products/partials/variation-selector.blade.php --}}
@if($product->variations->count() > 0)
    <div class="variation-selector">
        <h6 class="variation-title">Select Option:</h6>
        
        @php
            $groupedVariations = $product->variations->groupBy('attribute_type');
        @endphp
        
        @foreach($groupedVariations as $type => $variations)
            <div class="variation-group" data-variation-type="{{ $type }}">
                <label class="variation-label">{{ ucfirst(str_replace('_', ' ', $type)) }}:</label>
                <div class="variation-options">
                    @foreach($variations as $variation)
                        <div class="variation-option {{ $variation->stock_quantity == 0 ? 'unavailable' : '' }}" 
                             data-variation-id="{{ $variation->id }}"
                             data-price="{{ $variation->price }}"
                             data-sku="{{ $variation->sku }}"
                             data-stock="{{ $variation->stock_quantity }}"
                             data-attributes='@json($variation->attributes)'>
                            
                            @if($type == 'color' && $variation->color_hex)
                                <span class="color-swatch" style="background-color: {{ $variation->color_hex }}"></span>
                            @endif
                            
                            <span class="variation-name">{{ $variation->name }}</span>
                            
                            @if($variation->price != $product->base_price)
                                <span class="price-modifier">
                                    @if($variation->price > $product->base_price)
                                        +LKR {{ number_format($variation->price - $product->base_price) }}
                                    @else
                                        -LKR {{ number_format($product->base_price - $variation->price) }}
                                    @endif
                                </span>
                            @endif
                            
                            @if($variation->stock_quantity == 0)
                                <span class="out-of-stock-badge">Out of Stock</span>
                            @elseif($variation->stock_quantity < 5)
                                <span class="low-stock-badge">Only {{ $variation->stock_quantity }} left</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
        
        <!-- Selected Variation Summary -->
        <div class="selected-variation-summary" style="display: none;">
            <div class="summary-item">
                <span class="summary-label">Selected:</span>
                <span class="summary-value" id="selectedVariationName">-</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">SKU:</span>
                <span class="summary-value" id="selectedVariationSku">-</span>
            </div>
            <div class="summary-item">
                <span class="summary-label">Stock:</span>
                <span class="summary-value" id="selectedVariationStock">-</span>
            </div>
        </div>
    </div>
@endif

<style>
/* Variation Selector Styles */
.variation-selector {
    margin: 30px 0;
}

.variation-title {
    color: var(--off-white);
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 20px;
}

.variation-group {
    margin-bottom: 25px;
}

.variation-label {
    color: var(--text-gray);
    font-size: 14px;
    font-weight: 500;
    display: block;
    margin-bottom: 10px;
}

.variation-options {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.variation-option {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: var(--bg-card);
    border: 2px solid var(--border-dark);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
    min-width: 100px;
    justify-content: center;
}

.variation-option:hover:not(.unavailable) {
    border-color: var(--primary-purple);
    transform: translateY(-2px);
}

.variation-option.selected {
    background: rgba(147, 51, 234, 0.1);
    border-color: var(--primary-purple);
}

.variation-option.unavailable {
    opacity: 0.6;
    cursor: not-allowed;
}

.variation-option.unavailable:hover {
    transform: none;
    border-color: var(--border-dark);
}

/* Color Swatch */
.color-swatch {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 2px solid var(--border-dark);
    box-shadow: inset 0 0 0 2px var(--bg-card);
}

.variation-option.selected .color-swatch {
    box-shadow: inset 0 0 0 2px var(--primary-purple);
}

/* Variation Name */
.variation-name {
    color: var(--off-white);
    font-size: 14px;
    font-weight: 500;
}

/* Price Modifier */
.price-modifier {
    color: var(--primary-purple);
    font-size: 12px;
    font-weight: 600;
}

/* Stock Badges */
.out-of-stock-badge,
.low-stock-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
}

.out-of-stock-badge {
    background: var(--danger-red);
    color: white;
}

.low-stock-badge {
    background: var(--warning-yellow);
    color: var(--bg-dark);
}

/* Selected Variation Summary */
.selected-variation-summary {
    background: var(--bg-card);
    border: 1px solid var(--border-dark);
    border-radius: 8px;
    padding: 15px;
    margin-top: 20px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px 0;
}

.summary-item:not(:last-child) {
    border-bottom: 1px solid var(--border-dark);
    margin-bottom: 5px;
}

.summary-label {
    color: var(--text-gray);
    font-size: 14px;
}

.summary-value {
    color: var(--off-white);
    font-size: 14px;
    font-weight: 500;
}

/* Responsive */
@media (max-width: 576px) {
    .variation-option {
        min-width: auto;
        padding: 8px 15px;
        font-size: 13px;
    }
    
    .color-swatch {
        width: 20px;
        height: 20px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const variationOptions = document.querySelectorAll('.variation-option:not(.unavailable)');
    const summaryDiv = document.querySelector('.selected-variation-summary');
    
    variationOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remove selected from all options in this group
            const group = this.closest('.variation-group');
            group.querySelectorAll('.variation-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Add selected to clicked option
            this.classList.add('selected');
            
            // Update summary if exists
            if (summaryDiv) {
                const variationId = this.dataset.variationId;
                const variationName = this.querySelector('.variation-name').textContent;
                const variationSku = this.dataset.sku;
                const variationStock = this.dataset.stock;
                const variationPrice = this.dataset.price;
                
                document.getElementById('selectedVariationName').textContent = variationName;
                document.getElementById('selectedVariationSku').textContent = variationSku;
                document.getElementById('selectedVariationStock').textContent = variationStock + ' units';
                
                summaryDiv.style.display = 'block';
                
                // Update main price display
                updateProductPrice(variationPrice);
                
                // Update hidden form inputs if they exist
                const variationInput = document.getElementById('variation_id');
                if (variationInput) {
                    variationInput.value = variationId;
                }
                
                // Trigger availability check
                if (typeof checkAvailability === 'function') {
                    checkAvailability();
                }
            }
        });
    });
    
    // Auto-select first available option
    const firstAvailable = document.querySelector('.variation-option:not(.unavailable)');
    if (firstAvailable) {
        firstAvailable.click();
    }
});

function updateProductPrice(newPrice) {
    const priceDisplay = document.querySelector('.price-display');
    if (priceDisplay) {
        priceDisplay.textContent = 'LKR ' + parseInt(newPrice).toLocaleString();
    }
}
</script>