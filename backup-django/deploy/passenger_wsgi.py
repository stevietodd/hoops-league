"""
Passenger entrypoint for DreamHost.

Deploy this file to ~/bball.dreamhosters.com/passenger_wsgi.py
(or copy from deploy/passenger_wsgi.py during setup).
"""

import os
import sys

USERNAME = "dh_bball"
HOME = f"/home/{USERNAME}"
APP_DIR = f"{HOME}/hoops-league"
VENV_DIR = f"{APP_DIR}/venv"
INTERP = f"{VENV_DIR}/bin/python"

if sys.executable != INTERP:
    os.execl(INTERP, INTERP, *sys.argv)

sys.path.insert(0, APP_DIR)

os.environ.setdefault("DJANGO_SETTINGS_MODULE", "config.settings")

# Ensure production env file is visible even if Passenger doesn't inherit shell env.
env_path = os.path.join(APP_DIR, ".env")
if os.path.isfile(env_path):
    with open(env_path, encoding="utf-8") as handle:
        for raw in handle:
            line = raw.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, _, value = line.partition("=")
            os.environ.setdefault(key.strip(), value.strip().strip("'").strip('"'))

from django.core.wsgi import get_wsgi_application

application = get_wsgi_application()
