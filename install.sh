#!/bin/sh
#
# Acquia CLI installer.
#
#   curl -fsSL https://raw.githubusercontent.com/acquia/cli/main/install.sh | sh
#
# Installs the latest release of Acquia CLI and then starts `acli dev:init`,
# which walks you through creating a complete local development environment.
#
# On macOS (Apple Silicon) and Linux (x86_64) this installs a self-contained
# native binary: PHP is NOT required. On other platforms it falls back to
# acli.phar, which requires PHP 8.2+.
#
# The script is deliberately small and readable — please do inspect it.
# Environment variables:
#   ACLI_INSTALL_DIR       Install directory (default: ~/.local/bin)
#   ACLI_INSTALL_NO_SETUP  Set to 1 to skip running `acli dev:init` after install.
#   ACLI_INSTALL_BASE_URL  Alternative download location (e.g. a PR build).

set -eu

REPO="acquia/cli"
INSTALL_DIR="${ACLI_INSTALL_DIR:-"$HOME/.local/bin"}"
BASE_URL="${ACLI_INSTALL_BASE_URL:-"https://github.com/$REPO/releases/latest/download"}"

say() { printf '%s\n' "$*"; }
fail() { printf 'Error: %s\n' "$*" >&2; exit 1; }

command -v curl >/dev/null 2>&1 || fail "curl is required. Install it and re-run this script."

# Pick the right release asset for this machine.
OS="$(uname -s)"
ARCH="$(uname -m)"
ASSET=""
case "$OS-$ARCH" in
    Darwin-arm64) ASSET="native-acli-macos-aarch64.tar.gz" ;;
    Linux-x86_64) ASSET="native-acli-linux-x86_64.tar.gz" ;;
esac

TMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/acli-install.XXXXXXXX")"
trap 'rm -rf "$TMP_DIR"' EXIT

# Verify a downloaded file against the .sha256 file published with the
# release. Older releases predate the checksum files; warn in that case.
verify_checksum() {
    file="$1"
    if ! curl -fsSL "$BASE_URL/$(basename "$file").sha256" -o "$file.sha256" 2>/dev/null; then
        say "Warning: this release publishes no checksum for $(basename "$file"); skipping verification."
        return 0
    fi
    expected="$(cut -d' ' -f1 <"$file.sha256")"
    if command -v sha256sum >/dev/null 2>&1; then
        actual="$(sha256sum "$file" | cut -d' ' -f1)"
    else
        actual="$(shasum -a 256 "$file" | cut -d' ' -f1)"
    fi
    [ "$expected" = "$actual" ] || fail "Checksum mismatch for $(basename "$file"). Aborting."
}

if [ -n "$ASSET" ]; then
    say "Downloading Acquia CLI (native build, no PHP required) ..."
    curl -fsSL "$BASE_URL/$ASSET" -o "$TMP_DIR/$ASSET"
    verify_checksum "$TMP_DIR/$ASSET"
    tar -xzf "$TMP_DIR/$ASSET" -C "$TMP_DIR"
    ACLI_BIN="$TMP_DIR/acli"
else
    # No native build for this platform: fall back to the phar, which needs PHP.
    if ! command -v php >/dev/null 2>&1; then
        fail "No native Acquia CLI build exists for $OS/$ARCH and PHP was not found.
Install PHP 8.2+ first (macOS: brew install php, Debian/Ubuntu: sudo apt install php-cli), then re-run this script."
    fi
    php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' \
        || fail "Acquia CLI requires PHP 8.2+; you have $(php -r 'echo PHP_VERSION;'). Upgrade PHP and re-run this script."
    say "Downloading Acquia CLI (acli.phar) ..."
    curl -fsSL "$BASE_URL/acli.phar" -o "$TMP_DIR/acli"
    verify_checksum "$TMP_DIR/acli"
    ACLI_BIN="$TMP_DIR/acli"
fi

mkdir -p "$INSTALL_DIR"
install -m 755 "$ACLI_BIN" "$INSTALL_DIR/acli"
say "Installed acli to $INSTALL_DIR/acli"
"$INSTALL_DIR/acli" --version || fail "The installed acli binary does not run on this system."

case ":$PATH:" in
    *":$INSTALL_DIR:"*) ;;
    *)
        say ""
        say "Note: $INSTALL_DIR is not in your PATH. Add it with:"
        say "  echo 'export PATH=\"$INSTALL_DIR:\$PATH\"' >> ~/.$(basename "${SHELL:-bash}")rc"
        ;;
esac

if [ "${ACLI_INSTALL_NO_SETUP:-0}" = "1" ]; then
    exit 0
fi

say ""
# When piped to sh, stdin is the script itself. Reattach the terminal so
# `acli dev:init` can ask questions; in truly non-interactive contexts (CI),
# print the next step instead of running it.
if [ -t 0 ]; then
    exec "$INSTALL_DIR/acli" dev:init
elif [ -e /dev/tty ] && (: </dev/tty) 2>/dev/null; then
    exec "$INSTALL_DIR/acli" dev:init </dev/tty
else
    say "Next, run: $INSTALL_DIR/acli dev:init"
fi
