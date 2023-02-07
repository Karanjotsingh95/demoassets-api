<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAssetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_title')->nullable();
            $table->integer('mn_number')->nullable();
            $table->integer('equipment_id')->nullable();
            $table->longText('description')->nullable();
            $table->string('catalog_number')->nullable();
            $table->string('asset_image')->nullable();
            $table->string('functional_procedure')->nullable();
            $table->integer('manufacturing_site')->nullable();
            $table->integer('repair_site')->nullable();
            $table->string('application_segment')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('status')->nullable();
            $table->string('condition')->nullable();
            $table->string('fw_version')->nullable();
            $table->string('sw_version')->nullable();
            $table->date('last_calibration')->nullable();
            $table->date('calibration_due')->nullable();
            $table->date('purchase_date')->nullable();
            $table->integer('assigned_to')->nullable();
            $table->integer('region_id')->nullable();
            $table->integer('territory_id')->nullable();
            $table->timestamp('last_updated')->nullable();
            $table->integer('owner_region')->nullable();
            $table->timestamp('last_transaction_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();
            $table->integer('created_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('assets');
    }
}
