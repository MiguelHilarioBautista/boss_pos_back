# Referencia detallada de la API

Complementa a [`ARQUITECTURA.md`](./ARQUITECTURA.md) (que explica el *porqué* de cada patrón). Este documento es el *cómo* — cada endpoint con su request y response reales.

**Base URL**: `{{base_url}}/api` (en dev normalmente `http://127.0.0.1:8000/api`).

**Headers comunes a toda request autenticada** (después de haber hecho login):

```
Accept: application/json
Content-Type: application/json      (solo si el body es JSON)
Origin: http://localhost:5173        (el origin del frontend, debe estar en sanctum.stateful)
X-XSRF-TOKEN: {valor de la cookie XSRF-TOKEN, url-decodeado}
Cookie: laravel-session=...; XSRF-TOKEN=...   (el navegador/cliente HTTP las manda solas si acepta cookies)
```

Las rutas `GET` no necesitan `X-XSRF-TOKEN`. Las que suben archivos (`multipart/form-data`) no llevan `Content-Type` manual — lo pone el cliente HTTP con el boundary correcto.

---

## 0. Flujo de autenticación (obligatorio antes que nada)

### `GET /sanctum/csrf-cookie`

Pide la cookie `XSRF-TOKEN`. Sin este paso, cualquier `POST/PUT/PATCH` da `419 CSRF token mismatch`.

**Response**: `204 No Content`, con `Set-Cookie: XSRF-TOKEN=...` y `Set-Cookie: laravel-session=...`.

### `POST /auth/login`

Sin `permiso:` (endpoint público), con `throttle:10,1` (máx. 10 intentos por minuto por IP).

**Request**
```json
{
  "email": "admin@mila-pos.local",
  "password": "Cambiar#2026"
}
```

**Response 200**
```json
{
  "usuario": {
    "id": 5,
    "nombre": "Administrador",
    "apellidos": "Sistema",
    "numero_empleado": "272026001",
    "email": "admin@mila-pos.local",
    "telefono": "0000000000",
    "rol_id": 1,
    "rol": "Administrador",
    "activo": true,
    "debe_cambiar_pass": true,
    "ultimo_acceso": "2026-09-19T10:00:00+00:00"
  },
  "permisos": ["auditoria.consultar", "compra.registrar", "compra.recibir", "config.modificar", "..."],
  "sucursales": [
    {
      "id": 1,
      "codigo": "TPHC",
      "nombre": "Tortilleria El Patrón Huejutla Centro",
      "direccion": "C. Cuahutemoc 61 Col. Capitan Antonio Reyes, Huejutla Hgo",
      "telefono": "8119107458",
      "email": "tortilleriapr@gmail.com",
      "logo_url": "http://127.0.0.1:8000/storage/logoempresa/xxxx.png",
      "numero_exterior": "61",
      "codigo_postal": "43000",
      "pais": "Mexico",
      "estado": "Hidalgo",
      "es_principal": true
    }
  ],
  "debe_cambiar_pass": true
}
```
Nota: dentro de `usuario`, `rol` es el **nombre legible** del rol (`"Administrador"`); en listados de usuarios (`GET /usuarios`) ese mismo campo trae el **código** (`"ADMIN"`) — ver §3.

**Errores posibles**
| Status | `error` | Cuándo |
|---|---|---|
| 401 | `credenciales_invalidas` | Email no existe, o password incorrecta (mensaje genérico, nunca revela cuál de los dos falló) |
| 403 | `cuenta_inactiva` | El usuario existe pero `activo=false` |
| 423 | `cuenta_bloqueada` | Superó `login_intentos_max` (config, default 5) fallos seguidos; se desbloquea solo tras `login_bloqueo_minutos` (default 15) |
| 422 | `validacion` | Falta `email` o `password`, o `email` no tiene formato válido |

### `GET /auth/me`
Requiere sesión. Misma forma de respuesta que el login (sin la cookie, claro).

