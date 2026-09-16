# Validation record

## Executed locally

- PHP 8.4.25, Composer stable dependency resolution: Forumify 1.2.3, Endroid QR Code 6.1.3. MILHQ is not a mandatory dependency.
- `composer validate --strict`: valid.
- PHPUnit: **20 tests, 172 assertions passed**, including domain and Symfony HTTP integration tests. Originally 13 tests/148 assertions covering the manual/MILHQ path only; extended to cover the Command Net personnel source added afterward.
- SQLite migration test now applies both the original and the Command Net migration to the same schema before comparing against the entity mapping, so a future column drift on either one would fail the test rather than being asserted against a stale baseline.
- Manual create → public verification → revoke → public REVOKED state, with private notes excluded.
- Unknown verification token returns 404; anonymous administration is rejected; invalid CSRF is rejected.
- Six-digit leading-zero IDs, collision retry selection, leap-year calculations, revocation precedence, optional MILHQ absence, selective sync preservation and mapped organization lines.
- Command Net: optional-absence contract, unit-name fallback with no mapping table and no warning ever set (unlike MILHQ), discharge-only terminal status, sync preserving owned fields, sync as a no-op for other sources, the two admin routes' authorization (`/admin/id-cards/commandnet/search`, `/admin/id-cards/create-from-commandnet/{id}`) including the unavailable-record 404 path.
- Browser Chrome: editable name, regenerate ID, synthetic portrait upload and preview, leap-day calculation, manual save, export download, verification link.
- Export: 1200×756 PNG; QR independently decoded using jsQR and resolved to the correct member verification page.
- Desktop 1440px and mobile 390px inspected; no mobile horizontal overflow. Browser run recorded no page JavaScript errors.

## Boundaries

The host fixture uses the **actual plugin bundle and installed Forumify 1.2.3 dependencies**, Symfony forms/controllers/security and Doctrine. Its surrounding layout and authorization voter are test fixtures. The installed dependencies and source references were not modified.

No existing deployed Forumify installation or database was supplied. Therefore activation in the user's installation, real Forumify role assignments, production MySQL/MariaDB migrations, installed MILHQ personnel/asset data, scheduled sync, reverse-proxy behavior and external image storage remain staging acceptance checks. Optional-provider tests exercise the isolated integration contract; they do not certify the user's live MILHQ data.

The downloadable sample card uses a synthetic silhouette and a local-only verification URL. It illustrates the export and is not an issued community credential. Configure the production verification origin before creating actual community cards.

## Architecture references inspected

- Forumify platform 1.2.2 source (`1dd633c`) and Composer-installed 1.2.3: plugin base class, metadata, permission naming, kernel plugin registration, settings repository and Twig admin inheritance.
- [Forumify platform source](https://github.com/forumify/forumify-platform)
- [MILHQ source](https://github.com/forumify/forumify-milhq-plugin): Soldier/Unit entities, repositories, asset package names, plugin configuration and admin menu conventions.

No real government branding, military seals or rank fields are included. The fictional-ID footer cannot be removed through settings.
