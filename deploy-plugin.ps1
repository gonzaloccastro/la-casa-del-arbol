#Requires -Version 5.1
<#
.SYNOPSIS
    Deploys the casa-eventos plugin (plugins/casa-eventos/) to the production
    server as a whole-directory replacement, with a verified remote backup
    first. Deploys FILES only: it never activates, deactivates or uninstalls
    the plugin.

.DESCRIPTION
    Read docs/deployment.md (Plugin deployment) before running this script.

    What it deploys: the RUNTIME files of plugins/casa-eventos/ as committed at
    the current Git HEAD (packaged with "git archive"): casa-eventos.php,
    uninstall.php, inc/ and assets/. tests/ and readme.md are development
    material and are never shipped. Every other top-level entry must be
    classified in this script first; an unknown one stops the run. Uncommitted
    changes in the plugin directory are refused.

    Where: <RemoteWpRoot>/wp-content/plugins/casa-eventos/, derived from
    deploy.local.ps1 (the same file deploy-theme.ps1 uses; parsed as data,
    never executed). There is no command-line override for host or paths.
    Nothing else on the server is modified: no WordPress core, database,
    theme, uploads or other plugin.

    Replacement, not overlay: the new tree is staged, verified and permissioned
    in the account home (same filesystem as wp-content/plugins), and the live
    directory is then swapped by two renames. The previous directory is kept
    intact (moved aside, plus a verified tar.gz backup). Nothing is ever
    deleted from the live plugin directory, yet no stale file can survive: the
    old directory is replaced as a whole.

    Modes (exactly one is required; there is no default action):
      -LocalOnly   No network. Local checks, version gates, static checks,
                   package build and package inspection.
      -VerifyOnly  Adds the read-only remote verification and the transport
                   check (nothing is written on the server).
      -Deploy      Adds the confirmation prompt (type DEPLOY-PLUGIN) and the
                   real remote deployment.

    The remote script must never use bash process substitution: the production
    host has no /dev/fd. A local pre-flight check refuses to run if it does.

    Credentials: none are stored here. SSH uses your existing authentication.
    The server's host key must already be in your known_hosts.

.PARAMETER LocalOnly
    Local checks and package only. No network connection.

.PARAMETER VerifyOnly
    Local checks, package, and the read-only remote verification.

.PARAMETER Deploy
    Full deployment (asks for confirmation first).

.EXAMPLE
    .\deploy-plugin.ps1 -LocalOnly
.EXAMPLE
    .\deploy-plugin.ps1 -VerifyOnly
.EXAMPLE
    .\deploy-plugin.ps1 -Deploy

.NOTES
    Exit codes: 0 = success, 1 = failed (validation, backup, transfer or
    verification), 2 = cancelled at the confirmation prompt (nothing changed).
    This file must stay ASCII-only (Windows PowerShell 5.1 compatibility).
#>
[CmdletBinding()]
param(
    [switch] $LocalOnly,
    [switch] $VerifyOnly,
    [switch] $Deploy
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# ---------------------------------------------------------------------------
# Fixed settings. Environment-specific values live in deploy.local.ps1 (shared
# with deploy-theme.ps1, so the same six keys are required and validated).
# ---------------------------------------------------------------------------
$PluginSlug         = 'casa-eventos'
$PluginRelPath      = 'plugins/casa-eventos'
$ThemeSlug          = 'la-casa-del-arbol'
$ConfigFileName     = 'deploy.local.ps1'
$RequiredConfigKeys = @('RemoteHost', 'RemotePort', 'RemoteUser', 'RemoteHome', 'RemoteWpRoot', 'RemoteThemeDir')

# Top-level entries that ARE the production plugin (allowlist). Anything else
# in the plugin directory must be classified below or the run stops.
$ShipEntries = @('casa-eventos.php', 'uninstall.php', 'inc', 'assets')

# Top-level entries that are deliberately NOT shipped, and why.
$ExcludedEntries = @{
    'tests'     = 'development only (static tests and architecture checks; some need the repository layout)'
    'readme.md' = 'developer documentation; WordPress does not read it (only readme.txt)'
}

# Files that must exist in the package (runtime-required).
$RequiredFiles = @(
    'casa-eventos.php',
    'uninstall.php',
    'inc/core/schema.php',
    'inc/core/capabilities.php',
    'inc/core/post-type.php',
    'inc/core/queries.php',
    'inc/core/class-event.php',
    'inc/admin/navigation.php',
    'inc/admin/editor.php',
    'assets/admin/admin.css',
    'assets/admin/event-panel.js'
)

# ---------------------------------------------------------------------------
# Remote script (bash). Sent base64-encoded as part of the SSH command, so no
# file is written on the server for it. Placeholders __X__ are replaced from
# the validated configuration. Modes: "verify" (read-only) and "deploy".
# The package arrives as base64 text on the SSH connection's stdin, which the
# SSH command keeps open on fd 3 (fd 0 carries the script itself).
# ---------------------------------------------------------------------------
$RemoteScriptTemplate = @'
set -Eeuo pipefail
export LC_ALL=C
umask 022

MODE="${1:-}"
STAMP="${2:-}"
PKG_SHA="${3:-}"
EXPECTED_FILES="${4:-}"
EXPECTED_VERSION="${5:-}"

EXPECTED_HOME='__REMOTE_HOME__'
WP_ROOT='__WP_ROOT__'
SLUG='__PLUGIN_SLUG__'
PLUGINS_DIR="$WP_ROOT/wp-content/plugins"
PLUGIN_DIR='__REMOTE_PLUGIN_DIR__'
BACKUP_DIR="$EXPECTED_HOME/lcda-plugin-backups"
ASIDE_ROOT="$BACKUP_DIR/replaced"
STAGE_ROOT="$EXPECTED_HOME/.lcda-deploy"
TOP_ENTRIES='__TOP_ENTRIES__'

SWAP_STARTED=0
HAD_LIVE=0
RESTORED=0
PLUGIN_STATE="unknown"
BACKUP=""
ASIDE=""

log() { printf '[remote] %s\n' "$*"; }

# If the swap stopped after the old directory was moved aside and before the
# new one was in place, put the old one back (same filesystem rename). Only
# ever acts when the live directory is missing.
restore_if_needed() {
  if [ "$HAD_LIVE" = 1 ] && [ -n "$ASIDE" ] && [ ! -e "$PLUGIN_DIR" ] && [ -d "$ASIDE" ]; then
    if mv -T -- "$ASIDE" "$PLUGIN_DIR"; then
      RESTORED=1
      printf '[remote] The previous plugin directory was put back: %s\n' "$PLUGIN_DIR" >&2
    else
      printf '[remote] COULD NOT restore the previous plugin. It is intact at: %s\n' "$ASIDE" >&2
      printf '[remote] Restore by hand: mv -T -- %s %s\n' "$ASIDE" "$PLUGIN_DIR" >&2
    fi
  fi
}

rollback_hint() {
  if [ "$SWAP_STARTED" = 1 ]; then
    restore_if_needed
    if [ "$RESTORED" = 1 ]; then
      printf '[remote] The live plugin directory is the previous version again: nothing changed.\n' >&2
      printf '[remote] Backup archive: %s\n' "${BACKUP:-none}" >&2
      return 0
    fi
    if [ "$HAD_LIVE" = 0 ] && [ ! -e "$PLUGIN_DIR" ]; then
      printf '[remote] No plugin directory exists on the server (first install did not complete). Nothing was lost.\n' >&2
      return 0
    fi
    printf '[remote] The live plugin directory may not be the intended release.\n' >&2
    printf '[remote] Previous version kept at: %s\n' "${ASIDE:-none (first install)}" >&2
    printf '[remote] Backup archive: %s (see docs/deployment.md, Plugin rollback)\n' "${BACKUP:-none (first install)}" >&2
  fi
}

fail() {
  printf '[remote] ERROR: %s\n' "$*" >&2
  rollback_hint
  exit 1
}

# With set -E the trap also runs in subshells. Those lines are tagged.
on_error() {
  local code=$? where=""
  [ "${BASHPID:-$$}" = "$$" ] || where=" (subshell)"
  printf '[remote] ERROR: command failed (exit %s) near line %s%s\n' "$code" "${BASH_LINENO[0]}" "$where" >&2
  rollback_hint
  exit "$code"
}
trap on_error ERR

verify_target() {
  [ "$HOME" = "$EXPECTED_HOME" ] || fail "unexpected account home: $HOME"
  [ "$PLUGIN_DIR" = "$PLUGINS_DIR/$SLUG" ] || fail "configured plugin dir is not <wp root>/wp-content/plugins/$SLUG"
  local c
  for c in tar sha256sum base64 tr find grep sort comm wc cut sed mv stat chmod mkdir du rm xargs cmp; do
    command -v "$c" >/dev/null 2>&1 || fail "missing remote command: $c"
  done
  local mvhelp
  mvhelp="$(mv --help 2>&1 || true)"
  case "$mvhelp" in
    *--no-target-directory*) ;;
    *) fail "mv does not support -T (GNU coreutils required for the directory swap)" ;;
  esac
  [ ! -L "$STAGE_ROOT" ] || fail "staging root is a symlink: $STAGE_ROOT"
  [ ! -L "$BACKUP_DIR" ] || fail "backup directory is a symlink: $BACKUP_DIR"
  [ ! -L "$ASIDE_ROOT" ] || fail "replaced directory is a symlink: $ASIDE_ROOT"
  [ -d "$WP_ROOT" ] || fail "WordPress root not found: $WP_ROOT"
  { [ -f "$WP_ROOT/wp-load.php" ] && [ -f "$WP_ROOT/wp-includes/version.php" ]; } || fail "not a WordPress root: $WP_ROOT"
  { [ -f "$WP_ROOT/wp-config.php" ] || [ -f "$(dirname "$WP_ROOT")/wp-config.php" ]; } || fail "wp-config.php not found"
  [ -d "$PLUGINS_DIR" ] || fail "plugins directory not found: $PLUGINS_DIR"
  [ ! -L "$PLUGINS_DIR" ] || fail "plugins directory is a symlink: $PLUGINS_DIR"
  # The directory swap is a rename: staging (in the home) and the plugins
  # directory must be on the same filesystem.
  [ "$(stat -c %d "$EXPECTED_HOME")" = "$(stat -c %d "$PLUGINS_DIR")" ] || fail "home and plugins directory are on different filesystems: an atomic swap is not possible"

  if [ -e "$PLUGIN_DIR" ] || [ -L "$PLUGIN_DIR" ]; then
    [ ! -L "$PLUGIN_DIR" ] || fail "plugin directory is a symlink: $PLUGIN_DIR"
    [ -d "$PLUGIN_DIR" ] || fail "plugin path exists and is not a directory: $PLUGIN_DIR"
    local real_plugins real_plugin
    real_plugins="$(cd "$PLUGINS_DIR" && pwd -P)"
    real_plugin="$(cd "$PLUGIN_DIR" && pwd -P)"
    [ "$real_plugin" = "$real_plugins/$SLUG" ] || fail "plugin path resolves outside the plugins directory"
    [ -f "$PLUGIN_DIR/$SLUG.php" ] || fail "remote plugin directory has no $SLUG.php"
    grep -Eq '^[[:space:]]*\*?[[:space:]]*Plugin Name:[[:space:]]*Casa Eventos[[:space:]]*$' "$PLUGIN_DIR/$SLUG.php" || fail "remote $SLUG.php is not the Casa Eventos plugin"
    grep -Eq 'Text Domain:[[:space:]]*casa-eventos' "$PLUGIN_DIR/$SLUG.php" || fail "remote $SLUG.php is not the casa-eventos plugin"
    PLUGIN_STATE="installed"
    HAD_LIVE=1
  else
    PLUGIN_STATE="absent"
  fi
}

