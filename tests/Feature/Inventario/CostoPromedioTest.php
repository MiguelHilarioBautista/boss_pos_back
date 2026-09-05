<?php

namespace Tests\Feature\Inventario;

use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P-08: costo promedio ponderado (Regla 4.2), >=6 casos, prueba directa del
 * servicio (sin pasar por HTTP) para aislar la aritmetica del resto del
 * stack.
 */
class CostoPromedioTest extends CostoPromedioTestCase
{
    public function test_entrada_sobre_stock_previo_cero_usa_costo_de_la_entrada(): void
    {
        $this->crearInventario(0, 0);

        $mov = $this->servicio->aplicarMovimiento($this->entrada(cantidad: 10, costo: 8));

        $this->assertSame('8.00', $mov->costo_prom_nuevo);
        $this->assertSame('10.000', $mov->stock_nuevo);
    }

    public function test_entrada_sobre_stock_existente_promedia_ponderado(): void
    {
        // 10 uds a 5.00 + 10 uds a 9.00 => promedio 7.00
        $this->crearInventario(10, 5);

        $mov = $this->servicio->aplicarMovimiento($this->entrada(cantidad: 10, costo: 9));

        $this->assertSame('7.00', $mov->costo_prom_nuevo);
        $this->assertSame('20.000', $mov->stock_nuevo);
    }

    public function test_entrada_con_cantidades_desiguales(): void
    {
        // 3 uds a 10.00 + 7 uds a 20.00 => (30+140)/10 = 17.00
        $this->crearInventario(3, 10);

        $mov = $this->servicio->aplicarMovimiento($this->entrada(cantidad: 7, costo: 20));

        $this->assertSame('17.00', $mov->costo_prom_nuevo);
    }

    public function test_ajuste_positivo_sin_costo_explicito_no_altera_el_promedio(): void
    {
        $this->crearInventario(10, 6.50);

        $mov = $this->servicio->aplicarMovimiento([
            'tipo' => 'AJUSTE_POSITIVO', 'producto_id' => $this->producto->id,
            'sucursal_id' => $this->sucursal->id, 'cantidad' => 5,
            'usuario_id' => $this->usuarioId, 'motivo' => 'Encontrado en bodega',
        ]);

        $this->assertSame('6.50', $mov->costo_prom_nuevo);
        $this->assertSame('15.000', $mov->stock_nuevo);
    }

    public function test_ajuste_positivo_con_costo_explicito_si_recalcula(): void
    {
        // 10 uds a 5.00 + 10 uds a 15.00 (costo declarado) => 10.00
        $this->crearInventario(10, 5);

        $mov = $this->servicio->aplicarMovimiento([
            'tipo' => 'AJUSTE_POSITIVO', 'producto_id' => $this->producto->id,
            'sucursal_id' => $this->sucursal->id, 'cantidad' => 10, 'costo_unitario' => 15,
            'usuario_id' => $this->usuarioId, 'motivo' => 'Correccion de captura',
        ]);

        $this->assertSame('10.00', $mov->costo_prom_nuevo);
    }

    public function test_salida_nunca_recalcula_el_costo_promedio(): void
    {
        $this->crearInventario(10, 7.25);

        $mov = $this->servicio->aplicarMovimiento($this->salida(cantidad: 4));

        $this->assertSame('7.25', $mov->costo_prom_nuevo);
        $this->assertSame('7.25', $mov->costo_prom_anterior);
        $this->assertSame('6.000', $mov->stock_nuevo);
    }

    public function test_salida_registra_costo_unitario_igual_al_promedio_vigente(): void
    {
        $this->crearInventario(10, 12.34);

        $mov = $this->servicio->aplicarMovimiento($this->salida(cantidad: 1));

        $this->assertSame('12.34', (string) $mov->costo_unitario);
    }
}
