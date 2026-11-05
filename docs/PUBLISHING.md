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

Store the resulting base64 string as the GitHub Actions **repository secret** `PKG_REPO_SIGNING_KEY_B64`. Never commit the private key, base64 secret, or a generated key in this source repository. Back up the private key securely for future repository updates. The public key may be distributed separately for client verification.

In GitHub repository **Settings → Pages**, select **GitHub Actions** as the deployment source.

After a successful signed publish, the expected catalogue URL is:

```text
https://ricobach.github.io/os-localduo/26.7/amd64/
```

Do **not** configure the firewall repository before the workflow has succeeded, the public key has been independently verified and the package has been lab-tested.

## Client-side repository configuration (ONLY after verified signed publish)

Copy the corresponding public key to a trusted file on the test firewall, e.g. `/usr/local/etc/pkg/localduo.pub`. Ensure the exact public key matches the maintained private key, not an arbitrary downloaded replacement.

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
