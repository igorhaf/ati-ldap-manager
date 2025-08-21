<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		Schema::table('operation_logs', function (Blueprint $table) {
			$table->string('actor_uid')->nullable()->after('ou');
			$table->string('actor_role')->nullable()->after('actor_uid');
			$table->string('result')->default('success')->after('actor_role'); // success | failure
			$table->text('error_message')->nullable()->after('result');
			$table->text('changes_summary')->nullable()->after('error_message');
			$table->json('changes')->nullable()->after('changes_summary');
		});
	}

	public function down(): void
	{
		Schema::table('operation_logs', function (Blueprint $table) {
			$table->dropColumn(['actor_uid','actor_role','result','error_message','changes_summary','changes']);
		});
	}
};