### `POST /auth/logout`
Requiere sesión. **Response**: `204 No Content`. Invalida la sesión y rota el token CSRF (hay que volver a pedir `csrf-cookie` antes del siguiente login).

### `POST /auth/pin`
Revalida identidad con un PIN de 4 dígitos (no otorga permisos nuevos, solo confirma "sigues siendo tú" para una acción sensible en el front).

**Request**: `{"pin": "1234"}` → **Response 200**: `{"revalidado": true}`
Errores: `422 pin_no_configurado`, `401 pin_incorrecto`.

### `POST /auth/cambiar-password`
**Request**
```json
{
  "password_actual": "Cambiar#2026",
  "password_nuevo": "NuevaClave123",
  "password_confirmacion": "NuevaClave123"
}
```
**Response 200**: `{"cambiada": true}`
Errores: `401 password_incorrecto`; `422` si `password_nuevo` no cumple la política (mín. 8 caracteres, al menos una letra y un número) o es igual a la actual.

---

## 1. Forma estándar de error (aplica a TODA la API)

Cualquier endpoint, ante un error, responde así — nunca HTML, nunca un stack trace:

```json
{
  "error": "codigo_corto_snake_case",
  "mensaje": "Texto legible para mostrar al usuario.",
  "detalles": {
    "campo": ["mensaje de validación específico de ese campo"]
  }
}
```
`detalles` solo aparece en errores de validación (422 `validacion`). Los demás (`403 permiso_denegado`, `409 estado_invalido`, `409 stock_insuficiente`, `500 error_servidor`, etc.) solo traen `error` + `mensaje`.

**Catálogo de códigos de error usados en todo el proyecto:**

| `error` | HTTP | Origen |
|---|---|---|
| `no_autenticado` | 401 | No hay sesión válida |
| `credenciales_invalidas` | 401 | Login fallido |
| `cuenta_inactiva` | 403 | Usuario desactivado intenta login |
| `cuenta_bloqueada` | 423 | Demasiados intentos fallidos de login |
| `permiso_denegado` | 403 | Falta el permiso `permiso:codigoX` de la ruta |
| `validacion` | 422 | Body no pasa las reglas de `$request->validate()` |
| `duplicado` | 422 | Choque de `UNIQUE` a nivel BD (defensa en profundidad ante condiciones de carrera) |
| `stock_insuficiente` | 409 | Una salida de inventario dejaría stock negativo |
| `estado_invalido` | 409 | Transición de estado no permitida (ej. recibir una compra `CANCELADA`) |
| `conflicto_invariante` | 409 | Un trigger de la BD rechazó la operación (`SIGNAL`, ej. kardex append-only) |
| `error_servidor` | 500 | Cualquier excepción no controlada |

---

## 2. Cómo se arma una request de escritura (patrón general)

Todos los `store`/`update` siguen la misma receta:

1. `$request->validate([...])` con reglas array (`['required', 'string', 'max:100']`, etc.) — nunca clases `FormRequest` separadas.
2. Si el recurso tiene baja lógica, `activo` **nunca** se acepta en `store`/`update` — solo existe `PATCH /recurso/{id}/estado`.
3. Si el alta dispara efectos secundarios (folios, movimientos de inventario, auditoría), todo va dentro de `DB::transaction()`.
4. La respuesta de éxito es el recurso recién creado/editado, tal cual lo devuelve Eloquent o el Presenter correspondiente — **sin envoltura** (`{...}` directo, no `{"data": {...}}`).
5. Casi toda escritura relevante crea una fila en `auditoria` (tabla, registro_id, acción `INSERT`/`UPDATE`, valores antes/después, usuario, IP).

Ejemplo mínimo (`POST /categorias`):

**Request**
```json
{ "nombre": "Bebidas" }
```
**Response 201**
```json
{
  "id": 7,
  "nombre": "Bebidas",
  "categoria_padre_id": null,
  "activo": true,
  "creado_fecha": "2026-09-19T10:00:00.000000Z",
  "modificado_fecha": "2026-09-19T10:00:00.000000Z"
}
```

