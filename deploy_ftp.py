#!/usr/bin/env python3
"""Despliegue por FTPS a Webempresa (resenas.lauragarias.com).

Sube los archivos públicos de la app a tu hosting. NO toca api/config.php
(la API key vive solo en el servidor) ni los archivos de configuración del repo.

Uso local:
    FTP_PASSWORD='tu-contraseña' python3 deploy_ftp.py
"""
import os
import base64
from ftplib import FTP_TLS

host = os.environ.get("FTP_SERVER", "resenas.lauragarias.com")
user = os.environ.get("FTP_USERNAME", "claude@resenas.lauragarias.com")

if os.environ.get("FTP_PASSWORD_B64"):
    pw = base64.b64decode(os.environ["FTP_PASSWORD_B64"]).decode("utf-8")
elif os.environ.get("FTP_PASSWORD"):
    pw = os.environ["FTP_PASSWORD"]
else:
    raise SystemExit("Falta FTP_PASSWORD (o FTP_PASSWORD_B64) en el entorno.")

# (local, remoto) — añade aquí cualquier archivo nuevo que deba publicarse
FILES = [
    ("index.html", "index.html"),
    ("og.png", "og.png"),
    ("api/generar.php", "api/generar.php"),
    ("api/.htaccess", "api/.htaccess"),
]

ftp = FTP_TLS(host, timeout=60)
ftp.login(user, pw)
ftp.prot_p()
print("Conectado por FTPS ✓")

try:
    ftp.mkd("api")
except Exception:
    pass  # ya existe

for local, remote in FILES:
    with open(local, "rb") as f:
        ftp.storbinary("STOR " + remote, f)
    print("↑", remote)

ftp.quit()
print("Despliegue completado ✓")
