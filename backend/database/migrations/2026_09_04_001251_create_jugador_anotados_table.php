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
        Schema::create('jugador_anotados', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reserva_id')
                ->constrained('reservas')
                ->onDelete('cascade');

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->timestamp('fecha_inscripcion')->useCurrent();

            $table->timestamps();

            $table->unique(['reserva_id', 'usuario_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jugador_anotados');
    }
};
