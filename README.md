# os-localduo

Experimental OPNsense authentication plugin: native local database password + Duo Push via Auth API HTTPS.

Configure your Duo integration **per authentication server** under **System → Access → Servers**.

**Security warning:** OPNsense 26.7's third-party authserver fields do not support write-only secrets, so SKEY is visible as text when editing a server. Restrict authserver administration. Secrets are also stored in config.xml and backups.

The connector retains a temporary fallback for existing v0.4 global Duo settings. See [migration and security notes](security/localduo/README.md).

**Pre-production:** Keep a console recovery path and test before using this as the sole GUI authentication service. Verified Duo Push is not supported.
