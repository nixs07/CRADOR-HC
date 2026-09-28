#!/bin/sh
# Carga sql/99_datos_prueba.sql en la base local (se ejecuta DENTRO del contenedor db):
#   docker compose exec db sh /crador/cargar_datos_prueba.sh
# NO usar en la instalacion de produccion del hospital.
set -e
# Catalogos agregados despues de crear la base (IF NOT EXISTS): listas, permisos, Estado Ingreso, InstRemi,
# y tablas clinicas nuevas (antecedentes multiples, reconciliacion medicamentosa)
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < /sql/04_listas_permisos.sql
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < /sql/05_tablas_clinicas_nuevas.sql
# Catalogo HoraApli ("Cada" de la prescripcion hospitalaria: 0 = AHORA, 1..24 horas; estructura provisional)
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < /sql/06_hora_apli.sql
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" < /sql/99_datos_prueba.sql
echo "Datos de prueba cargados en $MYSQL_DATABASE. Usuarios: NIXON07 / MEDPRUEBA / ENFPRUEBA, clave prueba123"
