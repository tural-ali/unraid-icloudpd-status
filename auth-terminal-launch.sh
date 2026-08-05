#!/bin/bash
set -euo pipefail

name="${1:-}"
if [[ ! "$name" =~ ^[A-Za-z0-9_.-]+$ ]]; then
  exit 2
fi

/usr/bin/docker inspect --type container "$name" >/dev/null 2>&1

socket="/var/tmp/${name}.sock"
log="/var/tmp/icloudpd-status-${name}-terminal.log"
pid_file="/var/tmp/icloudpd-status-${name}-terminal.pid"

rm -f "$socket" "$log" "$pid_file"

source /etc/default/ttyd

if /usr/bin/docker exec "$name" test -f /config/python_keyring/keyring_pass.cfg >/dev/null 2>&1; then
  action=(/usr/local/bin/reauth.sh)
else
  action=(/usr/local/bin/sync-icloud.sh --Initialise)
fi

# TTYD_OPTS is maintained by Unraid as a shell-ready option string.
# shellcheck disable=SC2086
/usr/bin/ttyd -d2 $TTYD_OPTS -t closeOnDisconnect=false -s9 -om1 -i "$socket" \
  /usr/bin/docker exec -it "$name" "${action[@]}" >"$log" 2>&1 &

printf '%s\n' "$!" > "$pid_file"
