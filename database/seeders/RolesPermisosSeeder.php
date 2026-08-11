<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Reproduce el bloque 15 del esquema (roles, permisos y rol_permiso).
 * Idempotente: usa updateOrCreate/firstOrCreate para poder re-ejecutarse.
 */
class RolesPermisosSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['codigo' => 'ADMIN', 'nombre' => 'Administrador', 'descripcion' => 'Acceso total al sistema'],
            ['codigo' => 'GERENTE', 'nombre' => 'Gerente', 'descripcion' => 'Operacion completa de su sucursal'],
            ['codigo' => 'SUPERVISOR', 'nombre' => 'Supervisor', 'descripcion' => 'Autoriza cancelaciones, devoluciones y ajustes'],
            ['codigo' => 'CAJERO', 'nombre' => 'Cajero', 'descripcion' => 'Registra ventas y opera su turno de caja'],
            ['codigo' => 'ALMACEN', 'nombre' => 'Almacen', 'descripcion' => 'Compras, traspasos y conteos fisicos'],
            ['codigo' => 'CONSULTA', 'nombre' => 'Consulta', 'descripcion' => 'Solo lectura de reportes'],
        ];

        foreach ($roles as $rol) {
            Role::updateOrCreate(['codigo' => $rol['codigo']], $rol);
        }

        $permisos = [
            ['codigo' => 'venta.registrar', 'modulo' => 'VENTAS', 'nombre' => 'Registrar ventas'],
            ['codigo' => 'venta.cancelar', 'modulo' => 'VENTAS', 'nombre' => 'Cancelar ventas'],
            ['codigo' => 'venta.descuento', 'modulo' => 'VENTAS', 'nombre' => 'Aplicar descuento manual'],
            ['codigo' => 'venta.bajo_costo', 'modulo' => 'VENTAS', 'nombre' => 'Autorizar venta por debajo del costo'],
            ['codigo' => 'devolucion.registrar', 'modulo' => 'VENTAS', 'nombre' => 'Registrar devoluciones'],
            ['codigo' => 'devolucion.autorizar', 'modulo' => 'VENTAS', 'nombre' => 'Autorizar devoluciones'],
            ['codigo' => 'corte.abrir', 'modulo' => 'CAJA', 'nombre' => 'Abrir turno de caja'],
            ['codigo' => 'corte.cerrar', 'modulo' => 'CAJA', 'nombre' => 'Cerrar corte de caja'],
            ['codigo' => 'corte.auditar', 'modulo' => 'CAJA', 'nombre' => 'Auditar cortes cerrados'],
            ['codigo' => 'caja.retiro', 'modulo' => 'CAJA', 'nombre' => 'Registrar retiros y gastos de caja'],
            ['codigo' => 'producto.crear', 'modulo' => 'CATALOGO', 'nombre' => 'Alta de productos'],
            ['codigo' => 'precio.modificar', 'modulo' => 'CATALOGO', 'nombre' => 'Modificar precios'],
            ['codigo' => 'promocion.gestionar', 'modulo' => 'CATALOGO', 'nombre' => 'Crear y editar promociones'],
            ['codigo' => 'compra.registrar', 'modulo' => 'INVENTARIO', 'nombre' => 'Capturar compras'],
            ['codigo' => 'compra.recibir', 'modulo' => 'INVENTARIO', 'nombre' => 'Recibir mercancia y afectar inventario'],
            ['codigo' => 'inventario.ajustar', 'modulo' => 'INVENTARIO', 'nombre' => 'Aplicar ajustes de inventario'],
            ['codigo' => 'inventario.traspaso', 'modulo' => 'INVENTARIO', 'nombre' => 'Enviar y recibir traspasos'],
            ['codigo' => 'conteo.aplicar', 'modulo' => 'INVENTARIO', 'nombre' => 'Aplicar conteo fisico'],
            ['codigo' => 'cliente.ver_datos', 'modulo' => 'CLIENTES', 'nombre' => 'Ver datos personales de clientes'],
            ['codigo' => 'cxc.gestionar', 'modulo' => 'CLIENTES', 'nombre' => 'Gestionar credito y abonos'],
            ['codigo' => 'reporte.ventas', 'modulo' => 'REPORTES', 'nombre' => 'Reportes de ventas'],
            ['codigo' => 'reporte.utilidad', 'modulo' => 'REPORTES', 'nombre' => 'Reportes de utilidad y margen'],
            ['codigo' => 'usuario.gestionar', 'modulo' => 'SISTEMA', 'nombre' => 'Administrar usuarios y roles'],
            ['codigo' => 'config.modificar', 'modulo' => 'SISTEMA', 'nombre' => 'Modificar configuracion del sistema'],
            ['codigo' => 'auditoria.consultar', 'modulo' => 'SISTEMA', 'nombre' => 'Consultar bitacora de auditoria'],
        ];

        foreach ($permisos as $permiso) {
            Permiso::updateOrCreate(['codigo' => $permiso['codigo']], $permiso);
        }

        $this->asignarPermisos('ADMIN', Permiso::pluck('codigo')->all());

        $this->asignarPermisos('CAJERO', [
            'venta.registrar', 'devolucion.registrar', 'corte.abrir', 'corte.cerrar',
        ]);

        $this->asignarPermisos('SUPERVISOR', [
            'venta.registrar', 'venta.cancelar', 'venta.descuento', 'venta.bajo_costo',
            'devolucion.registrar', 'devolucion.autorizar', 'corte.abrir', 'corte.cerrar',
            'corte.auditar', 'caja.retiro', 'inventario.ajustar', 'conteo.aplicar',
            'reporte.ventas', 'cliente.ver_datos',
        ]);

        $this->asignarPermisos('ALMACEN', [
            'compra.registrar', 'compra.recibir', 'inventario.traspaso', 'conteo.aplicar', 'producto.crear',
        ]);

        // El seed original (bloque 15 del .sql) le quitaba tambien
        // config.modificar a GERENTE, pero M1 SS2.10 asume que GERENTE
        // administra sucursales/cajas/impuestos con ese permiso. Ajustado
        // deliberadamente: GERENTE mantiene config.modificar, solo se le
        // niega usuario.gestionar (exclusivo de ADMIN).
        $this->asignarPermisos('GERENTE', Permiso::query()
            ->whereNotIn('codigo', ['usuario.gestionar'])
            ->pluck('codigo')->all());

        $this->asignarPermisos('CONSULTA', ['reporte.ventas', 'reporte.utilidad']);
    }

    /**
     * @param  array<int, string>  $codigosPermiso
     */
    private function asignarPermisos(string $codigoRol, array $codigosPermiso): void
    {
        $rol = Role::where('codigo', $codigoRol)->firstOrFail();
        $permisoIds = Permiso::whereIn('codigo', $codigosPermiso)->pluck('id');

        $rol->permisos()->sync($permisoIds);
    }
}
