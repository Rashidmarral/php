<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom domains a platform admin can point at a company's panel (e.g. a client's own
 * "app.theirdomain.com" resolving to their BuildXact Saudi company), on top of — never
 * instead of — the platform's own URL, which keeps working unchanged for every company.
 *
 * `domain` is unique across the WHOLE table (not scoped per company): a hostname can only
 * ever resolve to one company at a time, otherwise ResolveCompanyDomain's
 * domain → company_id lookup would be ambiguous. A company may still have more than one
 * row (an apex domain plus its "www." variant, for example) — this table just doesn't
 * force one-domain-per-company at the schema level.
 *
 * `is_active` lets an admin temporarily unpublish a domain (e.g. while DNS/SSL is being
 * sorted out at the hosting layer) without losing the row, since ResolveCompanyDomain
 * only ever matches active rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 253)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_domains');
    }
};
