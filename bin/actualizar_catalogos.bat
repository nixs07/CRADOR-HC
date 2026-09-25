@echo off
REM Actualiza los catalogos de CRADOR-HC desde SIHOS (para el Programador de tareas de Windows).
REM Deja el resultado en catalogos.log en la carpeta del proyecto.
cd /d "%~dp0.."
echo. >> catalogos.log
docker compose exec -T app php bin/actualizar_catalogos.php >> catalogos.log 2>&1
exit /b %ERRORLEVEL%
