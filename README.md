# os-localduo

Experimental OPNsense authentication connector: local database password + Duo Push over the Auth API.

Configure **IKEY, SKEY and API hostname together** in **System → Access → Servers**. The SKEY is displayed as a normal masked password input and is saved with its server. The **Test Duo connection** button checks the Auth API credentials without a Push. Duo Push display title, firewall label and service label are configurable per authentication server.

Repository public key: [keys/localduo-repo-public.pem](keys/localduo-repo-public.pem). The publication workflow verifies that the key matches the configured private signer. See [publishing and verification instructions](docs/PUBLISHING.md) and [plugin README](security/localduo/README.md) for setup, migration, and security details.

**Pre-production:** Keep an active admin session and console access. Password masking does not remove the SKEY from authenticated HTML source or configuration backups. Verified Push is not supported.
