<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Fotos guardadas como un array de URLs en la columna "fotos" del modelo.
 * Los archivos van al disco "public", en {carpeta}/{id del modelo}/.
 * Lo usan CanchaController y ClubController.
 */
trait GestionaFotos
{
    // Guarda el archivo y agrega su URL al final del array
    protected function agregarFoto(Model $modelo, UploadedFile $archivo, string $carpeta): void
    {
        $ruta = $archivo->store("{$carpeta}/{$modelo->getKey()}", 'public');

        $fotos = $modelo->fotos ?? [];
        $fotos[] = Storage::disk('public')->url($ruta);
        $modelo->update(['fotos' => $fotos]);
    }

    // Borra la foto de esa posición. Devuelve false si no existe.
    protected function quitarFoto(Model $modelo, int $indice, string $carpeta): bool
    {
        $fotos = $modelo->fotos ?? [];

        if (! array_key_exists($indice, $fotos)) {
            return false;
        }

        $archivo = basename(parse_url($fotos[$indice], PHP_URL_PATH));
        Storage::disk('public')->delete("{$carpeta}/{$modelo->getKey()}/{$archivo}");

        array_splice($fotos, $indice, 1);
        $modelo->update(['fotos' => $fotos]);

        return true;
    }
}
