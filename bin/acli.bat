@ECHO OFF
REM Running this file is equivalent to running `php acli`
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0acli.php
php "%BIN_TARGET%" %*
