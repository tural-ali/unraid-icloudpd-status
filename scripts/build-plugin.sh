#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
version="$(tr -d '[:space:]' < "$root/VERSION")"
output="$root/plugin/icloudpd-status.plg"
temporary="$output.tmp"
sources=(status.php widget.php widget-poll.php auth-terminal.php auth-terminal-launch.sh IcloudpdStatus.page)

mkdir -p "$root/plugin"

{
  printf '%s\n' "<?xml version='1.0' standalone='yes'?>"
  printf '%s\n' '<!DOCTYPE PLUGIN ['
  printf '%s\n' '<!ENTITY name      "icloudpd-status">'
  printf '%s\n' '<!ENTITY author    "tural-ali">'
  printf '%s\n' "<!ENTITY version   \"$version\">"
  printf '%s\n' '<!ENTITY github    "tural-ali/unraid-icloudpd-status">'
  printf '%s\n' '<!ENTITY pluginURL "https://raw.githubusercontent.com/&github;/main/plugin/&name;.plg">'
  printf '%s\n' ']>'
  printf '\n'
  printf '%s\n' '<PLUGIN name="&name;" author="&author;" version="&version;" pluginURL="&pluginURL;"'
  printf '%s\n' '        min="6.12.0" support="https://github.com/&github;/issues" icon="cloud">'
  printf '\n'
  printf '%s\n' '<CHANGES>'
  printf '%s\n' "$version"
  printf '%s\n' '- Detects partial Apple sessions that are missing the required MFA trust marker.'
  printf '%s\n' '- Keeps Retry authentication visible until Apple authentication is genuinely complete.'
  printf '%s\n' '</CHANGES>'
  printf '\n'

  for source in "${sources[@]}"; do
    payload="$(base64 < "$root/$source" | tr -d '\n')"
    printf '%s\n' "<FILE Name=\"/tmp/$source.b64\">"
    printf '%s\n' '<INLINE>'
    printf '%s\n' "$payload"
    printf '%s\n' '</INLINE>'
    printf '%s\n\n' '</FILE>'
  done

  printf '%s\n' '<FILE Run="/bin/bash">'
  printf '%s\n' '<INLINE>'
  printf '%s\n' '<![CDATA['
  printf '%s\n' 'set -e'
  printf '%s\n' 'PLG=/boot/config/plugins/icloudpd-status'
  printf '%s\n' 'WEB=/usr/local/emhttp/plugins/icloudpd-status'
  printf '%s\n' 'mkdir -p "$PLG" "$WEB"'
  printf '%s\n' 'for file in status.php widget.php widget-poll.php auth-terminal.php IcloudpdStatus.page; do'
  printf '%s\n' '  [ -f "/tmp/$file.b64" ] || { echo "ERROR: Missing /tmp/$file.b64"; exit 1; }'
  printf '%s\n' '  base64 -d < "/tmp/$file.b64" > "$PLG/$file"'
  printf '%s\n' '  chmod 600 "$PLG/$file"'
  printf '%s\n' '  install -m 600 "$PLG/$file" "$WEB/$file"'
  printf '%s\n' '  rm -f "/tmp/$file.b64"'
  printf '%s\n' 'done'
  printf '%s\n' '[ -f "/tmp/auth-terminal-launch.sh.b64" ] || { echo "ERROR: Missing /tmp/auth-terminal-launch.sh.b64"; exit 1; }'
  printf '%s\n' 'base64 -d < "/tmp/auth-terminal-launch.sh.b64" > "$PLG/auth-terminal-launch.sh"'
  printf '%s\n' 'chmod 700 "$PLG/auth-terminal-launch.sh"'
  printf '%s\n' 'install -m 700 "$PLG/auth-terminal-launch.sh" "$WEB/auth-terminal-launch.sh"'
  printf '%s\n' 'rm -f "/tmp/auth-terminal-launch.sh.b64"'
  printf '%s\n' 'rm -f /var/local/emhttp/icloudpd-status-cache.json'
  printf '%s\n' 'echo "icloudpd-status installed. The Dashboard tile discovers all iCloudPD containers automatically."'
  printf '%s\n' ']]>'
  printf '%s\n' '</INLINE>'
  printf '%s\n\n' '</FILE>'

  printf '%s\n' '<FILE Run="/bin/bash" Method="remove">'
  printf '%s\n' '<INLINE>'
  printf '%s\n' '<![CDATA['
  printf '%s\n' 'rm -rf /usr/local/emhttp/plugins/icloudpd-status'
  printf '%s\n' 'rm -f /var/local/emhttp/icloudpd-status-cache.json'
  printf '%s\n' 'echo "icloudpd-status removed. Persistent source files were kept on flash."'
  printf '%s\n' ']]>'
  printf '%s\n' '</INLINE>'
  printf '%s\n' '</FILE>'
  printf '\n'
  printf '%s\n' '</PLUGIN>'
} > "$temporary"

mv "$temporary" "$output"
printf 'Built %s\n' "$output"
