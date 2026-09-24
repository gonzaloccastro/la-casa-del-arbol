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
| Staging (temporary) | `<RemoteHome>/.lcda-deploy/<id>/` (mode `700`) |

Nothing is uploaded to the home directory itself. The package travels inside the deploy SSH connection and is written straight into the private staging directory.

## Prerequisites

1. **Windows PowerShell 5.1+** with the built-in **OpenSSH client** (`ssh`) and **Git** on `PATH`. `scp` is not used.
2. **`deploy.local.ps1`** exists and is filled in (see Configuration).
3. **SSH access works** with your existing authentication (password, or key plus ssh-agent). The script stores no credentials. With password authentication you are prompted **twice**: once for verification, once for the deploy. `-VerifyOnly` prompts once.
4. **The server's host key is already trusted.** The script uses `StrictHostKeyChecking=yes`, so it refuses to connect to an unknown or changed host key. First time only: run `ssh -p <RemotePort> <RemoteUser>@<RemoteHost>`, compare the fingerprint with the one shown in the hosting panel, accept it, and exit.
5. **The remote child theme directory already exists** and is the `la-casa-del-arbol` Astra child theme. The script refuses to create it or to deploy anywhere else.
6. **The theme changes are committed.** Only `HEAD` is deployed.

## What the script changes

Only:

- `wp-content/themes/la-casa-del-arbol/`: files from the release are added or overwritten.
- `~/lcda-theme-backups/`: one new backup archive per deployment.
- `~/.lcda-deploy/<id>/`: temporary private staging, removed after a successful deploy.

`-VerifyOnly`, and the verification step of a full run, write nothing at all.

## What the script never changes

- WordPress core (`wp-admin/`, `wp-includes/`, root PHP files, `wp-config.php`)
- `wp-content/plugins/`, `wp-content/uploads/`, other themes, **the Astra parent theme** (only read, to check that it exists and report its version)
- The database (no WP-CLI, no SQL)
- **No remote file is deleted.** Files on the server that are not in the release are listed as "not in this release" and left in place. Removing them is a separate, reviewed decision (see Orphan files).

## How it works

Two SSH connections, no scp:

```
LOCAL (Windows)                                   SERVER (read-only until step 5)
0  load + validate deploy.local.ps1 (parsed as data, then read-only)
1  checks: git/ssh, repo root, theme files,
   style.css identity, clean Git state for the theme
2  package = git archive HEAD:themes/la-casa-del-arbol
   (LF line endings), SHA-256, file count
3  ssh ──── verify (read-only) ─────────────────▶ account home, WordPress root, wp-config,
        ─── package stream (stdin) ────────────▶  themes dir, child theme identity
                                                  (Template: astra, Text Domain), not a
                                                  symlink, Astra present; prints WP,
                                                  Astra, child and PHP versions;
                                                  warns about stale leftovers;
                                                  TRANSPORT CHECK: decode + SHA-256 the
                                                  stream in memory, must equal the local
                                                  SHA-256 (nothing is written)
4  prompt: type DEPLOY  (anything else = cancel, exit 2)
5  ssh ──── deploy ─────────────────────────────▶ a. re-verify target, warn about leftovers
        ─── package stream (stdin) ────────────▶  b. decode the stream into
                                                     ~/.lcda-deploy/<id>/theme.tar (dir 700)
                                                  c. SHA-256 must match; no absolute or ../
                                                     paths; no symlinks; file count must match
                                                  d. extract to staging; php -l every PHP file
                                                     (if PHP CLI exists), counted
                                                  ── nothing live has changed up to here ──
                                                  e. BACKUP live theme → tar.gz, verify entry
                                                     count == live file count
                                                  f. copy staging over the live theme
                                                     (overwrite and add, never delete)
                                                  g. verify every deployed file by SHA-256
                                                     → prints LIVE-VERIFIED <id>
                                                  h. list remote files not in the release
                                                  i. remove this deployment's staging dir
                                                  → prints RESULT: deploy OK <id>
6  summary: commit, deployment id, backup path
```

Before any of this, the script checks its own embedded remote script and refuses to run if it uses process substitution (see Server shell compatibility).

**The remote script.** A bash script embedded in `deploy-theme.ps1`. It is sent base64-encoded inside the SSH command, so no script file is written to the server and Windows line endings can't corrupt it. It runs with `set -Eeuo pipefail`: any failed command stops it with a non-zero exit.

