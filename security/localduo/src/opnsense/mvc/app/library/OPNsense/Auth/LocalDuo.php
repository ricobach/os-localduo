<?php
namespace OPNsense\Auth;

use OPNsense\Core\Config;

/** Native local password validation followed by Duo Auth API MFA. */
class LocalDuo extends Local
{
    public static function getType() { return 'localduo'; }
    public function getDescription() { return gettext('Local Database + Duo Push'); }

    /** Settings are supplied by the selected System > Access > Servers entry. */
    private $serverProperties = [];

    public function setProperties($config)
    {
        parent::setProperties($config);
        $this->serverProperties = [
            'ikey' => trim((string)($config['duo_ikey'] ?? '')),
            'skey' => (string)($config['duo_skey'] ?? ''),
            'host' => strtolower(trim((string)($config['duo_host'] ?? '')))
        ];
    }

    /**
     * OPNsense's native authserver editor supports only text, dropdown and checkbox
     * for plugin fields. The Duo SKEY is therefore visible to users who can edit
     * authentication servers. Restrict that privilege until upstream offers
     * a write-only password field.
     */
    public function getConfigurationOptions()
    {
        return [
            'duo_ikey' => [
                'name' => gettext('Duo integration key (IKEY)'),
                'type' => 'text',
                'help' => gettext('Integration key of your Duo Auth API application.'),
                'validate' => function ($value) {
                    return $value === null || $value === '' ||
                        preg_match('/^DI[A-Za-z0-9]{12,38}$/', (string)$value)
                        ? [] : [gettext('Invalid Duo integration key.')];
                }
            ],
            'duo_skey' => [
                'name' => gettext('Duo secret key (SKEY)'),
                'type' => 'text',
                'help' => gettext('Sensitive: native OPNsense authserver fields display this value when editing. Restrict access to server administration.'),
                'validate' => function ($value) {
                    return $value === null || $value === '' ||
                        preg_match('/^[A-Za-z0-9]{20,120}$/', (string)$value)
                        ? [] : [gettext('Invalid Duo secret key.')];
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

    /**
     * Prefer credentials belonging to the selected authentication server.
     * Older v0.4 installations may still have global credentials; permit
     * those only when the entire per-server configuration is absent.
     * Incomplete per-server settings are a hard error, never silently
     * mixed with global settings.
     */
    private function credentials(): array
    {
        $settings = $this->serverProperties;
        $ikey = trim((string)($settings['ikey'] ?? ''));
        $skey = (string)($settings['skey'] ?? '');
        $host = strtolower(trim((string)($settings['host'] ?? '')));

        if ($ikey === '' && $skey === '' && $host === '') {
            // Temporary compatibility path for existing configured servers.
            $root = Config::getInstance()->object();
            $legacy = $root->OPNsense->LocalDuo->general ?? null;
            $ikey = trim((string)($legacy->ikey ?? ''));
            $skey = (string)($legacy->skey ?? '');
            $host = strtolower(trim((string)($legacy->host ?? '')));
        }

        if (!preg_match('/^DI[A-Za-z0-9]{12,38}$/', $ikey) ||
            !preg_match('/^[A-Za-z0-9]{20,120}$/', $skey) ||
            !preg_match('/^api-[a-z0-9-]+\\.duosecurity\\.com$/', $host)) {
            throw new \\RuntimeException('Duo server credentials missing or invalid');
        }
        return compact('ikey', 'skey', 'host');
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
