<?php
namespace OPNsense\Auth;

use OPNsense\Core\Config;

/** Native local password validation followed by Duo Auth API MFA. */
class LocalDuo extends Local
{
    public static function getType() { return 'localduo'; }
    public function getDescription() { return gettext('Local Database + Duo Push'); }

    private $serverProperties = [];

    public function setProperties($config)
    {
        parent::setProperties($config);
        $this->serverProperties = [
            'ikey' => trim((string)($config['duo_ikey'] ?? '')),
            'host' => strtolower(trim((string)($config['duo_host'] ?? ''))),
            'legacy_skey' => (string)($config['duo_skey'] ?? '')
        ];
    }

    public function getConfigurationOptions()
    {
        return [
            'duo_ikey' => [
                'name' => gettext('Duo integration key (IKEY)') .
                    ' <a class="btn btn-default btn-xs" href="/ui/localduo" ' .
                    'target="_blank" rel="noopener noreferrer">' .
                    gettext('Configure SKEY / Test Duo') . '</a>',
                'type' => 'text',
                'help' => gettext('Enter the Duo Auth API integration key. Manage the write-only secret and test connectivity at ') .
                    '<a href="/ui/localduo" target="_blank" rel="noopener noreferrer">Duo credential manager</a>.',
                'validate' => function ($value) {
                    return $value === null || $value === '' ||
                        preg_match('/^DI[A-Za-z0-9]{12,38}$/', (string)$value)
                        ? [] : [gettext('Invalid Duo integration key.')];
                }
            ],
            'duo_host' => [
                'name' => gettext('Duo API hostname'),
                'type' => 'text',
                'help' => gettext('Example: api-xxxxxxxx.duosecurity.com'),
                'validate' => function ($value) {
                    return $value === null || $value === '' ||
                        preg_match('/^api-[a-z0-9-]+\\.duosecurity\\.com$/i', (string)$value)
                        ? [] : [gettext('Invalid Duo API hostname.')];
                }
            ]
        ];
    }

