# Arquitectura del proyecto — boss_pos_back

API REST para un sistema POS multisucursal (tortillería "El Patrón" / `mila_pos`). Este documento explica la estructura del repo, las tecnologías, los servicios internos y el contrato de request/response que sigue toda la API.

Para el detalle endpoint por endpoint (request/response reales, reglas de cada campo, errores esperados), ver [`API.md`](./API.md).

---

## 1. Tecnologías

| Capa | Tecnología |
|---|---|
| Lenguaje | PHP 8.2 |
| Framework | Laravel 12 |
| Autenticación | Laravel Sanctum 4 (modo **SPA cookie-based**, no tokens Bearer) |
| Base de datos | MySQL 8 / MariaDB 10.4 (esquema `mila_pos`) |
| ORM | Eloquent |
| Tests | PHPUnit 11 (Feature tests contra BD real con `RefreshDatabase`) |
| Cliente de API para pruebas manuales | **Bruno** (`bruno/`) — nunca Postman |
| Utilidades dev | Laravel Pint (estilo de código), Laravel Tinker, Laravel Sail |

No hay frontend en este repo — es solo la API. El frontend es un proyecto aparte (React/Vite, corre en otro puerto) que la consume.

---

## 2. Estructura de carpetas

```
app/
  Console/Commands/       Comandos artisan de soporte (ver §7)
  Exceptions/             Excepciones de dominio (ver §5.2)
  Http/
    Controllers/Api/      Un controlador por recurso, agrupados por modulo (ver §4)
    Middleware/           RBAC, auditoria de request, logging (ver §5.1)
  Models/                 Eloquent, uno por tabla relevante
  Providers/               AppServiceProvider (arranque de la app)
  Services/                Logica de negocio compartida entre controladores (ver §6)
  Support/                 Helpers/presenters sin estado de Eloquent (ver §6)
bootstrap/
  app.php                  Registro de rutas, middleware globales y manejo de excepciones (ver §5.2)
config/                    Config estandar de Laravel (filesystems, sanctum, etc.)
database/
  migrations/              Ver §3
  seeders/                 Datos iniciales (roles/permisos, admin, catalogos)
  factories/                Factories de Eloquent para tests
routes/
  api.php                  Todas las rutas de negocio (bajo /api)
  web.php                  Solo la ruta "/" (Laravel welcome), no se usa en produccion
tests/
  Feature/<Modulo>/         Tests de integracion HTTP contra la app real
  Concerns/                 Traits compartidos entre tests (ver §8)
bruno/                     Coleccion de API para pruebas manuales (ver §9), organizada por modulo
docs/                      Este documento
```

Convención de nombres: **el código, los comentarios y los nombres de rutas están en español** (coincide con el dominio: sucursal, compra, proveedor, kardex...), salvo las palabras reservadas/clases del framework.

---

## 3. Base de datos

El esquema completo vive en **una sola migración** (`database/migrations/2026_02_19_000001_create_mila_pos_schema.php`), escrita como bloques de SQL crudo (`DB::unprepared`) organizados por módulo de negocio (comentarios `BLOQUE N`):

1. Catálogos base (marcas, categorías, unidades, impuestos, sucursales, proveedores, clientes, métodos de pago)
2. Seguridad y acceso (roles, permisos, rol_permiso, usuarios, usuario_sucursal)
3. Productos e inventario (productos, historial_precios, inventario, movimientos_inventario)
4. Cajas, folios y cortes
5. Promociones
6. Ventas (ventas, detalle_venta, venta_pagos, corte_caja_metodos)
7. Compras (compras, detalle_compra)
8. Devoluciones de cliente
9. Traspasos entre sucursales
10. Cuentas por cobrar
11. Conteos físicos, gastos, configuración, auditoría
12. Triggers (ej. kardex append-only)
13. Procedimiento de folios
14. Vistas de apoyo

**Regla del proyecto: este esquema está congelado.** No se rediseña ni se le quitan/renombran columnas para que "quede más bonito" con lo que pida un PDF de especificación — se implementa contra las columnas reales. Los únicos cambios de esquema aceptados después de M0 son migraciones aditivas nuevas y pequeñas (ver `2026_09_03_*`, `2026_09_10_*`) cuando una regla de negocio confirmada lo exige explícitamente, nunca alterando el archivo base.

Los módulos M4 en adelante (ventas, cortes, devoluciones, traspasos, CxC, conteos) ya tienen tabla en el esquema pero **todavía no tienen controlador/rutas** — se construyen módulo por módulo según se comparten sus especificaciones.

---

## 4. Módulos y controladores (`app/Http/Controllers/Api`)

