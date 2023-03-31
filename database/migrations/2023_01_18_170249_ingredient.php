<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('feed', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('class');
            $table->string('dry_matter')->nullable();
            $table->string('crude_protein')->nullable();
            $table->string('ether_extract')->nullable();
            $table->string('crude_fibre')->nullable();
            $table->string('nitrogen_free_extract')->nullable();
            $table->string('ash')->nullable();
            $table->string('energy')->nullable();
            $table->string('neutral_detergent_fibre')->nullable();
            $table->string('acid_detergent_fibre')->nullable();
            $table->string('cellulose')->nullable();
            $table->string('hemicellulose')->nullable();
            $table->string('lignin')->nullable();
            $table->string('calcium')->nullable();
            $table->string('phosphorus')->nullable();
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
        //
    }
};
