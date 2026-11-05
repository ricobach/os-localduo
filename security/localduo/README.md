# os-localduo v0.7 (experimental)

Native OPNsense authentication server: local password validation followed by Duo Auth API Push.

## Configure

1. In **System → Access → Servers**, add or edit a server of type **Local Database + Duo Push**.
2. Enter **Integration key (IKEY)**, **Secret key (SKEY)** and **API hostname** on the same form.
3. Click **Test Duo connection** next to SKEY. It checks Duo /auth/v2/check using the entered credentials, without sending a Push.
4. Save the authentication server normally.
5. Use **System → Access → Tester** to verify local password + actual Duo Push.

The SKEY is a **standard password field** in the browser: it is populated from the saved server configuration, displayed masked, and resubmitted on Save. Changing the field replaces the stored value. No separate credential-management page remains.

## OPNsense 26.7 compatibility and security

OPNsense 26.7's pluggable authentication-server editor supports only text, dropdown and checkbox plugin fields. We register SKEY as a persisted text option and convert its input to type=password on DOM ready using trusted markup in the plugin-defined label. The authenticated page source still contains the populated secret, just as for other populated inputs. Masking does not provide encryption or protection from administrators with developer tools. The secret is stored in config.xml and backups.

The editor integration assumes OPNsense continues rendering plugin labels and inline scripts. Verify it visually on the target release.

## Migration from v0.6

A v0.6-only SKEY is stored separately, so when you first upgrade to v0.7 the inline SKEY field is empty. **Enter your SKEY once and save the server.** Until then, the authentication connector retains a temporary fallback to the v0.6 secret store or the older global credentials. Once saved, the inline SKEY takes priority. Legacy values are not deleted by the upgrade; back up and verify successful authentication before cleaning them up.

## Limitations

- Experimental: install and runtime-test on a real OPNsense 26.7 firewall.
- Ordinary Duo Push only; no Verified Push / number matching.
- Browser source IP may be unavailable to backend authentication.
- Test failure and success cases while keeping an existing administrator session and console recovery.
- Duo connection test API access is controlled by a separate plugin ACL; grant it to trusted users only.
