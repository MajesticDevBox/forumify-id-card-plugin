# milsim-id-card-plugin

A [Forumify](https://forumify.net) plugin (`majesticdev/milsim-id-card-plugin`) that
issues **fictional MILSIM personnel ID cards** for Spearhead Gaming. Every card permanently
displays `MILSIM / TRAINING · NOT A GOVERNMENT ID` — no rank, government seal, or real DoD
identifier is ever used. See [README.md](README.md) for the full card-issuing/verification
flow and [`VALIDATION.md`](VALIDATION.md) for what's been checked.

Built for this one community, not a general-purpose skeleton.

## The ecosystem

Sibling repos under `G:\Github Repos`: **commandnet-plugin** (optional data source — see
`CommandNetCardProvider`, one of two `AbstractCardProvider` implementations alongside
`MilhqCardProvider` for `forumify-milhq-plugin`; manual card entry works without either),
**commandnet-s3-plugin**, **commandnet-discord-plugin**, **commandnet-discord-bot**,
**command-net-theme**.

## Structure

```
src/
  Entity/IdentificationCard.php     the card itself: name, org lines, photo, member ID, dates
  Entity/UnitMapping.php             maps a source org (e.g. commandnet Unit) to card org lines
  Service/CardIssuer.php              creation/regeneration, incl. the 6-digit Member ID collision retry
  Service/CardRenderer.php             renders the card image
  Service/ExpirationCalculator.php      issue date + configured years, Feb 29 -> Feb 28 handled
  Service/CommandNetCardProvider.php     pulls org data from commandnet-plugin, if installed
  Service/MilhqCardProvider.php           same, for forumify-milhq-plugin
  Service/PhotoStorage.php               portrait upload storage
  Service/QrCodeGenerator.php             verification QR
  Controller/VerificationController.php    public "is this card valid" check
  Discord/Command/                        Discord slash command(s) for card lookup
  Command/SyncCardsCommand.php             bulk sync from a card provider
  Command/PrunePhotosCommand.php            cleanup for orphaned photo uploads
```

## Local dev

Developed via a Composer **path repository** — the real Forumify app is `~/dev/forumify` in
WSL, whose `composer.json` points a `path` repo at this directory's WSL path
(`/mnt/g/Github Repos/forumify-id-card-plugin`). Requires the `gd` PHP extension.

```bash
bin/console forumify:plugins:refresh
bin/console doctrine:migrations:migrate
bin/console assets:install public
```
Enable **ID Cards** in Forumify's plugin admin *before* running migrations (per README — if
activation already runs migrations on this install, the follow-up migrate command is safe to
run again regardless).

Note this repo (unlike its siblings) has its own `vendor/`, `composer.lock`, and `var/`
checked in/present locally — check whether that's intentional project state or leftover
before assuming the path-repo pattern is identical to the other plugins.

## Testing

```bash
./vendor/bin/phpunit -c phpunit.xml.dist
```

## Gotchas learned the hard way

- **Expiration math has a specific leap-year rule**: issue date + configured years (1–20,
  default 5), and if that lands on Feb 29 in a non-leap year it becomes Feb 28 — not Mar 1.
  `ExpirationCalculator` is the single place this is encoded; don't recompute it inline
  elsewhere.
- **Member ID generation retries on unique-constraint collision** rather than checking
  existence first (`CardIssuer`) — a cryptographically random 6-digit ID with leading zeroes,
  so collisions are rare but handled, not assumed impossible.
- **Revocation always takes precedence over expiration** — a revoked-but-not-yet-expired card
  is still invalid; check `CardStatusResolver`, not just the expiration date, when determining
  validity anywhere new.
