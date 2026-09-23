#Requires -Version 5.1
<#
.SYNOPSIS
    Deploys the La Casa del Arbol child theme (themes/la-casa-del-arbol/) to
    the production server, with a verified remote backup first.

.DESCRIPTION
    Read docs/deployment.md before running this script.

    What it deploys: ONLY the committed contents of themes/la-casa-del-arbol/
    at the current Git HEAD (packaged with "git archive"). Uncommitted changes
    are never deployed; the script refuses to run if the theme directory has
    any.

    Where: the single remote child-theme directory defined in deploy.local.ps1
    (git-ignored; template: deploy.example.ps1). That file is read as data
    (parsed, never executed), validated, and then fixed for the whole run.
    There is no command-line override for host or paths. Nothing else on the
    server is modified: no WordPress core, plugins, uploads, Astra parent
    theme or database.

    How (two SSH connections, no scp):
      1. Local checks: tools, repository, theme files, clean Git state.
      2. Package: git archive of HEAD:themes/la-casa-del-arbol (LF line
         endings), plus SHA-256 checksum and file count.
      3. Remote verification (read-only SSH): account, WordPress root, themes
         dir, child theme identity, Astra parent present. Prints versions
         and stale leftovers of earlier runs. Transport check: the package
         is streamed over the same connection, and the server hashes it in
         memory (nothing written) and must match the local SHA-256.
      4. Confirmation: you must type DEPLOY.
      5. Remote deploy (SSH): the package is streamed over the connection
         straight into a private staging dir. Re-verify, check the
         checksum, extract, PHP lint, create and verify a timestamped
         backup of the live child theme, copy the files over the live theme
         (overwrite, never delete), verify every deployed file by checksum,
         report remote files not in the package (NOT deleted), clean staging.

    Credentials: none are stored here. SSH uses your existing authentication
    (key, agent or password prompt). The server's host key must already be in
    your known_hosts (StrictHostKeyChecking=yes).

.PARAMETER VerifyOnly
    Run local checks, build the package, and run the read-only remote
    verification and transport check. Changes nothing on the server.

.PARAMETER LocalOnly
    Run local checks and build the package only. No network connection.

.EXAMPLE
    .\deploy-theme.ps1 -LocalOnly
.EXAMPLE
    .\deploy-theme.ps1 -VerifyOnly
.EXAMPLE
    .\deploy-theme.ps1

.NOTES
    Exit codes: 0 = success, 1 = failed (validation, backup, transfer or
    verification), 2 = cancelled at the confirmation prompt (nothing changed).
    This file must stay ASCII-only (Windows PowerShell 5.1 compatibility).
