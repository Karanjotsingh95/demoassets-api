<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEquipmentRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('equipment_requests', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('purpose');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('shipping_info')->nullable();
            $table->text('comments')->nullable();
            $table->integer('accepted')->default(0);
            $table->text('admin_feedback')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('equipment_requests');
    }
}