**Package streaming.** The package goes over the same SSH connection as the script, on the connection's standard input, as base64 text. Windows PowerShell 5.1 can't pipe raw binary to a native program, so base64 is used, and the server drops any line breaks. The SSH command keeps that input open on file descriptor 3 (`exec 3<&0`), because the script itself arrives on bash's standard input. The password prompt reads the console, not standard input, so password authentication works unchanged. Any damage in transit (truncation, a changed byte) is caught by the SHA-256 check before anything is extracted.

**Completion check.** Each remote run ends with an exact line, `[remote] RESULT: <verify|deploy> OK <id>`. The PowerShell script treats a run as successful only when ssh exits with 0 **and** this line appeared. A remote script that was cut short or never ran can't pass as a success.

**LIVE-VERIFIED vs RESULT.** A deploy prints two markers:

| Line | Printed when | Meaning |
|---|---|---|
| `[remote] LIVE-VERIFIED <id>` | right after step g | The live theme now holds exactly the release: every file was copied and passed its SHA-256 check. **Diagnostic only.** |
| `[remote] RESULT: deploy OK <id>` | after the last step | The whole run succeeded, including the orphan report and staging cleanup. **The only success signal.** |

LIVE-VERIFIED alone never counts as success: without the exact RESULT line (and ssh exit code 0) the run is reported as failed and the script exits 1. LIVE-VERIFIED only changes the failure message, so you know the live theme was already updated and verified (see Failure after LIVE-VERIFIED).

**PHP lint.** Every `.php` file in the release is checked with `php -l` in staging, before the backup and before anything live changes. The check fails closed: the script counts the PHP files in the release and the files actually linted, and stops if the release has no PHP files, if the two counts differ, or if any file has a syntax error. On success it prints the count, for example `PHP lint OK: 13 files`. A lint step that silently checked nothing (what happened on 2026-09-23) is now a hard error. If the server has no PHP CLI, the lint is skipped and the log says so.

**Why no scp.** Before 2026-09-23 the package went up with a separate scp connection, which meant three password logins per run. On the first real deployment, the server closed the third login (the deploy SSH) during authentication. The package had already been uploaded to `~`, so it was left behind. Streaming removes that connection and that leftover: if the deploy connection fails at login now, nothing at all has been written to the server.

**Failure behavior**

- Step 3 (verification or transport check) fails: nothing was written. Nothing is uploaded until you type DEPLOY.
- The deploy connection fails at login (ssh exit code 255 with no `[remote]` lines): nothing was written. Just run again.
- Steps 5a–5d fail (stream damaged, checksum, unsafe paths, file count, PHP lint): the live theme was not touched. This run's staging dir `~/.lcda-deploy/<id>/` may remain (private, `700`). The next run reports it as stale.
- Step 5e (backup) fails: the live theme was not touched.
- Step 5f or 5g fails: the script prints the backup path for rollback and exits non-zero.
- Step 5h or 5i fails (after `LIVE-VERIFIED`): see Failure after LIVE-VERIFIED.
- Any failure makes the PowerShell script exit with code 1 and print `DEPLOY ABORTED` or the remote error.

## Server shell compatibility (no process substitution)

The production host's shell has no `/dev/fd`. Bash's process substitution, `<(command)` and `>(command)`, hands commands paths like `/dev/fd/63`, and on this host those paths don't exist. On 2026-09-23 this caused two failures in the remote script:

- The PHP lint loop read its file list through process substitution. Its input could not be opened, so the loop never ran, and the step reported "PHP lint OK" without linting anything.
- The orphan report ran `comm` on two process substitutions. `comm` could not open them and the script stopped after the verified copy, so the run was reported as failed even though the release was live and correct.

Rules for the remote script:

- **Never use process substitution.** Write intermediate lists as regular files in this deployment's private staging dir, `~/.lcda-deploy/<id>/` (next to `theme.tar`, never inside `theme/`, never in `/tmp`), then read those files. Today these are `package-entries.list` (the `tar` listing scanned for unsafe paths), `package-files.list`, `live-files.list` (for the orphan report) and `php-files.list` (for the lint).
- **Don't pipe into an early-exiting reader inside a check.** With `pipefail`, `producer | grep -q` can make the check pass when it should fail (the producer is killed by SIGPIPE when `grep` stops at the first match). Write the producer's output to a staging file first, then scan the file.
- **Checks must fail closed on their own.** Don't rely on `set -e` to catch a failed loop redirection; its handling differs between bash versions. Count what was checked.

