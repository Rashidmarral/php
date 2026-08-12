<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('name_ar', 255)->nullable();
            $table->string('email', 150);
            $table->string('phone', 30)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('cr_number', 50)->nullable();
            $table->string('vat_number', 50)->nullable();
            $table->string('status', 20)->default('trial');
            $table->unsignedBigInteger('plan_id')->nullable()->index();
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->decimal('default_markup_percent', 5, 2)->default(0);
            $table->decimal('default_retention_percent', 5, 2)->default(0);
            $table->string('contractor_classification', 20)->nullable();
            $table->string('contractor_classification_number', 50)->nullable();
            $table->boolean('client_portal_enabled')->default(true);
            $table->string('price_sync_url', 500)->nullable();
            $table->timestamp('price_sync_last_at')->nullable();
            $table->string('building_number', 10)->nullable();
            $table->string('street_name', 255)->nullable();
            $table->string('district', 255)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('additional_number', 10)->nullable();
            $table->string('country_code', 2)->default('SA');
            $table->string('cr_document_path', 255)->nullable();
            $table->string('vat_document_path', 255)->nullable();
            $table->string('zatca_environment', 20)->default('sandbox');
            $table->string('zatca_status', 20)->default('not_started');
            $table->text('zatca_csr')->nullable();
            $table->text('zatca_private_key')->nullable();
            $table->text('zatca_compliance_csid')->nullable();
            $table->text('zatca_compliance_secret')->nullable();
            $table->text('zatca_production_csid')->nullable();
            $table->text('zatca_production_secret')->nullable();
            $table->integer('zatca_last_icv')->default(0);
            $table->string('zatca_last_invoice_hash', 255)->nullable();
            $table->text('zatca_last_error')->nullable();
            $table->string('moyasar_publishable_key', 255)->nullable();
            $table->string('moyasar_secret_key', 255)->nullable();
            $table->boolean('moyasar_enabled')->default(false);
            $table->timestamp('trial_reminder_sent_at')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
