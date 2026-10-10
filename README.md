# NapThe

NapThe is an experimental Vietnamese prepaid card donation plugin for PocketMine-MP. It submits card details to TrumThe and tracks confirmed donation totals. The plugin manifest declares minimum API `5.0.0`. Compatibility with a current Axolotl-PM release and the live payment service has not been verified.

> [!WARNING]
> An API credential was previously committed to this repository. Removing it from current source does not revoke the exposed value in Git history. Revoke that credential with the provider and issue a new one before any real transaction. Do not reuse the exposed credential.

## Configuration and dependencies

Set `TRUMTHE_PARTNER_ID` and `TRUMTHE_PARTNER_KEY` in the server process environment. The plugin disables itself when either value is missing. Do not put credentials in source, a PHAR, GitHub Actions logs or a public configuration file.

The plugin sends JSON over HTTPS with POST to the endpoint defined in `src/Constant.php`. It does not implement an incoming HTTP callback endpoint. Configure the provider according to its current official instructions and do not point a reverse proxy at the Minecraft server expecting a callback handler here. Form classes are bundled under `src/utils/FormAPI/`, with their LGPL-3.0 notice in `licenses/`.

## Commands and installation

The `napthe` command opens the card submission form. The `topnapthe` command shows donation totals. Their permissions are `napthe.command.napthe` and `napthe.command.topnapthe`, respectively. Both default to enabled in `plugin.yml`.

Install only in a disposable PocketMine-MP test server after creating a PHAR from this source. [DevTools](https://github.com/NhanAZ/DevTools) is the project build tool, but a PHAR and server runtime for this revision have not yet been verified. Do not use this source with real cards until the payment safety work below is complete.

## Current verification limits

This repository has no hosted CI, verified DevTools PHAR, stable release or tested live payment flow. Pending payments are held in memory, so a restart can lose their check state. Successful donation totals are stored in `naptheData.yml`, but the code does not grant economy coins or items. Before production use, implement durable pending payment and idempotency records, test restart and duplicate responses, verify provider behavior without real cards, and check PHPStan at maximum level plus a disposable server runtime.

The root source has no declared license. Preserve the bundled FormAPI notice and resolve the license for NapThe before distributing a modified build. For project support, use [NhanAZ Discord](https://discord.gg/j2X83ujT6c).
