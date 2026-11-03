#!/usr/bin/env bash
# Build a signed Nextcloud appstore tarball at build/artifacts/hot_tracks.tar.gz
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
APP_ID=hot_tracks
STAGE_ROOT="${ROOT}/build/artifacts"
APP_DIR="${STAGE_ROOT}/${APP_ID}"
ARCHIVE="${STAGE_ROOT}/${APP_ID}.tar.gz"
KEY_FILE="${APP_PRIVATE_KEY_FILE:-${HOME}/.nextcloud/certificates/${APP_ID}.key}"

if [[ ! -f "${KEY_FILE}" ]]; then
	echo "Missing private key: ${KEY_FILE}" >&2
	echo "Set APP_PRIVATE_KEY_FILE or place the key at ~/.nextcloud/certificates/${APP_ID}.key" >&2
	exit 1
fi

export COPYFILE_DISABLE=1
rm -rf "${STAGE_ROOT}"
mkdir -p "${APP_DIR}"

INCLUDE=(
	appinfo
	css
	img
	js
	lib
	l10n
	templates
	CHANGELOG.md
	LICENSE
	README.md
)

for name in "${INCLUDE[@]}"; do
	src="${ROOT}/${name}"
	if [[ -d "${src}" ]]; then
		cp -a "${src}" "${APP_DIR}/"
	elif [[ -f "${src}" ]]; then
		cp -a "${src}" "${APP_DIR}/"
	else
		echo "Missing required path: ${src}" >&2
		exit 1
	fi
done

# Drop macOS junk if present
find "${APP_DIR}" -name '.DS_Store' -delete

python3 - "${APP_DIR}" "${KEY_FILE}" <<'PY'
import base64, hashlib, json, subprocess, sys
from pathlib import Path

app = Path(sys.argv[1])
key = Path(sys.argv[2])

exclude_names = {
	'.git', '.gitignore', '.gitattributes', '.gitmodules',
	'.github', '.travis.yml', '.scrutinizer.yml',
	'Makefile', 'package.json', 'package-lock.json', 'composer.json', 'composer.lock',
	'node_modules', 'tests', 'test', 'src', 'preview', 'scripts', 'build',
	'vite.config.js', '.env', '.DS_Store',
}

hashes = {}
for path in sorted(app.rglob('*')):
	if not path.is_file():
		continue
	rel = path.relative_to(app).as_posix()
	parts = Path(rel).parts
	if any(part in exclude_names for part in parts) or path.name in exclude_names:
		continue
	hashes[rel] = hashlib.sha512(path.read_bytes()).hexdigest()

payload = json.dumps(hashes, separators=(',', ':'), ensure_ascii=False).encode()
sig = subprocess.check_output(['openssl', 'dgst', '-sha512', '-sign', str(key)], input=payload)
signature = {
	'hashes': hashes,
	'signature': base64.b64encode(sig).decode('ascii'),
}
(app / 'appinfo' / 'signature.json').write_text(json.dumps(signature, indent='\t') + '\n', encoding='utf-8')
print(f'signature.json written ({len(hashes)} files)')
PY

tar -czf "${ARCHIVE}" -C "${STAGE_ROOT}" "${APP_ID}"
VERSION="$(python3 - <<PY
import re
from pathlib import Path
text = Path('${APP_DIR}/appinfo/info.xml').read_text()
print(re.search(r'<version>([^<]+)</version>', text).group(1))
PY
)"
cp -f "${ARCHIVE}" "${STAGE_ROOT}/${APP_ID}-${VERSION}.tar.gz"

echo "Created ${ARCHIVE}"
echo "Created ${STAGE_ROOT}/${APP_ID}-${VERSION}.tar.gz"
tar -tzf "${ARCHIVE}" | head -20
echo "..."
echo "files: $(tar -tzf "${ARCHIVE}" | wc -l | tr -d ' ')"