    protected function _authenticate($username, $password)
    {
        if (!parent::_authenticate($username, $password)) {
            return false;
        }
        try {
            $config = $this->credentials();
            $system = Config::getInstance()->object()->system;
            $name = trim((string)($system->hostname ?? ''));
            $domain = trim((string)($system->domain ?? ''));
            $fqdn = $name === '' ? (gethostname() ?: 'OPNsense') :
                ($domain !== '' && strpos($name, '.') === false ? $name . '.' . $domain : $name);
            // REMOTE_ADDR may be unavailable if authentication executes outside the web process.
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            if (!is_string($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
                $ip = null;
            }
            $pre = ['username' => (string)$username];
            if ($ip !== null) { $pre['ipaddr'] = $ip; }
            $resp = self::request($config, 'POST', '/auth/v2/preauth', $pre);
            if (($resp['response']['result'] ?? '') !== 'auth') { return false; }
            // NOTE: Not supporting Verified Push. Deploy only with a policy that permits regular Push.
            $devices = $resp['response']['devices'] ?? [];
            $push = false;
            foreach ($devices as $device) {
                if (in_array('push', $device['capabilities'] ?? [], true)) { $push = true; break; }
            }
            if (!$push) { return false; }
            $args = [
                'username' => (string)$username,
                'factor' => 'push',
                'device' => 'auto',
                'type' => 'OPNsense Web GUI Login',
                'display_username' => (string)$username,
                'hostname' => $fqdn,
                'pushinfo' => http_build_query([
                    'Firewall' => $fqdn,
                    'Service' => 'Web GUI Administration',
                    'Source IP' => $ip ?? 'Unavailable',
                    'Authentication' => 'Administrative Login'
                ], '', '&', PHP_QUERY_RFC3986)
            ];
            if ($ip !== null) { $args['ipaddr'] = $ip; }
            $result = self::request($config, 'POST', '/auth/v2/auth', $args);
            return ($result['response']['result'] ?? '') === 'allow'
                && ($result['response']['status'] ?? '') === 'allow';
        } catch (\Throwable $e) {
            syslog(LOG_ERR, 'LocalDuo: authentication failed or Duo unavailable');
            return false;
        }
    }

    private function credentials(): array
    {
        $ikey = (string)($this->serverProperties['ikey'] ?? '');
        $host = (string)($this->serverProperties['host'] ?? '');
        $legacySecret = (string)($this->serverProperties['legacy_skey'] ?? '');

        // Preserve working pre-v0.5 servers while migrating to per-server configuration.
        if ($ikey === '' && $host === '' && $legacySecret === '') {
            $cfg = Config::getInstance()->object();
            $legacy = $cfg->OPNsense->LocalDuo->general ?? null;
            $ikey = trim((string)($legacy->ikey ?? ''));
            $host = strtolower(trim((string)($legacy->host ?? '')));
            $legacySecret = (string)($legacy->skey ?? '');
        }

        $skey = self::lookupSecret($ikey);
        if ($skey === '') {
            $skey = $legacySecret;
        }
        if ($skey === '') {
            $cfg = Config::getInstance()->object();
            $legacy = $cfg->OPNsense->LocalDuo->general ?? null;
            if ((string)($legacy->ikey ?? '') === $ikey) {
                $skey = (string)($legacy->skey ?? '');
            }
        }

        self::assertCredentials($ikey, $skey, $host);
        return compact('ikey', 'skey', 'host');
    }

    /** Lookup a secret by IKEY without exposing it through the API. */
    public static function lookupSecret(string $ikey): string
    {
        if ($ikey === '') {
            return '';
        }
        $root = Config::getInstance()->object();
        $entries = $root->OPNsense->LocalDuo->secrets ?? null;
        if ($entries !== null) {
            foreach ($entries->children() as $entry) {
                if ((string)($entry->ikey ?? '') === $ikey) {
                    return (string)($entry->skey ?? '');
                }
            }
        }
        return '';
    }

    public static function assertCredentials(string $ikey, string $skey, string $host): void
    {
        if (!preg_match('/^DI[A-Za-z0-9]{12,38}$/', $ikey) ||
            !preg_match('/^[A-Za-z0-9]{20,120}$/', $skey) ||
            !preg_match('/^api-[a-z0-9-]+\\.duosecurity\\.com$/', $host)) {
            throw new \RuntimeException('Duo credentials missing or invalid');
        }
    }

    /** HMAC-SHA512 Duo v5 signature and HTTPS request. */
    public static function request(array $config, string $method, string $path, array $params = []): array
    {
        if (!in_array($path, ['/auth/v2/preauth', '/auth/v2/auth', '/auth/v2/check'], true)) {
            throw new \InvalidArgumentException('Unsupported Duo endpoint');
        }
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST'], true)) {
            throw new \InvalidArgumentException('Unsupported HTTP method');
        }
        $date = gmdate('D, d M Y H:i:s -0000');
        ksort($params, SORT_STRING);
        $query = $method === 'GET' ? http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '';
        $body = $method === 'POST' ? json_encode($params, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $canonical = implode("\n", [
            $date, $method, $config['host'], $path, $query,
            hash('sha512', $body), hash('sha512', '')
        ]);
        $signature = hash_hmac('sha512', $canonical, $config['skey']);
        $url = 'https://' . $config['host'] . $path . ($query !== '' ? '?' . $query : '');
        $ch = curl_init($url);
        if ($ch === false) { throw new \RuntimeException('Unable to initialize HTTPS'); }
        try {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => [
                    'Date: ' . $date,
                    'Authorization: Basic ' . base64_encode($config['ikey'] . ':' . $signature),
                    'Accept: application/json',
                    'Content-Type: application/json'
                ],
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 70,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS
            ]);
            if ($method === 'POST') { curl_setopt($ch, CURLOPT_POSTFIELDS, $body); }
            $raw = curl_exec($ch);
            if ($raw === false || curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
                throw new \RuntimeException('Duo HTTPS request failed');
            }
            $result = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($result) || ($result['stat'] ?? '') !== 'OK') {
                throw new \RuntimeException('Duo API rejected request');
            }
            return $result;
        } finally {
            curl_close($ch);
        }
    }
}