`deploy-theme.ps1` enforces the first rule: before anything else, it refuses to run (exit 1, no connection) if the embedded remote script contains `<(` or `>(`. Ordinary redirections (`<`, `>`, `<&3`, `2>&1`) and command substitution `$(...)` are not affected. Each run also prints the server's bash version (`[remote] Bash: ...`).

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
   .\deploy-theme.ps1 -VerifyOnly    # adds the read-only server check and transport check
   ```
   Read the versions it reports (WordPress, Astra, current child theme, PHP) and any stale-leftover warnings. It must end with `Transport check OK` and `[remote] RESULT: verify OK <id>`. That proves the package reaches the server intact through this machine's PowerShell and OpenSSH, without writing anything there.
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

## Stale leftovers

Every run (verification and deploy) lists leftovers of earlier runs, read-only:

- **Legacy upload files** in the home directory named exactly `lcda-deploy-YYYYMMDD-HHMMSS.tar`. The current workflow never creates them, so any you see come from the old scp-based workflow (for example the 2026-09-23 failed run).
- **Stale staging directories** in `~/.lcda-deploy/` named exactly `YYYYMMDD-HHMMSS`, left by a run that failed before its cleanup step. They hold that run's `theme.tar`, the extracted `theme/`, and whatever it had produced when it stopped: the `*.list` files and, after the copy, `manifest.sha256`. They are private (`700`) and kept on purpose for inspection. A successful run removes only its own staging dir, never another run's.

The warning is informational. It never deletes anything and never blocks a deploy. Only regular files and real directories matching these exact names are listed; symlinks and other names are ignored, and a symlinked `~/.lcda-deploy` or `~/lcda-theme-backups` makes the script stop.

**Cleanup is manual and deliberate.** The script never deletes leftovers of other runs, even when they look obsolete. After reviewing the list, and only once a deployment has succeeded, delete the exact paths over SSH, without wildcards. For example:

```bash
rm -- ~/lcda-deploy-20260923-175458.tar
rm -rf -- ~/.lcda-deploy/20260923-190000
```

## Troubleshooting

- **`Host key verification failed`:** the host key isn't trusted yet, or it changed. See prerequisite 3. Don't bypass it.
- **"has uncommitted changes"**: commit or discard the changes in `themes/la-casa-del-arbol/`.
- **"child theme directory not found" / "not the la-casa-del-arbol theme"**: the server doesn't look as expected. Stop and inspect it manually; don't change the script's paths to force a deploy.
- **"PHP lint failed"**: a PHP file in the release has a syntax error (the file is named above the error). The live theme was not changed.
- **"PHP lint checked N of M files"** or **"package contains no PHP files"**: the lint could not check every PHP file. The live theme was not changed. Don't work around it; the staging dir holds `php-files.list` for inspection.
- **"package contains unsafe paths"** / **"could not scan the package listing"**: the package listing has an absolute or `..` path, or couldn't be read. The live theme was not changed.
- **`uses process substitution (line N)`** (local, before any connection): someone added `<(...)` or `>(...)` to the remote script. Rewrite it with a file in the staging dir (see Server shell compatibility).
- **A failure after the backup step**: the output prints the backup path; follow Rollback.
- **`ssh exited with code 255` and no `[remote]` lines**: the connection or login failed before the remote script started (wrong password, or the server refusing repeated logins). Nothing was changed. Wait a moment and run again.
- **`transport check FAILED` / `package checksum mismatch`**: the package arrived damaged. Nothing live was changed. Run again; if it repeats, stop and investigate the connection.
- **`the remote script did not report completion`**: ssh exited 0, but the remote script didn't finish (cut short, or didn't run). Treat the run as failed. Check the `[remote]` lines to see how far it got.
- **Failure after LIVE-VERIFIED** (`DEPLOY ABORTED ... NOTE: the live theme WAS updated and passed per-file SHA-256 verification`): the copy finished and every deployed file matched the release, and then a later step failed (orphan report or staging cleanup). The run still counts as failed. Don't roll back blindly: the live theme is the intended release. Instead:
  1. Read the `[remote] ERROR` lines to see which step failed. Lines tagged `(subshell)` come from a subshell; the untagged line is the one that stopped the script.
  2. Do the post-deploy checks. If the site is fine, keep the release. Roll back only if the checks fail.
  3. Optionally, read-only: `cd <RemoteThemeDir> && sha256sum --quiet -c ~/.lcda-deploy/<id>/manifest.sha256` confirms the live files still match the release.
  4. Fix the failing step in the script, and have it reviewed. The staging dir stays until you remove it by hand.
  This is what happened on 2026-09-23 (the orphan report failed; see Server shell compatibility), before these markers existed.
- **Leftovers after a failure**: `~/.lcda-deploy/<id>/` may remain for inspection. It's private (`700`), outside `public_html`, and reported as stale by later runs. See Stale leftovers for the manual cleanup.
