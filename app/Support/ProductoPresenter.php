<?php

namespace App\Support;

use App\Models\Producto;

/**
 * Forma de respuesta comun para un producto: campos propios + margen
 * (columnas generadas, ya vienen del modelo) + nombres cortos de sus
 * catalogos relacionados, sin forzar al cliente a resolver 4 FKs aparte.
 */
class ProductoPresenter
{
    public static function detalle(Producto $producto): array
    {
        return [
            'id' => $producto->id,
            'tipo_prod_serv' => $producto->tipo_prod_serv,
            'nombre' => $producto->nombre,
            'sku' => $producto->sku,
            'codigo_barras' => $producto->codigo_barras,
            'descripcion' => $producto->descripcion,
            'precio' => $producto->precio,
            'costo_neto' => $producto->costo_neto,
            'margen_unitario' => $producto->margen_unitario,
            'margen_pct' => $producto->margen_pct,
            'controla_stock' => $producto->controla_stock,
            'imagen_url' => $producto->imagen_url,
            'activo' => $producto->activo,
            'categoria' => $producto->relationLoaded('categoria') && $producto->categoria
                ? ['id' => $producto->categoria->id, 'nombre' => $producto->categoria->nombre]
                : null,
            'marca' => $producto->relationLoaded('marca') && $producto->marca
                ? ['id' => $producto->marca->id, 'nombre' => $producto->marca->nombre]
                : null,
            'unidad_medida' => $producto->relationLoaded('unidadMedida') && $producto->unidadMedida
                ? ['id' => $producto->unidadMedida->id, 'codigo' => $producto->unidadMedida->codigo, 'permite_decimales' => $producto->unidadMedida->permite_decimales]
                : null,
            'impuesto' => $producto->relationLoaded('impuesto') && $producto->impuesto
                ? ['id' => $producto->impuesto->id, 'codigo' => $producto->impuesto->codigo, 'tasa' => $producto->impuesto->tasa]
                : null,
        ];
    }
}