#>
[CmdletBinding()]
param(
    [switch] $VerifyOnly,
    [switch] $LocalOnly
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# ---------------------------------------------------------------------------
# Fixed settings. Environment-specific values (host, port, user, remote paths)
# live in deploy.local.ps1 and are loaded and validated by Read-DeployConfig.
# ---------------------------------------------------------------------------
$ThemeSlug          = 'la-casa-del-arbol'
$ThemeRelPath       = 'themes/la-casa-del-arbol'
$ConfigFileName     = 'deploy.local.ps1'
$RequiredConfigKeys = @('RemoteHost', 'RemotePort', 'RemoteUser', 'RemoteHome', 'RemoteWpRoot', 'RemoteThemeDir')

# Files that must exist locally (and in HEAD) for a deploy to be allowed.
$RequiredFiles = @(
    'style.css',
    'functions.php',
    'theme.json',
    'inc/setup.php',
    'inc/menus.php',
    'inc/template-tags.php',
    'inc/assets.php',
    'inc/astra.php',
    'inc/patterns.php',
    'page-templates/canvas.php',
    'template-parts/site/header.php',
    'template-parts/site/mobile-menu.php',
    'template-parts/site/footer.php',
    'assets/css/base.css',
    'assets/css/chrome.css',
    'assets/js/mobile-menu.js',
    'assets/images/logo-symbol.svg',
    'assets/images/logo-full.svg'
)

# ---------------------------------------------------------------------------
# Remote script (bash). Sent base64-encoded as part of the SSH command, so no
# file is written on the server for it, and Windows line endings or encodings
# can't corrupt it. Placeholders __X__ are replaced from the validated
# configuration. Modes: "verify" (read-only) and "deploy".
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

EXPECTED_HOME='__REMOTE_HOME__'
WP_ROOT='__WP_ROOT__'
SLUG='__THEME_SLUG__'
THEMES_DIR="$WP_ROOT/wp-content/themes"
THEME_DIR='__REMOTE_THEME_DIR__'
PARENT_DIR="$THEMES_DIR/astra"
BACKUP_DIR="$EXPECTED_HOME/lcda-theme-backups"
STAGE_ROOT="$EXPECTED_HOME/.lcda-deploy"

OVERLAY_STARTED=0
BACKUP=""

log() { printf '[remote] %s\n' "$*"; }

rollback_hint() {
  if [ "$OVERLAY_STARTED" = 1 ]; then
    printf '[remote] The live theme may be partially updated.\n' >&2
    printf '[remote] Restore it from the backup: %s (see docs/deployment.md, Rollback)\n' "$BACKUP" >&2
  fi
}

fail() {
  printf '[remote] ERROR: %s\n' "$*" >&2
  rollback_hint
  exit 1
}

on_error() {
  local code=$?
  printf '[remote] ERROR: command failed (exit %s) near line %s\n' "$code" "${BASH_LINENO[0]}" >&2
  rollback_hint
  exit "$code"
}
trap on_error ERR

verify_target() {
  [ "$HOME" = "$EXPECTED_HOME" ] || fail "unexpected account home: $HOME"
  [ "$THEME_DIR" = "$THEMES_DIR/$SLUG" ] || fail "configured theme dir is not <wp root>/wp-content/themes/$SLUG"
  local c
  for c in tar sha256sum base64 tr find cp grep sort comm wc cut sed; do
    command -v "$c" >/dev/null 2>&1 || fail "missing remote command: $c"
  done
  [ ! -L "$STAGE_ROOT" ] || fail "staging root is a symlink: $STAGE_ROOT"
  [ ! -L "$BACKUP_DIR" ] || fail "backup directory is a symlink: $BACKUP_DIR"
  [ -d "$WP_ROOT" ] || fail "WordPress root not found: $WP_ROOT"
  { [ -f "$WP_ROOT/wp-load.php" ] && [ -f "$WP_ROOT/wp-includes/version.php" ]; } || fail "not a WordPress root: $WP_ROOT"
  { [ -f "$WP_ROOT/wp-config.php" ] || [ -f "$(dirname "$WP_ROOT")/wp-config.php" ]; } || fail "wp-config.php not found"
  [ -d "$THEMES_DIR" ] || fail "themes directory not found: $THEMES_DIR"
  [ -d "$THEME_DIR" ] || fail "child theme directory not found: $THEME_DIR"
  [ ! -L "$THEME_DIR" ] || fail "child theme directory is a symlink: $THEME_DIR"
  local real_themes real_theme
  real_themes="$(cd "$THEMES_DIR" && pwd -P)"
  real_theme="$(cd "$THEME_DIR" && pwd -P)"
  [ "$real_theme" = "$real_themes/$SLUG" ] || fail "child theme path resolves outside the themes directory"
  [ -f "$THEME_DIR/style.css" ] || fail "remote child theme has no style.css"
  grep -Eq '^[[:space:]]*Template:[[:space:]]*astra[[:space:]]*$' "$THEME_DIR/style.css" || fail "remote style.css is not an Astra child theme"
  grep -Eq 'Text Domain:[[:space:]]*la-casa-del-arbol' "$THEME_DIR/style.css" || fail "remote style.css is not the la-casa-del-arbol theme"
  [ -f "$PARENT_DIR/style.css" ] || fail "Astra parent theme not found: $PARENT_DIR"
}

header_value() {
  grep -m1 -E "^[[:space:]]*$1:" "$2" | sed -E "s/^[[:space:]]*$1:[[:space:]]*//" | tr -d '\r' || true
}

report_target() {
  local wp_version
  wp_version="$(grep -m1 -E '^\$wp_version' "$WP_ROOT/wp-includes/version.php" | cut -d"'" -f2 || true)"
  log "Account home:      $HOME"
  log "WordPress root:    $WP_ROOT (WordPress ${wp_version:-unknown})"
  log "Child theme:       $THEME_DIR (version $(header_value Version "$THEME_DIR/style.css"), $(find "$THEME_DIR" -type f | wc -l | tr -d ' ') files)"
  log "Astra parent:      $PARENT_DIR (version $(header_value Version "$PARENT_DIR/style.css"), read-only)"
  if command -v php >/dev/null 2>&1; then
    log "PHP CLI:           $(php -r 'echo PHP_VERSION;' 2>/dev/null || echo unknown)"
  else
    log "PHP CLI:           not available (remote lint will be skipped)"
  fi
}

# Leftovers of earlier runs: informational only, never deleted, never
# blocking. Strict name patterns; find does not follow symlinks (-type f/d
# never match a symlink).
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
  { true <&3; } 2>/dev/null || fail "no package stream on fd 3"
}

