# os-localduo

Prototype OPNsense plugin for local database password authentication followed by Duo Auth API Push over HTTPS.

Source code lives in `security/localduo/` (OPNsense plugins tree layout).

**Pre-production only:** Not built or tested against an installed OPNsense release. Maintain console recovery and test in a lab before selecting it for Web GUI authentication. Ordinary Duo Push only; Verified Push is not supported. The Duo secret is stored in OPNsense config.xml and backups.

See [plugin README](security/localduo/README.md) for configuration and security limitations.