| Controlador | Recurso | Notas |
|---|---|---|
| `AuthController` | `/auth/*` | Login (por **email**, no username), logout, `me`, PIN, cambiar contraseña |
| `UsuarioController` | `/usuarios` | Alta/edición/baja/reactivación de usuarios del sistema |
| `RoleController` | `/roles` | Solo lectura de roles y sus permisos |
| `ProductoController` | `/productos` | CRUD + búsqueda + historial de precios |
| `CategoriaProductoController`, `MarcaProductoController`, `UnidadMedidaController`, `ImpuestoController` | Catálogos simples | CRUD + baja lógica (`estado`) |
| `SucursalController`, `CajaController` | Sucursales / cajas | CRUD + baja lógica; sucursal soporta logo (upload) |
| `InventarioController`, `AjusteController`, `KardexController`, `AlertasController` | Inventario | Consulta de existencias, ajustes manuales, kardex, alertas de stock bajo |
| `ProveedorController` | `/proveedores` | CRUD + baja lógica |
| `CompraController` | `/compras` | Ciclo de vida completo: borrador → recepción (parcial/total) → cancelación |

Todos siguen el mismo patrón: `index` (listar, con filtros por query string), `store` (crear), `update` (editar), `show` (detalle, solo donde aplica), y `estado`/`desactivar`+`reactivar` para baja lógica — **nunca hay `destroy()` real**, el sistema nunca borra filas de negocio, solo las desactiva (`activo=false`).

---

## 5. Cómo se arman las requests

### 5.1 Autenticación y autorización

- **Autenticación**: Sanctum en modo SPA — el cliente primero pide `GET /sanctum/csrf-cookie`, luego hace `POST /api/auth/login` con `{email, password}`; Laravel guarda la sesión en una cookie `httpOnly`. Las siguientes requests deben mandar el header `X-XSRF-TOKEN` (leído de la cookie `XSRF-TOKEN`) en cualquier método que no sea `GET`, más `Origin`/`Referer` dentro de `sanctum.stateful`.
- **Middleware global** (`bootstrap/app.php`):
  - `EnsureFrontendRequestsAreStateful` (Sanctum) — antepuesto al grupo `api`.
  - `SetAppUsuarioId` — en cada request autenticada, hace `SET @app_usuario_id` en la sesión de MySQL para que los triggers de auditoría de la BD sepan quién hizo el cambio.
  - `LogApiRequests` — loguea cada request/response de `/api/*` en `storage/logs/api.log` (útil para depurar sin adivinar).
- **Autorización (RBAC)**: middleware `permiso:codigo1,codigo2,...` (alias de `VerificarPermiso`). Se aplica por grupo de rutas en `routes/api.php`. Varios códigos separados por coma = "el usuario necesita **cualquiera** de esos permisos" (OR, no AND) — útil cuando dos roles distintos pueden tocar el mismo recurso.
- Los permisos son código de negocio (`compra.registrar`, `inventario.ajustar`, `config.modificar`, etc.), sembrados en `RolesPermisosSeeder`, asociados a roles (`ADMIN`, `GERENTE`, `SUPERVISOR`, `CAJERO`, `ALMACEN`, `CONSULTA`) vía tabla `rol_permiso`.

### 5.2 Validación y manejo de errores

- Cada `store`/`update` valida con `$request->validate([...])` (Form Request inline, sin clases `FormRequest` separadas — patrón usado en todo el proyecto).
- El manejo de excepciones es **centralizado** en `bootstrap/app.php` (`withExceptions`), no hay try/catch repartidos por los controladores. Cualquier excepción de dominio nueva (ver `app/Exceptions/`) se registra ahí una sola vez y automáticamente todos los controladores la heredan.
- Excepciones de dominio propias:
  - `PermisoDenegadoException` → 403
  - `StockInsuficienteException` → 409 (el motor de inventario la lanza si una salida dejaría stock negativo)
  - `EstadoInvalidoException` → 409 (transiciones de estado inválidas, ej. recibir una compra ya cancelada)
- `ValidationException` (la nativa de Laravel) siempre responde 422 con esta forma:
  ```json
  {
    "error": "validacion",
    "mensaje": "Los datos enviados no son validos.",
    "detalles": { "campo": ["mensaje de error"] }
  }
  ```
- Errores de base de datos (`QueryException`) se traducen según el código MySQL:
  - `1644` (trigger `SIGNAL`, ej. violación de invariante como kardex append-only) → 409 `conflicto_invariante`
  - `1062` (llave única duplicada, defensa en profundidad ante condiciones de carrera) → 422 `duplicado`
- Cualquier otra excepción no controlada → 500 `error_servidor` (nunca se filtra el stack trace al cliente si `APP_DEBUG=false`).

### 5.3 Convenciones de payload

- Los booleanos que llegan por `multipart/form-data` (subida de archivos, ej. logo de sucursal) llegan como texto `"true"`/`"false"`, no boolean JSON — los controladores que reciben archivos normalizan esos campos con `$request->boolean()` antes de validar (la regla `boolean` nativa de Laravel no acepta la palabra `"false"`, solo `1`/`0`/`"1"`/`"0"`).
- Los recursos "padre-hijo" (compra + detalle, usuario + sucursales) se mandan como un solo payload anidado en el `store`/`update` del padre — no hay endpoints separados para las líneas de detalle.

---

## 6. Servicios y helpers reutilizables

