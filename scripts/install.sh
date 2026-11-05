#!/bin/sh
# Install os-localduo with its pinned RSA repository signing key.
# Pass --repo-only to configure the repository without installing the package.
set -eu

REPO_URL='https://ricobach.github.io/os-localduo/26.7/amd64'
KEY_PATH='/usr/local/etc/pkg/localduo.pub'
REPO_PATH='/usr/local/etc/pkg/repos/localduo.conf'
EXPECTED_FINGERPRINT='9f162d139f7504c586cd868383d99658ec4e73207ec118dc8979bb1d90735ea7'

case "$#" in
    0) INSTALL_PLUGIN=yes ;;
    1)
        if [ "$1" = --repo-only ]; then
            INSTALL_PLUGIN=no
        else
            echo 'Usage: sh install.sh [--repo-only]' >&2
            exit 2
        fi
        ;;
    *) echo 'Usage: sh install.sh [--repo-only]' >&2; exit 2 ;;
esac
if [ "$(id -u)" -ne 0 ]; then
    echo 'Run this script as root on OPNsense.' >&2
    exit 1
fi
for command in openssl pkg mktemp install cmp awk; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "Missing required command: $command" >&2
        exit 1
    fi
done

umask 077
TMP_DIR=$(mktemp -d /tmp/localduo.XXXXXXXX)
trap 'rm -rf "$TMP_DIR"' EXIT HUP INT TERM
cat > "$TMP_DIR/localduo.pub" <<'KEY'
-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAwoO0bNfFst32V79YMH6+
QbgSR7J+mqFq9dPDko/vadHtmOrLikjOE+VMS/G7rN+/J+wkNFCrZUKt6B/f4Aqm
wENfyhbOZ0i6sBvJIAaN2asgGEOqIhHW7bNI1fY82hqNowKgQF9Uo7F0r7D8Svql
7Uqrr6wrr/kGsbB+j/PsUllFO/goe2wNoVtpzCiJflOzTjVuUWKDGX6zee+CvACu
MiwW9/DR6txCiPQAWc4rWV/KfCkglF49f3+0ZSIZlVpnFu+mYDL715l4V+bTXFZZ
DsffSV+nOB2K1FSKuy+8r9cT7scqMdsvTYe7s19zR2BvwSE/JVj63f9VxmomDn78
iiuW9/etwAbe4pAqcHHhyV0RiXkymqBohmueuNQDqKwmUj3fvsQDYrhJhayCUhTX
y1ZHa3LUxOgldFPaMeP2qE5nc6HNh2Uo+KXrfMJ9K4wxnT1f2JesQ6wHShfhvxE1
P8UsKWCbK05AmWffzoTd0Pvo7Jfzrlx72zlzT/iqXVfgF+q3ObnXnL+J6t72bJm8
y0dIZi23sMP6LtM7/j1wBePtFuheKujZ6u9bvIpN+JNxNHAF/hxmPGz897IGLe5p
SuR7zN0aMYJ3Hx/NiG4qw09m0R6s9UM3VT5ae5bQvq9TFO/XOxKijfH6e1gfPa0V
jOuLGFHc8gO85LuHmPcvQI8CAwEAAQ==
-----END PUBLIC KEY-----
KEY

# Parse the public key, then pin the SHA-256 of its DER SubjectPublicKeyInfo.
openssl pkey -pubin -in "$TMP_DIR/localduo.pub" -outform DER -out "$TMP_DIR/localduo.der" 2>/dev/null || {
    echo 'Invalid embedded public key.' >&2
    exit 1
}
ACTUAL_FINGERPRINT=$(openssl dgst -sha256 "$TMP_DIR/localduo.der" | awk '{print $NF}')
if [ "$ACTUAL_FINGERPRINT" != "$EXPECTED_FINGERPRINT" ]; then
    echo 'Public key fingerprint mismatch; refusing to install.' >&2
    exit 1
fi

# Never silently replace an existing, different trust anchor or repo config.
if [ -e "$KEY_PATH" ] && ! cmp -s "$KEY_PATH" "$TMP_DIR/localduo.pub"; then
    echo "Different public key already present: $KEY_PATH" >&2
    exit 1
fi
cat > "$TMP_DIR/localduo.conf" <<EOF
localduo: {
    url: "$REPO_URL",
    enabled: yes,
    signature_type: "pubkey",
    pubkey: "$KEY_PATH"
}
EOF
if [ -e "$REPO_PATH" ] && ! cmp -s "$REPO_PATH" "$TMP_DIR/localduo.conf"; then
    echo "Different repository configuration already present: $REPO_PATH" >&2
    exit 1
fi

mkdir -p /usr/local/etc/pkg/repos
install -m 0644 "$TMP_DIR/localduo.pub" "$KEY_PATH"
install -m 0644 "$TMP_DIR/localduo.conf" "$REPO_PATH"
echo "Installed signing key: $KEY_PATH"
echo "SHA-256 public key fingerprint: $ACTUAL_FINGERPRINT"
echo "Installed repo configuration: $REPO_PATH"
pkg update -r localduo -f
if [ "$INSTALL_PLUGIN" = yes ]; then
    pkg install -r localduo os-localduo
else
    echo 'Repository configured; package installation skipped (--repo-only).'
fi
