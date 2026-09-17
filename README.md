# Forumify ID Card Plugin

`majesticdev/forumify-id-card-plugin` creates **fictional MILSIM personnel cards** for Spearhead Gaming. The card permanently displays **MILSIM / TRAINING · NOT A GOVERNMENT ID**. No rank, government seal, or real DoD identifier is used.

## Requirements

- PHP 8.4+ with GD, plus Forumify's normal extensions.
- Forumify **1.2.2–1.2.x**; Composer installs stable versions without changing minimum-stability.
- Optional `forumify/forumify-milhq-plugin` 1.2.x. Manual cards work without it.
- Modern browser with Canvas support for PNG export.

## Installation

Once the package has been published to Packagist or your private Composer repository:

```sh
composer require majesticdev/forumify-id-card-plugin
php bin/console cache:clear
php bin/console doctrine:migrations:migrate
php bin/console assets:install public
```

Enable **ID Cards** in Forumify's plugin administration before running migrations. If activation already runs migrations on your installation, the subsequent migration command is safe. Clear the cache after activation and assign the permissions below to the relevant administrator role.

This source package is not automatically published by generating it. For local installation before publishing, add a Composer `path` repository pointing at this directory and set its package version to `0.1.0` in that repository's `options.versions`, then require `majesticdev/forumify-id-card-plugin:^0.1`. This does not weaken production minimum-stability. Tag releases `0.1.0`, `0.2.0`, `1.0.0` as development progresses. Do not use a fabricated dev-main alias as a release.

The bundle class and Composer metadata follow Forumify's plugin registration. Entity and migration paths are registered relative to the bundle, including path-repository installations. No Forumify or MILHQ core files need changes. Assets are plain browser modules, so there is no extra frontend build step.

## Creating a card

Open **Identification Cards → Create Card**. Choose **Manual Entry**, enter a display name, adjust organization lines and upload a portrait if desired. The form supplies a cryptographically generated six-digit Member ID, including leading zeroes, and a creation-date issue date. **Regenerate** picks another ID; saving retries concurrent creation collisions against the database unique constraint.

The default organization is:

- 2nd Ranger Battalion
- Misfit Company
- Misfit - 1 C

Expiration is five calendar years after the issue date. February 29 becomes February 28 in a non-leap year. Cards remain valid through 23:59:59 on the expiration date in the application's timezone. Changing the issue date recalculates expiration unless **Override calculated expiration** is checked. Settings can change the default years for future calculations (1–20). Revocation always takes precedence over expiration.

The right column previews the card; on narrow screens it moves below the editor. Reset restores the initially loaded form. Save before downloading so the QR points to an issued card. Issued Member ID changes require checking the explicit confirmation field. QR tokens do not change on edit.

## MILHQ integration

Choose **MILHQ Soldier**, search by name, and select a record. Name, unit mapping and the available portrait populate the form. Administrator text overrides are allowed. The server resolves the selected Soldier ID itself; submitted photo paths and personnel objects are never trusted. Rank is never read.

The provider checks both class availability and Doctrine mapping before accessing the optional Soldier repository. Its entities have no relationships to MILHQ. If MILHQ is removed or the soldier disappears, existing card data remains accessible. Linked records cannot be newly created against missing personnel. Editing an unavailable linked card disables auto sync and displays a warning.

Photo fallback is custom upload → MILHQ uniform → linked Forumify avatar → silhouette. You can explicitly select the default placeholder. Custom uploads are decoded, validated, resized and re-encoded as PNG. Limits: 5 MB, minimum 80×80, maximum 24 megapixels. Files use random names in `public/storage/id-cards`; that directory must be writable by PHP. Storage contains public card images; never upload a private document. Original EXIF metadata is removed by re-encoding. Uploading a new custom photo, switching a card away from the custom photo source, or deleting a card entirely never deletes the old file automatically. Run `bin/console id-cards:prune-photos` to list orphaned files (nothing is deleted), or `bin/console id-cards:prune-photos --delete` to remove them; it's opt-in and not scheduled anywhere — wire it to your own cron if you want it to run automatically.

### Unit mapping

Under **Unit Mapping**, enter the MILHQ unit ID and a readable name, then its three card organization lines. A unique unit ID prevents ambiguous mappings. Existing mappings can be edited, disabled or deleted. Without an enabled mapping, the provider keeps the configured first two lines and uses the unit's name for line three, with a non-blocking warning.

### Sync

**Sync Now** updates only enabled sync flags. Member ID, issue date, expiration and QR token remain card-owned. Custom portraits remain in place. MILHQ statuses are community-defined labels; only `discharged`, `revoked` and `terminated` (case-insensitive) revoke when status sync is enabled. Other labels never reactivate revoked cards.

