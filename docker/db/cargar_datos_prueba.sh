#!/bin/sh
# Carga sql/99_datos_prueba.sql en la base local (se ejecuta DENTRO del contenedor db):
#   docker compose exec db sh /crador/cargar_datos_prueba.sh
# NO usar en la instalacion de produccion del hospital.
set -e
# Catalogos de permisos (estructura PROVISIONAL, IF NOT EXISTS) por si la base se creo antes de agregarlos
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < /sql/04_permisos_PROVISIONAL.sql
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < /sql/05_instituciones_remision_PROVISIONAL.sql
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < /sql/99_datos_prueba.sql
echo "Datos de prueba cargados en $MYSQL_DATABASE. Usuarios: NIXON07 / MEDPRUEBA / ENFPRUEBA, clave prueba123"
