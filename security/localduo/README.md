# os-localduo (prototype)

OPNsense plugin source for local-password authentication followed by Duo Auth API Push.

## Important status
This is a **source package / pre-production prototype**, not a compiled or signed FreeBSD `.pkg`. It has not been executed on an OPNsense firewall. Verify GUI routes, ACL and request context against your installed OPNsense version before production use.

## Features
- Authentication connector `localduo` extending built-in `Local` password validation.
- Duo Auth API v2 using HMAC-SHA512 v5 signing and HTTPS.
- Fail closed on Duo reject, bypass, unenrolled users, transport errors.
- GUI settings at `System → Access → Duo MFA`, with write-only secret update.
- Duo `/auth/v2/check` credential test (no Push).
- Firewall FQDN from native OPNsense hostname/domain settings.
- Conditional source IP; cannot guarantee correct IP without integration at login request entry point.

## Installation (staging/testing)
From the `security/localduo` directory in an **OPNsense plugin build environment**, use `make package` in the standard `opnsense/plugins` tree. The build environment requires `Mk/plugins.mk` from that repository.

For a lab-only source overlay, copy the **contents** of `src/` into `/usr/local/` preserving paths. Back up any previous `LocalDuo.php` before overlaying. Restart PHP/GUI services as appropriate for the release, and confirm the authentication connector loads. Do not enable the new authenticator until tests pass.

## Configure
1. Create a Duo **Auth API** application (not a RADIUS-only application).
2. Enter integration key, secret key and API hostname via `System → Access → Duo MFA`.
3. Save and press **Check saved API credentials**.
4. Create a new authentication server under `System → Access → Servers`, type `Local Database + Duo Push` (type ID `localduo`).
5. Test with `System → Access → Tester`, including invalid password, denied Push, timeout, bypass account and successful approval.
6. Keep the existing Web GUI session and console recovery available. Only then set the new GUI auth server.

## Security limitations / TODO before production
- No Verified Duo Push/number matching; ordinary Push only. Do not assume a Verified Push-required policy is enforced. Duo may ignore that policy without client capability advertisement.
- Source IP may be missing or incorrect in a backend request context. Do not treat it as guaranteed. Do not trust arbitrary forwarded headers.
- `skey` is stored in OPNsense `config.xml` and its backups, and is not encrypted at rest. API never returns the secret.
- Ensure the `page-system-localduo` ACL is restricted to trusted administrators; audit permissions before deployment.
- A user with local-only GUI authentication configured in addition to LocalDuo may bypass MFA; keep fallback console recovery separate.
- `GET /auth/v2/check` validates credentials, it does not exercise actual Push.
- Test compatibility with actual OPNsense release and UI framework. GUI API methods are illustrative until verified live.
- Review synchronous Duo timeout against Web GUI authentication worker timeouts.
- Removal should not delete user configuration or break recovery. Do not remove while it is selected as the only GUI authenticator.

## Duo settings storage
`<OPNsense><LocalDuo><general><ikey>…</ikey><skey>…</skey><host>…</host></general></LocalDuo></OPNsense>`

## Files
- `src/opnsense/mvc/app/library/OPNsense/Auth/LocalDuo.php`: authentication connector.
- `src/opnsense/mvc/app/controllers/OPNsense/LocalDuo/Api/SettingsController.php`: write-only secret settings and credential test.
- `src/opnsense/mvc/app/views/OPNsense/LocalDuo/index.volt`: GUI.
- `src/opnsense/mvc/app/models/OPNsense/LocalDuo/{ACL,Menu}`: permissions/menu.
