#!/bin/sh
# Carga sql/99_datos_prueba.sql en la base local (se ejecuta DENTRO del contenedor db):
#   docker compose exec db sh /crador/cargar_datos_prueba.sh
# NO usar en la instalacion de produccion del hospital.
set -e
# Catalogos agregados despues de crear la base (IF NOT EXISTS): listas, permisos, Estado Ingreso, InstRemi,
# y tablas clinicas nuevas (antecedentes multiples, reconciliacion medicamentosa)
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < /sql/04_listas_permisos.sql
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < /sql/05_tablas_clinicas_nuevas.sql
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < /sql/99_datos_prueba.sql
echo "Datos de prueba cargados en $MYSQL_DATABASE. Usuarios: NIXON07 / MEDPRUEBA / ENFPRUEBA, clave prueba123"
