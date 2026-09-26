# Arma la demo navegable (un solo index.html) a partir de las pantallas recogidas.
import base64, json, re

import os
APP = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', 'public') + '/'
d = json.load(open('recogido.json', encoding='utf-8'))

css = open(APP + 'css/estilo.css', encoding='utf-8').read()
css = re.sub(r'@font-face\s*\{[^}]*\}', '', css)
sprite = open(APP + 'img/iconos.svg', encoding='utf-8').read()
sprite = re.sub(r'<\?xml[^>]*>', '', sprite).replace('<svg ', '<svg style="display:none" aria-hidden="true" ', 1)
logo = 'data:image/png;base64,' + base64.b64encode(open(APP + 'img/logo.png', 'rb').read()).decode()


def parche_js(src):
    src = src.replace('window.location.hash', 'DEMO.hash')
    src = src.replace('window.location.href', 'DEMO.url')
    src = src.replace("history.replaceState(null, '', ", 'DEMO.reemplazar(')
    return src

interfaz = parche_js(open(APP + 'js/interfaz.js', encoding='utf-8').read())
formularios = parche_js(open(APP + 'js/formularios.js', encoding='utf-8').read())

paginas = {}
for k, p in d['paginas'].items():
    html = p['html'].replace('img/iconos.svg#', '#').replace('img/logo.png', logo)
    paginas[k] = {'cls': p['cls'], 'html': html, 'title': p['title']}

datos = json.dumps({'paginas': paginas, 'api': d['api'], 'catalogo': d['catalogo']}, ensure_ascii=False)
datos = datos.replace('</', '<\\/')

plantilla = open('plantilla.html', encoding='utf-8').read()
salida = (plantilla.replace('/*CSS*/', css)
          .replace('<!--SPRITE-->', sprite)
          .replace('/*DATOS*/', 'var DATOS = ' + datos + ';')
          .replace('/*INTERFAZ*/', interfaz)
          .replace('/*FORMULARIOS*/', formularios))
open('index.html', 'w', encoding='utf-8').write(salida)
print('ok', len(salida) // 1024, 'KB')