Cards with **Auto Sync From MILHQ** checked can be processed by your scheduler:

```sh
php bin/console id-cards:sync
```

For example, run this hourly using your existing scheduler. No scheduler is necessary to detect expiration. Missing personnel records are skipped with a message. This command does not create records or reset revocations.

## Verification and revocation

QR codes contain only the absolute `/id/{64-character-random-token}` verification URL. They do not contain personal JSON, email, Steam IDs, notes or sequential database IDs. Public verification shows the same public card fields and computed ACTIVE / EXPIRED / REVOKED state. Unknown or deleted tokens return 404. Responses are not cached and are marked noindex. The route is rate-limited per IP address (`id_cards.verify`, 20 requests/minute by default — adjust via `framework.rate_limiter.limiters.id_cards.verify` in your app config); this doesn't make a token guessable, it just slows down a scripted scan of many tokens.

The QR is a bearer link: anyone possessing it can see the public card fields. It is a community roster lookup, not proof of a real-world identity. Configure the optional canonical HTTPS origin before issuing cards. Keep the host stable; changing a host cannot alter already downloaded QR images. Ensure reverse-proxy trusted-host settings are correct in the parent application. If your parent Forumify security configuration protects every URL, add a `PUBLIC_ACCESS` rule for `^/id/[a-f0-9]{64}$` before that catch-all; this plugin does not weaken the parent's security configuration.

Revoke from the detail page, provide a reason, and check confirmation. Public verification changes immediately. Issue a new card for a replacement; it receives a new token. Deletion requires its own explicit confirmation and makes the previous link return 404.

## Permissions

Forumify derives the prefix `id-cards` from the plugin name. All are enforced on the server:

| Permission | Capability |
|---|---|
| `id-cards.admin.id_cards.view` | List and view cards |
| `id-cards.admin.id_cards.create` | Create cards, personnel lookup, generate IDs |
| `id-cards.admin.id_cards.manage` | Edit, sync, personnel lookup, generate IDs |
| `id-cards.admin.id_cards.revoke` | Revoke with confirmation |
| `id-cards.admin.id_cards.delete` | Delete with confirmation |
| `id-cards.admin.id_cards.download` | Export page |
| `id-cards.admin.id_card_configuration.manage` | Settings |
| `id-cards.admin.id_card_configuration.unit_mapping` | Unit mappings |

Grant `view` alongside action permissions for normal navigation. Download permission controls the export page; like any web image preview, visible card pixels can still be captured by someone with view access. Forms and mutations use CSRF validation.

## PNG export

Open a saved card → **Download PNG**. Export uses the same Canvas drawing function as the live preview at **1200×756**, with a CR80-style aspect ratio, a scannable QR, centered portrait cropping and a decorative chip. PNG contains no editor controls. Canvas rendering is isolated in `public/card.js` so an alternative PDF renderer can be introduced later.

Uploads served by this plugin are same-origin. If Forumify serves avatars or uniforms from an external storage host, that host must allow CORS image access. Failed images prevent export and display a message instead of silently creating an incomplete card. The logo is optional; the built-in geometric SG mark is a placeholder, not an official seal.

## Development and validation

```sh
composer install
composer validate --strict
composer test
```

The test suite covers domain rules, a portable migration against SQLite, Doctrine schema agreement, optional-provider behavior, sync preservation and Symfony request flows. The HTTP fixture uses the real plugin bundle with a minimal host layout and test authorization voter. It is not a substitute for your deployed Forumify installation's roles, theme, database and storage configuration. See `VALIDATION.md` for executed checks and remaining acceptance boundaries.

Production release gates: run migrations on a staging copy of your target database, assign real Forumify roles, verify MILHQ records and remote image storage, scan a downloaded PNG, and inspect the admin theme on desktop/mobile. There are no changes to vendor code.

## Troubleshooting

- Missing menu: enable the plugin, clear cache and grant its permissions.
- Missing card styling: run `assets:install public` after installing/updating.
- Manual creation rejected: inspect form validation, upload dimensions and CSRF/session configuration.
- MILHQ unavailable: confirm the optional package is installed and enabled with registered entity mappings.
- Mapping not used: use the unit's numeric ID, and enable the mapping.
- QR opens the wrong host: configure the canonical origin before issuing new exports.
- Export image error: check file availability and remote storage CORS.
- Composer cannot locate the package: publish a tagged release to your repository or use the local path installation above; the package name alone does not publish it.
