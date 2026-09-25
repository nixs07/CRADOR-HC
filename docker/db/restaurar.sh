#!/bin/sh
# Restaura un respaldo hecho con respaldar.sh (se ejecuta DENTRO del contenedor db):
#   docker compose exec db sh /crador/restaurar.sh crador_hc_20260925_220000.sql.gz SI
# ATENCION: reemplaza TODO el contenido actual de la base local.
set -e
if [ -z "$1" ] || [ "$2" != "SI" ]; then
    echo "Uso: sh /crador/restaurar.sh ARCHIVO.sql.gz SI"
    echo "El archivo debe estar en la carpeta respaldos/ del proyecto. Respaldos disponibles:"
    ls -1 /respaldos 2>/dev/null || true
    exit 1
fi
gunzip -c "/respaldos/$1" | mysql -uroot -p"$MYSQL_ROOT_PASSWORD" --default-character-set=utf8
echo "Base restaurada desde respaldos/$1"