---

## 3. Usuarios — `/usuarios` (permiso `usuario.gestionar`)

### `GET /usuarios`
Query opcional: `?solo_activos=1`.

**Response 200**
```json
{
  "usuarios": [
    {
      "id": 1,
      "nombre": "Miguel",
      "apellidos": "Hilario Bautista Hernandez",
      "numero_empleado": "272026003",
      "email": "miguel@gmail.com",
      "telefono": "8119107458",
      "rol_id": 1,
      "rol": "ADMIN",
      "activo": true,
      "debe_cambiar_pass": true,
      "ultimo_acceso": "2026-09-19T10:00:00+00:00"
    }
  ]
}
```
`nombre`/`apellidos` van **separados** (tal cual la columna) — no hay un campo `nombre_completo`; si el front necesita mostrarlo junto, lo concatena él.

### `GET /usuarios/{id}`
Igual forma que un elemento del listado, más `sucursales`:
```json
{
  "id": 1, "nombre": "Miguel", "apellidos": "Hilario Bautista Hernandez",
  "numero_empleado": "272026003", "email": "miguel@gmail.com", "telefono": "8119107458",
  "rol_id": 1, "rol": "ADMIN", "activo": true, "debe_cambiar_pass": true, "ultimo_acceso": null,
  "sucursales": [
    { "id": 1, "codigo": "TPHC", "nombre": "Tortilleria El Patrón Huejutla Centro", "es_principal": true }
  ]
}
```

### `POST /usuarios` — alta
**Request**
```json
{
  "password_inicial": "Temporal123",
  "nombre": "Juan",
  "apellidos": "Pérez",
  "email": "juan.perez@example.com",
  "telefono": "8112345678",
  "rol_id": 4,
  "sucursales": [1, 2],
  "sucursal_principal_id": 1
}
```
**Response 201**: misma forma que `GET /usuarios/{id}`.

Reglas clave (no se pueden mandar, se generan/ignoran solos):
- **No** se manda `usuario`/`numero_empleado` — `numero_empleado` se autogenera (`27` + año + consecutivo de 3 dígitos, ej. `272026004`); si lo mandas igual, se ignora.
- `email`: obligatorio, formato válido, único en todo el sistema (es la credencial de login).
- `telefono`: obligatorio, exactamente 10 dígitos (`digits:10`, solo números).
- `password_inicial`: mín. 8 caracteres, letra + número, no puede ser igual al `email`. `debe_cambiar_pass` siempre queda en `true`.
- `sucursales`: array no vacío de IDs válidos; `sucursal_principal_id` debe estar dentro de ese array.

### `PUT /usuarios/{id}` — editar
**Request** (mismos campos que el alta, sin `password_inicial`):
```json
{
  "nombre": "Juan", "apellidos": "Pérez López", "email": "juan.perez@example.com",
  "telefono": "8112345678", "rol_id": 4, "sucursales": [1], "sucursal_principal_id": 1
}
```
**Response 200**: recurso actualizado. **Nunca** acepta `numero_empleado`, `usuario`, `password` ni `activo` — si los mandas, se ignoran silenciosamente (no están en las reglas de validación). Para password usa `POST /auth/cambiar-password` (el propio usuario) — no hay endpoint de "resetear password de otro usuario" todavía. Para `activo` usa los endpoints de abajo.

### `POST /usuarios/{id}/desactivar` / `POST /usuarios/{id}/reactivar`
Sin body. Responden el recurso actualizado (misma forma que `show`). `desactivar` da `422 operacion_invalida` si intentas desactivarte a ti mismo. `reactivar` limpia `intentos_fallidos`/`bloqueado_hasta`.