# Package stream (base64 text on fd 3) -> raw bytes on stdout. Anything
# outside the base64 alphabet (CR, LF, a BOM) is dropped; the SHA-256 check
# that always follows catches any other damage.
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
  local pkg="$stage/theme.tar"
  read_package > "$pkg" || fail "package stream could not be decoded (transfer interrupted?); the live theme was not changed"
  exec 3<&-
  [ "$(sha256sum "$pkg" | cut -d' ' -f1)" = "$PKG_SHA" ] || fail "package checksum mismatch (transfer corrupted?); the live theme was not changed"
  if tar -tf "$pkg" | grep -Eq '^/|(^|/)\.\.(/|$)'; then fail "package contains unsafe paths"; fi

  mkdir "$stage/theme"
  tar -xf "$pkg" -C "$stage/theme" --no-same-owner
  [ -z "$(find "$stage/theme" -type l)" ] || fail "package contains symlinks"
  local staged_count
  staged_count="$(find "$stage/theme" -type f | wc -l | tr -d ' ')"
  [ "$staged_count" = "$EXPECTED_FILES" ] || fail "package has $staged_count files, expected $EXPECTED_FILES"
  { [ -f "$stage/theme/style.css" ] && [ -f "$stage/theme/functions.php" ]; } || fail "package is missing style.css or functions.php"
  grep -Eq '^[[:space:]]*Template:[[:space:]]*astra[[:space:]]*$' "$stage/theme/style.css" || fail "package style.css is not an Astra child theme"
  log "Package OK: $staged_count files, checksum verified"

  # 2. PHP lint of the new code, before anything live changes.
  if command -v php >/dev/null 2>&1; then
    local bad=0 f
    while IFS= read -r -d '' f; do
      php -l "$f" >/dev/null 2>&1 || { log "PHP syntax error: ${f#"$stage/theme/"}"; bad=1; }
    done < <(find "$stage/theme" -type f -name '*.php' -print0)
    [ "$bad" = 0 ] || fail "PHP lint failed; the live theme was not changed"
    log "PHP lint OK"
  else
    log "PHP CLI not available: lint skipped"
  fi

  # 3. Backup of the live child theme (outside public_html), then verify it.
  mkdir -p "$BACKUP_DIR"
  chmod 700 "$BACKUP_DIR"
  BACKUP="$BACKUP_DIR/$SLUG-$STAMP.tar.gz"
  [ ! -e "$BACKUP" ] || fail "backup already exists: $BACKUP"
  tar -C "$THEMES_DIR" -czf "$BACKUP" "$SLUG"
  local live_count backup_count
  live_count="$(find "$THEME_DIR" -type f | wc -l | tr -d ' ')"
  backup_count="$(tar -tzf "$BACKUP" | grep -vc '/$' || true)"
  [ "$backup_count" = "$live_count" ] || fail "backup verification failed ($backup_count entries vs $live_count live files); the live theme was not changed"
  log "Backup OK: $BACKUP ($live_count files, $(du -h "$BACKUP" | cut -f1))"

  # 4. Copy over the live theme: overwrite and add, never delete.
  OVERLAY_STARTED=1
  cp -R "$stage/theme/." "$THEME_DIR/"

  # 5. Verify every deployed file against the package.
  (cd "$stage/theme" && find . -type f -print0 | xargs -0 sha256sum) > "$stage/manifest.sha256"
  (cd "$THEME_DIR" && sha256sum --quiet -c "$stage/manifest.sha256") || fail "post-copy checksum verification failed"
  OVERLAY_STARTED=0
  log "Deployed and verified: $staged_count files"

  # 6. Remote files that are not in the package: reported, never deleted.
  local orphans orphan_count
  orphans="$(comm -23 <(cd "$THEME_DIR" && find . -type f | sort) <(cd "$stage/theme" && find . -type f | sort))"
  if [ -n "$orphans" ]; then
    orphan_count="$(printf '%s\n' "$orphans" | wc -l | tr -d ' ')"
    log "Remote files not in this release (kept, NOT deleted): $orphan_count"
    printf '%s\n' "$orphans" | sed 's/^/[remote]   /'
  else
    log "Remote files not in this release: none"
  fi

  # 7. Remove this deployment's staging dir (only that path).
  case "$stage" in
    "$STAGE_ROOT"/[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]-[0-9][0-9][0-9][0-9][0-9][0-9]) rm -rf -- "$stage" ;;
    *) fail "refusing to remove unexpected staging path: $stage" ;;
  esac

  log "Child theme now: version $(header_value Version "$THEME_DIR/style.css")"
  log "Rollback backup: $BACKUP"
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
# Completion marker: the local side requires this exact line, so a remote
# script that was cut short or never ran can't pass as a success.
log "RESULT: $MODE OK $STAMP"
'@

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
function Write-Step([string] $Message) { Write-Host ''; Write-Host "==> $Message" -ForegroundColor Cyan }
function Write-Info([string] $Message) { Write-Host "    $Message" }
function Stop-Deploy([string] $Message) { throw "DEPLOY ABORTED: $Message" }

