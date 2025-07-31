<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('operation_logs', function (Blueprint $table) {
            $table->string('ou')->nullable()->after('entity_id');
        });
    }

    public function down(): void
    {
        Schema::table('operation_logs', function (Blueprint $table) {
            $table->dropColumn('ou');
        });
    }
}; 