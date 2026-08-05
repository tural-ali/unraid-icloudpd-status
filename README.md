# iCloudPD Status for Unraid

A native Unraid Dashboard plugin for monitoring every iCloudPD Docker container on the server.

It automatically discovers current and future containers when either the container name or image name contains `icloudpd`.
Each instance gets its own status card and progress bar.
The Dashboard starts with an aggregate summary, then presents each instance as a compact expandable card.

## What it shows

- Container and health status.
- Apple MFA cookie validity.
- A direct interactive reauthentication action when authentication fails.
- Active Primary or Shared Library.
- Downloaded and total photo/video item count.
- Downloaded bytes and estimated final library size.
- Remaining size, estimated completion time, and a measured 10-minute transfer-rate sparkline.
- Explicit download phase and relative last activity.
- Archive size, file count, current transfer rate, partial downloads, errors, and restarts.

The Dashboard refreshes every 15 seconds.

The primary view is progress-first and optimized for any number of instances.
A sole instance and the first active download open automatically.
Manual expansion state is preserved across refreshes.
Transfer and technical archive metrics remain hidden until an instance is expanded.

## Installation

In Unraid, open **Plugins**, select **Install Plugin**, and paste:

```text
https://raw.githubusercontent.com/tural-ali/unraid-icloudpd-status/main/plugin/icloudpd-status.plg
```

The plugin appears on the Unraid Dashboard as **iCloudPD Archives**.

Command-line installation is also supported:

```bash
plugin install https://raw.githubusercontent.com/tural-ali/unraid-icloudpd-status/main/plugin/icloudpd-status.plg
```

## Container requirements

The plugin is designed for [`boredazfcuk/docker-icloudpd`](https://github.com/boredazfcuk/docker-icloudpd) and compatible images.

For full reporting, a container should:

- Have `icloudpd` in its container name or image name.
- Mount the archive at `/home/user/iCloud` inside the container.
- Write the active sync log to `/tmp/icloudpd/icloudpd_sync.log`.
- Provide `/usr/local/bin/reauth.sh` for interactive MFA renewal.

Containers that do not follow all four conventions are still discovered, but some fields may remain unavailable.

## Authentication retry

When the container healthcheck or recent sync output reports an authentication failure, the card turns red and shows an authentication action.

For a new account without an iCloudPD keyring, the button opens an Unraid interactive terminal directly into:

```bash
/usr/local/bin/sync-icloud.sh --Initialise
```

For an account that has already been initialized, it opens:

```bash
/usr/local/bin/reauth.sh
```

Authentication opens in a normal browser tab through a plugin-owned ttyd launcher.
The launcher removes stale terminal sockets and keeps a diagnostic log instead of relying on Unraid's popup wrapper.

The plugin never reads or stores an Apple password, MFA code, or cookie content.

## Progress accuracy

Apple reports the item count but does not report the final byte size of a Photos library before download.

Downloaded size is measured from the active library folder.
Final size is estimated from the average downloaded item size and becomes more representative as the download progresses.

Counts are approximate because one Live Photo can create a photo and a paired video file.
The plugin deduplicates common `_HEVC.MOV` Live Photo pairs when calculating downloaded items.

## Updates

Unraid checks the manifest URL embedded in the plugin.
Installing a newer manifest updates the Dashboard files while retaining the normal iCloudPD containers and archives.

## Development

Build the self-contained plugin:

```bash
./scripts/build-plugin.sh
```

Validate the sources and embedded payloads:

```bash
./tests/validate.sh
```

## License

MIT