# Run a native command; fail on a non-zero exit code.
function Invoke-Native {
    param([string] $FilePath, [string[]] $Arguments)
    & $FilePath @Arguments
    if ($LASTEXITCODE -ne 0) { Stop-Deploy "$FilePath exited with code $LASTEXITCODE" }
}

# Run a native command and return its stdout lines; fail on non-zero exit.
function Get-NativeOutput {
    param([string] $FilePath, [string[]] $Arguments)
    $output = & $FilePath @Arguments
    if ($LASTEXITCODE -ne 0) { Stop-Deploy "$FilePath $($Arguments -join ' ') exited with code $LASTEXITCODE" }
    return $output
}

function Get-SshArgs {
    return @('-p', "$RemotePort", '-o', 'ConnectTimeout=20', '-o', 'StrictHostKeyChecking=yes')
}

# SSH command that decodes the remote script and runs it in the given mode.
# "exec 3<&0" keeps the connection's stdin (the package stream) on fd 3,
# because bash -s reads the script itself from its own stdin.
function Get-RemoteCommand([string] $Encoded, [string[]] $ScriptArgs) {
    return "exec 3<&0; printf %s $Encoded | base64 -d | bash -s -- $($ScriptArgs -join ' ')"
}

# Run the remote script over SSH, streaming the package (base64 text) on
# stdin. Windows PowerShell 5.1 can't pipe raw bytes to a native command,
# hence base64. The password prompt, if any, reads the console, not stdin.
# Success requires exit code 0 AND the script's exact completion line.
function Invoke-RemoteScript([string] $Target, [string] $Encoded, [string[]] $ScriptArgs, [string] $PackageBase64) {
    $sshArgs = (Get-SshArgs) + @($Target, (Get-RemoteCommand $Encoded $ScriptArgs))
    $resultLine = "[remote] RESULT: $($ScriptArgs[0]) OK $($ScriptArgs[1])"
    $completed = $false
    $PackageBase64 | & 'ssh' @sshArgs | ForEach-Object {
        Write-Host $_
        if ("$_" -ceq $resultLine) { $completed = $true }
    }
    if ($LASTEXITCODE -ne 0) {
        Stop-Deploy "ssh exited with code $LASTEXITCODE (code 255 with no [remote] lines above: connection or authentication failed before the remote script started)"
    }
    if (-not $completed) { Stop-Deploy 'the remote script did not report completion; treat this run as failed' }
}

