# Course Date Shift Pro Publication Guide

## Scope

This repository contains only the Pro Moodle local plugin:

- `coursedateshiftpro`

Recommended GitHub repository name:

- `moodle-local_coursedateshiftpro`

## Pre-Publication Checklist

1. Confirm the plugin installs in Moodle.
2. Confirm the guided preview flow works correctly.
3. Confirm suggested balancing changes remain optional until the user explicitly enables them.
4. Confirm execution history, rollback, and Privacy API behavior.
5. Confirm all release-facing docs are current.
6. Confirm `.DS_Store` files are absent.
7. Confirm the release ZIP root is `coursedateshiftpro/`.

## Documentation to Review

- `README.md`
- `CHANGELOG.md`
- `MANUAL_EN.md`
- `docs/screenshots/README.md`
- `docs/screenshots/pro-1.9.2/README.md`

## GitHub Workflow

The repository uses:

- `.github/workflows/ci.yml`

The workflow runs Moodle Plugin CI with:

- PHP 7.4, 8.0, 8.1
- Moodle `MOODLE_401_STABLE`
- PostgreSQL and MariaDB

## Recommended Local Git Sequence

```bash
git status
git add .
git commit -m "Prepare Course Date Shift Pro for GitHub publication"
git branch -M main
git remote add origin https://github.com/antoniomexdf-boop/moodle-local_coursedateshiftpro.git
git push -u origin main
```

## GitHub Release Sequence

1. Push `main`.
2. Wait for the CI workflow to finish.
3. Fix CI findings if any appear.
4. Create the release tag.
5. Attach the Moodle ZIP package in the GitHub Release.

## Notes

- ZIP files should not be committed to Git.
- Screenshots should be captured before public release.
- Moodle submission assets should stay English-only for the first release.
- The curated screenshot set already lives inside `docs/screenshots/pro-1.9.2/`.
