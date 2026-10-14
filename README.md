# NapThe

NapThe is an experimental Vietnamese prepaid card donation plugin for PocketMine-MP. It submits card details to TrumThe and tracks confirmed donation totals. The plugin manifest declares minimum API `5.0.0`, and the source requires PHP 8.2 or newer. Disposable Axolotl-PM 5.49.1 boots have been checked without credentials and with dummy credentials, but the live payment service and a wider server version range have not been verified.

> [!WARNING]
> An API credential was previously committed to this repository. Removing it from current source does not revoke the exposed value in Git history. Revoke that credential with the provider and issue a new one before any real transaction. Do not reuse the exposed credential.

## Configuration and dependencies

Set `TRUMTHE_PARTNER_ID` and `TRUMTHE_PARTNER_KEY` in the server process environment. Without both values, the plugin does not start payment tasks and its commands report that payment is unavailable. Do not put credentials in source, a PHAR, GitHub Actions logs or a public configuration file.

The server PHP build also needs `pdo_sqlite`. When payment credentials are configured, the plugin imports existing totals from `naptheData.yml` into `donations.sqlite` once. It records each successful provider request ID and the updated total in one SQLite transaction, then refreshes the YAML totals used by the leaderboard. Keep both files when backing up or moving the plugin. If the ledger cannot open or import the existing data, payment tasks do not start.

The provider's [migration notice](https://trumthe.vn/) directs API clients to move from its old hostname to the [current provider domain](https://doithegiagoc.com/). The default endpoint now uses `https://doithegiagoc.com/chargingws/v2`. Set `TRUMTHE_API_URL` to an HTTPS endpoint confirmed by the provider for your account if it uses a different route. The plugin rejects overrides that use HTTP or embed URL credentials. The POST response contract and live payment flow have not been verified, so endpoint reachability does not establish payment compatibility.

The plugin sends `application/x-www-form-urlencoded` data over HTTPS with POST. This request format and the charge signature are cross-checked against the public PocketMine-MP 5 integration in [ClickedTran/DonateGUI](https://github.com/ClickedTran/DonateGUI/blob/Master/src/ClickedTran/DonateGUI/api/DonateAPI.php), which targets `thesieure.com` and is a third-party implementation reference, not official confirmation for TrumThe's current host. The plugin does not implement an incoming HTTP callback endpoint. Configure the provider according to its current official instructions and do not point a reverse proxy at the Minecraft server expecting a callback handler here. Form classes are bundled under `src/utils/FormAPI/`, with their LGPL-3.0 notice in `licenses/`.

## Commands and installation

The `napthe` command opens the card submission form. The `topnapthe` command shows donation totals. Their permissions are `napthe.command.napthe` and `napthe.command.topnapthe`, respectively. Both default to enabled in `plugin.yml`.

Install only in a disposable PocketMine-MP test server. [DevTools](https://github.com/NhanAZ/DevTools) builds and validates the plugin PHAR. The build includes the bundled FormAPI license notice through `devtools.yml`. A local PHAR passed structural inspection and booted without credentials. Both commands reported that payment was unavailable, and the server shut down normally. No live payment flow has been verified. Do not use this source with real cards until the payment safety work below is complete.

## Current verification limits

The build workflow runs PHPStan at maximum level against the declared minimum API source and a pinned tested Axolotl-PM source revision. It builds a PHAR and checks its contents and checksum. It does not publish an artifact or release. Local fixtures verify the encoded provider request and that rate-limited and inconclusive status checks leave payments pending for later retries. SQLite stores donation totals, processed request IDs and the minimal metadata needed to restore pending checks after a restart; card codes and serials are not written to the ledger. A recovered check uses a request-ID-only status request, while in-memory checks include the original card fields. The request-ID-only recovery contract is not documented publicly and remains an unverified assumption; the third-party reference integration reuses the original card fields for checks. Tests verify recovery, finalization and duplicate protection with simulated provider responses. A disposable server with dummy credentials imported a legacy total and kept it unchanged across two boots. There is no tested live payment flow, and TrumThe's current request and response contract remains unverified. The code does not grant economy coins or items. Do not use this plugin with real cards until the provider contract and a live payment flow have been verified without exposing credentials or using real customer cards.

The root source has no declared license. Preserve the bundled FormAPI notice and resolve the license for NapThe before distributing a modified build. For project support, use [NhanAZ Discord](https://discord.gg/j2X83ujT6c).
