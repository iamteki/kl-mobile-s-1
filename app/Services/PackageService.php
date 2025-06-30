<?php
// app/Services/PackageService.php

namespace App\Services;

use App\Models\Package;
use App\Models\PackageItem;
use App\Models\Product;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use DB;
use Exception;

class PackageService
{
    protected $availabilityService;
    
    public function __construct(AvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }
    
    /**
     * Create a new package
     */
    public function createPackage(array $data, array $items)
    {
        DB::beginTransaction();
        
        try {
            // Create package
            $package = Package::create([
                'name' => $data['name'],
                'slug' => \Str::slug($data['name']),
                'description' => $data['description'],
                'short_description' => $data['short_description'] ?? null,
                'category' => $data['category'],
                'price' => $data['price'],
                'price_type' => $data['price_type'] ?? 'fixed',
                'min_rental_days' => $data['min_rental_days'] ?? 1,
                'max_rental_days' => $data['max_rental_days'] ?? null,
                'is_featured' => $data['is_featured'] ?? false,
                'is_customizable' => $data['is_customizable'] ?? false,
                'status' => $data['status'] ?? 'active',
                'meta_title' => $data['meta_title'] ?? $data['name'],
                'meta_description' => $data['meta_description'] ?? $data['short_description'],
            ]);
            
            // Add items to package
            foreach ($items as $item) {
                $this->addItemToPackage($package, $item);
            }
            
            // Calculate and update total value
            $this->updatePackageValue($package);
            
            DB::commit();
            
            return $package;
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Update package
     */
    public function updatePackage(Package $package, array $data, array $items = null)
    {
        DB::beginTransaction();
        
        try {
            // Update package details
            $package->update([
                'name' => $data['name'] ?? $package->name,
                'slug' => isset($data['name']) ? \Str::slug($data['name']) : $package->slug,
                'description' => $data['description'] ?? $package->description,
                'short_description' => $data['short_description'] ?? $package->short_description,
                'category' => $data['category'] ?? $package->category,
                'price' => $data['price'] ?? $package->price,
                'price_type' => $data['price_type'] ?? $package->price_type,
                'min_rental_days' => $data['min_rental_days'] ?? $package->min_rental_days,
                'max_rental_days' => $data['max_rental_days'] ?? $package->max_rental_days,
                'is_featured' => $data['is_featured'] ?? $package->is_featured,
                'is_customizable' => $data['is_customizable'] ?? $package->is_customizable,
                'status' => $data['status'] ?? $package->status,
                'meta_title' => $data['meta_title'] ?? $package->meta_title,
                'meta_description' => $data['meta_description'] ?? $package->meta_description,
            ]);
            
            // Update items if provided
            if ($items !== null) {
                // Remove existing items
                $package->items()->delete();
                
                // Add new items
                foreach ($items as $item) {
                    $this->addItemToPackage($package, $item);
                }
                
                // Recalculate value
                $this->updatePackageValue($package);
            }
            
            DB::commit();
            
            return $package;
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Add item to package
     */
    protected function addItemToPackage(Package $package, array $item)
    {
        $product = Product::findOrFail($item['product_id']);
        
        return $package->items()->create([
            'product_id' => $product->id,
            'variation_id' => $item['variation_id'] ?? null,
            'quantity' => $item['quantity'] ?? 1,
            'is_optional' => $item['is_optional'] ?? false,
            'notes' => $item['notes'] ?? null,
        ]);
    }
    
    /**
     * Update package value
     */
    protected function updatePackageValue(Package $package)
    {
        $totalValue = 0;
        
        foreach ($package->items as $item) {
            $productPrice = $item->product->base_price;
            
            // Apply variation pricing if applicable
            if ($item->variation) {
                if ($item->variation->price) {
                    $productPrice = $item->variation->price;
                } elseif ($item->variation->price_modifier) {
                    if ($item->variation->price_modifier_type === 'percentage') {
                        $productPrice += ($productPrice * $item->variation->price_modifier / 100);
                    } else {
                        $productPrice += $item->variation->price_modifier;
                    }
                }
            }
            
            $totalValue += $productPrice * $item->quantity;
        }
        
        $package->update(['total_value' => $totalValue]);
        
        return $totalValue;
    }
    
    /**
     * Check package availability
     */
    public function checkPackageAvailability(Package $package, Carbon $startDate, Carbon $endDate, int $quantity = 1)
    {
        $availabilityIssues = [];
        $allAvailable = true;
        
        foreach ($package->items as $item) {
            $availability = $this->availabilityService->checkAvailability(
                $item->product,
                $startDate,
                $endDate,
                $item->quantity * $quantity,
                $item->variation_id
            );
            
            if (!$availability['available']) {
                $allAvailable = false;
                $availabilityIssues[] = [
                    'product' => $item->product->name,
                    'variation' => $item->variation?->name,
                    'required' => $item->quantity * $quantity,
                    'available' => $availability['quantity'],
                    'message' => $availability['message']
                ];
            }
        }
        
        return [
            'available' => $allAvailable,
            'issues' => $availabilityIssues
        ];
    }
    
    /**
     * Get package suggestions based on event type
     */
    public function getPackageSuggestions(string $eventType, int $numberOfPax)
    {
        $query = Package::where('status', 'active');
        
        // Filter by event type category
        $categoryMap = [
            'wedding' => ['wedding', 'premium'],
            'birthday' => ['birthday', 'party'],
            'corporate' => ['corporate', 'professional'],
            'concert' => ['concert', 'performance'],
            'festival' => ['festival', 'outdoor'],
        ];
        
        if (isset($categoryMap[$eventType])) {
            $query->whereIn('category', $categoryMap[$eventType]);
        }
        
        // Get packages and sort by relevance
        $packages = $query->get()->map(function ($package) use ($numberOfPax) {
            // Calculate relevance score
            $score = 0;
            
            // Check if package suits the number of pax
            if ($package->recommended_pax_min && $package->recommended_pax_max) {
                if ($numberOfPax >= $package->recommended_pax_min && 
                    $numberOfPax <= $package->recommended_pax_max) {
                    $score += 10;
                }
            }
            
            // Featured packages get higher score
            if ($package->is_featured) {
                $score += 5;
            }
            
            // Popular packages (based on booking count)
            $score += min($package->bookings_count / 10, 5);
            
            $package->relevance_score = $score;
            
            return $package;
        });
        
        return $packages->sortByDesc('relevance_score')->take(6);
    }
    
    /**
     * Duplicate package
     */
    public function duplicatePackage(Package $package, string $newName)
    {
        DB::beginTransaction();
        
        try {
            // Create new package with same attributes
            $newPackage = $package->replicate();
            $newPackage->name = $newName;
            $newPackage->slug = \Str::slug($newName);
            $newPackage->status = 'draft';
            $newPackage->save();
            
            // Copy items
            foreach ($package->items as $item) {
                $newItem = $item->replicate();
                $newItem->package_id = $newPackage->id;
                $newItem->save();
            }
            
            // Copy media
            foreach ($package->media as $media) {
                $newMedia = $media->replicate();
                $newMedia->model_id = $newPackage->id;
                $newMedia->save();
            }
            
            DB::commit();
            
            return $newPackage;
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Get package statistics
     */
    public function getPackageStatistics(Package $package)
    {
        return [
            'total_bookings' => $package->bookingItems()->count(),
            'total_revenue' => $package->bookingItems()->sum('total_price'),
            'average_booking_value' => $package->bookingItems()->avg('total_price'),
            'last_booked' => $package->bookingItems()->latest()->first()?->created_at,
            'popularity_rank' => Package::where('status', 'active')
                ->withCount('bookingItems')
                ->orderByDesc('booking_items_count')
                ->get()
                ->search(function ($p) use ($package) {
                    return $p->id === $package->id;
                }) + 1,
        ];
    }
    
    /**
     * Get popular packages
     */
    public function getPopularPackages(int $limit = 6)
    {
        return Package::where('status', 'active')
            ->withCount(['bookingItems' => function ($query) {
                $query->whereHas('booking', function ($q) {
                    $q->where('created_at', '>=', now()->subMonths(3));
                });
            }])
            ->orderByDesc('booking_items_count')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Validate package configuration
     */
    public function validatePackageConfiguration(Package $package): array
    {
        $errors = [];
        
        // Check if package has items
        if ($package->items->count() == 0) {
            $errors[] = 'Package must have at least one item';
        }
        
        // Check if all required products are active
        foreach ($package->items as $item) {
            if ($item->product->status !== 'active') {
                $errors[] = "Product '{$item->product->name}' is not active";
            }
            
            if ($item->variation && $item->variation->status !== 'active') {
                $errors[] = "Variation '{$item->variation->name}' for '{$item->product->name}' is not active";
            }
        }
        
        // Check if package price is reasonable
        if ($package->price > $package->total_value) {
            $errors[] = 'Package price is higher than total value of items';
        }
        
        // Check if package has media
        if ($package->media->count() == 0) {
            $errors[] = 'Package should have at least one image';
        }
        
        return $errors;
    }
}