<?php

namespace App\Models;

/** Immutable audit row for one admin CSV import into material_library_item_city_prices. */
class MaterialLibraryPriceImportLog extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];
}
