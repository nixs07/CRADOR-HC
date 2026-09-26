# Instalación en Windows con Docker Desktop

Guía para instalar CRADOR-HC en el computador de sistemas (o en cualquier equipo/servidor Windows).
La app queda en dos contenedores: `crador_db` (MySQL 5.6, igual que SIHOS) y `crador_app` (PHP 8 + Apache).

## 1. Requisitos

- Windows 10/11 de 64 bits con **Docker Desktop** instalado y abierto (motor WSL 2).
  Descarga: <https://www.docker.com/products/docker-desktop/>. Al instalar, dejar marcada la opción "Use WSL 2".
- La carpeta del proyecto (clonada con git o copiada), por ejemplo `C:\CRADOR-HC`.
- Para copiar catálogos: un usuario de **solo lectura** en la base de SIHOS (192.168.0.250).

Comprobar Docker en PowerShell:

```powershell
docker version
docker compose version
```

## 2. Configurar el `.env`

En PowerShell, dentro de la carpeta del proyecto:

```powershell
cd C:\CRADOR-HC
Copy-Item .env.example .env
notepad .env
```

Cambiar como mínimo:

| Variable | Qué poner |
| --- | --- |
| `DB_CLAVE`, `DB_CLAVE_ROOT` | Claves nuevas para la base local (inventarlas, no usar las de SIHOS). |
| `SIHOS_HOST`, `SIHOS_PUERTO`, `SIHOS_NOMBRE` | Servidor y base de SIHOS (ej. `192.168.0.250`, `3306`, `sihos`). |
| `SIHOS_USUARIO`, `SIHOS_CLAVE` | Usuario de solo lectura de SIHOS. |
| `ADMIN_LOGIN` | Login de SIHOS del administrador (`NIXON07`). |
| `APP_PUERTO` | Puerto de la app (por defecto `8080`). |

El `.env` **no se sube a GitHub** (está en `.gitignore`).

> Las claves de la base local (`DB_CLAVE`, `DB_CLAVE_ROOT`) solo se aplican la **primera vez** que se crea la base.
> Si después se cambian en el `.env`, hay que cambiarlas también dentro de MySQL.

## 3. Levantar la aplicación

```powershell
docker compose up -d
```

La primera vez tarda unos minutos: descarga las imágenes, construye la app y crea la base ejecutando, en orden,
`sql/00_control.sql`, `sql/01_tablas_clinicas.sql`, `sql/02_catalogos.sql` y `sql/03_catalogos_listas.sql`.

Verificar que ambos contenedores estén arriba (`crador_db` debe decir `healthy`):

```powershell
docker compose ps
docker compose logs db --tail 30
```

Abrir en el navegador: <http://localhost:8080> (desde otro equipo de la red: `http://IP-DEL-EQUIPO:8080`).

## 4. Primera carga de catálogos (obligatoria)

Al crearse, la base local está **vacía**: no hay usuarios, así que nadie puede ingresar todavía.
La primera copia de catálogos (incluye la tabla `Usuarios`) se hace por línea de comandos:

```powershell
docker compose exec -T app php bin/actualizar_catalogos.php
```

Muestra el resultado tabla por tabla. Código de salida: `0` todo bien, `2` parcial (alguna tabla falló),
`1` error (por ejemplo SIHOS no responde; en ese caso **no se borra nada** local).
Después de esto, los usuarios ingresan con su mismo login y clave de SIHOS.

Para copiar solo algunas tablas: `docker compose exec -T app php bin/actualizar_catalogos.php --tablas=Usuarios,Contrato`

Desde la app, el administrador (`ADMIN_LOGIN`) tiene el botón **Actualizar catálogos** en el tablero y la página
**Catálogos** con el historial de cada actualización.

### Cómo funciona la copia

- Se copian las 61 tablas definidas en `sql/02_catalogos.sql` y `sql/03_catalogos_listas.sql`, columna por columna
  por nombre, con `INSERT` por lotes.
- Cada tabla se llena primero en una tabla temporal `cont_tmp_<Tabla>` y solo si terminó bien se reemplaza la local
  en un solo paso (`RENAME TABLE`). Si SIHOS se cae a mitad de la copia, la tabla local queda como estaba.
- Si SIHOS devuelve 0 filas en una tabla que localmente tiene datos, no se reemplaza (queda "omitida").
- `Usuarios`: solo `Activo = 1`. No se copian fotos/huellas (`Paciente.Foto`, `Paciente.Huella`, `Usuarios.huella`)
  ni claves de otros sistemas (`Usuarios.ContraSiifa`, credenciales de `CodiInst`).
- Cada tabla queda registrada en `cont_catalogo_actualizacion`, y cada ejecución en `cont_catalogo_ejecucion`.

## 5. Programar la actualización cada noche

Con el **Programador de tareas** de Windows:

1. Crear tarea básica → nombre "CRADOR-HC catálogos" → Diariamente, 2:00 a. m.
2. Acción "Iniciar un programa": `C:\CRADOR-HC\bin\actualizar_catalogos.bat`
   y en "Iniciar en": `C:\CRADOR-HC`.
