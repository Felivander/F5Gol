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
        Schema::table('canchas', function (Blueprint $table) {
            // cesped_sintetico, cesped_natural, cemento, parquet o caucho (ver Cancha::SUPERFICIES)
            $table->string('superficie', 30)->nullable()->after('capacidad_jugadores');
            $table->boolean('techada')->default(false)->after('superficie');
            $table->boolean('iluminacion')->default(false)->after('techada');
            $table->boolean('vestuarios')->default(false)->after('iluminacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('canchas', function (Blueprint $table) {
            $table->dropColumn(['superficie', 'techada', 'iluminacion', 'vestuarios']);
        });
    }
};
