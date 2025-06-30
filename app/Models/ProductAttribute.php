<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAttribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'attribute_template_id',
        'value',
    ];

    protected $casts = [
        'value' => 'string',
    ];

    /**
     * Get the product that owns this attribute.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the attribute template.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(AttributeTemplate::class, 'attribute_template_id');
    }

    /**
     * Get the formatted value based on template type.
     */
    public function getFormattedValueAttribute(): string
    {
        if (!$this->template) {
            return $this->value;
        }

        switch ($this->template->type) {
            case 'boolean':
                return $this->value ? 'Yes' : 'No';
                
            case 'number':
                $value = $this->value;
                if ($this->template->unit) {
                    $value .= ' ' . $this->template->unit;
                }
                return $value;
                
            case 'multiselect':
                $values = json_decode($this->value, true);
                return is_array($values) ? implode(', ', $values) : $this->value;
                
            case 'date':
                return \Carbon\Carbon::parse($this->value)->format('Y-m-d');
                
            default:
                return $this->value;
        }
    }

    /**
     * Set the value based on template type.
     */
    public function setValueAttribute($value)
    {
        if ($this->template && $this->template->type === 'multiselect' && is_array($value)) {
            $value = json_encode($value);
        }

        $this->attributes['value'] = $value;
    }

    /**
     * Get the parsed value for multiselect attributes.
     */
    public function getParsedValueAttribute()
    {
        if ($this->template && $this->template->type === 'multiselect') {
            return json_decode($this->value, true) ?: [];
        }

        return $this->value;
    }

    /**
     * Check if the attribute is required.
     */
    public function isRequired(): bool
    {
        return $this->template ? $this->template->is_required : false;
    }

    /**
     * Validate the attribute value.
     */
    public function validateValue(): bool
    {
        if (!$this->template) {
            return true;
        }

        // Check if required
        if ($this->template->is_required && empty($this->value)) {
            return false;
        }

        // Validate based on type
        switch ($this->template->type) {
            case 'number':
                return is_numeric($this->value);
                
            case 'boolean':
                return in_array($this->value, ['0', '1', 0, 1, true, false], true);
                
            case 'select':
                $options = $this->template->options ?: [];
                return in_array($this->value, $options);
                
            case 'multiselect':
                $values = json_decode($this->value, true);
                $options = $this->template->options ?: [];
                
                if (!is_array($values)) {
                    return false;
                }
                
                foreach ($values as $value) {
                    if (!in_array($value, $options)) {
                        return false;
                    }
                }
                return true;
                
            case 'date':
                try {
                    \Carbon\Carbon::parse($this->value);
                    return true;
                } catch (\Exception $e) {
                    return false;
                }
                
            default:
                return true;
        }
    }
}