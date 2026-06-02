<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->dropColumn(['used_count', 'per_customer_limit']);
        });
    }

    public function down()
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->integer('used_count')->default(0);
            $table->integer('per_customer_limit')->nullable();
        });
    }
};
