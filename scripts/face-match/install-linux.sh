#!/usr/bin/env bash
set -euo pipefail

# Run at image build time; keep runtime/models outside mutable application storage.
FACE_ROOT="${1:-/opt/lendly-face}"
python3 -m venv "$FACE_ROOT/runtime"
"$FACE_ROOT/runtime/bin/python" -m pip install --no-cache-dir -r scripts/face-match/requirements.txt
"$FACE_ROOT/runtime/bin/python" scripts/face-match/setup_models.py "$FACE_ROOT/models"
"$FACE_ROOT/runtime/bin/python" scripts/face-match/health_check.py "$FACE_ROOT/models"
mkdir -p /usr/local/etc/php/conf.d
printf 'expose_php = Off\n' > /usr/local/etc/php/conf.d/99-lendly-security.ini
