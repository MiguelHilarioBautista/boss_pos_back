-- ============================================================================
-- SISTEMA PUNTO DE VENTA MULTISUCURSAL — mila_pos
-- DDL para MySQL 8.0.16 o superior / InnoDB / utf8mb4
-- ============================================================================
-- REQUISITO CRITICO: verificar la version ANTES de ejecutar.
--   SELECT VERSION();
-- En versiones anteriores a 8.0.16 los CHECK se PARSEAN PERO SE IGNORAN
-- silenciosamente: el esquema se crearia sin ninguna de sus validaciones.
-- ============================================================================
-- ORDEN DE EJECUCION: este archivo respeta las dependencias de claves foraneas.
-- No reordenar bloques.
--
-- NOTA DE ESTE REPOSITORIO: este archivo es la referencia versionada del
-- esquema `mila_pos`. La migracion Laravel que reproduce este DDL vive en
-- database/migrations/2026_02_19_000001_create_mila_pos_schema.php (bloques
-- 1-14, ejecutados via DB::unprepared). Los datos semilla (bloque 15) se
-- implementan como Seeders (database/seeders/), no como parte del DDL. El
-- bloque 16 (usuarios de BD / GRANTs) es [OPS]: se documenta aqui pero no se
-- ejecuta desde la aplicacion.
-- ============================================================================

SET NAMES utf8mb4;
SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION,ERROR_FOR_DIVISION_BY_ZERO';

CREATE DATABASE IF NOT EXISTS mila_pos
  CHARACTER SET utf8mb4;

USE mila_pos;


-- ############################################################################
-- BLOQUE 1: CATALOGOS BASE (sin dependencias)
-- ############################################################################

