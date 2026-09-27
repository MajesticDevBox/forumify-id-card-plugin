# Changelog

## Unreleased

## v1.1.2

- Fix a `qualifications`/`awards` migration that failed outright on MySQL (error 1101,
  a JSON column can't have a `DEFAULT` value). Columns are now added nullable, backfilled
  to `[]`, then tightened to `NOT NULL`. **v1.1.1 is broken and should not be used** —
  upgrading straight to v1.1.2 is safe even if a v1.1.1 upgrade attempt partially applied.

## v1.1.1

- The public QR-scan verification page now shows rank, specialty, callsign, qualifications
  (with tier), and awards pulled from Command Net, opt-in per card via a new "sync
  qualifications" toggle. Already-issued Command Net cards default to this being on after
  upgrading. MILHQ cards are unaffected.

## v1.1.0

- (no release notes recorded for this tag)

## v1.0.0

- Initial release.
