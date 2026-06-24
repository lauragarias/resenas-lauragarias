#!/usr/bin/env python3
"""Despliegue por FTPS a Webempresa. Lo usa la GitHub Action en cada push.
Sube solo los archivos públicos de la app; NO toca api/config.php (la API key
vive solo en el servidor) ni archivos de configuración del repo."""
import os
from ftplib import FTP_TLS

host = os.environ["FTP_SERVER"]
user = os.environ["FTP_USERNAME"]
pw   = os.environ["FTP_PASSWORD"]

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
