#!/usr/bin/env bash
# Runs the official WordPress.org Plugin Check against this plugin in a throwaway Docker WordPress.
#
#   bin/plugin-check.sh            check our own code as it would ship (.distignore applied, excludes bundled vendor/ libraries)
#   bin/plugin-check.sh --vendor   include vendor/ and providers/CSV2/vendor/ too
#   bin/plugin-check.sh --json     machine readable output
#   bin/plugin-check.sh --down     remove the containers and volumes
#
# Needs Docker. The first run pulls images and installs Plugin Check from wordpress.org.
set -euo pipefail

cd "$(dirname "$0")/.."
# Docker Desktop ships its CLI and credential helpers here; they are not always on PATH.
[ -d /Applications/Docker.app/Contents/Resources/bin ] && export PATH="$PATH:/Applications/Docker.app/Contents/Resources/bin"
DOCKER="$(command -v docker || true)"
[ -z "$DOCKER" ] && [ -x /Applications/Docker.app/Contents/Resources/bin/docker ] && DOCKER=/Applications/Docker.app/Contents/Resources/bin/docker
[ -z "$DOCKER" ] && { echo "docker not found" >&2; exit 1; }

# Check what would actually ship: a copy of the plugin with .distignore applied (no .git, AGENTS.md, bin/ etc).
DIST="build/plugin-check/culture-object"
mkdir -p "$DIST"
rsync -a --delete --exclude-from=.distignore ./ "$DIST/"
export PLUGIN_SRC="$PWD/$DIST"

COMPOSE=("$DOCKER" compose -f .dev/plugin-check/compose.yml)
WP=("${COMPOSE[@]}" run --rm -T cli wp)
PLUGIN=culture-object

INCLUDE_VENDOR=0
FORMAT=table
for arg in "$@"; do
	case "$arg" in
		--vendor) INCLUDE_VENDOR=1 ;;
		--json) FORMAT=json ;;
		--down) "${COMPOSE[@]}" down -v --remove-orphans; exit 0 ;;
		*) echo "Unknown option: $arg" >&2; exit 2 ;;
	esac
done

"${COMPOSE[@]}" up -d db wordpress >&2

echo "Waiting for WordPress files..." >&2
until "${COMPOSE[@]}" exec -T wordpress test -f /var/www/html/wp-config.php 2>/dev/null; do sleep 2; done

if ! "${WP[@]}" core is-installed 2>/dev/null; then
	echo "Installing WordPress..." >&2
	until "${WP[@]}" db check >/dev/null 2>&1; do sleep 2; done
	"${WP[@]}" core install --url=http://localhost --title="CO Plugin Check" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email >&2
fi

"${WP[@]}" plugin is-installed plugin-check 2>/dev/null || "${WP[@]}" plugin install plugin-check --activate >&2
"${WP[@]}" plugin activate plugin-check >/dev/null 2>&1 || true
"${WP[@]}" plugin activate "$PLUGIN" >&2

ARGS=("$PLUGIN" "--format=$FORMAT")
if [ "$INCLUDE_VENDOR" = "0" ]; then
	ARGS+=("--exclude-directories=vendor,providers/CSV2/vendor")
fi

echo "Running Plugin Check (vendor: $([ "$INCLUDE_VENDOR" = 1 ] && echo included || echo excluded))..." >&2
"${WP[@]}" plugin check "${ARGS[@]}"
