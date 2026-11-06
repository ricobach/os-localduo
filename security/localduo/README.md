# os-localduo v0.9 (experimental)

Native OPNsense authentication server: local password validation followed by Duo Auth API Push.

## Configure

1. In **System → Access → Servers**, add or edit a server of type **Local Database + Duo Push**.
2. Enter **Integration key (IKEY)**, **Secret key (SKEY)** and **API hostname** on the same form.
3. Click **Test Duo connection** next to SKEY. It checks Duo /auth/v2/check using the entered credentials, without sending a Push.
4. Save the authentication server normally.
5. Use **System → Access → Tester** to verify local password + actual Duo Push.

The SKEY is a **standard password field** in the browser: it is populated from the saved server configuration, displayed masked, and resubmitted on Save. Changing the field replaces the stored value. No separate credential-management page remains.

## Duo Push appearance

The authentication-server editor now provides three optional presentation settings:

- **Duo Push title**: blank by default, which lets Duo Mobile show its normal login wording. Set e.g. `Login` if you want a short `Login: OPNsense` heading. The app name `OPNsense` comes from the Duo application's name in the Duo Admin Panel, not from this setting.
- **Duo Push firewall label**: blank uses the firewall's FQDN; set a short custom label if preferred.
- **Duo Push service label**: defaults to `Web GUI`. Clear it to omit the Service line.

The old repetitive `Authentication: Administrative Login` line is removed. The source IP, if available, and username continue to be supplied automatically. Duo controls app-origin and location details independently; for example the `N/A` website/domain shown in Duo Mobile is not set by these plugin fields. These fields affect the content of new pushes only.

## OPNsense 26.7 compatibility and security

OPNsense 26.7's pluggable authentication-server editor supports only text, dropdown and checkbox plugin fields. We register SKEY as a persisted text option and convert its input to type=password on DOM ready using trusted markup in the plugin-defined label. The authenticated page source still contains the populated secret, just as for other populated inputs. Masking does not provide encryption or protection from administrators with developer tools. The secret is stored in config.xml and backups.

The editor integration assumes OPNsense continues rendering plugin labels and inline scripts. Verify it visually on the target release.

## Migration to v0.9

The connector now reads IKEY, SKEY and API hostname exclusively from the selected authentication server. The old global and separate-secret fallbacks are gone. **Before upgrading**, confirm these three fields are populated and a full Duo login succeeds. Back up the firewall configuration and keep recovery access. Legacy secret values are not automatically deleted from the configuration file.

The native editor now requires valid IKEY, SKEY and API hostname values on save, including edits. Hostname matching is case-insensitive, and runtime normalization uses lowercase.

## Diagnostics

The connection test now reports safe, actionable messages for DNS, TLS, timeouts, invalid credentials, HTTP failures, and rate limits, without returning raw errors. Successful and failed tests are logged. Push denial codes distinguish known Duo statuses: deny, timeout, fraud and locked_out; other outcomes remain denied.

## Limitations

- Experimental: install and runtime-test on a real OPNsense 26.7 firewall.
- Ordinary Duo Push only; no Verified Push / number matching.
- Browser source IP may be unavailable to backend authentication.
- Test failure and success cases while keeping an existing administrator session and console recovery.
- Duo connection test API access is controlled by a separate plugin ACL; grant it to trusted users only.

## Safe diagnostic logging

LocalDuo logs fixed reason codes, sanitized usernames and validated source IP addresses for failed logins. Failed connection tests log the same transport and API reason codes. Neither path logs passwords, SKEY, IKEY, Duo response bodies, signatures, raw exception strings or endpoint URLs.

Examples: local_password_rejected, duo_preauth_denied, duo_preauth_not_authorized, duo_push_unavailable, duo_push_denied, duo_push_not_approved, duo_configuration_invalid, duo_api_timeout, duo_dns_failure, duo_connection_failed, duo_tls_failed, duo_network_error, duo_api_unauthorized, duo_api_rate_limited, duo_api_server_error, duo_api_http_error, duo_api_rejected, duo_invalid_response and duo_unexpected_error. All errors continue to fail closed.

## Optional login progress feedback (v0.9_2)

Under **System → Access → Servers**, select a **Local Database + Duo Push** server. In the SKEY row, the new **Login progress feedback** controls show whether the *global* OPNsense login page is currently patched. Click **Enable** to add the script reference; click **Disable** to remove it. The controls check the actual login template, including patches left by v0.9_1. This feature is not stored as a per-server option.

New installations do **not** patch the login page automatically. On upgrade from v0.9_1, the earlier package's uninstall hook may remove its previous patch; use **Enable** after upgrading if desired. Package removal removes the patch.

The patch is idempotent and changes only one script include in `/usr/local/www/authgui.inc`. It is performed by a privileged configd action, available only to full administrators. An unknown core template or duplicate patch is reported rather than overwritten. A core upgrade may remove the inclusion; re-enable it from the server editor.

The optional login message reads **Signing in… Check Duo Mobile if prompted.** It never claims the password was verified, and the password/Duo authentication flow is unchanged.
