# os-localduo v0.5 (experimental)

OPNsense authentication connector: native local database password validation followed by Duo Auth API Push.

## Configure in the native server editor

Go to **System → Access → Servers**, add or edit an authentication server of type **Local Database + Duo Push**, and configure:

- **Duo integration key (IKEY)**
- **Duo secret key (SKEY)**
- **Duo API hostname**

Create an Auth API integration in Duo first. A given server now uses **its own credentials**. You can create different localduo servers with different Duo applications. The former custom **System → Access → Duo MFA** settings page and its API have been removed.

Keep an existing management session and console recovery available. Run **System → Access → Tester** before switching the GUI authentication server. Test approved/denied Push, wrong password, network failure and user bypass handling.

## Migration from v0.4

The existing global Duo credentials remain in OPNsense `config.xml`, unchanged by this upgrade. For compatibility, a selected localduo authentication server **without any of the three new fields** can continue using the global credentials. This is a **temporary** fallback; migrate each server by filling all three fields, testing, and confirming the selected server. Once complete, remove the legacy credentials via an administrator-controlled configuration change after a verified backup. Do not remove them until every server has been migrated.

If even one of the three new fields is set, all three are required; no partial fallback is allowed.

## Security caveat: native OPNsense authserver editor

**OPNsense 26.7's pluggable authentication-server editor only supports text, dropdown, and checkbox fields. It has no write-only/password field type for plugin options.** Accordingly, when configuring SKEY in the server editor, it appears as a *plain text field*, including when reopening an existing server. This is **less protective** than the previous write-only settings API. Limit privileges for editing authentication servers, secure OPNsense backups, and do not deploy in production unless this exposure is acceptable. A truly masked/write-only field requires core OPNsense support or an alternative secured editor.

All three server fields are stored in `config.xml` under `system/authserver` (secrets are not encrypted at rest). The legacy global secret is not removed automatically.

## Remaining limitations

- Prototype: requires installation testing on the target OPNsense release.
- Duo Verified Push number matching is not implemented or enforced.
- `REMOTE_ADDR` may be absent or represent a backend process rather than the browser. Never trust unverified forwarded headers.
- Synchronous Duo Push requests may exceed GUI timeout; test.
- Keep break-glass console access and avoid configuring a local-only Web GUI fallback that bypasses MFA.
- Duo `/auth/v2/check` connectivity testing is no longer provided by a separate GUI button; use the authentication tester for end-to-end testing.
