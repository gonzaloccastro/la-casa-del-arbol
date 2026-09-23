# Theme deployment

How the `la-casa-del-arbol` child theme goes from this repository to the server, with `deploy-theme.ps1`.

The local Git repository is the source of truth. The server is never edited by hand, and Git is never initialized on the server.

## Configuration

| File | Versioned | Contents |
|---|---|---|
| `deploy-theme.ps1` | yes | The deployment logic. No environment values. |
| `deploy.example.ps1` | yes | Template with `CHANGE_ME` placeholders, documenting every setting. |
| `deploy.local.ps1` | **no, git-ignored** | The real environment values. Exists only on the machine that deploys. |

Settings in `deploy.local.ps1` (all required; any other key is rejected):

| Setting | Meaning |
|---|---|
| `RemoteHost` | SSH host name or IP |
| `RemotePort` | SSH port (number) |
| `RemoteUser` | SSH user |
| `RemoteHome` | The SSH user's home directory on the server (`$HOME`); backups and staging go here |
| `RemoteWpRoot` | WordPress root (contains `wp-load.php`); must be inside `RemoteHome` |
| `RemoteThemeDir` | Child theme directory, the only deploy target; must be exactly `<RemoteWpRoot>/wp-content/themes/la-casa-del-arbol` |

How the script treats the file:

- **Read as data, never executed.** It must contain exactly one `@{ ... }` hashtable of constant values. Variables, expressions, commands and unknown keys are rejected.
- **Validated before anything else runs.** The script fails clearly if the file is missing, a setting is missing, a `CHANGE_ME` placeholder remains, or a value has an invalid format (host, user, port range, absolute paths without `..` or special characters, WordPress root inside the home, theme dir consistent with the WordPress root).
- **Fixed for the whole run.** After loading, the values are read-only variables. There are no command-line parameters for host or paths.
- **Checked again on the server.** The remote side re-checks `$HOME` and the theme dir.
- **No credentials** are stored there or anywhere in the repository.

Setup on a new machine: copy `deploy.example.ps1` to `deploy.local.ps1` and fill in the values from the hosting panel's SSH details.

## Server layout

Paths are shown relative to the configured values:

| | |
|---|---|
| Deploy target | `<RemoteThemeDir>` |
| Backups | `<RemoteHome>/lcda-theme-backups/` (outside the web root, not web-accessible) |
| Upload and staging (temporary) | `<RemoteHome>/lcda-deploy-<id>.tar` and `<RemoteHome>/.lcda-deploy/<id>/` |

## Prerequisites

1. **Windows PowerShell 5.1+** with the built-in **OpenSSH client** (`ssh`, `scp`) and **Git** on `PATH`.
2. **`deploy.local.ps1`** exists and is filled in (see Configuration).
3. **SSH access works** with your existing authentication (key plus ssh-agent recommended, or password). The script stores no credentials. With password authentication you are prompted up to 3 times (verify, upload, deploy).
4. **The server's host key is already trusted.** The script uses `StrictHostKeyChecking=yes`, so it refuses to connect to an unknown or changed host key. First time only: run `ssh -p <RemotePort> <RemoteUser>@<RemoteHost>`, compare the fingerprint with the one shown in the hosting panel, accept it, and exit.
5. **The remote child theme directory already exists** and is the `la-casa-del-arbol` Astra child theme. The script refuses to create it or to deploy anywhere else.
6. **The theme changes are committed.** Only `HEAD` is deployed.

## What the script changes

Only:

- `wp-content/themes/la-casa-del-arbol/`: files from the release are added or overwritten.
- `~/lcda-theme-backups/`: one new backup archive per deployment.
- `~/lcda-deploy-<id>.tar` and `~/.lcda-deploy/<id>/`: temporary upload and staging, removed after a successful deploy.

## What the script never changes

- WordPress core (`wp-admin/`, `wp-includes/`, root PHP files, `wp-config.php`)
- `wp-content/plugins/`, `wp-content/uploads/`, other themes, **the Astra parent theme** (only read, to check that it exists and report its version)
- The database (no WP-CLI, no SQL)
- **No remote file is deleted.** Files on the server that are not in the release are listed as "not in this release" and left in place. Removing them is a separate, reviewed decision (see Orphan files).

## How it works

```
LOCAL (Windows)                                   SERVER (read-only until step 6)
0  load + validate deploy.local.ps1 (parsed as data, then read-only)
1  checks: git/ssh/scp, repo root, theme files,
   style.css identity, clean Git state for the theme
2  package = git archive HEAD:themes/la-casa-del-arbol
   (LF line endings), SHA-256, file count
3  ssh ──── verify (read-only) ─────────────────▶ account home, WordPress root, wp-config,
                                                  themes dir, child theme identity
                                                  (Template: astra, Text Domain), not a
                                                  symlink, Astra present; prints WP,
                                                  Astra, child and PHP versions
4  prompt: type DEPLOY  (anything else = cancel, exit 2)
5  scp ──── package ────────────────────────────▶ ~/lcda-deploy-<id>.tar
6  ssh ──── deploy ─────────────────────────────▶ a. re-verify target
                                                  b. move package to ~/.lcda-deploy/<id>/
                                                  c. SHA-256 must match; no absolute or ../
                                                     paths; no symlinks; file count must match
                                                  d. extract to staging; php -l every PHP file
                                                     (if PHP CLI exists)
                                                  ── nothing live has changed up to here ──
                                                  e. BACKUP live theme → tar.gz, verify entry
                                                     count == live file count
                                                  f. copy staging over the live theme
                                                     (overwrite and add, never delete)
                                                  g. verify every deployed file by SHA-256
                                                  h. list remote files not in the release
                                                  i. remove this deployment's staging dir
7  summary: commit, deployment id, backup path
```