### `GET /roles` (mismo permiso `usuario.gestionar`)
```json
{
  "roles": [
    {
      "id": 1, "codigo": "ADMIN", "nombre": "Administrador", "descripcion": "Acceso total al sistema",
      "permisos": [{ "codigo": "compra.registrar", "modulo": "INVENTARIO", "nombre": "Capturar compras" }]
    }
  ]
}
```
Solo lectura — los roles y sus permisos no se editan desde la API, vienen sembrados por `RolesPermisosSeeder`.

---

## 4. Catálogos simples — Categorías / Marcas / Unidades / Impuestos

Los cuatro siguen **exactamente el mismo patrón** (permiso `config.modificar` **o** `producto.crear`):

| Recurso | Ruta base | Campos propios |
|---|---|---|
| Categorías | `/categorias` | `nombre` (único), `categoria_padre_id` (nullable, no puede ser ella misma) |
| Marcas | `/marcas` | `nombre` (único) |
| Unidades | `/unidades` | `nombre`, `codigo` (único), `permite_decimales` (bool) |
| Impuestos | `/impuestos` | `codigo` (único), `nombre`, `tasa` (0 ≤ tasa < 1) — **sin `update()`**: la tasa nunca se edita, se crea un impuesto nuevo (RN-M1-05) |

`GET /{recurso}` acepta `?activo=1|0`. `PATCH /{recurso}/{id}/estado` con `{"activo": true|false}` es la baja/reactivación lógica de los cuatro.

Ejemplo completo (`Unidades`, el más rico de los cuatro):

**`POST /unidades`**
```json
{ "nombre": "Pieza", "codigo": "PZA", "permite_decimales": false }
```
**Response 201**
```json
{ "id": 3, "nombre": "Pieza", "codigo": "PZA", "permite_decimales": false, "activo": true, "creado_fecha": "...", "modificado_fecha": "..." }
```

**`PATCH /impuestos/5/estado`**
```json
{ "activo": false }
```
→ `200` con el impuesto actualizado.

---

## 5. Productos — `/productos`

`GET /productos` y `GET /productos/buscar` son de **solo sesión** (cualquier rol logueado puede consultar el catálogo). `store`/`estado` exigen `producto.crear`; `update` exige `producto.crear` **o** `precio.modificar` — pero si el `PUT` en verdad cambia `precio` o `costo_neto`, exige específicamente `precio.modificar` aunque tengas `producto.crear` (chequeo extra dentro del controlador, no solo en la ruta).

### `GET /productos?categoria_id=&marca_id=&activo=&q=&por_pagina=`
```json
{
  "productos": [
    {
      "id": 10, "tipo_prod_serv": "PRODUCTO", "nombre": "Refresco 600ml", "sku": "SKU-0010",
      "codigo_barras": "7501234567890", "descripcion": null, "precio": "18.00", "costo_neto": "12.00",
      "margen_unitario": "6.00", "margen_pct": "33.33", "controla_stock": true, "imagen_url": null, "activo": true,
      "categoria": { "id": 2, "nombre": "Bebidas" },
      "marca": { "id": 1, "nombre": "Coca-Cola" },
      "unidad_medida": { "id": 1, "codigo": "PZA", "permite_decimales": false },
      "impuesto": { "id": 1, "codigo": "IVA16", "tasa": "0.1600" }
    }
  ],
  "total": 1, "pagina": 1, "por_pagina": 20
}
```
`margen_unitario`/`margen_pct` son columnas **generadas** por MySQL (`precio - costo_neto`), no se calculan en PHP.

### `GET /productos/buscar?q=`
Pensado para el punto de venta: intenta SKU exacto → código de barras exacto → nombre por prefijo (en ese orden, se detiene en el primer tipo de match que encuentre algo), solo productos activos, límite 20.
```json
{ "resultados": [{ "id": 10, "sku": "SKU-0010", "nombre": "Refresco 600ml", "precio": "18.00", "impuesto_id": 1, "unidad": "PZA" }], "total": 1 }
```