header_value() {
  grep -m1 -E "^[[:space:]]*\*?[[:space:]]*$1:" "$2" | sed -E "s/^[[:space:]]*\*?[[:space:]]*$1:[[:space:]]*//" | tr -d '\r' || true
}

report_target() {
  local wp_version
  wp_version="$(grep -m1 -E '^\$wp_version' "$WP_ROOT/wp-includes/version.php" | cut -d"'" -f2 || true)"
  log "Account home:      $HOME"
  log "WordPress root:    $WP_ROOT (WordPress ${wp_version:-unknown})"
  log "Plugins directory: $PLUGINS_DIR (same filesystem as the home: yes)"
  if [ "$PLUGIN_STATE" = "installed" ]; then
    log "Plugin directory:  $PLUGIN_DIR (version $(header_value Version "$PLUGIN_DIR/$SLUG.php"), $(find "$PLUGIN_DIR" -type f | wc -l | tr -d ' ') files)"
    log "STATE: installed $(header_value Version "$PLUGIN_DIR/$SLUG.php")"
  else
    log "Plugin directory:  $PLUGIN_DIR (not present: first install)"
    log "STATE: absent"
  fi
  log "Activation:        not inspected and never changed by this script"
  log "Bash:              ${BASH_VERSION:-unknown}"
  if command -v php >/dev/null 2>&1; then
    log "PHP CLI:           $(php -r 'echo PHP_VERSION;' 2>/dev/null || echo unknown)"
  else
    log "PHP CLI:           not available (remote lint will be skipped)"
  fi
  # A second copy of the plugin under another folder name would redeclare its
  # functions if both were active. Informational.
  local other
  other="$(find "$PLUGINS_DIR" -mindepth 2 -maxdepth 2 -name "$SLUG.php" -not -path "$PLUGIN_DIR/*" 2>/dev/null | sort || true)"
  if [ -n "$other" ]; then
    log "WARNING: another $SLUG.php exists under the plugins directory (NOT touched):"
    printf '%s\n' "$other" | sed 's/^/[remote]   /'
  fi
}

# Leftovers of earlier runs: informational only, never deleted, never
# blocking. Same exact-name patterns as deploy-theme.ps1.
report_stale() {
  local uploads stages=""
  uploads="$(find "$EXPECTED_HOME" -maxdepth 1 -type f -name 'lcda-deploy-*.tar' -printf '%f  %s bytes  %TY-%Tm-%Td %TH:%TM\n' 2>/dev/null \
    | grep -E '^lcda-deploy-[0-9]{8}-[0-9]{6}\.tar  ' | sort || true)"
  if [ -d "$STAGE_ROOT" ] && [ ! -L "$STAGE_ROOT" ]; then
    stages="$(find "$STAGE_ROOT" -mindepth 1 -maxdepth 1 -type d -printf '%f  %TY-%Tm-%Td %TH:%TM\n' 2>/dev/null \
      | grep -E '^[0-9]{8}-[0-9]{6}  ' | sort || true)"
  fi
  if [ -z "$uploads" ] && [ -z "$stages" ]; then
    log "Stale leftovers:   none"
    return 0
  fi
  if [ -n "$uploads" ]; then
    log "WARNING: stale upload files from an earlier run in $EXPECTED_HOME (unused, NOT deleted):"
    printf '%s\n' "$uploads" | sed 's/^/[remote]   /'
  fi
  if [ -n "$stages" ]; then
    log "WARNING: stale staging directories in $STAGE_ROOT (unused, NOT deleted):"
    printf '%s\n' "$stages" | sed 's/^/[remote]   /'
  fi
  log "These are informational only. Remove them by hand after review (see docs/deployment.md)."
}

check_args() {
  [[ "$STAMP" =~ ^[0-9]{8}-[0-9]{6}$ ]] || fail "invalid deployment id: $STAMP"
  [[ "$PKG_SHA" =~ ^[0-9a-f]{64}$ ]] || fail "invalid package checksum"
  [[ "$EXPECTED_FILES" =~ ^[0-9]+$ ]] || fail "invalid expected file count"
  [[ "$EXPECTED_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || fail "invalid expected plugin version"
  { true <&3; } 2>/dev/null || fail "no package stream on fd 3"
}

# Package stream (base64 text on fd 3) -> raw bytes on stdout.
read_package() {
  tr -cd 'A-Za-z0-9+/=' <&3 | base64 -d
}

# Read-only transport check: decode and hash the stream in memory.
transport_check() {
  local got
  got="$(read_package | sha256sum | cut -d' ' -f1)" || fail "transport check FAILED: the package stream could not be decoded"
  exec 3<&-
  [ "$got" = "$PKG_SHA" ] || fail "transport check FAILED: received SHA-256 $got, expected $PKG_SHA"
  log "Transport check OK: the streamed package matches SHA-256 $PKG_SHA (nothing written)"
}

deploy() {
  check_args
  verify_target
  report_target
  report_stale

  # 1. Package: stream it into a private staging dir and check it.
  local stage="$STAGE_ROOT/$STAMP"
  [ ! -e "$stage" ] || fail "staging directory already exists: $stage"
  mkdir -p "$STAGE_ROOT"
  chmod 700 "$STAGE_ROOT"
  mkdir "$stage"
  chmod 700 "$stage"
  [ "$(stat -c %d "$stage")" = "$(stat -c %d "$PLUGINS_DIR")" ] || fail "staging and plugins directory are on different filesystems; the live plugin was not changed"
  # Intermediate lists are regular files in the private staging dir, next to
  # plugin.tar and outside plugin/. Never process substitution: the
  # production host has no /dev/fd (see docs/deployment.md).
  local pkg="$stage/plugin.tar"
  local entries_list="$stage/package-entries.list"
  local package_list="$stage/package-files.list"
  local live_list="$stage/live-files.list"
  local post_list="$stage/post-swap-files.list"
  local php_list="$stage/php-files.list"
  local top_list="$stage/top-entries.list"
  local top_expected="$stage/top-entries.expected"
  read_package > "$pkg" || fail "package stream could not be decoded (transfer interrupted?); the live plugin was not changed"
  exec 3<&-
  [ "$(sha256sum "$pkg" | cut -d' ' -f1)" = "$PKG_SHA" ] || fail "package checksum mismatch (transfer corrupted?); the live plugin was not changed"

  # Unsafe paths: list to a file first (a tar failure is fatal), then scan
  # the file. grep status 1 = clean; 0 = unsafe path; anything else = error.
  tar -tf "$pkg" > "$entries_list"
  [ -s "$entries_list" ] || fail "package listing is empty; the live plugin was not changed"
  local scan=0
  grep -Eq '^/|(^|/)\.\.(/|$)' "$entries_list" || scan=$?
  case "$scan" in
    0) fail "package contains unsafe paths; the live plugin was not changed" ;;
    1) ;;
    *) fail "could not scan the package listing (grep exit $scan); the live plugin was not changed" ;;
  esac

  mkdir "$stage/plugin"
  tar -xf "$pkg" -C "$stage/plugin" --no-same-owner
  [ -z "$(find "$stage/plugin" -type l)" ] || fail "package contains symlinks"
  local staged_count
  staged_count="$(find "$stage/plugin" -type f | wc -l | tr -d ' ')"
  [ "$staged_count" = "$EXPECTED_FILES" ] || fail "package has $staged_count files, expected $EXPECTED_FILES"

  # Exactly the production top level: no tests/, no readme, nothing extra.
  find "$stage/plugin" -mindepth 1 -maxdepth 1 -printf '%f\n' | sort > "$top_list"
  printf '%s\n' $TOP_ENTRIES | sort > "$top_expected"
  [ -z "$(comm -3 "$top_list" "$top_expected")" ] || fail "package top level is not exactly: $TOP_ENTRIES; the live plugin was not changed"

  # Identity and version of the new code.
  [ -f "$stage/plugin/$SLUG.php" ] || fail "package is missing $SLUG.php"
  grep -Eq '^[[:space:]]*\*?[[:space:]]*Plugin Name:[[:space:]]*Casa Eventos[[:space:]]*$' "$stage/plugin/$SLUG.php" || fail "package $SLUG.php is not the Casa Eventos plugin"
  [ "$(header_value Version "$stage/plugin/$SLUG.php")" = "$EXPECTED_VERSION" ] || fail "package header version is not $EXPECTED_VERSION"
  grep -Fq "define( 'CASA_EVENTOS_VERSION', '$EXPECTED_VERSION' );" "$stage/plugin/$SLUG.php" || fail "package CASA_EVENTOS_VERSION is not $EXPECTED_VERSION"
  (cd "$stage/plugin" && find . -type f) | sort > "$package_list"
  log "Package OK: $staged_count files, version $EXPECTED_VERSION, checksum verified"

  # 2. PHP lint of the new code, before anything live changes. Fail-closed:
  # the number of files actually linted must equal an independent count.
  if command -v php >/dev/null 2>&1; then
    local php_expected linted=0 bad=0 f
    find "$stage/plugin" -type f -name '*.php' -print0 > "$php_list"
    php_expected="$(find "$stage/plugin" -type f -name '*.php' | wc -l | tr -d ' ')"
    [ "$php_expected" -gt 0 ] || fail "package contains no PHP files; the live plugin was not changed"
    while IFS= read -r -d '' f; do
      linted=$((linted + 1))
      php -l "$f" >/dev/null 2>&1 || { log "PHP syntax error: ${f#"$stage/plugin/"}"; bad=1; }
    done < "$php_list"
    [ "$linted" = "$php_expected" ] || fail "PHP lint checked $linted of $php_expected files; the live plugin was not changed"
    [ "$bad" = 0 ] || fail "PHP lint failed; the live plugin was not changed"
    log "PHP lint OK: $linted files"
  else
    log "PHP CLI not available: lint skipped"
  fi

  # 3. Permissions of the new tree (the staging dir itself is 700; the plugin
  # directory must be readable by the web server once it is live).
  find "$stage/plugin" -type d -exec chmod 755 {} +
  find "$stage/plugin" -type f -exec chmod 644 {} +
  [ -z "$(find "$stage/plugin" \( -type d ! -perm 755 \) -o \( -type f ! -perm 644 \))" ] || fail "could not normalize permissions (dirs 755, files 644)"
  (cd "$stage/plugin" && find . -type f -print0 | xargs -0 -r sha256sum) > "$stage/manifest.sha256"
  log "Permissions OK: directories 755, files 644"

  # 4. Backup of the live plugin (outside public_html), verified by content.
  if [ "$PLUGIN_STATE" = "installed" ]; then
    mkdir -p "$BACKUP_DIR"
    chmod 700 "$BACKUP_DIR"
    mkdir -p "$ASIDE_ROOT"
    chmod 700 "$ASIDE_ROOT"
    BACKUP="$BACKUP_DIR/$SLUG-$STAMP.tar.gz"
    ASIDE="$ASIDE_ROOT/$SLUG-$STAMP"
    [ ! -e "$BACKUP" ] || fail "backup already exists: $BACKUP"
    [ ! -e "$ASIDE" ] || fail "moved-aside directory already exists: $ASIDE"
    (cd "$PLUGIN_DIR" && find . -type f) | sort > "$live_list"
    tar -C "$PLUGINS_DIR" -czf "$BACKUP" "$SLUG"
    local live_count backup_count
    live_count="$(wc -l < "$live_list" | tr -d ' ')"
    backup_count="$(tar -tzf "$BACKUP" | grep -vc '/$' || true)"
    [ "$backup_count" = "$live_count" ] || fail "backup verification failed ($backup_count entries vs $live_count live files); the live plugin was not changed"
    # Content check: extract the archive and compare every file's SHA-256
    # with the live directory's.
    mkdir "$stage/backup-check"
    tar -xzf "$BACKUP" -C "$stage/backup-check" --no-same-owner
    (cd "$PLUGIN_DIR" && find . -type f -print0 | xargs -0 -r sha256sum) | sort > "$stage/live.sha256"
    (cd "$stage/backup-check/$SLUG" && find . -type f -print0 | xargs -0 -r sha256sum) | sort > "$stage/backup.sha256"
    cmp -s "$stage/live.sha256" "$stage/backup.sha256" || fail "backup content verification failed; the live plugin was not changed"
    log "Backup OK: $BACKUP ($live_count files, content verified, $(du -h "$BACKUP" | cut -f1))"
  else
    log "No previous plugin directory: nothing to back up (first install)"
  fi

  # 5. Swap. Two renames on the same filesystem; the old directory is moved
  # aside (never deleted), the verified new one takes its place. The window
  # with no plugin directory is the time between the two renames.
  SWAP_STARTED=1
  if [ "$PLUGIN_STATE" = "installed" ]; then
    mv -T -- "$PLUGIN_DIR" "$ASIDE"
  fi
  if ! mv -T -- "$stage/plugin" "$PLUGIN_DIR"; then
    restore_if_needed
    fail "could not move the new plugin into place"
  fi

  # 6. Verify the live directory against the package: every file by SHA-256
  # and the exact file list (nothing extra, nothing missing).
  (cd "$PLUGIN_DIR" && sha256sum --quiet -c "$stage/manifest.sha256") || fail "post-swap checksum verification failed"
  (cd "$PLUGIN_DIR" && find . -type f) | sort > "$post_list"
  [ -z "$(comm -3 "$post_list" "$package_list")" ] || fail "post-swap file list differs from the package"
  [ -z "$(find "$PLUGIN_DIR" -type l)" ] || fail "post-swap tree contains symlinks"
  SWAP_STARTED=0
  log "Deployed and verified: $staged_count files"
  # Diagnostic only, NOT success: success is the final RESULT line.
  log "LIVE-VERIFIED $STAMP"

  # 7. What the replacement removed and added (informational).
  if [ "$PLUGIN_STATE" = "installed" ]; then
    local removed added
    removed="$(comm -23 "$live_list" "$package_list")"
    added="$(comm -13 "$live_list" "$package_list")"
    if [ -n "$removed" ]; then
      log "Files of the previous version not in this release (gone from the live plugin, kept in the backup): $(printf '%s\n' "$removed" | wc -l | tr -d ' ')"
      printf '%s\n' "$removed" | sed 's/^/[remote]   /'
    else
      log "Files of the previous version not in this release: none"
    fi
    if [ -n "$added" ]; then
      log "Files new in this release: $(printf '%s\n' "$added" | wc -l | tr -d ' ')"
    fi
  fi

  # 8. Remove this deployment's staging dir (only that path).
  case "$stage" in
    "$STAGE_ROOT"/[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]-[0-9][0-9][0-9][0-9][0-9][0-9]) rm -rf -- "$stage" ;;
    *) fail "refusing to remove unexpected staging path: $stage" ;;
  esac

  log "Plugin now: version $(header_value Version "$PLUGIN_DIR/$SLUG.php")"
  log "Plugin FILES only: activation state was not touched (no WordPress code was run)"
  if [ "$PLUGIN_STATE" = "installed" ]; then
    log "Rollback backup:  $BACKUP"
    log "Previous version: $ASIDE"
  fi
}

case "$MODE" in
  verify)
    check_args
    verify_target
    report_target
    report_stale
    transport_check
    log "Read-only verification complete. Nothing was changed."
    ;;
  deploy)
    deploy
    ;;
  *)
    fail "unknown mode: $MODE"
    ;;
esac
# Completion marker: the local side requires this exact line.
log "RESULT: $MODE OK $STAMP"
'@

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
function Write-Step([string] $Message) { Write-Host ''; Write-Host "==> $Message" -ForegroundColor Cyan }
function Write-Info([string] $Message) { Write-Host "    $Message" }
function Stop-Deploy([string] $Message) { throw "DEPLOY ABORTED: $Message" }

function Invoke-Native {
    param([string] $FilePath, [string[]] $Arguments)
    & $FilePath @Arguments
    if ($LASTEXITCODE -ne 0) { Stop-Deploy "$FilePath exited with code $LASTEXITCODE" }
}

function Get-NativeOutput {
    param([string] $FilePath, [string[]] $Arguments)
    $output = & $FilePath @Arguments
    if ($LASTEXITCODE -ne 0) { Stop-Deploy "$FilePath $($Arguments -join ' ') exited with code $LASTEXITCODE" }
    return $output
}

function Get-SshArgs {
    return @('-p', "$RemotePort", '-o', 'ConnectTimeout=20', '-o', 'StrictHostKeyChecking=yes')
}

function Get-RemoteCommand([string] $Encoded, [string[]] $ScriptArgs) {
    return "exec 3<&0; printf %s $Encoded | base64 -d | bash -s -- $($ScriptArgs -join ' ')"
}

# Run the remote script over SSH, streaming the package (base64 text) on
# stdin. Success requires exit code 0 AND the script's exact completion line.
# Returns the remote output lines.
function Invoke-RemoteScript([string] $Target, [string] $Encoded, [string[]] $ScriptArgs, [string] $PackageBase64) {
    $sshArgs = (Get-SshArgs) + @($Target, (Get-RemoteCommand $Encoded $ScriptArgs))
    # The script travels inside the ssh command line. Windows allows 32767
    # characters in total; keep a safety margin so growth is caught locally.
    $commandLength = 'ssh '.Length + (($sshArgs | ForEach-Object { $_.Length + 3 }) | Measure-Object -Sum).Sum
    if ($commandLength -gt 30000) { Stop-Deploy "the ssh command line would be $commandLength characters (Windows limit 32767, safety limit 30000). Shorten the remote script." }
    $resultLine = "[remote] RESULT: $($ScriptArgs[0]) OK $($ScriptArgs[1])"
    $liveLine = "[remote] LIVE-VERIFIED $($ScriptArgs[1])"
    $completed = $false
    $liveVerified = $false
    $lines = New-Object System.Collections.ArrayList
    $PackageBase64 | & 'ssh' @sshArgs | ForEach-Object {
        Write-Host $_
        [void] $lines.Add("$_")
        if ("$_" -ceq $resultLine) { $completed = $true }
        if ("$_" -ceq $liveLine) { $liveVerified = $true }
    }
    $note = ''
    if ($liveVerified) {
        $note = ' NOTE: the live plugin directory WAS replaced and passed per-file SHA-256 verification (LIVE-VERIFIED) before a later step failed. Do not roll back blindly; see docs/deployment.md, "Plugin rollback".'
    }
    if ($LASTEXITCODE -ne 0) {
        Stop-Deploy "ssh exited with code $LASTEXITCODE (code 255 with no [remote] lines above: connection or authentication failed before the remote script started).$note"
    }
    if (-not $completed) { Stop-Deploy "the remote script did not report completion; treat this run as failed.$note" }
    return , $lines.ToArray()
}

# The production host has no /dev/fd: refuse bash process substitution.
function Assert-NoProcessSubstitution([string] $Script) {
    $lineNo = 0
    foreach ($line in ($Script -split "`n")) {
        $lineNo++
        if ($line -match '[<>]\(') {
            Stop-Deploy "the remote script uses process substitution (line $lineNo), which fails on the production host (no /dev/fd). Use a file in the staging dir instead."
        }
    }
}

# Structural guarantees of the remote script, checked before any connection:
# it cannot touch WordPress state and it never deletes the live plugin
# directory, its backups or the moved-aside copy.
function Assert-RemoteScriptSafety([string] $Script) {
    $lineNo = 0
    foreach ($line in ($Script -split "`n")) {
        $lineNo++
        $code = ($line -replace '^\s*#.*$', '')
        if ($code -match '\brm\b' -and $code -match 'PLUGIN_DIR|PLUGINS_DIR|BACKUP|ASIDE|WP_ROOT') {
            Stop-Deploy "the remote script removes a protected path (line $lineNo): $($line.Trim())"
        }
        if ($code -match '\bwp\s+(plugin|option|eval|db|core|rewrite|role|user)\b|wp-cli|\buninstall\b(?!\.php)|activate_plugin|deactivate_plugins|update_option|delete_plugins') {
            Stop-Deploy "the remote script touches WordPress plugin state (line $lineNo): $($line.Trim())"
        }
    }
}

# Load and validate deploy.local.ps1 (same rules as deploy-theme.ps1: parsed
# as data, never executed; every key required; unknown keys rejected).
function Read-DeployConfig([string] $Path) {
    $name = Split-Path -Leaf $Path
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        Stop-Deploy "local configuration not found: $Path. Copy deploy.example.ps1 to $name and fill in every value (see docs/deployment.md)."
    }

    $tokens = $null
    $parseErrors = $null
    $ast = [System.Management.Automation.Language.Parser]::ParseFile($Path, [ref] $tokens, [ref] $parseErrors)
    if ($parseErrors.Count -gt 0) { Stop-Deploy "$name has a syntax error: $($parseErrors[0].Message)" }

    $statements = @()
    if ($ast.EndBlock) { $statements = @($ast.EndBlock.Statements) }
    $onlyHashtable = ($null -eq $ast.ParamBlock) -and ($null -eq $ast.BeginBlock) -and ($null -eq $ast.ProcessBlock) -and ($statements.Count -eq 1)
    $hashAst = $null
    if ($onlyHashtable) {
        $pipeline = $statements[0] -as [System.Management.Automation.Language.PipelineAst]
        if ($pipeline -and $pipeline.PipelineElements.Count -eq 1) {
            $element = $pipeline.PipelineElements[0] -as [System.Management.Automation.Language.CommandExpressionAst]
            if ($element) { $hashAst = $element.Expression -as [System.Management.Automation.Language.HashtableAst] }
        }
    }
    if (-not $hashAst) { Stop-Deploy "$name must contain only a single @{ ... } hashtable (see deploy.example.ps1)" }

    try {
        $config = $hashAst.SafeGetValue()
    }
    catch {
        Stop-Deploy "$name may only contain constant values (no variables, expressions or commands)"
    }

    foreach ($key in @($config.Keys)) {
        if ($RequiredConfigKeys -notcontains $key) { Stop-Deploy "unknown setting in ${name}: $key" }
    }
    foreach ($key in $RequiredConfigKeys) {
        if (-not $config.ContainsKey($key) -or [string]::IsNullOrWhiteSpace("$($config[$key])")) { Stop-Deploy "missing setting in ${name}: $key" }
        if ("$($config[$key])" -match 'CHANGE_ME') { Stop-Deploy "$key in $name still has a placeholder value" }
    }

    if ("$($config.RemoteHost)" -notmatch '^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$') { Stop-Deploy 'RemoteHost must be a host name or IP address' }
    if (-not ($config.RemotePort -is [int]) -or $config.RemotePort -lt 1 -or $config.RemotePort -gt 65535) { Stop-Deploy 'RemotePort must be a number between 1 and 65535' }
    if ("$($config.RemoteUser)" -notmatch '^[A-Za-z_][A-Za-z0-9_.-]*$') { Stop-Deploy 'RemoteUser contains invalid characters' }

    foreach ($key in @('RemoteHome', 'RemoteWpRoot', 'RemoteThemeDir')) {
        $value = "$($config[$key])"
        if ($value -notmatch '^/[A-Za-z0-9._/-]+$' -or $value -match '//|/\.\.?(/|$)' -or $value.EndsWith('/')) {
            Stop-Deploy "$key must be an absolute path without trailing slash, '..' or special characters"
        }
    }
    if (-not "$($config.RemoteWpRoot)".StartsWith("$($config.RemoteHome)/")) { Stop-Deploy 'RemoteWpRoot must be inside RemoteHome' }
    $expectedThemeDir = "$($config.RemoteWpRoot)/wp-content/themes/$ThemeSlug"
    if ("$($config.RemoteThemeDir)" -cne $expectedThemeDir) { Stop-Deploy "RemoteThemeDir must be exactly $expectedThemeDir" }

    return $config
}

# Locate PHP: $env:PHP, then PATH, then the newest LocalWP bundled PHP.
function Find-Php {
    if ($env:PHP -and (Test-Path -LiteralPath $env:PHP -PathType Leaf)) { return (Resolve-Path -LiteralPath $env:PHP).Path }
    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    if ($env:APPDATA) {
        $local = Get-ChildItem -Path (Join-Path $env:APPDATA 'Local\lightning-services') -Directory -Filter 'php-*' -ErrorAction SilentlyContinue |
            Sort-Object Name -Descending
        foreach ($dir in $local) {
            $exe = Join-Path $dir.FullName 'bin\win64\php.exe'
            if (Test-Path -LiteralPath $exe -PathType Leaf) { return $exe }
        }
    }
    return $null
}

# Git for Windows' bash (never the WSL launcher that may sit first on PATH).
function Find-GitBash {
    $git = Get-Command git -ErrorAction SilentlyContinue
    if (-not $git) { return $null }
    $gitRoot = Split-Path -Parent (Split-Path -Parent $git.Source)
    foreach ($rel in @('bin\bash.exe', 'usr\bin\bash.exe')) {
        $exe = Join-Path $gitRoot $rel
        if (Test-Path -LiteralPath $exe -PathType Leaf) { return $exe }
    }
    return $null
}

# The plugin's own static suite (tests/run-static.sh): PHP lint of every file,
# JS syntax, unit tests, architecture checks, panel script test. Fail-closed:
# exit code 0 AND every expected summary line.
function Invoke-StaticChecks([string] $RepoRoot) {
    $php = Find-Php
    if (-not $php) { Stop-Deploy 'PHP CLI not found. Set $env:PHP to a php.exe (LocalWP ships one under %APPDATA%\Local\lightning-services\php-*\bin\win64\) and run again.' }
    $node = Get-Command node -ErrorAction SilentlyContinue
    if (-not $node) { Stop-Deploy 'node not found on PATH (needed by the plugin static checks).' }
    $bash = Find-GitBash
    if (-not $bash) { Stop-Deploy 'Git for Windows bash not found (needed to run tests/run-static.sh).' }
    Write-Info "php:  $php"
    Write-Info "node: $($node.Source)"
    Write-Info "bash: $bash"

    $previousPhp = $env:PHP
    $env:PHP = $php
    $lines = @()
    $code = 0
    Push-Location $RepoRoot
    # Windows PowerShell 5.1 turns native stderr into terminating errors under
    # 'Stop'; the exit code and the summary lines are what is checked here.
    $ErrorActionPreference = 'Continue'
    try {
        $lines = @(& $bash "$PluginRelPath/tests/run-static.sh" 2>&1 | ForEach-Object { "$_" })
        $code = $LASTEXITCODE
    }
    finally {
        $ErrorActionPreference = 'Stop'
        Pop-Location
        if ($null -eq $previousPhp) { Remove-Item Env:\PHP -ErrorAction SilentlyContinue } else { $env:PHP = $previousPhp }
    }
    $lines | ForEach-Object { Write-Info $_ }
    if ($code -ne 0) { Stop-Deploy "plugin static checks failed (exit code $code)" }
    $text = $lines -join "`n"
    foreach ($marker in @('all PHP files lint clean', 'event-panel.js syntax OK')) {
        if ($text -notmatch [regex]::Escape($marker)) { Stop-Deploy "plugin static checks did not report: $marker" }
    }
    if ($text -notmatch '(?m)^\d+ passed, 0 failed \(\d+ files\)\s*$') { Stop-Deploy 'plugin unit tests did not report "N passed, 0 failed"' }
    if ($text -notmatch '(?m)^architecture: \d+ checks, 0 problems\s*$') { Stop-Deploy 'architecture check did not report "0 problems"' }
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
$tempDir = $null
$exitCode = 0

try {
    $modeCount = @($LocalOnly, $VerifyOnly, $Deploy | Where-Object { $_ }).Count
    if ($modeCount -ne 1) { Stop-Deploy 'choose exactly one mode: -LocalOnly (no network), -VerifyOnly (read-only remote check) or -Deploy (real deployment, asks for confirmation). There is no default action.' }

    $mode = 'DEPLOY'
    if ($VerifyOnly) { $mode = 'VERIFY ONLY (no remote changes)' }
    if ($LocalOnly) { $mode = 'LOCAL ONLY (no network)' }

    Write-Host "La Casa del Arbol - casa-eventos plugin deployment [$mode]" -ForegroundColor White

    Write-Step 'Checking the remote script'
    Assert-NoProcessSubstitution $RemoteScriptTemplate
    Assert-RemoteScriptSafety $RemoteScriptTemplate
    Write-Info 'No process substitution'
    Write-Info 'No removal of live/backup paths, no WordPress plugin-state commands'

    # 0. Local configuration -------------------------------------------------
    Write-Step "Loading $ConfigFileName"
    $config = Read-DeployConfig (Join-Path $PSScriptRoot $ConfigFileName)
    foreach ($key in $RequiredConfigKeys) {
        New-Variable -Name $key -Value $config[$key] -Option ReadOnly -Force
    }
    $RemotePluginDir = "$RemoteWpRoot/wp-content/plugins/$PluginSlug"
    Write-Info "Target: $RemoteUser@${RemoteHost}:$RemotePort"
    Write-Info "Plugin: $RemotePluginDir"

    # 1. Local tools ---------------------------------------------------------
    Write-Step 'Checking local tools'
    $tools = @('git', 'tar')
    if (-not $LocalOnly) { $tools += @('ssh') }
    foreach ($tool in $tools) {
        if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) { Stop-Deploy "required tool not found: $tool" }
        Write-Info "$tool OK"
    }

    # 2. Repository and plugin files ----------------------------------------
    Write-Step 'Checking repository and plugin files'
    $repoRoot = $PSScriptRoot
    $gitTop = (Get-NativeOutput 'git' @('-C', $repoRoot, 'rev-parse', '--show-toplevel')) | Select-Object -First 1
    if ([System.IO.Path]::GetFullPath($gitTop) -ne [System.IO.Path]::GetFullPath($repoRoot)) {
        Stop-Deploy "this script must live at the repository root ($gitTop)"
    }

    $pluginDir = Join-Path $repoRoot $PluginRelPath
    if (-not (Test-Path -LiteralPath $pluginDir -PathType Container)) { Stop-Deploy "local plugin directory not found: $pluginDir" }
    foreach ($file in $RequiredFiles) {
        if (-not (Test-Path -LiteralPath (Join-Path $pluginDir $file) -PathType Leaf)) { Stop-Deploy "required plugin file missing: $file" }
    }

    # 2a. Every top-level entry of the plugin is either shipped or explicitly
    # excluded. A new, unclassified entry stops the run.
    $topEntries = @(Get-NativeOutput 'git' @('-C', $repoRoot, 'ls-tree', '--name-only', "HEAD:$PluginRelPath"))
    foreach ($entry in $topEntries) {
        if ($ShipEntries -ccontains $entry) { continue }
        if ($ExcludedEntries.ContainsKey($entry) -and $ExcludedEntries[$entry]) { continue }
        Stop-Deploy "unclassified top-level entry in $PluginRelPath/: $entry. Decide whether it ships (add it to `$ShipEntries) or not (add it to `$ExcludedEntries) in deploy-plugin.ps1, then commit."
    }
    foreach ($entry in $ShipEntries) {
        if ($topEntries -cnotcontains $entry) { Stop-Deploy "shipped entry not committed in HEAD: $entry" }
    }
    Write-Info ("Ships:    " + ($ShipEntries -join ', '))
    Write-Info ("Excluded: " + (($ExcludedEntries.Keys | Sort-Object | ForEach-Object { "$_ ($($ExcludedEntries[$_]))" }) -join '; '))

    # 2b. Version gate: header, constant and readme must agree.
    $mainSrc = Get-Content -LiteralPath (Join-Path $pluginDir 'casa-eventos.php') -Raw
    $readmeSrc = Get-Content -LiteralPath (Join-Path $pluginDir 'readme.md') -Raw
    if ($mainSrc -notmatch '(?m)^\s*\*\s*Plugin Name:\s*Casa Eventos\s*$') { Stop-Deploy 'casa-eventos.php does not declare "Plugin Name: Casa Eventos"' }
    if ($mainSrc -notmatch '(?m)^\s*\*\s*Text Domain:\s*casa-eventos\s*$') { Stop-Deploy 'casa-eventos.php does not declare "Text Domain: casa-eventos"' }
    $headerVersion = ''
    if ($mainSrc -match '(?m)^\s*\*\s*Version:\s*(\S+)\s*$') { $headerVersion = $Matches[1] }
    $constVersion = ''
    if ($mainSrc -match "define\(\s*'CASA_EVENTOS_VERSION',\s*'([^']+)'\s*\)") { $constVersion = $Matches[1] }
    $readmeVersion = ''
    if ($readmeSrc -match '(?m)^\*\*Version:\*\*\s*([0-9]+\.[0-9]+\.[0-9]+)') { $readmeVersion = $Matches[1] }
    Write-Info "Versions: header=$headerVersion, CASA_EVENTOS_VERSION=$constVersion, readme=$readmeVersion"
    if ($headerVersion -notmatch '^[0-9]+\.[0-9]+\.[0-9]+$') { Stop-Deploy "plugin header version is missing or not MAJOR.MINOR.PATCH: '$headerVersion'" }
    if (($headerVersion -cne $constVersion) -or ($headerVersion -cne $readmeVersion)) {
        Stop-Deploy "version mismatch: header=$headerVersion, CASA_EVENTOS_VERSION=$constVersion, readme=$readmeVersion. All three must be equal."
    }
    $pluginVersion = $headerVersion

    # 3. Git state: deploy exactly what is committed -------------------------
    Write-Step 'Checking Git state'
    $dirty = Get-NativeOutput 'git' @('-C', $repoRoot, 'status', '--porcelain', '--untracked-files=all', '--', $PluginRelPath)
    if ($dirty) {
        $dirty | ForEach-Object { Write-Info $_ }
        Stop-Deploy "$PluginRelPath has uncommitted changes. Commit or discard them first; only committed code is deployed."
    }
    $lsTree = @(Get-NativeOutput 'git' (@('-C', $repoRoot, 'ls-tree', '-r', "HEAD:$PluginRelPath", '--') + $ShipEntries))
    $shipFiles = @()
    foreach ($row in $lsTree) {
        if ($row -notmatch '^(\d{6}) (\w+) ([0-9a-f]{40})\t(.+)$') { Stop-Deploy "unexpected git ls-tree row: $row" }
        if ($Matches[1] -notin @('100644', '100755') -or $Matches[2] -ne 'blob') { Stop-Deploy "unsupported entry (symlink or submodule): $($Matches[4])" }
        $shipFiles += $Matches[4]
    }
    foreach ($file in $RequiredFiles) {
        if ($shipFiles -cnotcontains $file) { Stop-Deploy "required file not committed in HEAD: $file" }
    }
    $commit = (Get-NativeOutput 'git' @('-C', $repoRoot, 'log', '-1', '--format=%h %s')) | Select-Object -First 1
    $commitFull = (Get-NativeOutput 'git' @('-C', $repoRoot, 'rev-parse', 'HEAD')) | Select-Object -First 1
    $branch = (Get-NativeOutput 'git' @('-C', $repoRoot, 'rev-parse', '--abbrev-ref', 'HEAD')) | Select-Object -First 1
    Write-Info "Branch: $branch"
    Write-Info "Commit: $commit"
    Write-Info "Files to ship: $($shipFiles.Count)"

    # A real deployment must be pushed: the repository is the source of truth.
    # Local ref only (no fetch, no network).
    $originMain = & 'git' '-C' $repoRoot 'rev-parse' '--verify' '--quiet' 'refs/remotes/origin/main'
    $isPushed = ($LASTEXITCODE -eq 0) -and ("$originMain" -eq "$commitFull")
    if ($isPushed) { Write-Info 'HEAD equals origin/main: yes' } else { Write-Info 'HEAD equals origin/main: NO (-Deploy will refuse)' }
    if ($Deploy -and -not $isPushed) { Stop-Deploy 'HEAD is not origin/main (unpushed, or origin/main unknown). Push first: only published code is deployed.' }

    # 4. Static checks (plugin's own suite) -----------------------------------
    Write-Step 'Running the plugin static checks (tests/run-static.sh)'
    Invoke-StaticChecks $repoRoot
    Write-Info 'Static checks passed'

    # 5. Package -------------------------------------------------------------
    Write-Step 'Building package'
    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $tempDir = Join-Path ([System.IO.Path]::GetTempPath()) "lcda-plugin-deploy-$stamp"
    New-Item -ItemType Directory -Path $tempDir | Out-Null
    $package = Join-Path $tempDir 'plugin.tar'

    # core.autocrlf/eol overrides: archive the blobs exactly as committed (LF).
    # The allowlist is applied here: only the shipped entries enter the archive.
    Invoke-Native 'git' (@('-C', $repoRoot, '-c', 'core.autocrlf=false', '-c', 'core.eol=lf', 'archive', '--format=tar', '-o', $package, "HEAD:$PluginRelPath") + $ShipEntries)
    $packageSha = (Get-FileHash -LiteralPath $package -Algorithm SHA256).Hash.ToLowerInvariant()
    $packageKb = [math]::Round((Get-Item -LiteralPath $package).Length / 1KB, 1)
    Write-Info "Deployment id: $stamp"
    Write-Info "Package: $packageKb KB, $($shipFiles.Count) files, version $pluginVersion"
    Write-Info "SHA-256: $packageSha"

    # 5a. Inspect the package exactly as the server will receive it.
    Write-Step 'Inspecting the package'
    $entries = @(Get-NativeOutput 'tar' @('-tf', $package))
    foreach ($e in $entries) {
        $p = "$e".TrimEnd('/')
        if ($p -match '^[/\\]|^[A-Za-z]:|(^|[/\\])\.\.([/\\]|$)|\\') { Stop-Deploy "package entry escapes the plugin directory or is not a plain relative path: $e" }
        $top = ($p -split '/')[0]
        if ($ShipEntries -cnotcontains $top) { Stop-Deploy "package entry outside the allowlist: $e" }
    }
    $treeDir = Join-Path $tempDir 'tree'
    New-Item -ItemType Directory -Path $treeDir | Out-Null
    Invoke-Native 'tar' @('-xf', $package, '-C', $treeDir)
    $treeRoot = (Resolve-Path -LiteralPath $treeDir).Path.TrimEnd('\')
    $treeItems = @(Get-ChildItem -LiteralPath $treeDir -Recurse -Force)
    if (@($treeItems | Where-Object { $_.Attributes -band [System.IO.FileAttributes]::ReparsePoint }).Count -gt 0) { Stop-Deploy 'package contains links' }
    $treeFiles = @($treeItems | Where-Object { -not $_.PSIsContainer })
    $treeRel = @($treeFiles | ForEach-Object { $_.FullName.Substring($treeRoot.Length + 1).Replace('\', '/') } | Sort-Object { $_ } -CaseSensitive)
    $expectedRel = @($shipFiles | Sort-Object { $_ } -CaseSensitive)
    if (($treeRel -join "`n") -cne ($expectedRel -join "`n")) {
        $diff = Compare-Object $expectedRel $treeRel | ForEach-Object { "$($_.SideIndicator) $($_.InputObject)" }
        Stop-Deploy "package tree differs from the committed shipped files:`n$($diff -join "`n")"
    }
    $topNames = @(Get-ChildItem -LiteralPath $treeDir -Force | ForEach-Object { $_.Name } | Sort-Object { $_ } -CaseSensitive)
    if (($topNames -join ',') -cne (($ShipEntries | Sort-Object { $_ } -CaseSensitive) -join ',')) { Stop-Deploy "package top level is not exactly: $($ShipEntries -join ', ')" }
    $bytes = ($treeFiles | Measure-Object -Property Length -Sum).Sum
    Write-Info "Tree matches the committed shipped files: $($treeRel.Count) files, $bytes bytes"
    Write-Info ("Top level: " + ($topNames -join ', '))
    $byDir = $treeRel | Group-Object { if ($_ -match '/') { ($_ -split '/')[0] + '/' } else { '(root)' } }
    foreach ($g in $byDir) { Write-Info ("  {0,-8} {1} files" -f $g.Name, $g.Count) }

    # Runtime references: every file the shipped code loads must be in the
    # package, and no shipped code may reach for tests/.
    $refs = 0
    foreach ($f in @($treeRel | Where-Object { $_ -like '*.php' })) {
        $src = Get-Content -LiteralPath (Join-Path $treeDir $f) -Raw
        $fileDir = ''
        if ($f -match '/') { $fileDir = ($f -replace '/[^/]+$', '') + '/' }
        $targets = @()
        foreach ($m in [regex]::Matches($src, "CASA_EVENTOS_(?:DIR|URL)\s*\.\s*'([^']+)'")) { $targets += $m.Groups[1].Value }
        foreach ($m in [regex]::Matches($src, "__DIR__\s*\.\s*'/([^']+)'")) { $targets += ($fileDir + $m.Groups[1].Value) }
        foreach ($t in $targets) {
            $refs++
            if ($t -match '^tests/') { Stop-Deploy "shipped file $f references tests/: $t" }
            if ($treeRel -cnotcontains $t) { Stop-Deploy "shipped file $f references a file that is not in the package: $t" }
        }
    }
    Write-Info "Runtime references resolved inside the package: $refs"

    # PHP lint of the package itself (not the working tree).
    $php = Find-Php
    $phpFiles = @($treeRel | Where-Object { $_ -like '*.php' })
    $linted = 0
    foreach ($f in $phpFiles) {
        $ErrorActionPreference = 'Continue'
        $out = & $php '-l' (Join-Path $treeDir $f) 2>&1
        $lintCode = $LASTEXITCODE
        $ErrorActionPreference = 'Stop'
        if ($lintCode -ne 0) { Stop-Deploy "PHP syntax error in package file ${f}: $out" }
        $linted++
    }
    if ($linted -ne $phpFiles.Count -or $linted -eq 0) { Stop-Deploy "package lint checked $linted of $($phpFiles.Count) PHP files" }
    Write-Info "Package PHP lint OK: $linted files"
    $pkgHeader = Get-Content -LiteralPath (Join-Path $treeDir 'casa-eventos.php') -Raw
    if ($pkgHeader -notmatch "(?m)^\s*\*\s*Version:\s*$([regex]::Escape($pluginVersion))\s*$" -or $pkgHeader -notmatch "define\(\s*'CASA_EVENTOS_VERSION',\s*'$([regex]::Escape($pluginVersion))'\s*\)") {
        Stop-Deploy "packaged casa-eventos.php does not carry version $pluginVersion"
    }
    Write-Info "Packaged version: $pluginVersion"

    if ($LocalOnly) {
        Write-Step 'Local checks passed. No network connection was made.'
    }
    else {
        $remoteScript = $RemoteScriptTemplate.Replace('__REMOTE_HOME__', $RemoteHome).Replace('__WP_ROOT__', $RemoteWpRoot).Replace('__REMOTE_PLUGIN_DIR__', $RemotePluginDir).Replace('__PLUGIN_SLUG__', $PluginSlug).Replace('__TOP_ENTRIES__', ($ShipEntries -join ' '))
        $remoteScript = $remoteScript -replace "`r`n", "`n"
        Assert-NoProcessSubstitution $remoteScript
        Assert-RemoteScriptSafety $remoteScript
        $encoded = [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes($remoteScript))
        $packageBase64 = [Convert]::ToBase64String([System.IO.File]::ReadAllBytes($package))
        $scriptArgs = @($stamp, $packageSha, "$($shipFiles.Count)", $pluginVersion)
        $target = "$RemoteUser@$RemoteHost"
        $OutputEncoding = New-Object System.Text.ASCIIEncoding

        # 6. Remote verification and transport check (read-only) ------------
        Write-Step 'Verifying remote target and package transport (read-only)'
        $verifyLines = Invoke-RemoteScript $target $encoded (@('verify') + $scriptArgs) $packageBase64
        $remoteState = 'unknown'
        foreach ($l in $verifyLines) { if ("$l" -match '^\[remote\] STATE: (absent|installed [0-9][0-9A-Za-z.+-]*)$') { $remoteState = $Matches[1] } }
        if ($remoteState -eq 'unknown') { Stop-Deploy 'the remote verification did not report the plugin state' }

        if ($VerifyOnly) {
            Write-Step 'Verification complete. Nothing was uploaded or changed.'
            Write-Info "Remote plugin state: $remoteState"
        }
        else {
            # 7. Confirmation ------------------------------------------------
            Write-Host ''
            Write-Host "About to deploy casa-eventos $pluginVersion (commit '$commit') to ${RemoteHost}:$RemotePluginDir" -ForegroundColor Yellow
            if ($remoteState -eq 'absent') {
                Write-Host 'The plugin is not on the server yet: this is a FIRST INSTALL of files.' -ForegroundColor Yellow
            }
            else {
                Write-Host "Remote state: $remoteState. The whole directory is REPLACED: a verified backup is made, and the old directory is kept aside." -ForegroundColor Yellow
            }
            Write-Host 'Only files are deployed. The plugin is NOT activated, deactivated or uninstalled by this script.' -ForegroundColor Yellow
            $answer = Read-Host 'Type DEPLOY-PLUGIN to continue'
            if ($answer -cne 'DEPLOY-PLUGIN') {
                Write-Step 'Cancelled. Nothing was uploaded or changed.'
                $exitCode = 2
            }
            else {
                # 8. Remote deploy ------------------------------------------
                Write-Step 'Deploying on the server (stream, lint, backup, swap, verify)'
                Invoke-RemoteScript $target $encoded (@('deploy') + $scriptArgs) $packageBase64 | Out-Null

                Write-Step 'Deployment complete (files only)'
                Write-Info "Commit:        $commit"
                Write-Info "Version:       $pluginVersion"
                Write-Info "Deployment id: $stamp"
                if ($remoteState -ne 'absent') {
                    Write-Info "Backup:        $RemoteHome/lcda-plugin-backups/$PluginSlug-$stamp.tar.gz"
                    Write-Info "Previous dir:  $RemoteHome/lcda-plugin-backups/replaced/$PluginSlug-$stamp"
                }
                Write-Info 'Next: activation is a separate, reviewed step (wp-admin > Plugins). See docs/deployment.md.'
            }
        }
    }
}
catch {
    Write-Host ''
    Write-Host $_.Exception.Message -ForegroundColor Red
    Write-Host 'See docs/deployment.md (Plugin deployment: Troubleshooting / Rollback).' -ForegroundColor Red
    $exitCode = 1
}
finally {
    if ($tempDir -and (Test-Path -LiteralPath $tempDir)) {
        Remove-Item -LiteralPath $tempDir -Recurse -Force
    }
}

exit $exitCode
