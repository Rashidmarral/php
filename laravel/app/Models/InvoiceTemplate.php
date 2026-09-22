<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceTemplate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'show_logo' => 'boolean',
            'show_unit_labels' => 'boolean',
            'show_party_vat_number' => 'boolean',
            'show_item_description' => 'boolean',
            'show_vat_column' => 'boolean',
            'is_default' => 'boolean',
            'watermark_opacity' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function notesFor(string $locale): ?string
    {
        return $locale === 'ar' ? ($this->notes_ar ?: $this->notes_en) : $this->notes_en;
    }

    public function termsFor(string $locale): ?string
    {
        return $locale === 'ar' ? ($this->terms_ar ?: $this->terms_en) : $this->terms_en;
    }

    /** Falls back to the shared accent color when no separate totals color has been chosen — most companies want one brand color throughout. */
    public function totalsColor(): string
    {
        return $this->totals_color ?: $this->accent_color;
    }

    public function isCompact(): bool
    {
        return $this->density !== 'comfortable';
    }
}
