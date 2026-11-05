# os-localduo v0.6 (experimental)

OPNsense local password verification + Duo Auth API Push.

## Configuration

- Under **System → Access → Servers**, configure a server of type **Local Database + Duo Push** with its Duo integration key (IKEY) and API hostname.
- Click the **Duo credential manager** link in the IKEY field help (or visit **/ui/localduo** directly) to store a write-only SKEY for that IKEY.
- On the credential manager, click **Test Duo connection**. This calls Duo `/auth/v2/check` without sending Push.
- Use **System → Access → Tester** for full end-to-end primary password + Duo Push validation.

No separate **Duo MFA** sidebar menu is shipped by v0.6. The protected credential management page remains accessible from the authserver help link.

## Security

OPNsense 26.7's native authentication-server plugin field renderer does **not** support password-type/write-only fields. The SKEY is therefore intentionally **not present in the server editor**. It is stored via a separate ACL-protected write-only API, keyed by the Duo integration key. It can still be present in `config.xml` and backups, so protect both.

Upgrade migration: pre-v0.5 global SKEY and v0.5 inline server SKEY are accepted as temporary fallbacks. **Existing inline secrets might remain stored in config.xml even after disappearing from the editor.** Save the secret in the new credential manager and test before removing legacy values. Do not remove any old credentials without a config backup, tested recovery access, and a successful end-to-end Duo login.

## Stale menu after upgrade

If System → Access → Duo MFA persists after upgrading, first confirm `pkg info os-localduo` reports v0.6 and reload the GUI. The old menu file may have been left by a previous **manual source overlay** outside package management. Check:

`ls -l /usr/local/opnsense/mvc/app/models/OPNsense/LocalDuo/Menu/Menu.xml`

If it remains, back it up and remove this exact obsolete file, then restart the GUI; do not delete unrelated OPNsense menu files.

## Limitations

- Experimental: not yet tested against the live firewall installation.
- Verified Duo Push / number matching is not supported.
- Browser source IP may be missing in background authentication processes.
- Native Web GUI/worker timeouts may constrain synchronous Push handling.
- Do not leave an alternative password-only GUI authenticator in place.
