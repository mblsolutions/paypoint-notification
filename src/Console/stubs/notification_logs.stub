<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('notification.database.table'), function (Blueprint $table) {
            $table->uuid('id');
            $table->string('template_id')->nullable()->index();
            $table->bigInteger('user_id')->nullable()->index();
            $table->string('method', 7)->nullable()->index();
            $table->text('uri')->nullable();
            $table->smallInteger('status')->nullable()->index();            
            $table->text('notification_request')->nullable();
            $table->text('notification_response')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // To add an index to the timestamp columns
            $table->index('created_at');
            $table->index('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('notification.database.table'));
    }
};
