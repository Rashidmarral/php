<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opt-in TOTP two-factor authentication for the `users` table — shared by
 * both company users and platform admins, since they log in through the
 * same single form. `two_factor_secret` and `two_factor_recovery_codes`
 * are encrypted at rest (see the `encrypted`/`encrypted:array` casts on
 * User) the same way ZATCA credentials elsewhere in this app are treated
 * as sensitive. `two_factor_confirmed_at` stays null until the user
 * actually verifies a code during setup — a secret that was generated but
 * never confirmed must never silently count as "2FA enabled".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable()->after('other_earnings');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
