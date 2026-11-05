# os-localduo

## Quick installation (OPNsense 26.7 / amd64)

Run as **root** on a test firewall. The [self-contained setup script](scripts/install.sh) embeds the RSA-4096 repository signing public key, verifies its SHA-256 fingerprint, writes the key to `/usr/local/etc/pkg/localduo.pub`, creates `/usr/local/etc/pkg/repos/localduo.conf` with `signature_type: "pubkey"`, updates the signed repository catalog, and installs `os-localduo`. **You do not need to download a separate PEM key.**

### Download, inspect and run (recommended)

```sh
fetch -o /tmp/localduo-install.sh https://raw.githubusercontent.com/ricobach/os-localduo/main/scripts/install.sh
less /tmp/localduo-install.sh
sh /tmp/localduo-install.sh
```

### One-liner (only after reviewing the script)

```sh
curl -fsSL https://raw.githubusercontent.com/ricobach/os-localduo/main/scripts/install.sh | sh
```

To **configure only the repository**, without installing the plugin:

```sh
fetch -o /tmp/localduo-install.sh https://raw.githubusercontent.com/ricobach/os-localduo/main/scripts/install.sh
sh /tmp/localduo-install.sh --repo-only
```

The script refuses to overwrite a different existing public key or repository configuration. It does not change OPNsense authentication settings. Keep an administrator session and console recovery available when testing.

**Pinned public key SHA-256 (DER SubjectPublicKeyInfo):** `9f162d139f7504c586cd868383d99658ec4e73207ec118dc8979bb1d90735ea7`. Check this value against an independently trusted copy before granting repository trust. A downloaded installer verifying its own embedded key is not an independent source of trust.

For manual installation and signing details, see [docs/PUBLISHING.md](docs/PUBLISHING.md).


Experimental OPNsense authentication connector: local database password + Duo Push over the Auth API.

Configure **IKEY, SKEY and API hostname together** in **System → Access → Servers**. The SKEY is displayed as a normal masked password input and is saved with its server. The **Test Duo connection** button checks the Auth API credentials without a Push. Duo Push display title, firewall label and service label are configurable per authentication server.

Repository public key: [keys/localduo-repo-public.pem](keys/localduo-repo-public.pem). The publication workflow verifies that the key matches the configured private signer. See [publishing and verification instructions](docs/PUBLISHING.md) and [plugin README](security/localduo/README.md) for setup, migration, and security details.

**Pre-production:** Keep an active admin session and console access. Password masking does not remove the SKEY from authenticated HTML source or configuration backups. Verified Push is not supported.