| Clase | Ubicación | Qué hace |
|---|---|---|
| `InventarioService` | `app/Services/` | **Motor único** de movimientos de inventario. Todo módulo que necesite alterar existencias (compras, ajustes, y a futuro ventas/devoluciones/traspasos) pasa por `aplicarMovimiento()` — nunca se toca `inventario`/`movimientos_inventario` directo desde un controlador. Usa `SELECT ... FOR UPDATE` + aritmética `bcmath` (evita errores de punto flotante en costo promedio). |
| `GeneradorNumeroEmpleado` | `app/Support/` | Genera el número de empleado (`27` + año + consecutivo de 3 dígitos) con lock de fila para altas concurrentes. |
| `PasswordPolicy` | `app/Support/` | Reglas de validación reutilizables para contraseñas (longitud, complejidad). |
| `UsuarioPresenter` | `app/Support/` | Da forma a las respuestas de usuario (`contexto()` para login/me, `detalle()` para admin viendo a otro usuario, `resumen()` para listados) — nunca expone `password_hash`/`pin_hash`. |
| `ProductoPresenter` | `app/Support/` | Forma de respuesta de producto para catálogo/búsqueda. |

**Patrón "Presenter"**: los controladores no devuelven `$model->toJson()` a pelo — devuelven la forma que arma el Presenter correspondiente, así la forma de la respuesta es consistente sin importar qué controlador la genere, y cambiar esa forma es un solo punto de edición.

---

## 7. Comandos artisan de soporte

En `app/Console/Commands/`, usados para pruebas de concurrencia real de inventario (no simulada) y verificación de integridad del kardex:

- `InventarioSembrarPruebaConcurrenciaCommand` / `InventarioDecrementarUnoCommand` / `InventarioMostrarStockCommand` — usados por `scripts/prueba-concurrencia-inventario.sh` para lanzar N procesos PHP paralelos reales contra el mismo producto/sucursal y verificar que el stock nunca queda negativo.
- `VerificarKardexCommand` — reconstruye el stock a partir del historial de movimientos y lo compara contra el valor actual en `inventario`, para detectar si algo lo tocó por fuera del motor.

---

## 8. Tests

- **Feature tests** (`tests/Feature/<Modulo>/`) contra HTTP real (`postJson`/`putJson`/`getJson`) con `RefreshDatabase` — no hay mocks de la base de datos, cada test corre contra MySQL real migrado desde cero.
- `tests/Concerns/CreaUsuarioConPermiso.php` — trait compartido: `$this->usuarioConPermisos('compra.registrar', ...)` crea un rol con exactamente esos permisos y un usuario con ese rol, para no repetir el boilerplate de RBAC en cada test.
- Convención de nombres: `test_<algo>_devuelve_<codigo>` para casos de error, `test_p0N_<...>` cuando el test cubre un punto explícito (`P-01`, `P-02`...) de un documento de especificación.
- Correr todo: `php artisan test`. Filtrar por módulo: `php artisan test --filter=CompraControllerTest`.

---

## 9. Colección Bruno (`bruno/`)

Cliente de API para pruebas manuales — **siempre `.bru` nativo, nunca Postman JSON** (rompe el importador de Bruno). Estructura por módulo, un subfolder por entidad con CRUD completo:

```
bruno/
  M0 — Auth/        csrf-cookie, login, me, logout, pin, cambiar contraseña
  Usuarios/         listar, ver, crear, editar, desactivar, reactivar, listar roles
  Catalogos/        Productos, Categorias, Marcas, Unidades, Impuestos, Sucursales, Cajas
  Inventario/       existencias, alertas, kardex, ajustes, fijar minimos
  Compras/          Proveedores, Compras (borrador → recibir → cancelar)
```

Cada request trae un bloque `vars:pre-request` con `base_url`/`frontend_origin` (no depende de tener un Environment de Bruno seleccionado) y, cuando aplica, un `script:post-response` que encadena IDs (`usuario_id_creado`, etc.) para el siguiente request de la secuencia.

**Flujo obligatorio antes de probar cualquier endpoint protegido**: `M0 — Auth/0. Obtener CSRF Cookie` → `M0 — Auth/Login` → el resto. El token CSRF rota al hacer login (anti session-fixation), así que si vuelves a loguearte hay que volver a pedir la cookie.

---

## 10. Convenciones generales del proyecto

- **P2 (nunca se borra nada)**: dar de baja = `activo=false` + auditoría, jamás `DELETE`.
- **Auditoría**: casi toda escritura relevante crea una fila en `auditoria` (usuario, tabla, registro, acción, valores antes/después, IP).
- **RN- / D- / P- / RN-M#-##**: los comentarios en el código referencian el identificador de la regla de negocio o decisión que justifica ese código (ej. `RN-CRED-02`, `D-M2-4`), para poder rastrear el porqué sin tener que releer el documento fuente completo.
- Antes de codificar cada módulo nuevo se verifica columna por columna contra la migración real — los documentos de especificación (PDF/MD) a veces usan nombres de campo distintos a los del esquema congelado (ej. `compras.estatus` no `compras.estado`).
