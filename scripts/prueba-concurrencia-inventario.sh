#!/usr/bin/env bash
# Criterio de aceptacion explicito de M2 (P-07): 50 peticiones simultaneas
# descontando el mismo producto con stock 10 -> exactamente 10 exitos y 40
# rechazos, sin stock negativo.
#
# pcntl_fork no existe en Windows: la paralelizacion real se logra lanzando
# 50 procesos de PHP independientes en segundo plano (cada uno con su
# propia conexion a MySQL), no Promise.all ni nada de un solo hilo.
#
# Uso: bash scripts/prueba-concurrencia-inventario.sh
# Requiere: estar parado en la raiz del proyecto Laravel.

set -euo pipefail

STOCK_INICIAL=10
INTENTOS=50

echo "== Sembrando producto de prueba con stock=${STOCK_INICIAL} =="
SALIDA=$(php artisan inventario:sembrar-prueba-concurrencia "$STOCK_INICIAL" | tail -1)

PRODUCTO_ID=$(echo "$SALIDA" | awk '{print $1}')
SUCURSAL_ID=$(echo "$SALIDA" | awk '{print $2}')

echo "Producto de prueba: id=${PRODUCTO_ID} sucursal=${SUCURSAL_ID}"

RESULTADOS_DIR=$(mktemp -d)
echo "== Lanzando ${INTENTOS} procesos en paralelo real =="

for i in $(seq 1 "$INTENTOS"); do
  php artisan inventario:decrementar-uno "$PRODUCTO_ID" "$SUCURSAL_ID" 1 > "${RESULTADOS_DIR}/resultado_${i}.txt" 2>&1 &
done

wait

EXITOS=$(grep -l "^OK$" "${RESULTADOS_DIR}"/resultado_*.txt 2>/dev/null | wc -l | tr -d ' ')
RECHAZOS=$(grep -l "STOCK_INSUFICIENTE" "${RESULTADOS_DIR}"/resultado_*.txt 2>/dev/null | wc -l | tr -d ' ')

STOCK_FINAL=$(php artisan inventario:mostrar-stock "$PRODUCTO_ID" "$SUCURSAL_ID" | tail -1)

echo ""
echo "== Resultado =="
echo "Exitos:        ${EXITOS} (esperado: ${STOCK_INICIAL})"
echo "Rechazos:      ${RECHAZOS} (esperado: $((INTENTOS - STOCK_INICIAL)))"
echo "Stock final:   ${STOCK_FINAL} (esperado: 0, NUNCA negativo)"

rm -rf "$RESULTADOS_DIR"

if [ "$EXITOS" -eq "$STOCK_INICIAL" ] && [ "$RECHAZOS" -eq $((INTENTOS - STOCK_INICIAL)) ] && [ "$STOCK_FINAL" = "0.000" ]; then
  echo "PASA: exactamente ${STOCK_INICIAL}/$((INTENTOS - STOCK_INICIAL)), stock nunca negativo."
  exit 0
else
  echo "FALLA: revisar el motor de InventarioService."
  exit 1
fi