### `POST /productos`
```json
{
  "tipo_prod_serv": "PRODUCTO",
  "nombre": "Refresco 600ml",
  "sku": "SKU-0010",
  "codigo_barras": "7501234567890",
  "precio": 18.00,
  "costo_neto": 12.00,
  "impuesto_id": 1,
  "id_categoria": 2,
  "id_marca": 1,
  "id_unidad_medida": 1,
  "controla_stock": true
}
```
→ `201` con la misma forma del listado (un solo objeto, sin envoltura de array).

### `PATCH /productos/{id}/estado`
```json
{ "activo": false }
```

### `GET /productos/{id}/historial-precios?por_pagina=`
```json
{
  "historial": [
    { "id": 1, "precio_anterior": "15.00", "precio_nuevo": "18.00", "costo_anterior": "10.00", "costo_nuevo": "12.00", "usuario": "miguel@gmail.com", "fecha": "2026-09-01T10:00:00.000000Z" }
  ],
  "total": 1
}
```

---

## 6. Sucursales y Cajas

### Sucursales — `/sucursales` (permiso `config.modificar`)

**`GET /sucursales?activo=`**
```json
{ "sucursales": [ { "id": 1, "nombre": "...", "codigo": "TPHC", "direccion": "...", "telefono": "...", "email": "...", "logo_url": "http://.../storage/logoempresa/x.png", "numero_exterior": "61", "codigo_postal": "43000", "pais": "Mexico", "estado": "Hidalgo", "zona_frontera": false, "activo": true, "creado_fecha": "...", "modificado_fecha": "..." } ] }
```

**`GET /sucursales/{id}`** — mismo objeto suelto (sin envoltura), usado para precargar la pantalla "Datos de tu Tienda"/Editar antes de un `PUT`.

**`POST /sucursales`**
```json
{ "nombre": "Sucursal Norte", "codigo": "NORTE", "direccion": "Av. Siempre Viva 123", "telefono": "8110000000", "serie": "A01" }
```
`serie` (opcional, default `A01`) provisiona automáticamente los folios `VENTA`/`DEVOL` de esa sucursal en la misma transacción — nunca queda una sucursal operando sin folios.
**Response 201**: el objeto sucursal + `"serie_folios_creada": "A01"`.

**`PUT /sucursales/{id}`** — acepta los mismos campos que el alta (sin `codigo`/`serie`) **más**, si el body es `multipart/form-data`, un campo `logo` (archivo imagen, máx. 3MB):
```
POST/PUT multipart/form-data:
  nombre=Sucursal Norte
  telefono=8110000000
  zona_frontera=false          <- string "false"/"true", el backend lo normaliza
  logo=<archivo binario>
```
El logo se guarda en `storage/app/public/logoempresa/` y se sirve vía una ruta dedicada de Laravel (`/storage/{path}`, disco `public` con `'serve' => true` en `config/filesystems.php`) — **no depende de un symlink del sistema operativo**. `logo_url` en la respuesta siempre es una URL absoluta (`http://host:puerto/storage/logoempresa/archivo.png`).

**`PATCH /sucursales/{id}/estado`**: `{"activo": false}`.

### Cajas — `/cajas` (permiso `config.modificar`)

**`POST /cajas`**
```json
{ "sucursal_id": 1, "codigo": "CAJA-01", "nombre": "Caja principal", "serie_folio": "A01", "identificador_hw": null }
```
`codigo` y `serie_folio` son únicos **por sucursal**, no globalmente — puedes repetir `"CAJA-01"` en dos sucursales distintas. Si la serie no tiene folios provisionados en esa sucursal todavía, se crean aquí también (idempotente).

**`PUT /cajas/{id}`**: solo `nombre`/`identificador_hw` son editables (no se puede reasignar de sucursal ni cambiar código/serie desde aquí).

**`PATCH /cajas/{id}/estado`**: `{"activo": false}`.

---

## 7. Inventario — `/inventario` (solo sesión para lectura; `inventario.ajustar` para escritura)