The remote part is a bash script embedded in `deploy-theme.ps1`. It is sent base64-encoded inside the SSH command, so nothing extra is written to the server and Windows line endings can't corrupt it. It runs with `set -Eeuo pipefail`: any failed command stops it with a non-zero exit, and the PowerShell script stops on any non-zero exit.

**Failure behavior**

- If anything fails in steps 1–6d, the live theme was not touched.
- If step 6e (backup) fails, the live theme was not touched.
- If 6f or 6g fails, the script prints the backup path for rollback and exits non-zero.

## Backup

- **When:** every deployment, after the package has been validated and linted, and **before** any live file changes.
- **What:** the whole live `la-casa-del-arbol` directory as it was, as a gzip tarball.
- **Name:** `~/lcda-theme-backups/la-casa-del-arbol-<YYYYMMDD-HHMMSS>.tar.gz`. The timestamp is the deployment id, also shown locally.
- **Verification:** the archive must list exactly as many files as the live directory has. Otherwise the deploy aborts before copying.
- **Where:** in the account home, outside `public_html`, directory mode `700`. It is not web-accessible and not visible to WordPress.
- **Retention:** backups are never deleted automatically. Prune old ones by hand when there are many; they are small.

## Deployment procedure

1. Commit the work to deploy. Check `git status`: the theme directory must be clean.
2. Dry runs:
   ```powershell
   .\deploy-theme.ps1 -LocalOnly     # local checks and package only, no network
   .\deploy-theme.ps1 -VerifyOnly    # adds the read-only server check
   ```
   Read the versions it reports (WordPress, Astra, current child theme, PHP).
3. Deploy:
   ```powershell
   .\deploy-theme.ps1
   ```
   Type `DEPLOY` at the prompt. Note the **deployment id** and **backup path** it prints.
4. Run the post-deploy checks below.

Exit codes: `0` success, `1` failure (validation, backup, transfer or verification), `2` cancelled at the prompt (nothing changed).

## Post-deploy checks

1. **Caches:** purge the page cache (LiteSpeed Cache / Hostinger cache, if active) so the new CSS and JS are served. PHP OPcache normally picks up the changed files on its own. If PHP changes don't show, restart PHP from hPanel.
2. **Log in** (the site is password-protected) and open a page using the **La Casa — Lienzo** template:
   - The V1 header and footer render; no Astra header or footer remains.
   - The browser console shows no errors; no 404s for CSS, JS, fonts or logos.
   - Fonts load from `/wp-content/themes/la-casa-del-arbol/assets/fonts/` (no Google Fonts requests).
3. **Mobile width:** "Menú" opens and closes the dialog, Esc closes it, and the page doesn't scroll behind it.
4. **A normal page** (not Lienzo) still renders with Astra's header and footer.
5. **wp-admin:** Appearance → Themes shows La Casa del Árbol (active, the new version); Appearance → Menus lists the 4 "La Casa — …" locations; the page editor opens without errors.
6. **Health:** no new PHP errors (hPanel → error logs, or `wp-content/debug.log` if enabled).

If any check fails in a way that affects visitors, roll back.

## Rollback

Restores the child theme exactly as it was before a given deployment. The replaced version is moved aside, not deleted.

```bash
ssh -p <RemotePort> <RemoteUser>@<RemoteHost>        # values from deploy.local.ps1

ID=20260922-120000        # the deployment id to undo (from the deploy output)
cd <RemoteWpRoot>/wp-content/themes                  # i.e. the parent of <RemoteThemeDir>
ls -l ~/lcda-theme-backups/la-casa-del-arbol-$ID.tar.gz     # must exist
tar -tzf ~/lcda-theme-backups/la-casa-del-arbol-$ID.tar.gz >/dev/null && echo "backup readable"

mkdir -p ~/lcda-theme-backups/replaced
mv la-casa-del-arbol ~/lcda-theme-backups/replaced/la-casa-del-arbol-$ID
tar -xzf ~/lcda-theme-backups/la-casa-del-arbol-$ID.tar.gz -C .
ls la-casa-del-arbol/style.css && grep -m1 Version la-casa-del-arbol/style.css
```

Then purge caches and repeat the post-deploy checks.

- Between `mv` and `tar -xzf`, WordPress briefly can't find the child theme (a few seconds). Run the two commands back to back.
- The moved-aside copy in `~/lcda-theme-backups/replaced/` can be inspected, then removed by hand.
- To roll back in Git as well: `git revert` the bad commit locally, then deploy again. The repository remains the source of truth.

## Orphan files

Because nothing is deleted remotely, a file removed from the repository stays on the server. Each deployment lists these files as "Remote files not in this release (kept, NOT deleted)". Usually they're harmless, since WordPress only loads what the theme references. Exception: files in `patterns/` are auto-registered, so a removed pattern file still shows in the editor. Removing orphans is a manual, reviewed step: check the list, and delete over SSH only the specific paths approved. Automatic mirroring with deletions is intentionally not implemented.

## Troubleshooting

- **`Host key verification failed`:** the host key isn't trusted yet, or it changed. See prerequisite 3. Don't bypass it.
- **"has uncommitted changes"**: commit or discard the changes in `themes/la-casa-del-arbol/`.
- **"child theme directory not found" / "not the la-casa-del-arbol theme"**: the server doesn't look as expected. Stop and inspect it manually; don't change the script's paths to force a deploy.
- **"PHP lint failed"**: a PHP file in the release has a syntax error. The live theme was not changed.
- **A failure after the backup step**: the output prints the backup path; follow Rollback.
- **Leftovers after a failure**: `~/lcda-deploy-<id>.tar` and `~/.lcda-deploy/<id>/` may remain for inspection. They're outside `public_html` and safe to delete by hand. The next deployment uses a new id.
