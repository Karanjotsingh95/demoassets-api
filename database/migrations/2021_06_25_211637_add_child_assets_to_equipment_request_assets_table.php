<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddChildAssetsToEquipmentRequestAssetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('equipment_request_assets', function (Blueprint $table) {
            $table->integer('child_asset_available')->default(1);
            $table->integer('child_asset_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('equipment_request_assets', function (Blueprint $table) {
            //
        });
    }
}
