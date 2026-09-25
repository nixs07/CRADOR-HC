#!/bin/sh
# Respaldo completo de la base local (se ejecuta DENTRO del contenedor db):
#   docker compose exec db sh /crador/respaldar.sh
# Deja el archivo en la carpeta respaldos/ del proyecto (no se sube a GitHub).
set -e
ARCHIVO="/respaldos/${MYSQL_DATABASE}_$(date +%Y%m%d_%H%M%S).sql.gz"
mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --default-character-set=utf8 \
          --databases "$MYSQL_DATABASE" | gzip > "$ARCHIVO"
echo "Respaldo creado: respaldos/$(basename "$ARCHIVO")"
