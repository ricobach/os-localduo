# os-localduo

**Experimental OPNsense authentication plugin:** verify an OPNsense local user password, then require approval through Duo Push MFA. The plugin integrates with **System → Access → Servers**, without a separate authentication management page.

It targets **OPNsense 26.7 / amd64**. Keep console access and an existing administrator session during initial testing.

## Features

- Local database password verification before Duo Push; authentication succeeds only when both factors succeed.
- Duo integration key (IKEY), secret key (SKEY), API hostname and Push presentation options configured in the native authentication server editor.
- **Test Duo connection** validates the entered API credentials without sending a Push.
- Duo Push details can include firewall and service labels, source IP, and a validated **Login URL** (scheme, hostname and optional port).
- Fail-closed handling of Duo errors, denials and timeouts, with sanitized diagnostic logging.
- Optional login progress modal and **Enable / Disable** controls in the server editor, including detection of the current login-page patch state.

Only ordinary Duo Push is supported; **Verified Push / number matching is not supported**.

## Installation

The [installation script](scripts/install.sh) embeds the repository signing public key and verifies its SHA-256 fingerprint. It creates the key file and `pkg` repository configuration, refreshes the repository catalogue and installs the plugin. No separate public key download is needed.

**Recommended: download and review before running as root on a test firewall:**

```sh
fetch -o /tmp/localduo-install.sh https://raw.githubusercontent.com/ricobach/os-localduo/main/scripts/install.sh
less /tmp/localduo-install.sh
sh /tmp/localduo-install.sh
```

An alternative after reviewing the script is:

```sh
curl -fsSL https://raw.githubusercontent.com/ricobach/os-localduo/main/scripts/install.sh | sh
```

To configure only the repository, without installing the plugin:

```sh
sh /tmp/localduo-install.sh --repo-only
```

The script refuses to overwrite a different existing key or repository configuration. The configured files are:

- `/usr/local/etc/pkg/localduo.pub`
- `/usr/local/etc/pkg/repos/localduo.conf`

**Signing public key SHA-256 (DER SubjectPublicKeyInfo):**
`9f162d139f7504c586cd868383d99658ec4e73207ec118dc8979bb1d90735ea7`

Verify this fingerprint against an independently trusted copy: a downloaded installer verifying its own embedded key does not independently establish trust. The [public key](keys/localduo-repo-public.pem) and [publishing instructions](docs/PUBLISHING.md) are available in this repository.

## Configuration

1. In **System → Access → Servers**, create a server of type **Local Database + Duo Push**.
2. Enter the Duo **IKEY**, **SKEY** and **API hostname**; optionally set Push title, firewall and service labels.
3. Select **Test Duo connection** to check API credentials without generating a Push.
4. Save the server and use **System → Access → Tester** to verify end-to-end authentication.
5. Configure the intended Web GUI authentication servers, ensuring that an unintended alternate authenticator does not bypass Duo.

The SKEY is masked in the editor but is still present in the authenticated HTML source and saved in OPNsense configuration/backups. Masking is not encryption.

## Optional login progress overlay

The server editor includes **Login progress feedback** controls that show whether the global Web GUI login page is currently patched. **Enable** adds the progress overlay; **Disable** removes it. It is opt-in and is not a per-server setting.

When enabled, submitting the normal login form displays a centered, non-dismissible **Signing in…** modal prompting the user to check Duo Mobile if requested. This does not confirm that the password was accepted; authentication and failure handling are unchanged.

### Why the login page is patched

OPNsense 26.7 does not expose a supported plugin hook for the unauthenticated login form. To display feedback without replacing the form or redesigning authentication, LocalDuo conditionally adds one JavaScript include to **`/usr/local/www/authgui.inc`**.

This is a **compatibility trade-off**, not the preferred long-term integration:

- `authgui.inc` belongs to OPNsense core; firmware updates may overwrite the change or alter its structure.
- The patcher checks for the expected anchor, detects the installed state, avoids duplicate changes and supports removal. It declines unfamiliar template layouts rather than risk rewriting the form.
- A core upgrade may require enabling the overlay again. The feature is optional and is never required for Duo authentication.

**We would prefer an official OPNsense extension point** for login-progress UI (for example, a supported script hook or an authentication progress event). That would remove the need to patch a core-owned file, make upgrades more robust, and allow other MFA providers to offer the same feedback. The current patch remains an optional workaround until such an interface exists.

## Security and limitations

LocalDuo fails closed on Duo API errors and uses fixed diagnostic reason codes rather than logging credentials, raw API responses or request signatures. Push request details are assembled from constrained values; an unrecognized login hostname is omitted.

**Pre-production software:** validate successful and denied Push flows, invalid local passwords, timeout handling, and recovery access on the target OPNsense release. The third-party repository and this plugin are not official OPNsense packages.

For technical details and compatibility notes, see the [plugin README](security/localduo/README.md).
