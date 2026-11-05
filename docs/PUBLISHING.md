# GitHub packaging and repository publication

The workflow [Build and publish OPNsense plugin](../../.github/workflows/opnsense-package.yml) builds `os-localduo` in a real **OPNsense 26.7 amd64** VM using the official `opnsense/plugins` build Makefile. It runs on pushes touching the plugin source and can also be run manually.

## Output

1. The workflow uploads an **Actions artifact** named `os-localduo-26.7-amd64` containing the experimental `.pkg`. This artifact is not a public repository and does not make the plugin visible in System → Firmware → Plugins.
2. When a signing key is configured, the same workflow generates a **signed pkg repository** using `pkg repo -s`, then deploys it via **GitHub Pages**.

## Signing setup (required before Pages publication)

Generate an RSA private key in a secured offline environment:

```sh
openssl genrsa -out localduo-repo-private.pem 4096
openssl rsa -in localduo-repo-private.pem -pubout -out localduo-repo-public.pem
base64 < localduo-repo-private.pem | tr -d '\n'
```

Store the resulting base64 string as the GitHub Actions **repository secret** `PKG_REPO_SIGNING_KEY_B64`. Never commit the private key, base64 secret, or a generated key in this source repository. Back up the private key securely for future repository updates. The public key is published in this repository at `keys/localduo-repo-public.pem` and copied to GitHub Pages after successful signing. The workflow checks that it matches the configured private signing key before publishing.

In GitHub repository **Settings → Pages**, select **GitHub Actions** as the deployment source.

After a successful signed publish, the expected catalogue URL is:

```text
https://ricobach.github.io/os-localduo/26.7/amd64/
```

Do **not** configure the firewall repository before the workflow has succeeded, the public key has been independently verified and the package has been lab-tested.

## Public key distribution

The public key is committed here:

```text
https://raw.githubusercontent.com/ricobach/os-localduo/main/keys/localduo-repo-public.pem
```

A successful signed build also publishes it at:

```text
https://ricobach.github.io/os-localduo/localduo-repo-public.pem
```

It is an RSA-4096 public key. Its **SHA-256 digest of the DER-encoded SubjectPublicKeyInfo** is:

```text
9f162d139f7504c586cd868383d99658ec4e73207ec118dc8979bb1d90735ea7
```

To check the digest on a computer with OpenSSL:

```sh
openssl pkey -pubin -in localduo-repo-public.pem -outform DER | openssl dgst -sha256
```

Compare the fingerprint with an independently trusted copy before configuring a new client. A key hosted alongside the repository cannot independently establish the repository's authenticity.

## Automated repository setup and installation

The [scripts/install.sh](../scripts/install.sh) script embeds the committed PEM public key and validates its SHA-256 digest. It creates both local key and repository configuration, refreshes package metadata and installs os-localduo. The signature scheme remains `pubkey`.

```sh
fetch -o /tmp/localduo-install.sh https://raw.githubusercontent.com/ricobach/os-localduo/main/scripts/install.sh
less /tmp/localduo-install.sh
sh /tmp/localduo-install.sh
```

Use `sh /tmp/localduo-install.sh --repo-only` to configure only the repository. After reviewing the script, the alternative `curl -fsSL https://raw.githubusercontent.com/ricobach/os-localduo/main/scripts/install.sh | sh` is available. Do not run unreviewed remote shell scripts as root.

The script refuses to replace any existing, different key or repo configuration. Check the pinned fingerprint independently: verifying a key embedded in a downloaded installer is not, by itself, a trust bootstrap.

## Client-side repository configuration (ONLY after verified signed publish)

Download the committed public key to a trusted file on the test firewall (and verify its fingerprint independently):

```sh
fetch -o /usr/local/etc/pkg/localduo.pub https://raw.githubusercontent.com/ricobach/os-localduo/main/keys/localduo-repo-public.pem
```

The publishing workflow verifies that this public key matches the private signer.

```text
# /usr/local/etc/pkg/repos/localduo.conf
localduo: {
  url: "https://ricobach.github.io/os-localduo/26.7/amd64",
  enabled: yes,
  signature_type: "pubkey",
  pubkey: "/usr/local/etc/pkg/localduo.pub"
}
```

Then: `pkg update -r localduo`; verify `pkg search -r localduo os-localduo`. OPNsense does **not** officially support adding arbitrary third-party package repositories. Avoid production use until all compatibility and rollback tests pass.

## Limitations

- The current plugin is an **untested prototype**, and the Actions workflow itself is unverified until a successful run.
- Only OPNsense **26.7 / amd64** is targeted; there are no universal or cross-release packages.
- Pages publication will not run unless `PKG_REPO_SIGNING_KEY_B64` is configured.
- Changing the signing key requires distributing the new public key to all clients.
- Do not use the development artifact as the sole authentication option for a production Web GUI.
