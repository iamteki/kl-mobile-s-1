<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AttributeTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'type',
        'unit',
        'options',
        'is_required',
        'is_filterable',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'is_filterable' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * The available attribute types.
     */
    const TYPES = [
        'text' => 'Text',
        'number' => 'Number',
        'select' => 'Select',
        'multiselect' => 'Multi Select',
        'boolean' => 'Yes/No',
        'date' => 'Date',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($template) {
            if (empty($template->slug)) {
                $template->slug = Str::slug($template->name);
            }
        });

        static::updating(function ($template) {
            if ($template->isDirty('name') && empty($template->slug)) {
                $template->slug = Str::slug($template->name);
            }
        });
    }

    /**
     * Get the category this template belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get all product attributes using this template.
     */
    public function productAttributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    /**
     * Scope to get only active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get only filterable templates.
     */
    public function scopeFilterable($query)
    {
        return $query->where('is_filterable', true);
    }

    /**
     * Scope to order by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Check if this is a select type (select or multiselect).
     */
    public function isSelectType(): bool
    {
        return in_array($this->type, ['select', 'multiselect']);
    }

    /**
     * Get validation rules for this attribute.
     */
    public function getValidationRules(): array
    {
        $rules = [];

        if ($this->is_required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        switch ($this->type) {
            case 'number':
                $rules[] = 'numeric';
                break;
                
            case 'boolean':
                $rules[] = 'boolean';
                break;
                
            case 'date':
                $rules[] = 'date';
                break;
                
            case 'select':
                if ($this->options) {
                    $rules[] = 'in:' . implode(',', $this->options);
                }
                break;
                
            case 'multiselect':
                $rules[] = 'array';
                if ($this->options) {
                    $rules[] = 'in:' . implode(',', $this->options);
                }
                break;
                
            default:
                $rules[] = 'string';
                $rules[] = 'max:255';
        }

        return $rules;
    }

    /**
     * Get the validation rule string.
     */
    public function getValidationRuleString(): string
    {
        return implode('|', $this->getValidationRules());
    }

    /**
     * Add an option (for select/multiselect types).
     */
    public function addOption(string $option): bool
    {
        if (!$this->isSelectType()) {
            return false;
        }

        $options = $this->options ?: [];
        
        if (in_array($option, $options)) {
            return false;
        }

        $options[] = $option;
        $this->options = $options;
        
        return $this->save();
    }

    /**
     * Remove an option.
     */
    public function removeOption(string $option): bool
    {
        if (!$this->isSelectType()) {
            return false;
        }

        $options = $this->options ?: [];
        $options = array_values(array_diff($options, [$option]));
        
        $this->options = $options;
        
        return $this->save();
    }

    /**
     * Get distinct values used in products.
     */
    public function getDistinctValues(): array
    {
        if ($this->type === 'multiselect') {
            $values = [];
            $attributes = $this->productAttributes()->pluck('value');
            
            foreach ($attributes as $attribute) {
                $decoded = json_decode($attribute, true);
                if (is_array($decoded)) {
                    $values = array_merge($values, $decoded);
                }
            }
            
            return array_unique($values);
        }

        return $this->productAttributes()
            ->distinct()
            ->pluck('value')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get value counts for filtering.
     */
    public function getValueCounts(): array
    {
        $counts = [];
        
        if ($this->type === 'multiselect') {
            $attributes = $this->productAttributes()
                ->whereHas('product', function ($query) {
                    $query->where('status', 'active');
                })
                ->pluck('value');
            
            foreach ($attributes as $attribute) {
                $decoded = json_decode($attribute, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $value) {
                        $counts[$value] = ($counts[$value] ?? 0) + 1;
                    }
                }
            }
        } else {
            $results = $this->productAttributes()
                ->whereHas('product', function ($query) {
                    $query->where('status', 'active');
                })
                ->groupBy('value')
                ->selectRaw('value, COUNT(*) as count')
                ->pluck('count', 'value');
            
            $counts = $results->toArray();
        }
        
        return $counts;
    }
}