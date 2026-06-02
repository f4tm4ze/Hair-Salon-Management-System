<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->integer('quantity_used')->default(1); // how many units of product are consumed per service
            $table->timestamps();

            $table->unique(['product_id', 'service_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_service');
    }
};
