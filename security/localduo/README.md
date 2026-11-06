# os-localduo — plugin documentation

Native OPNsense local-account authentication followed by Duo Auth API Push MFA. Supported target: **OPNsense 26.7 / amd64**.

## Authentication behavior

The plugin verifies the user's password against the existing OPNsense local database, including the base provider's normal account checks, before contacting Duo. It performs preauthentication and sends an ordinary Duo Push only when an eligible Push-capable device is available. A login succeeds only after Duo reports an explicit approval; other outcomes and API errors deny access.

**Verified Push / number matching is not supported.** Keep console access and a separate working administrator session while testing. Review the Web GUI authenticator selection: permitting a non-MFA fallback server could bypass this plugin.

## Configure in System → Access → Servers

Create or edit an authentication server of type **Local Database + Duo Push**, with:

- **Duo integration key (IKEY)**, **Duo secret key (SKEY)** and **Duo API hostname** — required, stored with this server.
- **Duo Push title** — optional; leaving it blank uses Duo's default wording.
- **Duo Push firewall label** — optional; leaving it blank uses the firewall FQDN.
- **Duo Push service label** — defaults to `Web GUI`; leave blank to omit the Service line.

The SKEY input is populated from the stored configuration, then visually masked by browser-side JavaScript. **Masking is not secrecy:** the value remains inspectable in the authenticated page HTML and is stored in `config.xml` and backups. The generic OPNsense auth server editor exposes text, dropdown and checkbox plugin field types; the masking relies on the editor rendering the plugin-supplied inline script.

**Test Duo connection** calls `/auth/v2/check` using the entered credentials. It tests API connectivity without initiating a Push. Save the authentication server and use **System → Access → Tester** to verify actual password-plus-Push behavior.

## Duo Push details

The request can include Firewall, Service, Source IP and **Login URL**, alongside Duo's standard approval context. The login URL is derived from the current Web GUI request and includes only the configured scheme, host and optional port (for example, `https://fw01.example.com:8443`), not paths, query parameters or credentials.

DNS hostnames must match the configured firewall name/FQDN or an **Alternate Hostname** under **System → Settings → Administration**. Valid IPv4 and IPv6 addresses are accepted. If the host is unavailable or unrecognized, the URL is omitted. Forwarded proxy headers are not trusted, so reverse proxies may prevent the external URL from being shown correctly.

This is a custom Duo Push detail; it does not control Duo Mobile's own website/domain field.

## Optional login progress overlay

The **Login progress feedback** controls are displayed in the authentication server editor, beside the SKEY input. They detect whether the login page is patched and provide **Enable** and **Disable** actions. This controls the **global** Web GUI login page; it is not a per-server preference.

When enabled, the login form displays a modal overlay with a spinner and **Signing in… Check Duo Mobile and approve the request if prompted.** while the ordinary blocking login request runs. It disappears when OPNsense loads the next page. The message deliberately does not reveal whether the local password was valid.

### Core login-template patch: trade-off and preferred design

The OPNsense login form is implemented in **`/usr/local/www/authgui.inc`**, and the targeted OPNsense version has no supported plugin extension hook for adding unauthenticated-login progress UI.

LocalDuo therefore inserts one `/ui/js/localduo-login.js` script reference using a privileged OPNsense `configd` action. The patcher reads the actual file to detect its current state, makes an idempotent change, supports removing the reference, and refuses unexpected or duplicated patterns. The overlay is **optional**; Duo authentication works without it.

**This is a compromise:** the file is maintained by OPNsense core, so firmware upgrades can remove the reference or change the expected location. Modifying a core-owned file can create compatibility and maintenance work. The patcher does not own or replace the entire login template, but this is not a guarantee of future compatibility.

**A supported extension interface in OPNsense core would be preferable**, such as a dedicated script-inclusion hook or a neutral authentication-progress UI event. It would eliminate core-file patching, survive upgrades more reliably, and provide a reusable option for other MFA authentication providers. Until that exists, keeping the feature opt-in and reversible limits the impact.

## Diagnostics, access and limitations

LocalDuo uses fixed diagnostic reason codes for local-password failures, Duo preauthentication denials, Push denials/timeouts and Duo network/API failures. Username and source-IP logging is sanitized. It does not log passwords, IKEY/SKEY, API bodies, signatures, or raw exception strings.

The connection-test API has its own plugin ACL. Login-page patch management is restricted to full administrators and uses privileged `configd` operations.

This is experimental software. Test the GUI integration, TLS/DNS failures, Push approval/denial, retry behavior and recovery procedures on a real target firewall. The optional GUI customization and authentication code have different operational risks, and both should be validated before production use.

For installation, signing-key fingerprint and package repository configuration, see the [repository README](../../README.md) and [publishing documentation](../../docs/PUBLISHING.md).
