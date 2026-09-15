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
        Schema::create('canchas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('club_id')
                ->constrained('clubs')
                ->onDelete('cascade');

            $table->string('nombre', 100);
            $table->string('tipo', 20)->default('futbol5');
            $table->integer('capacidad_jugadores')->default(10);
            $table->text('descripcion')->nullable();
            $table->decimal('tarifa', 10, 2);
            $table->string('estado', 20)->default('activa');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('canchas');
    }
};
