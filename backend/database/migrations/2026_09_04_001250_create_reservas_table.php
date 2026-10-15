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
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('turno_id')
                ->constrained('turnos')
                ->onDelete('cascade');

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->string('tipo', 20)->default('completa');
            $table->integer('cantidad_jugadores')->default(1);
            $table->string('estado', 20)->default('pendiente');
            $table->string('estado_pago', 20)->default('pendiente');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
