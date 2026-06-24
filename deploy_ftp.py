#!/usr/bin/env python3
"""Despliegue por FTPS a Webempresa. Lo usa la GitHub Action en cada push.
Sube solo los archivos públicos de la app; NO toca api/config.php (la API key
vive solo en el servidor) ni archivos de configuración del repo."""
import os
import base64
from ftplib import FTP_TLS

host = os.environ["FTP_SERVER"]
user = os.environ["FTP_USERNAME"]
# La contraseña viaja en base64 (ASCII) para no corromperse en los secrets de GitHub.
pw = base64.b64decode(os.environ["FTP_PASSWORD_B64"]).decode("utf-8")

# (local, remoto) — añade aquí cualquier archivo nuevo que deba publicarse
FILES = [
    ("index.html", "index.html"),
    ("og.png", "og.png"),
    ("api/generar.php", "api/generar.php"),
    ("api/.htaccess", "api/.htaccess"),
]

print(f"DEBUG user={user!r} pw_len={len(pw)} ascii={pw.isascii()}")
ftp = FTP_TLS(host, timeout=60)
try:
    ftp.login(user, pw)
except Exception as e:
    print("DEBUG login error:", repr(e))
    raise
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