# Load and validate deploy.local.ps1.
# The file is parsed as data, never executed: it must contain exactly one
# @{ ... } hashtable of constant values. Values are validated strictly
# because they are embedded in the remote bash script.
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

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
$tempDir = $null
$exitCode = 0

try {
    if ($VerifyOnly -and $LocalOnly) { Stop-Deploy 'use either -VerifyOnly or -LocalOnly, not both' }

    $mode = 'DEPLOY'
    if ($VerifyOnly) { $mode = 'VERIFY ONLY (no remote changes)' }
    if ($LocalOnly) { $mode = 'LOCAL ONLY (no network)' }

    Write-Host "La Casa del Arbol - child theme deployment [$mode]" -ForegroundColor White

    # 0. Local configuration (read once, then read-only for the whole run) --
    Write-Step "Loading $ConfigFileName"
    $config = Read-DeployConfig (Join-Path $PSScriptRoot $ConfigFileName)
    foreach ($key in $RequiredConfigKeys) {
        New-Variable -Name $key -Value $config[$key] -Option ReadOnly -Force
    }
    Write-Info "Target: $RemoteUser@${RemoteHost}:$RemotePort"
    Write-Info "Theme:  $RemoteThemeDir"

    # 1. Local tools --------------------------------------------------------
    Write-Step 'Checking local tools'
    $tools = @('git')
    if (-not $LocalOnly) { $tools += @('ssh') }
    foreach ($tool in $tools) {
        if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) { Stop-Deploy "required tool not found: $tool" }
        Write-Info "$tool OK"
    }

    # 2. Repository and theme files -----------------------------------------
    Write-Step 'Checking repository and theme files'
    $repoRoot = $PSScriptRoot
    $gitTop = (Get-NativeOutput 'git' @('-C', $repoRoot, 'rev-parse', '--show-toplevel')) | Select-Object -First 1
    if ([System.IO.Path]::GetFullPath($gitTop) -ne [System.IO.Path]::GetFullPath($repoRoot)) {
        Stop-Deploy "this script must live at the repository root ($gitTop)"
    }

    $themeDir = Join-Path $repoRoot $ThemeRelPath
    if (-not (Test-Path -LiteralPath $themeDir -PathType Container)) { Stop-Deploy "local theme directory not found: $themeDir" }

    foreach ($file in $RequiredFiles) {
        if (-not (Test-Path -LiteralPath (Join-Path $themeDir $file) -PathType Leaf)) { Stop-Deploy "required theme file missing: $file" }
    }
    $styleCss = Get-Content -LiteralPath (Join-Path $themeDir 'style.css') -Raw
    if ($styleCss -notmatch '(?m)^\s*Template:\s*astra\s*$') { Stop-Deploy 'style.css does not declare "Template: astra"' }
    if ($styleCss -notmatch 'Text Domain:\s*la-casa-del-arbol') { Stop-Deploy 'style.css is not the la-casa-del-arbol theme' }
    $themeVersion = ''
    if ($styleCss -match '(?m)^\s*Version:\s*(\S+)') { $themeVersion = $Matches[1] }
    Write-Info "Theme directory OK ($($RequiredFiles.Count) required files present, version $themeVersion)"

    # 3. Git state: deploy exactly what is committed -------------------------
    Write-Step 'Checking Git state'
    $dirty = Get-NativeOutput 'git' @('-C', $repoRoot, 'status', '--porcelain', '--untracked-files=all', '--', $ThemeRelPath)
    if ($dirty) {
        $dirty | ForEach-Object { Write-Info $_ }
        Stop-Deploy "$ThemeRelPath has uncommitted changes. Commit or discard them first; only committed code is deployed."
    }

    $headFiles = @(Get-NativeOutput 'git' @('-C', $repoRoot, 'ls-tree', '-r', '--name-only', "HEAD:$ThemeRelPath"))
    foreach ($file in $RequiredFiles) {
        if ($headFiles -notcontains $file) { Stop-Deploy "required file not committed in HEAD: $file" }
    }
    $commit = (Get-NativeOutput 'git' @('-C', $repoRoot, 'log', '-1', '--format=%h %s')) | Select-Object -First 1
    $branch = (Get-NativeOutput 'git' @('-C', $repoRoot, 'rev-parse', '--abbrev-ref', 'HEAD')) | Select-Object -First 1
    Write-Info "Branch: $branch"
    Write-Info "Commit: $commit"
    Write-Info "Files:  $($headFiles.Count)"

    # 4. Package -------------------------------------------------------------
    Write-Step 'Building package'
    $stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
    $tempDir = Join-Path ([System.IO.Path]::GetTempPath()) "lcda-deploy-$stamp"
    New-Item -ItemType Directory -Path $tempDir | Out-Null
    $package = Join-Path $tempDir 'theme.tar'

    # core.autocrlf/eol overrides: archive the blobs exactly as committed (LF).
    Invoke-Native 'git' @('-C', $repoRoot, '-c', 'core.autocrlf=false', '-c', 'core.eol=lf', 'archive', '--format=tar', '-o', $package, "HEAD:$ThemeRelPath")
    $packageSha = (Get-FileHash -LiteralPath $package -Algorithm SHA256).Hash.ToLowerInvariant()
    $packageKb = [math]::Round((Get-Item -LiteralPath $package).Length / 1KB, 1)
    Write-Info "Deployment id: $stamp"
    Write-Info "Package: $packageKb KB, $($headFiles.Count) files"
    Write-Info "SHA-256: $packageSha"

    if ($LocalOnly) {
        Write-Step 'Local checks passed. No network connection was made.'
    }
    else {
        $remoteScript = $RemoteScriptTemplate.Replace('__REMOTE_HOME__', $RemoteHome).Replace('__WP_ROOT__', $RemoteWpRoot).Replace('__REMOTE_THEME_DIR__', $RemoteThemeDir).Replace('__THEME_SLUG__', $ThemeSlug)
        $remoteScript = $remoteScript -replace "`r`n", "`n"
        $encoded = [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes($remoteScript))
        $packageBase64 = [Convert]::ToBase64String([System.IO.File]::ReadAllBytes($package))
        $scriptArgs = @($stamp, $packageSha, "$($headFiles.Count)")
        $target = "$RemoteUser@$RemoteHost"
        # Text piped to native commands is encoded with this: keep it ASCII (no BOM).
        $OutputEncoding = New-Object System.Text.ASCIIEncoding

        # 5. Remote verification and transport check (read-only) ------------
        Write-Step 'Verifying remote target and package transport (read-only)'
        Invoke-RemoteScript $target $encoded (@('verify') + $scriptArgs) $packageBase64

        if ($VerifyOnly) {
            Write-Step 'Verification complete. Nothing was uploaded or changed.'
        }
        else {
            # 6. Confirmation ------------------------------------------------
            Write-Host ''
            Write-Host "About to deploy commit '$commit' to ${RemoteHost}:$RemoteThemeDir" -ForegroundColor Yellow
            Write-Host 'A backup of the current remote theme is created first. Remote files are overwritten, never deleted.' -ForegroundColor Yellow
            $answer = Read-Host 'Type DEPLOY to continue'
            if ($answer -cne 'DEPLOY') {
                Write-Step 'Cancelled. Nothing was uploaded or changed.'
                $exitCode = 2
            }
            else {
                # 7. Remote deploy (package streamed over the connection) ----
                Write-Step 'Deploying on the server (stream, backup, copy, verify)'
                Invoke-RemoteScript $target $encoded (@('deploy') + $scriptArgs) $packageBase64

                Write-Step 'Deployment complete'
                Write-Info "Commit:        $commit"
                Write-Info "Deployment id: $stamp"
                Write-Info "Backup:        $RemoteHome/lcda-theme-backups/$ThemeSlug-$stamp.tar.gz"
                Write-Info 'Next: run the post-deploy checks in docs/deployment.md.'
            }
        }
    }
}
catch {
    Write-Host ''
    Write-Host $_.Exception.Message -ForegroundColor Red
    Write-Host 'See docs/deployment.md (Troubleshooting / Rollback).' -ForegroundColor Red
    $exitCode = 1
}
finally {
    if ($tempDir -and (Test-Path -LiteralPath $tempDir)) {
        Remove-Item -LiteralPath $tempDir -Recurse -Force
    }
}

exit $exitCode