### `GET /inventario?sucursal_id=&producto_id=&bajo_minimo=1&por_pagina=`
```json
{
  "existencias": [
    { "producto_id": 10, "producto": "Refresco 600ml", "sku": "SKU-0010", "sucursal_id": 1, "sucursal": "Tortilleria El Patrón Huejutla Centro", "stock": "48.000", "stock_minimo": "10.000", "stock_maximo": null, "costo_promedio": "12.00", "ubicacion": null }
  ],
  "total": 1
}
```

### `PUT /inventario/{producto_id}/minimos` (permiso `inventario.ajustar`)
```json
{ "sucursal_id": 1, "stock_minimo": 10, "stock_maximo": 100, "ubicacion": "Pasillo 3" }
```
No es un movimiento de inventario (no toca stock ni genera kardex) — solo configura umbrales; crea la fila de `inventario` si aún no existía para ese producto/sucursal.

### `GET /inventario/alertas?sucursal_id=`
```json
{ "alertas": [ { "producto_id": 10, "sucursal_id": 1, "stock": "5.000", "stock_minimo": "10.000", "faltante": "5.000", "...": "..." } ] }
```
Lee directo la vista SQL `v_stock_bajo` (no es un modelo Eloquent).

### `GET /inventario/{producto_id}/kardex?sucursal_id=&desde=&hasta=&tipo=&por_pagina=`
```json
{
  "movimientos": [
    {
      "id": 101, "sucursal_id": 1, "tipo": "ENTRADA_COMPRA", "cantidad": "10.000", "costo_unitario": "12.00",
      "stock_anterior": "38.000", "stock_nuevo": "48.000", "costo_prom_anterior": "11.50", "costo_prom_nuevo": "12.00",
      "referencia_tabla": "compras", "referencia_id": 5, "usuario": "admin@mila-pos.local", "motivo": null,
      "fecha": "2026-09-19T10:00:00.000000Z"
    }
  ],
  "total": 1, "pagina": 1
}
```
`tipo` puede filtrarse por cualquiera de: `ENTRADA_COMPRA, SALIDA_VENTA, DEVOLUCION_CLIENTE, DEVOLUCION_PROVEEDOR, AJUSTE_POSITIVO, AJUSTE_NEGATIVO, TRASPASO_ENTRADA, TRASPASO_SALIDA, MERMA, CONTEO_FISICO`. Es de **solo lectura** — el kardex es append-only, el único que le escribe es `InventarioService`.

### `POST /inventario/ajustes` (permiso `inventario.ajustar`)
Único endpoint que expone ajustes manuales (positivos/negativos); todo lo demás que toca inventario (compras, y a futuro ventas/devoluciones/traspasos) lo dispara su propio módulo directo contra `InventarioService`.

**Request**
```json
{
  "producto_id": 10,
  "sucursal_id": 1,
  "tipo": "AJUSTE_NEGATIVO",
  "cantidad": 2,
  "motivo": "Merma por caducidad",
  "autorizado_por_id": 3,
  "costo_unitario": null
}
```
Reglas de negocio del cuerpo del endpoint (no solo del validador):
- `producto_id` debe tener `controla_stock=true` (los servicios no generan movimientos) → si no, `422`.
- Si la unidad de medida del producto no permite decimales, `cantidad` debe ser entero → si no, `422`.
- **Segregación de funciones**: `autorizado_por_id` no puede ser el mismo usuario que ejecuta (el de la sesión) → `422`; debe ser un usuario activo con permiso `inventario.ajustar` → `422`.
- Si el ajuste es negativo y dejaría stock < 0 → `409 stock_insuficiente` (lo lanza `InventarioService`, no se llega a insertar nada).

