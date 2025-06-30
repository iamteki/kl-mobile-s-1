{{-- resources/views/components/package-card.blade.php --}}
<div class="package-card {{ $package->is_featured ? 'featured' : '' }}">
    @if($package->is_featured)
        <span class="package-badge">Most Popular</span>
    @endif
    <h4>{{ $package->name }}</h4>
    <div class="package-price">LKR {{ number_format($package->price) }}</div>
    <p class="text-muted">{{ $package->short_description }}</p>
    <ul class="package-features">
        @foreach($package->inclusions as $inclusion)
            <li><i class="fas fa-check"></i> {{ $inclusion }}</li>
        @endforeach
    </ul>
    <a href="{{ route('packages.show', $package->slug) }}" class="btn {{ $package->is_featured ? 'btn-primary' : 'btn-outline-primary' }} w-100">
        Select Package
    </a>
</div>