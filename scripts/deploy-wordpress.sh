#!/usr/bin/env bash

set -euo pipefail

: "${SMITHARIA_SSH_HOST:?Set SMITHARIA_SSH_HOST to the configured SSH host alias}"
: "${SMITHARIA_WP_ROOT:?Set SMITHARIA_WP_ROOT to the WordPress document root}"

readonly REMOTE_HOST="$SMITHARIA_SSH_HOST"
readonly REMOTE_ROOT="$SMITHARIA_WP_ROOT"
readonly PLUGIN_PATH="wordpress/wp-content/plugins/smitharia-core"

if [[ ! -f "$PLUGIN_PATH/smitharia-core.php" ]]; then
  echo "Run this script from the repository root." >&2
  exit 1
fi

ssh "$REMOTE_HOST" "install -d -m 755 '$REMOTE_ROOT/wp-content/plugins/smitharia-core'"
rsync -a --exclude '/tests/' "$PLUGIN_PATH/" "$REMOTE_HOST:$REMOTE_ROOT/wp-content/plugins/smitharia-core/"
ssh "$REMOTE_HOST" "find '$REMOTE_ROOT/wp-content/plugins/smitharia-core' -name '*.php' -exec /usr/local/php82/bin/php -l {} \;"

echo "Smitharia Core uploaded and syntax-checked."