**Response 201**
```json
{
  "movimiento": {
    "id": 102, "tipo": "AJUSTE_NEGATIVO", "cantidad": "2.000", "costo_unitario": "12.00",
    "stock_anterior": "48.000", "stock_nuevo": "46.000", "costo_prom_anterior": "12.00", "costo_prom_nuevo": "12.00",
    "motivo": "Merma por caducidad", "fecha": "2026-09-19T10:05:00.000000Z"
  },
  "ejecutado_por": "admin@mila-pos.local",
  "autorizado_por_id": 3
}
```
El "doble control" (quién autorizó) no vive en `movimientos_inventario` (no tiene esa columna) — queda registrado en la fila de `auditoria` asociada.

---

## 8. Proveedores y Compras (M3)

### Proveedores — `/proveedores` (permiso `compra.registrar` **o** `config.modificar`)

**`POST /proveedores`**
```json
{
  "codigo": "PROV-001", "razon_social": "Distribuidora del Norte SA de CV", "nombre_comercial": "Dist. Norte",
  "rfc": "DNO010203AB1", "contacto": "Juan Perez", "telefono": "5512345678", "email": "compras@distnorte.mx",
  "direccion": "Av. Industria 100, CDMX", "dias_credito": 30
}
```
`rfc` (si se manda) debe tener exactamente 12 o 13 caracteres (persona moral/física), igual que el `CHECK` real de la BD. `codigo` es único.
**Response 201**: el proveedor creado. `PUT /proveedores/{id}` (mismos campos) y `PATCH /proveedores/{id}/estado` (`{"activo": false}`) siguen el patrón estándar.

### Compras — `/compras`

Permisos: `index`/`store`/`update`/`cancelar` exigen `compra.registrar`; `recibir` exige el permiso **separado** `compra.recibir` (alguien puede capturar compras sin poder recibir mercancía, o viceversa); `show` solo exige sesión (cualquier rol puede consultar una compra puntual).

Ciclo de vida: `BORRADOR` → (`recibir`, parcial o total) → `PARCIAL` → `RECIBIDA` | `CANCELADA` (desde `BORRADOR` o `PARCIAL`, nunca desde `RECIBIDA`).

**`POST /compras`** — crea en `BORRADOR`, **no toca inventario todavía**.
```json
{
  "proveedor_id": 1,
  "sucursal_id": 1,
  "folio_documento": "F-0001",
  "fecha_documento": "2026-09-19",
  "detalle": [
    { "producto_id": 10, "cantidad": 10, "costo_unitario": 12.00, "descuento_monto": 0, "tasa_iva": 0.16 }
  ]
}
```
- `folio_documento` es único **por proveedor** (puedes repetir folio entre proveedores distintos).
- El proveedor debe estar `activo=true` → si no, `422`.
- `detalle` no puede venir vacío; cada línea numera su `numero_renglon` solo (1, 2, 3...) y hace un snapshot de `producto.nombre` en `descripcion`.
- `costo_unitario` es el costo **neto** (sin IVA) — es el que después viaja al kardex, nunca el importe con impuesto.

**Response 201**
```json
{
  "id": 5, "folio_documento": "F-0001", "proveedor": "Distribuidora del Norte SA de CV", "sucursal": "Tortilleria El Patrón Huejutla Centro",
  "fecha_documento": "2026-09-19", "estatus": "BORRADOR", "total": "139.20",
  "subtotal": "120.00", "descuento_total": "0.00", "impuesto_total": "19.20",
  "fecha_recepcion": null, "cancelada_motivo": null,
  "detalle": [
    { "id": 11, "numero_renglon": 1, "producto_id": 10, "producto": "Refresco 600ml", "cantidad": "10.000", "cantidad_recibida": "0.000", "costo_unitario": "12.00", "descuento_monto": "0.00", "tasa_iva": "0.1600", "importe_neto": "120.00", "impuesto_monto": "19.20" }
  ]
}
```
`importe_neto`/`impuesto_monto` son columnas **generadas** por MySQL (`cantidad*costo_unitario - descuento`, y ese resultado por `tasa_iva`) — se leen, no se calculan en PHP.

