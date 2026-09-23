# Deployment configuration template for deploy-theme.ps1.
#
# 1. Copy this file to deploy.local.ps1 (same folder, the repository root).
#    deploy.local.ps1 is git-ignored and must never be committed.
# 2. Replace every CHANGE_ME value. The script refuses to run while any
#    placeholder remains.
#
# Rules:
# - This file holds ONLY the hashtable below. deploy-theme.ps1 reads it as
#   data (it is parsed, never executed): variables, expressions and commands
#   are rejected, and so is any setting not listed here.
# - No passwords, private keys or tokens. SSH authentication uses your
#   existing SSH setup (key/agent or password prompt).
# - Paths are absolute server paths, with no trailing slash.
# - ASCII only (Windows PowerShell 5.1).
@{
    # SSH host name or IP address of the server.
    RemoteHost     = 'CHANGE_ME.example.com'

    # SSH port (a number, not a string).
    RemotePort     = 22

    # SSH user name.
    RemoteUser     = 'CHANGE_ME'

    # Home directory of the SSH user on the server (the value of $HOME there).
    # Backups (lcda-theme-backups/) and temporary staging are created here,
    # outside the web root. The remote side checks that $HOME matches.
    RemoteHome     = '/home/CHANGE_ME'

    # WordPress root: the directory that contains wp-load.php. Must be inside
    # RemoteHome.
    RemoteWpRoot   = '/home/CHANGE_ME/CHANGE_ME/public_html'

    # The child theme directory: the ONLY place the deployment writes to.
    # Must be exactly <RemoteWpRoot>/wp-content/themes/la-casa-del-arbol.
    RemoteThemeDir = '/home/CHANGE_ME/CHANGE_ME/public_html/wp-content/themes/la-casa-del-arbol'
}
