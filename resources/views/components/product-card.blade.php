{{-- resources/views/components/product-card.blade.php --}}
<div class="product-card" data-product-id="{{ $product->id }}">
    <div class="product-image">
        <img src="{{ $product->getFirstMediaUrl('products', 'medium') }}" alt="{{ $product->name }}">
        @if($product->is_featured)
            <div class="product-badges">
                <span class="badge-custom">Popular</span>
            </div>
        @endif
        <span class="availability-badge {{ $product->inventory->available_quantity > 5 ? 'in-stock' : ($product->inventory->available_quantity > 0 ? 'low-stock' : 'out-stock') }}">
            {{ $product->inventory->available_quantity > 0 ? $product->inventory->available_quantity . ' Available' : 'Out of Stock' }}
        </span>
    </div>
    <div class="product-info">
        <div class="product-category">{{ $product->category->name }}</div>
        <a href="{{ route('products.show', $product->slug) }}" class="product-title">{{ $product->name }}</a>
        @if(!isset($compact) || !$compact)
            <ul class="product-specs">
                @foreach($product->attributes->take(3) as $attr)
                    <li><i class="{{ $attr->template->icon ?? 'fas fa-check' }}"></i> {{ $attr->value }}{{ $attr->template->unit ? ' ' . $attr->template->unit : '' }}</li>
                @endforeach
            </ul>
        @endif
        <div class="product-footer">
            <div class="product-price">
                LKR {{ number_format($product->base_price) }}<small>/day</small>
            </div>
            <div class="product-actions">
                <button class="btn-icon" title="Quick View" data-bs-toggle="modal" data-bs-target="#quickView{{ $product->id }}">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn-icon btn-add-to-cart" title="Add to Cart" {{ $product->inventory->available_quantity == 0 ? 'disabled' : '' }}>
                    <i class="fas fa-shopping-cart"></i>
                </button>
            </div>
        </div>
    </div>
</div>