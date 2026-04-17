<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', static function (Blueprint $table): void {
            $table->unsignedInteger('consecutive_failures')->default(0)->after('last_checked_at');
            $table->timestamp('deactivated_at')->nullable()->after('consecutive_failures');
        });
    }

    public function down(): void
    {
        Schema::table('listings', static function (Blueprint $table): void {
            $table->dropColumn(['consecutive_failures', 'deactivated_at']);
        });
    }
};