CREATE TABLE marca_producto (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre            VARCHAR(80)  NOT NULL,
  activo            BOOLEAN      NOT NULL DEFAULT TRUE,
  creado_fecha      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificado_fecha  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_marca_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE categoria_productos (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre             VARCHAR(80)  NOT NULL,
  categoria_padre_id INT UNSIGNED NULL,
  activo             BOOLEAN      NOT NULL DEFAULT TRUE,
  creado_fecha       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificado_fecha   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categoria_nombre (nombre),
  KEY idx_categoria_padre (categoria_padre_id),
  CONSTRAINT fk_categoria_padre FOREIGN KEY (categoria_padre_id)
    REFERENCES categoria_productos (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE unidades_medida (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre            VARCHAR(40)  NOT NULL,
  codigo            VARCHAR(10)  NOT NULL,
  permite_decimales BOOLEAN      NOT NULL DEFAULT FALSE,
  activo            BOOLEAN      NOT NULL DEFAULT TRUE,
  creado_fecha      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificado_fecha  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_unidad_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE impuestos (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo            VARCHAR(20)  NOT NULL,
  nombre            VARCHAR(60)  NOT NULL,
  tasa              DECIMAL(5,4) NOT NULL,
  activo            BOOLEAN      NOT NULL DEFAULT TRUE,
  creado_fecha      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificado_fecha  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_impuesto_codigo (codigo),
  CONSTRAINT ck_impuesto_tasa CHECK (tasa >= 0 AND tasa < 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE sucursales (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre            VARCHAR(100) NOT NULL,
  codigo            VARCHAR(20)  NOT NULL,
  direccion         VARCHAR(255) NULL,
  telefono          VARCHAR(20)  NULL,
  zona_frontera     BOOLEAN      NOT NULL DEFAULT FALSE,
  activo            BOOLEAN      NOT NULL DEFAULT TRUE,
  creado_fecha      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificado_fecha  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sucursal_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE proveedores (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo            VARCHAR(20)  NOT NULL,
  razon_social      VARCHAR(200) NOT NULL,
  nombre_comercial  VARCHAR(150) NULL,
  rfc               VARCHAR(13)  NULL,
  contacto          VARCHAR(100) NULL,
  telefono          VARCHAR(20)  NULL,
  email             VARCHAR(120) NULL,
  direccion         VARCHAR(255) NULL,
  dias_credito      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  activo            BOOLEAN      NOT NULL DEFAULT TRUE,
  creado_fecha      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificado_fecha  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_proveedor_codigo (codigo),
  KEY idx_proveedor_razon (razon_social),
  KEY idx_proveedor_activo (activo),
  CONSTRAINT ck_proveedor_rfc CHECK (rfc IS NULL OR CHAR_LENGTH(rfc) IN (12,13))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE clientes (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo                 VARCHAR(20)  NULL,
  tipo_persona           ENUM('FISICA','MORAL') NOT NULL DEFAULT 'FISICA',
  nombre                 VARCHAR(80)  NOT NULL,
  apellidos              VARCHAR(100) NULL,
  razon_social           VARCHAR(200) NULL,
  telefono               VARCHAR(20)  NULL,
  email                  VARCHAR(120) NULL,
  rfc                    VARCHAR(13)  NULL,
  regimen_fiscal         VARCHAR(3)   NULL,
  uso_cfdi               VARCHAR(3)   NULL,
  codigo_postal          VARCHAR(5)   NULL,
  direccion              VARCHAR(255) NULL,
  limite_credito         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  dias_credito           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  activo                 BOOLEAN      NOT NULL DEFAULT TRUE,
  anonimizado            BOOLEAN      NOT NULL DEFAULT FALSE,
  acepta_promociones     BOOLEAN      NOT NULL DEFAULT FALSE,
  aviso_privacidad_fecha DATETIME     NULL,
  creado_fecha           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificado_fecha       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cliente_codigo (codigo),
  KEY idx_cliente_nombre (apellidos, nombre),
  KEY idx_cliente_telefono (telefono),
  KEY idx_cliente_rfc (rfc),
  KEY idx_cliente_activo (activo),
  CONSTRAINT ck_cliente_rfc CHECK (rfc IS NULL OR CHAR_LENGTH(rfc) IN (12,13)),
  CONSTRAINT ck_cliente_moral CHECK (tipo_persona = 'FISICA' OR razon_social IS NOT NULL),
  CONSTRAINT ck_cliente_credito CHECK (limite_credito >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE metodos_pago (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo               VARCHAR(20)  NOT NULL,
  nombre               VARCHAR(60)  NOT NULL,
  tipo                 ENUM('EFECTIVO','TARJETA','TRANSFERENCIA','VALE','MONEDERO','CREDITO','OTRO') NOT NULL,
  afecta_efectivo_caja BOOLEAN      NOT NULL DEFAULT FALSE,
  permite_cambio       BOOLEAN      NOT NULL DEFAULT FALSE,
  requiere_referencia  BOOLEAN      NOT NULL DEFAULT FALSE,
  requiere_cliente     BOOLEAN      NOT NULL DEFAULT FALSE,
  genera_cxc           BOOLEAN      NOT NULL DEFAULT FALSE,
  comision_pct         DECIMAL(6,4) NOT NULL DEFAULT 0.0000,
  activo               BOOLEAN      NOT NULL DEFAULT TRUE,
  orden                SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  creado_fecha         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificado_fecha     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_metodo_codigo (codigo),
  KEY idx_metodo_activo (activo, orden),
  CONSTRAINT ck_metodo_comision CHECK (comision_pct >= 0 AND comision_pct < 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


-- ############################################################################
-- BLOQUE 2: SEGURIDAD Y ACCESO
-- ############################################################################

CREATE TABLE roles (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo       VARCHAR(30)  NOT NULL,
  nombre       VARCHAR(60)  NOT NULL,
  descripcion  VARCHAR(200) NULL,
  activo       BOOLEAN      NOT NULL DEFAULT TRUE,
  creado_fecha DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rol_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE permisos (
  id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(50)  NOT NULL,
  modulo VARCHAR(30)  NOT NULL,
  nombre VARCHAR(80)  NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_permiso_codigo (codigo),
  KEY idx_permiso_modulo (modulo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE rol_permiso (
  rol_id     INT UNSIGNED NOT NULL,
  permiso_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (rol_id, permiso_id),
  KEY idx_rp_permiso (permiso_id),
  CONSTRAINT fk_rp_rol     FOREIGN KEY (rol_id)     REFERENCES roles (id)    ON DELETE CASCADE,
  CONSTRAINT fk_rp_permiso FOREIGN KEY (permiso_id) REFERENCES permisos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE usuarios (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  rol_id               INT UNSIGNED NOT NULL,
  usuario              VARCHAR(50)  NOT NULL,
  password_hash        VARCHAR(255) NOT NULL,
  pin_hash             VARCHAR(255) NULL,
  nombre               VARCHAR(60)  NOT NULL,
  apellidos            VARCHAR(80)  NOT NULL,
  numero_empleado      VARCHAR(20)  NULL,
  email                VARCHAR(120) NULL,
  telefono             VARCHAR(20)  NULL,
  activo               BOOLEAN      NOT NULL DEFAULT TRUE,
  debe_cambiar_pass    BOOLEAN      NOT NULL DEFAULT TRUE,
  intentos_fallidos    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta      DATETIME     NULL,
  ultimo_acceso        DATETIME     NULL,
  password_actualizado DATETIME     NULL,
  creado_fecha         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificado_fecha     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuario_login (usuario),
  UNIQUE KEY uq_usuario_numemp (numero_empleado),
  KEY idx_usuario_activo (activo),
  KEY idx_usuario_rol (rol_id),
  CONSTRAINT fk_usuario_rol FOREIGN KEY (rol_id) REFERENCES roles (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE usuario_sucursal (
  usuario_id   INT UNSIGNED NOT NULL,
  sucursal_id  INT UNSIGNED NOT NULL,
  es_principal BOOLEAN      NOT NULL DEFAULT FALSE,
  PRIMARY KEY (usuario_id, sucursal_id),
  KEY idx_us_sucursal (sucursal_id),
  CONSTRAINT fk_us_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios (id)   ON DELETE RESTRICT,
  CONSTRAINT fk_us_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


-- ############################################################################
-- BLOQUE 3: PRODUCTOS E INVENTARIO
-- ############################################################################

CREATE TABLE productos (
  id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  tipo_prod_serv    ENUM('PRODUCTO','SERVICIO') NOT NULL DEFAULT 'PRODUCTO',
  nombre            VARCHAR(150)  NOT NULL,
  sku               VARCHAR(50)   NOT NULL,
  codigo_barras     VARCHAR(50)   NULL,
  descripcion       VARCHAR(255)  NULL,
  precio            DECIMAL(12,2) NOT NULL,
  costo_neto        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  impuesto_id       INT UNSIGNED  NOT NULL,
  margen_unitario   DECIMAL(12,2) GENERATED ALWAYS AS (precio - costo_neto) VIRTUAL,
  margen_pct        DECIMAL(7,4)  GENERATED ALWAYS AS
                      (CASE WHEN precio > 0 THEN (precio - costo_neto) / precio ELSE NULL END) VIRTUAL,
  controla_stock    BOOLEAN       NOT NULL DEFAULT TRUE,
  imagen_url        VARCHAR(255)  NULL,
  activo            BOOLEAN       NOT NULL DEFAULT TRUE,
  id_categoria      INT UNSIGNED  NOT NULL,
  id_marca          INT UNSIGNED  NULL,
  id_unidad_medida  INT UNSIGNED  NOT NULL,
  creado_fecha      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificado_fecha  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_producto_sku (sku),
  UNIQUE KEY uq_producto_barras (codigo_barras),
  KEY idx_producto_nombre (nombre),
  KEY idx_producto_categoria (id_categoria),
  KEY idx_producto_marca (id_marca),
  KEY idx_producto_unidad (id_unidad_medida),
  KEY idx_producto_impuesto (impuesto_id),
  KEY idx_producto_activo_nombre (activo, nombre),
  CONSTRAINT fk_producto_categoria FOREIGN KEY (id_categoria)     REFERENCES categoria_productos (id) ON DELETE RESTRICT,
  CONSTRAINT fk_producto_marca     FOREIGN KEY (id_marca)         REFERENCES marca_producto (id)      ON DELETE RESTRICT,
  CONSTRAINT fk_producto_unidad    FOREIGN KEY (id_unidad_medida) REFERENCES unidades_medida (id)     ON DELETE RESTRICT,
  CONSTRAINT fk_producto_impuesto  FOREIGN KEY (impuesto_id)      REFERENCES impuestos (id)           ON DELETE RESTRICT,
  CONSTRAINT ck_producto_precio CHECK (precio >= 0 AND costo_neto >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE historial_precios (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  producto_id     INT UNSIGNED    NOT NULL,
  precio_anterior DECIMAL(12,2)   NOT NULL,
  precio_nuevo    DECIMAL(12,2)   NOT NULL,
  costo_anterior  DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  costo_nuevo     DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  motivo          VARCHAR(200)    NULL,
  usuario_id      INT UNSIGNED    NULL,
  fecha           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_histprecio_producto (producto_id, fecha),
  KEY idx_histprecio_usuario (usuario_id, fecha),
  CONSTRAINT fk_histprecio_producto FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE RESTRICT,
  CONSTRAINT fk_histprecio_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios (id)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE inventario (
  producto_id      INT UNSIGNED  NOT NULL,
  sucursal_id      INT UNSIGNED  NOT NULL,
  stock            DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  stock_minimo     DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  stock_maximo     DECIMAL(12,3) NULL,
  ubicacion        VARCHAR(50)   NULL,
  costo_promedio   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  modificado_fecha DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (producto_id, sucursal_id),
  KEY idx_inventario_sucursal (sucursal_id),
  KEY idx_inventario_suc_stock (sucursal_id, stock),
  CONSTRAINT fk_inventario_producto FOREIGN KEY (producto_id) REFERENCES productos (id)  ON DELETE RESTRICT,
  CONSTRAINT fk_inventario_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales (id) ON DELETE RESTRICT,
  CONSTRAINT ck_inventario_stock CHECK (stock >= 0 AND stock_minimo >= 0 AND costo_promedio >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE movimientos_inventario (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  producto_id         INT UNSIGNED    NOT NULL,
  sucursal_id         INT UNSIGNED    NOT NULL,
  tipo                ENUM('ENTRADA_COMPRA','SALIDA_VENTA','DEVOLUCION_CLIENTE','DEVOLUCION_PROVEEDOR',
                           'AJUSTE_POSITIVO','AJUSTE_NEGATIVO','TRASPASO_ENTRADA','TRASPASO_SALIDA',
                           'MERMA','CONTEO_FISICO') NOT NULL,
  cantidad            DECIMAL(12,3)   NOT NULL,
  costo_unitario      DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  stock_anterior      DECIMAL(12,3)   NOT NULL,
  stock_nuevo         DECIMAL(12,3)   NOT NULL,
  costo_prom_anterior DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  costo_prom_nuevo    DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  referencia_tabla    VARCHAR(40)     NULL,
  referencia_id       BIGINT UNSIGNED NULL,
  usuario_id          INT UNSIGNED    NOT NULL,
  motivo              VARCHAR(200)    NULL,
  fecha               DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_movinv_prod_suc_fecha (producto_id, sucursal_id, fecha),
  KEY idx_movinv_suc_fecha (sucursal_id, fecha),
  KEY idx_movinv_tipo_fecha (tipo, fecha),
  KEY idx_movinv_referencia (referencia_tabla, referencia_id),
  KEY idx_movinv_usuario (usuario_id),
  CONSTRAINT fk_movinv_producto FOREIGN KEY (producto_id) REFERENCES productos (id)  ON DELETE RESTRICT,
  CONSTRAINT fk_movinv_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales (id) ON DELETE RESTRICT,
  CONSTRAINT fk_movinv_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios (id)   ON DELETE RESTRICT,
  CONSTRAINT ck_movinv_cantidad CHECK (cantidad > 0),
  CONSTRAINT ck_movinv_costo    CHECK (costo_unitario >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


-- ############################################################################
-- BLOQUE 4: CAJAS, FOLIOS Y CORTES
-- ############################################################################

CREATE TABLE cajas (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sucursal_id      INT UNSIGNED NOT NULL,
  codigo           VARCHAR(20)  NOT NULL,
  nombre           VARCHAR(60)  NOT NULL,
  serie_folio      CHAR(3)      NOT NULL,
  identificador_hw VARCHAR(80)  NULL,
  activo           BOOLEAN      NOT NULL DEFAULT TRUE,
  creado_fecha     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificado_fecha DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_caja_suc_codigo (sucursal_id, codigo),
  UNIQUE KEY uq_caja_suc_serie (sucursal_id, serie_folio),
  KEY idx_caja_suc_activo (sucursal_id, activo),
  CONSTRAINT fk_caja_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE folios (
  sucursal_id  INT UNSIGNED    NOT NULL,
  serie        CHAR(3)         NOT NULL,
  tipo_doc     VARCHAR(10)     NOT NULL DEFAULT 'VENTA',
  ultimo_folio BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (sucursal_id, serie, tipo_doc),
  CONSTRAINT fk_folio_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE cortes_caja (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sucursal_id         INT UNSIGNED    NOT NULL,
  caja_id             INT UNSIGNED    NOT NULL,
  usuario_apertura_id INT UNSIGNED    NOT NULL,
  usuario_cierre_id   INT UNSIGNED    NULL,
  autorizado_por_id   INT UNSIGNED    NULL,
  fecha_apertura      DATETIME        NOT NULL,
  fecha_cierre        DATETIME        NULL,
  fondo_inicial       DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  efectivo_esperado   DECIMAL(14,2)   NULL,
  efectivo_contado    DECIMAL(14,2)   NULL,
  diferencia          DECIMAL(14,2)   GENERATED ALWAYS AS (efectivo_contado - efectivo_esperado) STORED,
  total_ventas        DECIMAL(14,2)   NULL,
  num_tickets         INT UNSIGNED    NULL,
  total_cancelaciones DECIMAL(14,2)   NULL,
  total_devoluciones  DECIMAL(14,2)   NULL,
  total_retiros       DECIMAL(14,2)   NULL,
  total_ingresos      DECIMAL(14,2)   NULL,
  estatus             ENUM('ABIERTO','CERRADO','AUDITADO') NOT NULL DEFAULT 'ABIERTO',
  -- Truco MySQL: emula un indice unico parcial. Vale caja_id mientras el corte
  -- esta ABIERTO y NULL al cerrarse; los NULL no colisionan en un UNIQUE.
  caja_abierta_id     INT UNSIGNED    GENERATED ALWAYS AS
                        (IF(estatus = 'ABIERTO', caja_id, NULL)) VIRTUAL,
  observaciones       VARCHAR(255)    NULL,
  creado_fecha        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificado_fecha    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_corte_caja_abierta (caja_abierta_id),
  KEY idx_corte_caja_fecha (caja_id, fecha_apertura),
  KEY idx_corte_suc_fecha (sucursal_id, fecha_apertura),
  KEY idx_corte_estatus (estatus),
  KEY idx_corte_uapert (usuario_apertura_id),
  KEY idx_corte_ucierre (usuario_cierre_id),
  KEY idx_corte_uautor (autorizado_por_id),
  CONSTRAINT fk_corte_sucursal FOREIGN KEY (sucursal_id)         REFERENCES sucursales (id) ON DELETE RESTRICT,
  CONSTRAINT fk_corte_caja     FOREIGN KEY (caja_id)             REFERENCES cajas (id)      ON DELETE RESTRICT,
  CONSTRAINT fk_corte_uapert   FOREIGN KEY (usuario_apertura_id) REFERENCES usuarios (id)   ON DELETE RESTRICT,
  CONSTRAINT fk_corte_ucierre  FOREIGN KEY (usuario_cierre_id)   REFERENCES usuarios (id)   ON DELETE RESTRICT,
  CONSTRAINT fk_corte_uautor   FOREIGN KEY (autorizado_por_id)   REFERENCES usuarios (id)   ON DELETE RESTRICT,
  CONSTRAINT ck_corte_fondo  CHECK (fondo_inicial >= 0),
  CONSTRAINT ck_corte_fechas CHECK (fecha_cierre IS NULL OR fecha_cierre >= fecha_apertura),
  CONSTRAINT ck_corte_cierre CHECK (
    estatus = 'ABIERTO'
    OR (fecha_cierre IS NOT NULL AND usuario_cierre_id IS NOT NULL
        AND efectivo_esperado IS NOT NULL AND efectivo_contado IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE movimientos_caja (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  corte_caja_id     BIGINT UNSIGNED NOT NULL,
  tipo              ENUM('RETIRO','INGRESO','FONDO','GASTO') NOT NULL,
  monto             DECIMAL(14,2)   NOT NULL,
  concepto          VARCHAR(150)    NOT NULL,
  usuario_id        INT UNSIGNED    NOT NULL,
  autorizado_por_id INT UNSIGNED    NULL,
  fecha             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_movcaja_corte (corte_caja_id, tipo),
  KEY idx_movcaja_usuario (usuario_id),
  KEY idx_movcaja_autor (autorizado_por_id),
  CONSTRAINT fk_movcaja_corte FOREIGN KEY (corte_caja_id)     REFERENCES cortes_caja (id) ON DELETE RESTRICT,
  CONSTRAINT fk_movcaja_user  FOREIGN KEY (usuario_id)        REFERENCES usuarios (id)    ON DELETE RESTRICT,
  CONSTRAINT fk_movcaja_autor FOREIGN KEY (autorizado_por_id) REFERENCES usuarios (id)    ON DELETE RESTRICT,
  CONSTRAINT ck_movcaja_monto CHECK (monto > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


-- ############################################################################
-- BLOQUE 5: PROMOCIONES
-- ############################################################################

CREATE TABLE promociones (
  id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  codigo             VARCHAR(30)   NOT NULL,
  nombre             VARCHAR(100)  NOT NULL,
  tipo               ENUM('PORCENTAJE','MONTO_FIJO','PRECIO_FIJO') NOT NULL,
  valor              DECIMAL(12,4) NOT NULL,
  prioridad          SMALLINT      NOT NULL DEFAULT 0,
  acumulable         BOOLEAN       NOT NULL DEFAULT FALSE,
  permite_bajo_costo BOOLEAN       NOT NULL DEFAULT FALSE,
  vigencia_ini       DATETIME      NOT NULL,
  vigencia_fin       DATETIME      NOT NULL,
  activo             BOOLEAN       NOT NULL DEFAULT TRUE,
  creado_por         INT UNSIGNED  NOT NULL,
  creado_fecha       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificado_fecha   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_promocion_codigo (codigo),
  KEY idx_promocion_vigencia (activo, vigencia_ini, vigencia_fin),
  KEY idx_promocion_creador (creado_por),
  CONSTRAINT fk_promocion_usuario FOREIGN KEY (creado_por) REFERENCES usuarios (id) ON DELETE RESTRICT,
  CONSTRAINT ck_promocion_valor    CHECK (valor >= 0),
  CONSTRAINT ck_promocion_vigencia CHECK (vigencia_fin > vigencia_ini)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE promocion_alcance (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  promocion_id INT UNSIGNED NOT NULL,
  producto_id  INT UNSIGNED NULL,
  categoria_id INT UNSIGNED NULL,
  sucursal_id  INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_promoalc_promocion (promocion_id),
  KEY idx_promoalc_producto (producto_id),
  KEY idx_promoalc_categoria (categoria_id),
  KEY idx_promoalc_sucursal (sucursal_id),
  CONSTRAINT fk_promoalc_promocion FOREIGN KEY (promocion_id) REFERENCES promociones (id)         ON DELETE CASCADE,
  CONSTRAINT fk_promoalc_producto  FOREIGN KEY (producto_id)  REFERENCES productos (id)           ON DELETE RESTRICT,
  CONSTRAINT fk_promoalc_categoria FOREIGN KEY (categoria_id) REFERENCES categoria_productos (id) ON DELETE RESTRICT,
  CONSTRAINT fk_promoalc_sucursal  FOREIGN KEY (sucursal_id)  REFERENCES sucursales (id)          ON DELETE RESTRICT,
  CONSTRAINT ck_promoalc_scope CHECK (producto_id IS NOT NULL OR categoria_id IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


-- ############################################################################
-- BLOQUE 6: VENTAS
-- ############################################################################

CREATE TABLE ventas (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sucursal_id      INT UNSIGNED    NOT NULL,
  caja_id          INT UNSIGNED    NOT NULL,
  corte_caja_id    BIGINT UNSIGNED NOT NULL,
  usuario_id       INT UNSIGNED    NOT NULL,
  cliente_id       INT UNSIGNED    NULL,
  serie            CHAR(3)         NOT NULL,
  folio            BIGINT UNSIGNED NOT NULL,
  fecha            DATETIME        NOT NULL,
  subtotal         DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  descuento_total  DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  impuesto_total   DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  total            DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  costo_total      DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  utilidad_total   DECIMAL(14,2)   GENERATED ALWAYS AS
                     (total - impuesto_total - costo_total) STORED,
  estatus          ENUM('COMPLETADA','CANCELADA','DEVUELTA_PARCIAL','DEVUELTA_TOTAL')
                     NOT NULL DEFAULT 'COMPLETADA',
  cancelada_por    INT UNSIGNED    NULL,
  cancelada_fecha  DATETIME        NULL,
  cancelada_motivo VARCHAR(255)    NULL,
  uuid_fiscal      CHAR(36)        NULL,
  notas            VARCHAR(255)    NULL,
  creado_fecha     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificado_fecha DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_venta_folio (sucursal_id, serie, folio),
  KEY idx_venta_fecha (fecha),
  KEY idx_venta_suc_fecha (sucursal_id, fecha),
  KEY idx_venta_usuario_fecha (usuario_id, fecha),
  KEY idx_venta_cliente_fecha (cliente_id, fecha),
  KEY idx_venta_corte (corte_caja_id),
  KEY idx_venta_estatus_fecha (estatus, fecha),
  KEY idx_venta_caja (caja_id),
  KEY idx_venta_cancelo (cancelada_por),
  CONSTRAINT fk_venta_sucursal FOREIGN KEY (sucursal_id)   REFERENCES sucursales (id)  ON DELETE RESTRICT,
  CONSTRAINT fk_venta_caja     FOREIGN KEY (caja_id)       REFERENCES cajas (id)       ON DELETE RESTRICT,
  CONSTRAINT fk_venta_corte    FOREIGN KEY (corte_caja_id) REFERENCES cortes_caja (id) ON DELETE RESTRICT,
  CONSTRAINT fk_venta_usuario  FOREIGN KEY (usuario_id)    REFERENCES usuarios (id)    ON DELETE RESTRICT,
  CONSTRAINT fk_venta_cliente  FOREIGN KEY (cliente_id)    REFERENCES clientes (id)    ON DELETE RESTRICT,
  CONSTRAINT fk_venta_cancelo  FOREIGN KEY (cancelada_por) REFERENCES usuarios (id)    ON DELETE RESTRICT,
  CONSTRAINT ck_venta_montos CHECK (
    subtotal >= 0 AND descuento_total >= 0 AND impuesto_total >= 0
    AND total >= 0 AND costo_total >= 0
  ),
  CONSTRAINT ck_venta_cancel CHECK (
    estatus <> 'CANCELADA'
    OR (cancelada_por IS NOT NULL AND cancelada_fecha IS NOT NULL AND cancelada_motivo IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE detalle_venta (
  id                BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  venta_id          BIGINT UNSIGNED   NOT NULL,
  producto_id       INT UNSIGNED      NOT NULL,
  numero_renglon    SMALLINT UNSIGNED NOT NULL,
  descripcion       VARCHAR(150)      NOT NULL,
  sku               VARCHAR(50)       NOT NULL,
  unidad_medida     VARCHAR(20)       NOT NULL,
  cantidad          DECIMAL(12,3)     NOT NULL,
  precio_unitario   DECIMAL(12,2)     NOT NULL,
  costo_unitario    DECIMAL(12,2)     NOT NULL,
  descuento_monto   DECIMAL(14,2)     NOT NULL DEFAULT 0.00,
  promocion_id      INT UNSIGNED      NULL,
  tasa_iva          DECIMAL(5,4)      NOT NULL DEFAULT 0.0000,
  importe_bruto     DECIMAL(14,2) GENERATED ALWAYS AS
                      (ROUND(cantidad * precio_unitario, 2)) STORED,
  importe_neto      DECIMAL(14,2) GENERATED ALWAYS AS
                      (ROUND(cantidad * precio_unitario - descuento_monto, 2)) STORED,
  impuesto_monto    DECIMAL(14,2) GENERATED ALWAYS AS
                      (ROUND((cantidad * precio_unitario - descuento_monto) * tasa_iva, 2)) STORED,
  costo_renglon     DECIMAL(14,2) GENERATED ALWAYS AS
                      (ROUND(cantidad * costo_unitario, 2)) STORED,
  utilidad_renglon  DECIMAL(14,2) GENERATED ALWAYS AS
                      (ROUND(cantidad * precio_unitario - descuento_monto - cantidad * costo_unitario, 2)) STORED,
  cantidad_devuelta DECIMAL(12,3)     NOT NULL DEFAULT 0.000,
  PRIMARY KEY (id),
  UNIQUE KEY uq_detalle_renglon (venta_id, numero_renglon),
  KEY idx_detalle_venta (venta_id),
  KEY idx_detalle_producto (producto_id, venta_id),
  KEY idx_detalle_promocion (promocion_id),
  CONSTRAINT fk_detalle_venta     FOREIGN KEY (venta_id)     REFERENCES ventas (id)      ON DELETE RESTRICT,
  CONSTRAINT fk_detalle_producto  FOREIGN KEY (producto_id)  REFERENCES productos (id)   ON DELETE RESTRICT,
  CONSTRAINT fk_detalle_promocion FOREIGN KEY (promocion_id) REFERENCES promociones (id) ON DELETE RESTRICT,
  CONSTRAINT ck_detalle_cantidad  CHECK (cantidad > 0),
  CONSTRAINT ck_detalle_montos    CHECK (
    precio_unitario >= 0 AND costo_unitario >= 0 AND descuento_monto >= 0
    AND tasa_iva >= 0 AND tasa_iva < 1
  ),
  CONSTRAINT ck_detalle_descuento CHECK (descuento_monto <= cantidad * precio_unitario),
  CONSTRAINT ck_detalle_devuelta  CHECK (cantidad_devuelta >= 0 AND cantidad_devuelta <= cantidad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE venta_pagos (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  venta_id       BIGINT UNSIGNED NOT NULL,
  metodo_pago_id INT UNSIGNED    NOT NULL,
  monto          DECIMAL(14,2)   NOT NULL,
  recibido       DECIMAL(14,2)   NULL,
  cambio         DECIMAL(14,2)   NULL,
  referencia     VARCHAR(60)     NULL,
  comision_pct   DECIMAL(6,4)    NOT NULL DEFAULT 0.0000,
  comision_monto DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  creado_fecha   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pago_venta (venta_id),
  KEY idx_pago_metodo (metodo_pago_id),
  CONSTRAINT fk_pago_venta  FOREIGN KEY (venta_id)       REFERENCES ventas (id)       ON DELETE RESTRICT,
  CONSTRAINT fk_pago_metodo FOREIGN KEY (metodo_pago_id) REFERENCES metodos_pago (id) ON DELETE RESTRICT,
  CONSTRAINT ck_pago_monto  CHECK (monto > 0),
  CONSTRAINT ck_pago_cambio CHECK (cambio IS NULL OR cambio >= 0),
  CONSTRAINT ck_pago_comision CHECK (comision_pct >= 0 AND comision_pct < 1 AND comision_monto >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE corte_caja_metodos (
  corte_caja_id  BIGINT UNSIGNED NOT NULL,
  metodo_pago_id INT UNSIGNED    NOT NULL,
  monto_esperado DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  monto_contado  DECIMAL(14,2)   NULL,
  diferencia     DECIMAL(14,2)   GENERATED ALWAYS AS (monto_contado - monto_esperado) STORED,
  PRIMARY KEY (corte_caja_id, metodo_pago_id),
  KEY idx_ccm_metodo (metodo_pago_id),
  CONSTRAINT fk_ccm_corte  FOREIGN KEY (corte_caja_id)  REFERENCES cortes_caja (id)  ON DELETE RESTRICT,
  CONSTRAINT fk_ccm_metodo FOREIGN KEY (metodo_pago_id) REFERENCES metodos_pago (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


-- ############################################################################
-- BLOQUE 7: COMPRAS (origen del costo)
-- ############################################################################

CREATE TABLE compras (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sucursal_id       INT UNSIGNED    NOT NULL,
  proveedor_id      INT UNSIGNED    NOT NULL,
  usuario_id        INT UNSIGNED    NOT NULL,
  folio_documento   VARCHAR(40)     NOT NULL,
  fecha_documento   DATE            NOT NULL,
  fecha_recepcion   DATETIME        NULL,
  subtotal          DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  descuento_total   DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  impuesto_total    DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  total             DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  estatus           ENUM('BORRADOR','PARCIAL','RECIBIDA','CANCELADA') NOT NULL DEFAULT 'BORRADOR',
  es_credito        BOOLEAN         NOT NULL DEFAULT FALSE,
  fecha_vencimiento DATE            NULL,
  notas             VARCHAR(255)    NULL,
  cancelada_por     INT UNSIGNED    NULL,
  cancelada_fecha   DATETIME        NULL,
  cancelada_motivo  VARCHAR(255)    NULL,
  creado_fecha      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificado_fecha  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_compra_proveedor_folio (proveedor_id, folio_documento),
  KEY idx_compra_suc_fecha (sucursal_id, fecha_documento),
  KEY idx_compra_estatus_fecha (estatus, fecha_documento),
  KEY idx_compra_usuario (usuario_id),
  KEY idx_compra_cancelo (cancelada_por),
  CONSTRAINT fk_compra_sucursal  FOREIGN KEY (sucursal_id)   REFERENCES sucursales (id)  ON DELETE RESTRICT,
  CONSTRAINT fk_compra_proveedor FOREIGN KEY (proveedor_id)  REFERENCES proveedores (id) ON DELETE RESTRICT,
  CONSTRAINT fk_compra_usuario   FOREIGN KEY (usuario_id)    REFERENCES usuarios (id)    ON DELETE RESTRICT,
  CONSTRAINT fk_compra_cancelo   FOREIGN KEY (cancelada_por) REFERENCES usuarios (id)    ON DELETE RESTRICT,
  CONSTRAINT ck_compra_montos CHECK (subtotal >= 0 AND total >= 0 AND impuesto_total >= 0),
  CONSTRAINT ck_compra_vence  CHECK (fecha_vencimiento IS NULL OR fecha_vencimiento >= fecha_documento),
  CONSTRAINT ck_compra_cancel CHECK (
    estatus <> 'CANCELADA'
    OR (cancelada_por IS NOT NULL AND cancelada_fecha IS NOT NULL AND cancelada_motivo IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE detalle_compra (
  id                BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  compra_id         BIGINT UNSIGNED   NOT NULL,
  producto_id       INT UNSIGNED      NOT NULL,
  numero_renglon    SMALLINT UNSIGNED NOT NULL,
  descripcion       VARCHAR(150)      NOT NULL,
  cantidad          DECIMAL(12,3)     NOT NULL,
  cantidad_recibida DECIMAL(12,3)     NOT NULL DEFAULT 0.000,
  costo_unitario    DECIMAL(12,2)     NOT NULL,
  descuento_monto   DECIMAL(14,2)     NOT NULL DEFAULT 0.00,
  tasa_iva          DECIMAL(5,4)      NOT NULL DEFAULT 0.0000,
  importe_neto      DECIMAL(14,2) GENERATED ALWAYS AS
                      (ROUND(cantidad * costo_unitario - descuento_monto, 2)) STORED,
  impuesto_monto    DECIMAL(14,2) GENERATED ALWAYS AS
                      (ROUND((cantidad * costo_unitario - descuento_monto) * tasa_iva, 2)) STORED,
  PRIMARY KEY (id),
  UNIQUE KEY uq_detcompra_renglon (compra_id, numero_renglon),
  KEY idx_detcompra_compra (compra_id),
  KEY idx_detcompra_producto (producto_id, compra_id),
  CONSTRAINT fk_detcompra_compra   FOREIGN KEY (compra_id)   REFERENCES compras (id)   ON DELETE RESTRICT,
  CONSTRAINT fk_detcompra_producto FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE RESTRICT,
  CONSTRAINT ck_detcompra_cantidad CHECK (cantidad > 0 AND cantidad_recibida >= 0 AND cantidad_recibida <= cantidad),
  CONSTRAINT ck_detcompra_costo    CHECK (costo_unitario >= 0 AND descuento_monto >= 0),
  CONSTRAINT ck_detcompra_iva      CHECK (tasa_iva >= 0 AND tasa_iva < 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


-- ############################################################################
-- BLOQUE 8: DEVOLUCIONES DE CLIENTE
-- ############################################################################

CREATE TABLE devoluciones (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  venta_id          BIGINT UNSIGNED NOT NULL,
  sucursal_id       INT UNSIGNED    NOT NULL,
  caja_id           INT UNSIGNED    NOT NULL,
  corte_caja_id     BIGINT UNSIGNED NOT NULL,
  usuario_id        INT UNSIGNED    NOT NULL,
  autorizado_por_id INT UNSIGNED    NOT NULL,
  cliente_id        INT UNSIGNED    NULL,
  serie             CHAR(3)         NOT NULL,
  folio             BIGINT UNSIGNED NOT NULL,
  fecha             DATETIME        NOT NULL,
  motivo            ENUM('DEFECTUOSO','ERROR_VENTA','INSATISFACCION','GARANTIA','OTRO') NOT NULL,
  subtotal          DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  impuesto_total    DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  total             DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  costo_total       DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  estatus           ENUM('PROCESADA','CANCELADA') NOT NULL DEFAULT 'PROCESADA',
  notas             VARCHAR(255)    NULL,
  creado_fecha      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_devolucion_folio (sucursal_id, serie, folio),
  KEY idx_devolucion_venta (venta_id),
  KEY idx_devolucion_suc_fecha (sucursal_id, fecha),
  KEY idx_devolucion_corte (corte_caja_id),
  KEY idx_devolucion_caja (caja_id),
  KEY idx_devolucion_usuario (usuario_id),
  KEY idx_devolucion_autor (autorizado_por_id),
  KEY idx_devolucion_cliente (cliente_id),
  CONSTRAINT fk_devolucion_venta   FOREIGN KEY (venta_id)          REFERENCES ventas (id)      ON DELETE RESTRICT,
  CONSTRAINT fk_devolucion_suc     FOREIGN KEY (sucursal_id)       REFERENCES sucursales (id)  ON DELETE RESTRICT,
  CONSTRAINT fk_devolucion_caja    FOREIGN KEY (caja_id)           REFERENCES cajas (id)       ON DELETE RESTRICT,
  CONSTRAINT fk_devolucion_corte   FOREIGN KEY (corte_caja_id)     REFERENCES cortes_caja (id) ON DELETE RESTRICT,
  CONSTRAINT fk_devolucion_usuario FOREIGN KEY (usuario_id)        REFERENCES usuarios (id)    ON DELETE RESTRICT,
  CONSTRAINT fk_devolucion_autor   FOREIGN KEY (autorizado_por_id) REFERENCES usuarios (id)    ON DELETE RESTRICT,
  CONSTRAINT fk_devolucion_cliente FOREIGN KEY (cliente_id)        REFERENCES clientes (id)    ON DELETE RESTRICT,
  CONSTRAINT ck_devolucion_montos CHECK (subtotal >= 0 AND total >= 0 AND costo_total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE detalle_devolucion (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  devolucion_id    BIGINT UNSIGNED NOT NULL,
  detalle_venta_id BIGINT UNSIGNED NOT NULL,
  producto_id      INT UNSIGNED    NOT NULL,
  cantidad         DECIMAL(12,3)   NOT NULL,
  precio_unitario  DECIMAL(12,2)   NOT NULL,
  costo_unitario   DECIMAL(12,2)   NOT NULL,
  tasa_iva         DECIMAL(5,4)    NOT NULL DEFAULT 0.0000,
  importe_neto     DECIMAL(14,2) GENERATED ALWAYS AS
                     (ROUND(cantidad * precio_unitario, 2)) STORED,
  destino          ENUM('INVENTARIO','MERMA') NOT NULL DEFAULT 'INVENTARIO',
  PRIMARY KEY (id),
  KEY idx_detdev_devolucion (devolucion_id),
  KEY idx_detdev_detventa (detalle_venta_id),
  KEY idx_detdev_producto (producto_id),
  CONSTRAINT fk_detdev_devolucion FOREIGN KEY (devolucion_id)    REFERENCES devoluciones (id)  ON DELETE RESTRICT,
  CONSTRAINT fk_detdev_detventa   FOREIGN KEY (detalle_venta_id) REFERENCES detalle_venta (id) ON DELETE RESTRICT,
  CONSTRAINT fk_detdev_producto   FOREIGN KEY (producto_id)      REFERENCES productos (id)     ON DELETE RESTRICT,
  CONSTRAINT ck_detdev_cantidad CHECK (cantidad > 0),
  CONSTRAINT ck_detdev_montos   CHECK (precio_unitario >= 0 AND costo_unitario >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE devolucion_pagos (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  devolucion_id  BIGINT UNSIGNED NOT NULL,
  metodo_pago_id INT UNSIGNED    NOT NULL,
  monto          DECIMAL(14,2)   NOT NULL,
  referencia     VARCHAR(60)     NULL,
  creado_fecha   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_devpago_devolucion (devolucion_id),
  KEY idx_devpago_metodo (metodo_pago_id),
  CONSTRAINT fk_devpago_devolucion FOREIGN KEY (devolucion_id)  REFERENCES devoluciones (id) ON DELETE RESTRICT,
  CONSTRAINT fk_devpago_metodo     FOREIGN KEY (metodo_pago_id) REFERENCES metodos_pago (id) ON DELETE RESTRICT,
  CONSTRAINT ck_devpago_monto CHECK (monto > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


-- ############################################################################
-- BLOQUE 9: TRASPASOS ENTRE SUCURSALES
-- ############################################################################

CREATE TABLE traspasos (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sucursal_origen_id  INT UNSIGNED    NOT NULL,
  sucursal_destino_id INT UNSIGNED    NOT NULL,
  usuario_envia_id    INT UNSIGNED    NOT NULL,
  usuario_recibe_id   INT UNSIGNED    NULL,
  folio               VARCHAR(20)     NOT NULL,
  fecha_envio         DATETIME        NULL,
  fecha_recepcion     DATETIME        NULL,
  estatus             ENUM('SOLICITADO','ENVIADO','RECIBIDO','PARCIAL','CANCELADO') NOT NULL DEFAULT 'SOLICITADO',
  notas               VARCHAR(255)    NULL,
  creado_fecha        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificado_fecha    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_traspaso_folio (folio),
  KEY idx_traspaso_origen (sucursal_origen_id, fecha_envio),
  KEY idx_traspaso_destino (sucursal_destino_id, fecha_recepcion),
  KEY idx_traspaso_estatus (estatus),
  KEY idx_traspaso_uenvia (usuario_envia_id),
  KEY idx_traspaso_urecibe (usuario_recibe_id),
  CONSTRAINT fk_traspaso_origen  FOREIGN KEY (sucursal_origen_id)  REFERENCES sucursales (id) ON DELETE RESTRICT,
  CONSTRAINT fk_traspaso_destino FOREIGN KEY (sucursal_destino_id) REFERENCES sucursales (id) ON DELETE RESTRICT,
  CONSTRAINT fk_traspaso_uenvia  FOREIGN KEY (usuario_envia_id)    REFERENCES usuarios (id)   ON DELETE RESTRICT,
  CONSTRAINT fk_traspaso_urecibe FOREIGN KEY (usuario_recibe_id)   REFERENCES usuarios (id)   ON DELETE RESTRICT,
  CONSTRAINT ck_traspaso_sucursal CHECK (sucursal_destino_id <> sucursal_origen_id),
  CONSTRAINT ck_traspaso_fechas   CHECK (fecha_recepcion IS NULL OR fecha_envio IS NULL OR fecha_recepcion >= fecha_envio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE detalle_traspaso (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  traspaso_id       BIGINT UNSIGNED NOT NULL,
  producto_id       INT UNSIGNED    NOT NULL,
  cantidad_enviada  DECIMAL(12,3)   NOT NULL,
  cantidad_recibida DECIMAL(12,3)   NOT NULL DEFAULT 0.000,
  costo_unitario    DECIMAL(12,2)   NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dettraspaso (traspaso_id, producto_id),
  KEY idx_dettraspaso_producto (producto_id),
  CONSTRAINT fk_dettraspaso_traspaso FOREIGN KEY (traspaso_id) REFERENCES traspasos (id) ON DELETE RESTRICT,
  CONSTRAINT fk_dettraspaso_producto FOREIGN KEY (producto_id) REFERENCES productos (id) ON DELETE RESTRICT,
  CONSTRAINT ck_dettraspaso_cant CHECK (
    cantidad_enviada > 0 AND cantidad_recibida >= 0 AND cantidad_recibida <= cantidad_enviada
  ),
  CONSTRAINT ck_dettraspaso_costo CHECK (costo_unitario >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


-- ############################################################################
-- BLOQUE 10: CUENTAS POR COBRAR (credito)
-- ############################################################################

CREATE TABLE cuentas_por_cobrar (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_id        INT UNSIGNED    NOT NULL,
  venta_id          BIGINT UNSIGNED NOT NULL,
  monto_original    DECIMAL(14,2)   NOT NULL,
  saldo             DECIMAL(14,2)   NOT NULL,
  fecha_emision     DATE            NOT NULL,
  fecha_vencimiento DATE            NOT NULL,
  estatus           ENUM('PENDIENTE','PARCIAL','LIQUIDADA','VENCIDA','CANCELADA') NOT NULL DEFAULT 'PENDIENTE',
  creado_fecha      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificado_fecha  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cxc_venta (venta_id),
  KEY idx_cxc_cliente (cliente_id, estatus),
  KEY idx_cxc_vencimiento (estatus, fecha_vencimiento),
  CONSTRAINT fk_cxc_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id) ON DELETE RESTRICT,
  CONSTRAINT fk_cxc_venta   FOREIGN KEY (venta_id)   REFERENCES ventas (id)   ON DELETE RESTRICT,
  CONSTRAINT ck_cxc_montos CHECK (monto_original > 0 AND saldo >= 0 AND saldo <= monto_original),
  CONSTRAINT ck_cxc_fechas CHECK (fecha_vencimiento >= fecha_emision)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE abonos_cxc (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cuenta_id      BIGINT UNSIGNED NOT NULL,
  metodo_pago_id INT UNSIGNED    NOT NULL,
  monto          DECIMAL(14,2)   NOT NULL,
  referencia     VARCHAR(60)     NULL,
  usuario_id     INT UNSIGNED    NOT NULL,
  corte_caja_id  BIGINT UNSIGNED NULL,
  fecha          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_abono_cuenta (cuenta_id, fecha),
  KEY idx_abono_corte (corte_caja_id),
  KEY idx_abono_metodo (metodo_pago_id),
  KEY idx_abono_usuario (usuario_id),
  CONSTRAINT fk_abono_cuenta FOREIGN KEY (cuenta_id)      REFERENCES cuentas_por_cobrar (id) ON DELETE RESTRICT,
  CONSTRAINT fk_abono_metodo FOREIGN KEY (metodo_pago_id) REFERENCES metodos_pago (id)       ON DELETE RESTRICT,
  CONSTRAINT fk_abono_usuario FOREIGN KEY (usuario_id)    REFERENCES usuarios (id)           ON DELETE RESTRICT,
  CONSTRAINT fk_abono_corte  FOREIGN KEY (corte_caja_id)  REFERENCES cortes_caja (id)        ON DELETE RESTRICT,
  CONSTRAINT ck_abono_monto CHECK (monto > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


-- ############################################################################
-- BLOQUE 11: CONTEOS FISICOS, GASTOS, CONFIGURACION, AUDITORIA
-- ############################################################################

CREATE TABLE conteos_fisicos (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sucursal_id       INT UNSIGNED    NOT NULL,
  usuario_id        INT UNSIGNED    NOT NULL,
  autorizado_por_id INT UNSIGNED    NULL,
  descripcion       VARCHAR(150)    NOT NULL,
  fecha_inicio      DATETIME        NOT NULL,
  fecha_cierre      DATETIME        NULL,
  estatus           ENUM('ABIERTO','CERRADO','APLICADO','CANCELADO') NOT NULL DEFAULT 'ABIERTO',
  notas             VARCHAR(255)    NULL,
  PRIMARY KEY (id),
  KEY idx_conteo_suc_fecha (sucursal_id, fecha_inicio),
  KEY idx_conteo_estatus (estatus),
  KEY idx_conteo_usuario (usuario_id),
  KEY idx_conteo_autor (autorizado_por_id),
  CONSTRAINT fk_conteo_sucursal FOREIGN KEY (sucursal_id)       REFERENCES sucursales (id) ON DELETE RESTRICT,
  CONSTRAINT fk_conteo_usuario  FOREIGN KEY (usuario_id)        REFERENCES usuarios (id)   ON DELETE RESTRICT,
  CONSTRAINT fk_conteo_autor    FOREIGN KEY (autorizado_por_id) REFERENCES usuarios (id)   ON DELETE RESTRICT,
  CONSTRAINT ck_conteo_aplicado CHECK (estatus <> 'APLICADO' OR autorizado_por_id IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE detalle_conteo (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conteo_id      BIGINT UNSIGNED NOT NULL,
  producto_id    INT UNSIGNED    NOT NULL,
  stock_sistema  DECIMAL(12,3)   NOT NULL,
  stock_contado  DECIMAL(12,3)   NOT NULL,
  diferencia     DECIMAL(12,3)   GENERATED ALWAYS AS (stock_contado - stock_sistema) STORED,
  costo_unitario DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  aplicado       BOOLEAN         NOT NULL DEFAULT FALSE,
  PRIMARY KEY (id),
  UNIQUE KEY uq_detconteo (conteo_id, producto_id),
  KEY idx_detconteo_producto (producto_id),
  CONSTRAINT fk_detconteo_conteo   FOREIGN KEY (conteo_id)   REFERENCES conteos_fisicos (id) ON DELETE RESTRICT,
  CONSTRAINT fk_detconteo_producto FOREIGN KEY (producto_id) REFERENCES productos (id)       ON DELETE RESTRICT,
  CONSTRAINT ck_detconteo_stock CHECK (stock_contado >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE gastos (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sucursal_id    INT UNSIGNED    NOT NULL,
  proveedor_id   INT UNSIGNED    NULL,
  usuario_id     INT UNSIGNED    NOT NULL,
  corte_caja_id  BIGINT UNSIGNED NULL,
  metodo_pago_id INT UNSIGNED    NOT NULL,
  concepto       VARCHAR(150)    NOT NULL,
  categoria      VARCHAR(40)     NOT NULL,
  monto          DECIMAL(14,2)   NOT NULL,
  impuesto_monto DECIMAL(14,2)   NOT NULL DEFAULT 0.00,
  comprobante    VARCHAR(60)     NULL,
  fecha          DATE            NOT NULL,
  notas          VARCHAR(255)    NULL,
  creado_fecha   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gasto_suc_fecha (sucursal_id, fecha),
  KEY idx_gasto_categoria (categoria, fecha),
  KEY idx_gasto_corte (corte_caja_id),
  KEY idx_gasto_proveedor (proveedor_id),
  KEY idx_gasto_usuario (usuario_id),
  KEY idx_gasto_metodo (metodo_pago_id),
  CONSTRAINT fk_gasto_sucursal  FOREIGN KEY (sucursal_id)    REFERENCES sucursales (id)   ON DELETE RESTRICT,
  CONSTRAINT fk_gasto_proveedor FOREIGN KEY (proveedor_id)   REFERENCES proveedores (id)  ON DELETE RESTRICT,
  CONSTRAINT fk_gasto_usuario   FOREIGN KEY (usuario_id)     REFERENCES usuarios (id)     ON DELETE RESTRICT,
  CONSTRAINT fk_gasto_corte     FOREIGN KEY (corte_caja_id)  REFERENCES cortes_caja (id)  ON DELETE RESTRICT,
  CONSTRAINT fk_gasto_metodo    FOREIGN KEY (metodo_pago_id) REFERENCES metodos_pago (id) ON DELETE RESTRICT,
  CONSTRAINT ck_gasto_monto CHECK (monto > 0 AND impuesto_monto >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE configuracion (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  clave            VARCHAR(60)  NOT NULL,
  valor            VARCHAR(255) NOT NULL,
  tipo_dato        ENUM('STRING','INT','DECIMAL','BOOLEAN','JSON') NOT NULL DEFAULT 'STRING',
  descripcion      VARCHAR(200) NULL,
  sucursal_id      INT UNSIGNED NULL,
  -- Emula UNIQUE(clave, sucursal_id) tratando NULL como 0 (valor global).
  sucursal_key     INT UNSIGNED GENERATED ALWAYS AS (IFNULL(sucursal_id, 0)) STORED,
  modificable      BOOLEAN      NOT NULL DEFAULT TRUE,
  modificado_por   INT UNSIGNED NULL,
  modificado_fecha DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_config_clave_suc (clave, sucursal_key),
  KEY idx_config_sucursal (sucursal_id),
  KEY idx_config_usuario (modificado_por),
  CONSTRAINT fk_config_sucursal FOREIGN KEY (sucursal_id)    REFERENCES sucursales (id) ON DELETE RESTRICT,
  CONSTRAINT fk_config_usuario  FOREIGN KEY (modificado_por) REFERENCES usuarios (id)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


CREATE TABLE auditoria (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id         INT UNSIGNED    NULL,
  sucursal_id        INT UNSIGNED    NULL,
  tabla              VARCHAR(64)     NOT NULL,
  registro_id        BIGINT UNSIGNED NULL,
  accion             ENUM('INSERT','UPDATE','DELETE','LOGIN','LOGIN_FALLIDO') NOT NULL,
  valores_anteriores JSON            NULL,
  valores_nuevos     JSON            NULL,
  ip_origen          VARCHAR(45)     NULL,
  fecha              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_auditoria_tabla (tabla, registro_id),
  KEY idx_auditoria_usuario (usuario_id, fecha),
  KEY idx_auditoria_fecha (fecha),
  KEY idx_auditoria_sucursal (sucursal_id),
  CONSTRAINT fk_auditoria_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios (id)   ON DELETE RESTRICT,
  CONSTRAINT fk_auditoria_sucursal FOREIGN KEY (sucursal_id) REFERENCES sucursales (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;


-- ############################################################################
-- BLOQUE 12: TRIGGERS
-- ############################################################################
-- Los triggers no conocen al usuario de aplicacion. La app debe ejecutar,
-- al inicio de cada transaccion:
--     SET @app_usuario_id = <id del usuario autenticado>;
-- Si la variable no esta definida, se registra NULL (proceso automatico).
-- ############################################################################

DELIMITER $$

CREATE TRIGGER trg_productos_au_historial
AFTER UPDATE ON productos
FOR EACH ROW
BEGIN
  IF (NEW.precio <> OLD.precio) OR (NEW.costo_neto <> OLD.costo_neto) THEN
    INSERT INTO historial_precios
      (producto_id, precio_anterior, precio_nuevo, costo_anterior, costo_nuevo, usuario_id)
    VALUES
      (NEW.id, OLD.precio, NEW.precio, OLD.costo_neto, NEW.costo_neto, @app_usuario_id);
  END IF;
END$$


CREATE TRIGGER trg_ventas_au_auditoria
AFTER UPDATE ON ventas
FOR EACH ROW
BEGIN
  IF NEW.estatus <> OLD.estatus THEN
    INSERT INTO auditoria
      (usuario_id, sucursal_id, tabla, registro_id, accion, valores_anteriores, valores_nuevos)
    VALUES
      (@app_usuario_id, NEW.sucursal_id, 'ventas', NEW.id, 'UPDATE',
       JSON_OBJECT('estatus', OLD.estatus),
       JSON_OBJECT('estatus', NEW.estatus, 'motivo', NEW.cancelada_motivo));
  END IF;
END$$


-- Blindaje del kardex: prohibe modificar o borrar movimientos historicos
-- incluso si alguien lo intenta directamente desde el motor.
CREATE TRIGGER trg_movinv_bu_bloquear
BEFORE UPDATE ON movimientos_inventario
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'El kardex es append-only: corrija con un movimiento inverso.';
END$$

CREATE TRIGGER trg_movinv_bd_bloquear
BEFORE DELETE ON movimientos_inventario
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'El kardex es append-only: no se permite DELETE.';
END$$

DELIMITER ;


-- ############################################################################
-- BLOQUE 13: PROCEDIMIENTO DE FOLIOS
-- ############################################################################

DELIMITER $$

CREATE PROCEDURE sp_siguiente_folio(
  IN  p_sucursal_id INT UNSIGNED,
  IN  p_serie       CHAR(3),
  IN  p_tipo_doc    VARCHAR(10),
  OUT p_folio       BIGINT UNSIGNED
)
MODIFIES SQL DATA
BEGIN
  -- Debe llamarse DENTRO de la transaccion de la venta.
  -- Bloquea la fila del folio hasta el COMMIT: consecutivo sin huecos ni carreras.
  UPDATE folios
     SET ultimo_folio = LAST_INSERT_ID(ultimo_folio + 1)
   WHERE sucursal_id = p_sucursal_id
     AND serie       = p_serie
     AND tipo_doc    = p_tipo_doc;

  IF ROW_COUNT() = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'No existe serie de folios para esa sucursal/tipo de documento.';
  END IF;

  SET p_folio = LAST_INSERT_ID();
END$$

DELIMITER ;


-- ############################################################################
-- BLOQUE 14: VISTAS DE APOYO
-- ############################################################################

-- Utilidad real por venta: descuenta tambien la comision bancaria.
CREATE OR REPLACE VIEW v_utilidad_venta AS
SELECT
  v.id                AS venta_id,
  v.sucursal_id,
  v.fecha,
  v.total,
  v.impuesto_total,
  v.costo_total,
  v.utilidad_total    AS utilidad_bruta,
  COALESCE(p.comisiones, 0) AS comisiones,
  v.utilidad_total - COALESCE(p.comisiones, 0) AS utilidad_neta
FROM ventas v
LEFT JOIN (
  SELECT venta_id, SUM(comision_monto) AS comisiones
    FROM venta_pagos
   GROUP BY venta_id
) p ON p.venta_id = v.id
WHERE v.estatus <> 'CANCELADA';


-- Productos por debajo del minimo, por sucursal.
CREATE OR REPLACE VIEW v_stock_bajo AS
SELECT
  s.id     AS sucursal_id,
  s.nombre AS sucursal,
  p.id     AS producto_id,
  p.sku,
  p.nombre AS producto,
  i.stock,
  i.stock_minimo,
  i.stock_minimo - i.stock AS faltante
FROM inventario i
JOIN productos  p ON p.id = i.producto_id
JOIN sucursales s ON s.id = i.sucursal_id
WHERE p.activo = TRUE
  AND p.controla_stock = TRUE
  AND i.stock <= i.stock_minimo;


-- Efectivo esperado por corte (base del arqueo).
CREATE OR REPLACE VIEW v_arqueo_corte AS
SELECT
  c.id AS corte_caja_id,
  c.sucursal_id,
  c.caja_id,
  c.fondo_inicial,
  COALESCE(ef.cobrado, 0)     AS cobrado_efectivo,
  COALESCE(ef.cambio, 0)      AS cambio_entregado,
  COALESCE(dev.reembolso, 0)  AS reembolsos_efectivo,
  COALESCE(mc.ingresos, 0)    AS ingresos,
  COALESCE(mc.salidas, 0)     AS salidas,
  c.fondo_inicial
    + COALESCE(ef.cobrado, 0)
    - COALESCE(ef.cambio, 0)
    - COALESCE(dev.reembolso, 0)
    + COALESCE(mc.ingresos, 0)
    - COALESCE(mc.salidas, 0)  AS efectivo_esperado_calculado
FROM cortes_caja c
LEFT JOIN (
  SELECT v.corte_caja_id,
         SUM(vp.monto)             AS cobrado,
         SUM(COALESCE(vp.cambio,0)) AS cambio
    FROM ventas v
    JOIN venta_pagos  vp ON vp.venta_id = v.id
    JOIN metodos_pago mp ON mp.id = vp.metodo_pago_id
   WHERE mp.afecta_efectivo_caja = TRUE
     AND v.estatus <> 'CANCELADA'
   GROUP BY v.corte_caja_id
) ef ON ef.corte_caja_id = c.id
LEFT JOIN (
  SELECT d.corte_caja_id, SUM(dp.monto) AS reembolso
    FROM devoluciones     d
    JOIN devolucion_pagos dp ON dp.devolucion_id = d.id
    JOIN metodos_pago     mp ON mp.id = dp.metodo_pago_id
   WHERE mp.afecta_efectivo_caja = TRUE
     AND d.estatus = 'PROCESADA'
   GROUP BY d.corte_caja_id
) dev ON dev.corte_caja_id = c.id
LEFT JOIN (
  SELECT corte_caja_id,
         SUM(CASE WHEN tipo IN ('INGRESO','FONDO')  THEN monto ELSE 0 END) AS ingresos,
         SUM(CASE WHEN tipo IN ('RETIRO','GASTO')   THEN monto ELSE 0 END) AS salidas
    FROM movimientos_caja
   GROUP BY corte_caja_id
) mc ON mc.corte_caja_id = c.id;


-- ############################################################################
-- BLOQUE 15: DATOS SEMILLA
-- ############################################################################
-- Implementado como Seeders de Laravel: database/seeders/RolesPermisosSeeder.php,
-- ConfiguracionSeeder.php, SucursalSeeder.php, AdminUserSeeder.php.
-- Se documenta aqui el contenido de referencia (igual al original del .sql),
-- mas dos claves nuevas de configuracion (D2, ver MO.pdf S4.4):
--   login_intentos_max = 5
--   login_bloqueo_minutos = 15
-- ############################################################################


-- ############################################################################
-- BLOQUE 16: USUARIOS DE BASE DE DATOS Y PRIVILEGIOS  [OPS — no se ejecuta desde la app]
-- ############################################################################
-- La aplicacion NUNCA debe conectarse como root.
-- Sustituya las contrasenas antes de ejecutar y restrinja el host.
-- ############################################################################

-- CREATE USER 'pos_app'@'10.0.0.%'     IDENTIFIED BY 'CAMBIAR_ESTA_PASSWORD';
-- CREATE USER 'pos_reportes'@'10.0.0.%' IDENTIFIED BY 'CAMBIAR_ESTA_PASSWORD';
-- CREATE USER 'pos_backup'@'localhost'  IDENTIFIED BY 'CAMBIAR_ESTA_PASSWORD';

-- Aplicacion: opera, pero no altera la estructura ni borra nada.
-- GRANT SELECT, INSERT, UPDATE, EXECUTE ON mila_pos.* TO 'pos_app'@'10.0.0.%';

-- Reportes: solo lectura. Apuntar a la replica cuando exista.
-- GRANT SELECT ON mila_pos.* TO 'pos_reportes'@'10.0.0.%';

-- Respaldos.
-- GRANT SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER, RELOAD, REPLICATION CLIENT
--   ON *.* TO 'pos_backup'@'localhost';

-- FLUSH PRIVILEGES;


-- ############################################################################
-- FIN DEL SCRIPT
-- ############################################################################
-- Verificacion posterior sugerida:
--   SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
--     FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'mila_pos';
--   SELECT CONSTRAINT_NAME, TABLE_NAME
--     FROM information_schema.TABLE_CONSTRAINTS
--    WHERE CONSTRAINT_SCHEMA = 'mila_pos' AND CONSTRAINT_TYPE = 'CHECK';
--   SELECT ROUTINE_NAME, ROUTINE_TYPE
--     FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = 'mila_pos';
-- ############################################################################