**`PUT /compras/{id}`** — mismos campos que el alta; **solo funciona si `estatus=BORRADOR`** (si no, `409 estado_invalido`). Reemplaza el detalle completo (borra los renglones viejos e inserta los nuevos) y recalcula totales.

**`POST /compras/{id}/recibir`** (permiso `compra.recibir`)
```json
{
  "recepciones": [
    { "detalle_id": 11, "cantidad_recibida": 6 }
  ]
}
```
- Rechaza si `estatus` no es `BORRADOR`/`PARCIAL` → `409 estado_invalido`.
- Rechaza si `cantidad_recibida` excede lo pendiente de ese renglón → `422`.
- Por cada renglón de un producto con `controla_stock=true`, dispara `InventarioService::aplicarMovimiento()` (`tipo=ENTRADA_COMPRA`, `costo_unitario` = el neto del renglón, `referencia_tabla=compras`, `referencia_id={id}`) — eso es lo que mueve stock y aparece en el kardex. Los productos con `controla_stock=false` (servicios) solo actualizan `cantidad_recibida`, sin tocar inventario.
- Si con esta recepción todos los renglones quedan completos → `estatus=RECIBIDA` + `fecha_recepcion=now()`; si no, `estatus=PARCIAL`.

**Response 200**: misma forma que el `POST` original, con `estatus`/`cantidad_recibida` actualizados.

**`POST /compras/{id}/cancelar`** (permiso `compra.registrar`)
```json
{ "motivo": "Proveedor ya no tiene existencias" }
```
Rechaza (`409 estado_invalido`) si ya está `RECIBIDA` o `CANCELADA`. `motivo` es obligatorio (coincide con el `CHECK` real de la BD, que exige `cancelada_por/fecha/motivo` no nulos). **No revierte** lo que ya se haya recibido — cancelar una `PARCIAL` solo cierra el resto pendiente, el stock ya entrado se queda como está.

**`GET /compras?proveedor_id=&estatus=&sucursal_id=&por_pagina=`**
```json
{
  "compras": [
    { "id": 5, "folio_documento": "F-0001", "proveedor": "Distribuidora del Norte SA de CV", "sucursal": "...", "fecha_documento": "2026-09-19", "estatus": "RECIBIDA", "total": "139.20" }
  ],
  "total": 1
}
```
Nota: el listado usa una forma **reducida** (sin `detalle`); `GET /compras/{id}` (show) trae la forma completa con `detalle`.

---

## 9. Subida de archivos (multipart) — notas generales

Aplica al logo de sucursal (único endpoint con upload por ahora, `PUT /sucursales/{id}`):

- El cliente debe mandar el body como `multipart/form-data`, no JSON — no pongas `Content-Type: application/json` manual, deja que el cliente HTTP arme el boundary.
- Los booleanos viajan como texto (`"true"`/`"false"`) en un form-data; el backend los normaliza antes de validar (ver §6). Si agregas un campo booleano nuevo a un endpoint que también reciba archivos, replica ese mismo patrón de normalización o la regla `boolean` de Laravel lo va a rechazar.
- Límite actual del logo: 3MB, tipos imagen estándar (`image` rule de Laravel).
- La URL que regresa el backend (`logo_url`) siempre es absoluta — no hace falta concatenar un `base_url` en el frontend.

---

## 10. Paginación

Los endpoints que devuelven listas grandes (`productos`, `compras`, `inventario` existencias, `kardex`, `historial-precios`) usan el paginador nativo de Laravel por debajo, pero **la respuesta no es la forma default de Laravel** (`{data, links, meta}`) — cada controlador la aplana a su propia forma con al menos `total` y la lista bajo una clave nombrada (`productos`, `compras`, `existencias`, `movimientos`, `historial`). Parámro común: `?por_pagina=20` (default varía por endpoint, casi siempre 20).

Los catálogos simples (categorías, marcas, unidades, impuestos, sucursales, cajas, proveedores, roles) **no paginan** — `index` siempre trae la lista completa (son catálogos chicos por diseño).