3. En propiedades, marcar "Ejecutar tanto si el usuario inició sesión como si no".

El resultado queda en `C:\CRADOR-HC\catalogos.log` y en la página **Catálogos** de la app.
Docker Desktop debe estar iniciado (configurarlo para que arranque con Windows).

## 6. Datos de prueba (solo equipos de prueba)

`sql/99_datos_prueba.sql` trae usuarios, pacientes, contratos y admisiones **inventados** para ver el tablero.
**No se carga automáticamente y nunca debe cargarse en la instalación del hospital.**

```powershell
docker compose exec db sh /crador/cargar_datos_prueba.sh
```

Usuarios de prueba (clave `prueba123` en todos, con hash MD5-crypt igual que SIHOS):

| Login | Rol |
| --- | --- |
| `NIXON07` | Administrador (por `ADMIN_LOGIN`) |
| `MEDPRUEBA` | Médico |
| `ENFPRUEBA` | Enfermería |
| `INAPRUEBA` | Inactivo (`Activo = 2`): **no** debe poder ingresar |

Tablero esperado: Urgencias 3, Observación 2, Consulta Externa 3 abiertas; 6 admisiones del día; 4 pendientes por
cargar a SIHOS (más 1 con error y 1 cargada).

## 7. Operación diaria

| Tarea | Comando (PowerShell, en la carpeta del proyecto) |
| --- | --- |
| Ver estado | `docker compose ps` |
| Detener | `docker compose stop` |
| Iniciar | `docker compose start` (o `docker compose up -d`) |
| Ver errores de la app | `docker compose logs app --tail 100` |
| Aplicar cambios del Dockerfile | `docker compose up -d --build` |
| Respaldo de la base local | `docker compose exec db sh /crador/respaldar.sh` (queda en `respaldos\`) |
| Restaurar un respaldo | `docker compose exec db sh /crador/restaurar.sh ARCHIVO.sql.gz SI` |
| Consola MySQL local | `docker compose exec db mysql -uroot -p crador_hc` |

Los cambios en archivos `.php` se ven de inmediato (el código se monta desde la carpeta, no hay que reconstruir).
Desde HeidiSQL/Workbench en el mismo equipo: host `127.0.0.1`, puerto `DB_PUERTO_EQUIPO` (3307), usuario `root`.

**Acceso desde otros equipos:** si no abre desde la red, permitir el puerto en el firewall de Windows
(PowerShell como administrador):

```powershell
New-NetFirewallRule -DisplayName "CRADOR-HC" -Direction Inbound -Protocol TCP -LocalPort 8080 -Action Allow
```

## 8. Mover a otro equipo o servidor

1. En el equipo actual: sacar un respaldo (`docker compose exec db sh /crador/respaldar.sh`).
   Importante si hay admisiones de contingencia **sin cargar a SIHOS**.
2. Copiar la carpeta completa del proyecto **con su `.env`** (y la carpeta `respaldos\`) al equipo nuevo.
3. En el equipo nuevo, con Docker instalado: `docker compose up -d`. La base se crea sola con la estructura.
4. Restaurar el respaldo: `docker compose exec db sh /crador/restaurar.sh ARCHIVO.sql.gz SI`
   (o, si no había datos de contingencia, solo actualizar catálogos: paso 4).

Los datos de MySQL viven en el volumen de Docker `db_datos` (no en la carpeta), por eso se mueven con el respaldo.

## 9. Problemas frecuentes

| Síntoma | Causa / solución |
| --- | --- |
| "No hay conexión con la base de datos local" | `crador_db` no ha terminado de arrancar o está detenido: `docker compose ps`, `docker compose logs db`. |
| Nadie puede ingresar | Falta la primera carga de catálogos (paso 4) o el usuario está inactivo en SIHOS. |
| "Demasiados intentos fallidos" | 5 claves erradas en 15 minutos para ese login. Esperar 15 minutos. |
| Tarjeta "Conexión con SIHOS: Caída" | SIHOS apagado, sin red, o datos `SIHOS_*` del `.env` errados. El mensaje dice cuál. Tras cambiar el `.env`: `docker compose up -d` (recrea la app con los nuevos valores). |
| Error al crear la base "Invalid default value" | MySQL sin `explicit_defaults_for_timestamp`: usar el `docker-compose.yml` del proyecto sin cambios. |
| Se quiere empezar de cero (borra TODO) | `docker compose down -v` y luego `docker compose up -d`. |

## Actualizar una instalación que ya existía

Los scripts de `sql/` solo se ejecutan solos la primera vez. Si la base ya estaba creada y una versión nueva
agrega tablas de control (por ejemplo `cont_paciente` en la fase 2), ejecute de nuevo `sql/00_control.sql`
(usa `CREATE TABLE IF NOT EXISTS`, no borra nada):

```powershell
Get-Content sql/00_control.sql | docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
```

