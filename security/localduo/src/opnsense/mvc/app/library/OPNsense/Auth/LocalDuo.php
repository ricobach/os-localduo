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
            'skey' => (string)($config['duo_skey'] ?? ''),
            'push_type' => trim((string)($config['duo_push_type'] ?? '')),
            'push_firewall' => trim((string)($config['duo_push_firewall'] ?? '')),
            'push_service' => trim((string)($config['duo_push_service'] ?? 'Web GUI')),
            'trust_cf_client_ip' => in_array(strtolower((string)($config['duo_trust_cf_client_ip'] ?? '')), ['1', 'yes', 'true', 'on'], true),
            'trusted_proxy_ips' => trim((string)($config['duo_trusted_proxy_ips'] ?? ''))
        ];
    }

    public function getConfigurationOptions()
    {
        /*
         * OPNsense 26.7's generic authserver editor only exposes "text",
         * "dropdown" and "checkbox" extension fields. It loads their saved
         * values and writes posted values back to config.xml.
         *
         * Use normal persistence for SKEY and convert the browser input to
         * password type on DOM ready. This provides visual masking only:
         * the value remains accessible in page source / developer tools.
         */
        $duoControls = <<<'HTML'
 <script>
 $(function () {
     'use strict';
     var secret = $('input[name="duo_skey"]');
     if (!secret.length) {
         return;
     }
     secret.attr('type', 'password').attr('autocomplete', 'new-password');
     // Put controls in the same cell as the native SKEY input.
     var test = $('<button/>', {
         id: 'localduo_test',
         type: 'button',
         class: 'btn btn-default btn-sm',
         text: 'Test Duo connection'
     });
     var status = $('<span/>', {id: 'localduo_test_status', role: 'status'})
         .css({display: 'block', marginTop: '6px', fontSize: '12px'});
     secret.after(test, status);
     test.css({marginLeft: '8px'});

     // Login progress feedback affects the firewall's login page globally.
     // It is managed here, not persisted per Duo authentication server.
     var progress = $('<div/>', {id: 'localduo_login_progress'})
         .css({marginTop: '12px', paddingTop: '8px'});
     var title = $('<strong/>', {text: 'Login progress feedback'});
     var state = $('<span/>', {id: 'localduo_login_state', role: 'status'})
         .css({display: 'block', margin: '5px 0', fontSize: '12px'});
     var enable = $('<button/>', {
         type: 'button', class: 'btn btn-default btn-sm', text: 'Enable'
     });
     var disable = $('<button/>', {
         type: 'button', class: 'btn btn-default btn-sm', text: 'Disable'
     }).css({marginLeft: '8px'});
     progress.append(title, state, enable, disable);
     secret.parent().append(progress);
     function showProgress(response) {
         var current = response && response.state;
         if (current === 'enabled') {
             state.text('Enabled — login page is patched.');
         } else if (current === 'disabled') {
             state.text('Disabled — login page is not patched.');
         } else {
             state.text(response.message || 'Unable to determine login progress status.');
         }
         enable.prop('disabled', current !== 'disabled');
         disable.prop('disabled', current !== 'enabled');
     }
     function refreshProgress() {
         state.text('Checking login page...');
         enable.prop('disabled', true);
         disable.prop('disabled', true);
         ajaxCall('/api/localduo/settings/loginstatus', {}, showProgress);
     }
     function changeProgress(endpoint) {
         state.text('Updating login page...');
         enable.prop('disabled', true);
         disable.prop('disabled', true);
         ajaxCall(endpoint, {}, function (response) {
             if (response.status !== 'ok') {
                 state.text(response.message || 'Could not update the login page.');
                 enable.prop('disabled', true);
                 disable.prop('disabled', true);
             } else {
                 showProgress(response);
             }
         });
     }
     enable.on('click', function () {
         changeProgress('/api/localduo/settings/loginenable');
     });
     disable.on('click', function () {
         changeProgress('/api/localduo/settings/logindisable');
     });
     refreshProgress();
     // Alternative to the core-file patch: manual copy and activation of a theme.
     var theme = $('<div/>', {id: 'localduo_theme_tools'})
         .css({marginTop: '12px', paddingTop: '8px'});
     var themeState = $('<span/>', {role: 'status'})
         .css({display: 'block', margin: '5px 0', fontSize: '12px'});
     var createTheme = $('<button/>', {type: 'button', class: 'btn btn-default btn-sm', text: 'Create Duo Theme'});
     var activateTheme = $('<button/>', {type: 'button', class: 'btn btn-default btn-sm', text: 'Activate Duo Theme'}).css({marginLeft: '8px'});
     var restoreTheme = $('<button/>', {type: 'button', class: 'btn btn-default btn-sm', text: 'Restore Original Theme'}).css({marginLeft: '8px'});
     theme.append($('<strong/>', {text: 'Theme-based login progress (no core patch)'}),
         themeState, createTheme, activateTheme, restoreTheme);
     progress.after(theme);
     function refreshTheme() {
         themeState.text('Checking theme...');
         ajaxCall('/api/localduo/settings/themestatus', {}, function (response) {
             themeState.text(response.message || 'Theme status unavailable');
             createTheme.prop('disabled', response.status !== 'ok' || response.state === 'active');
             activateTheme.prop('disabled', response.state !== 'created');
             restoreTheme.prop('disabled', response.state !== 'active');
         });
     }
     function changeTheme(action) {
         createTheme.prop('disabled', true);
         activateTheme.prop('disabled', true);
         restoreTheme.prop('disabled', true);
         themeState.text('Updating theme...');
         ajaxCall('/api/localduo/settings/theme' + action, {}, function (response) {
             themeState.text(response.message || 'Theme operation failed');
             refreshTheme();
         });
     }
     createTheme.on('click', function () { changeTheme('create'); });
     activateTheme.on('click', function () {
         if (window.confirm('Activate the copied Duo theme? Keep another admin session open for recovery.')) {
             changeTheme('activate');
         }
     });
     restoreTheme.on('click', function () { changeTheme('restore'); });
     refreshTheme();
     test.on('click', function () {
         var button = $(this);
         button.prop('disabled', true);
         $('#localduo_test_status').text('Checking Duo API credentials...');
         ajaxCall('/api/localduo/settings/check', {
             ikey: $('input[name="duo_ikey"]').val(),
             skey: secret.val(),
             host: $('input[name="duo_host"]').val()
         }, function (response) {
             button.prop('disabled', false);
             $('#localduo_test_status')
                 .text(response.message || (response.status === 'ok' ? 'Connection successful' : 'Connection failed'))
                 .css('color', response.status === 'ok' ? 'green' : 'red');
         });
     });
 });
 </script>
HTML;
        return [
            'duo_ikey' => [
                'name' => gettext('Duo integration key (IKEY)'),
                'type' => 'text',
                'help' => gettext('Integration key for your Duo Auth API application.'),
                'validate' => function ($value) {
                    return preg_match('/^DI[A-Za-z0-9]{12,38}$/', (string)($value ?? ''))
                        ? [] : [gettext('Duo integration key (IKEY) is required and must be valid.')];
                }
            ],
            'duo_skey' => [
                'name' => gettext('Duo secret key (SKEY)') . $duoControls,
                'type' => 'text',
                'help' => gettext('Saved with the authentication server and displayed masked. Editing replaces it; admins can inspect it in HTML source.'),
                'validate' => function ($value) {
                    return preg_match('/^[A-Za-z0-9]{20,120}$/', (string)($value ?? ''))
                        ? [] : [gettext('Duo secret key (SKEY) is required and must be valid.')];
                }
            ],
            'duo_push_type' => [
                'name' => gettext('Duo Push title (optional)'),
                'type' => 'text',
                'help' => gettext('Leave blank to use Duo Mobile default wording. Enter e.g. Login for a short Login: OPNsense heading. The application name comes from Duo Admin.'),
                'validate' => function ($value) {
                    return preg_match('/^[\p{L}\p{N} ._()\/:-]{0,60}$/u', (string)($value ?? ''))
                        ? [] : [gettext('Use at most 60 letters, digits, spaces or basic punctuation.')];
                }
            ],
            'duo_push_firewall' => [
                'name' => gettext('Duo Push firewall label (optional)'),
                'type' => 'text',
                'help' => gettext('Leave blank to show the firewall hostname automatically.'),
                'validate' => function ($value) {
                    return preg_match('/^[\p{L}\p{N} ._()\/:-]{0,100}$/u', (string)($value ?? ''))
                        ? [] : [gettext('Invalid firewall display label.')];
                }
            ],
            'duo_push_service' => [
                'name' => gettext('Duo Push service label'),
                'type' => 'text',
                'default' => 'Web GUI',
                'help' => gettext('Shown in Duo Push details as Service. Leave blank to omit that line.'),
                'validate' => function ($value) {
                    return preg_match('/^[\p{L}\p{N} ._()\/:-]{0,100}$/u', (string)($value ?? ''))
                        ? [] : [gettext('Invalid service display label.')];
                }
            ],
            'duo_trust_cf_client_ip' => [
                'name' => gettext('Trust Cloudflare Tunnel client IP'),
                'type' => 'checkbox',
                'help' => gettext('Disabled by default. Only read CF-Connecting-IP when the connecting peer matches a configured trusted proxy IP or CIDR.')
            ],
            'duo_trusted_proxy_ips' => [
                'name' => gettext('Trusted tunnel/proxy peer IPs or CIDRs'),
                'type' => 'text',
                'help' => gettext('Comma or space separated IP addresses/CIDRs of the immediate peers connecting to this Web GUI (for example 127.0.0.1/32 or 192.0.2.10/32). Never trust an entire shared network unnecessarily. Required when Cloudflare client IP trust is enabled.'),
                'validate' => function ($value) {
                    return self::validProxyRanges((string)($value ?? ''))
                        ? [] : [gettext('Enter at most 32 valid IPv4/IPv6 IP addresses or CIDRs, separated by commas or spaces.')];
                }
            ],
            'duo_host' => [
                'name' => gettext('Duo API hostname'),
                'type' => 'text',
                'help' => gettext('Example: api-xxxxxxxx.duosecurity.com'),
                'validate' => function ($value) {
                    return preg_match('/^api-[a-z0-9-]+\.duosecurity\.com$/i', strtolower(trim((string)($value ?? ''))))
                        ? [] : [gettext('Duo API hostname is required and must be valid.')];
                }
            ]
        ];
    }

    /** Translate only our own known exceptions to safe, fixed log codes. */
    public static function diagnosticReason(\Throwable $error): string
    {
        $codes = [
            'duo_configuration_invalid', 'duo_api_timeout', 'duo_dns_failure',
            'duo_connection_failed', 'duo_tls_failed', 'duo_network_error',
            'duo_api_unauthorized', 'duo_api_rate_limited', 'duo_api_server_error',
            'duo_api_http_error', 'duo_api_rejected', 'duo_invalid_response'
        ];
        $reason = $error->getMessage();
        return $error instanceof \RuntimeException && in_array($reason, $codes, true)
            ? $reason : 'duo_unexpected_error';
    }

    private static function logAuthFailure(string $reason, $username): void
    {
        // No passwords, keys, API responses, or unfiltered exception strings.
        $user = substr(preg_replace('/[^A-Za-z0-9_.@+-]/', '_', (string)$username), 0, 96);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $source = is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unavailable';
        syslog(LOG_NOTICE, sprintf(
            'LocalDuo: context=login outcome=deny reason=%s user=%s source=%s',
            $reason, $user !== '' ? $user : 'unknown', $source
        ));
    }

    protected function _authenticate($username, $password)
    {
        if (!parent::_authenticate($username, $password)) {
            self::logAuthFailure('local_password_rejected', $username);
            return false;
        }
        try {
            $config = $this->credentials();
            $system = Config::getInstance()->object()->system;
            $name = trim((string)($system->hostname ?? ''));
            $domain = trim((string)($system->domain ?? ''));
            $fqdn = $name === '' ? (gethostname() ?: 'OPNsense') :
                ($domain !== '' && strpos($name, '.') === false ? $name . '.' . $domain : $name);
            $ip = $this->sourceIp();
            $pre = ['username' => (string)$username];
            if ($ip !== null) { $pre['ipaddr'] = $ip; }
            $resp = self::request($config, 'POST', '/auth/v2/preauth', $pre);
            $preResult = $resp['response']['result'] ?? null;
            if ($preResult !== 'auth') {
                self::logAuthFailure(
                    $preResult === 'deny' ? 'duo_preauth_denied' : 'duo_preauth_not_authorized',
                    $username
                );
                return false;
            }
            // Ordinary Push only, not Verified Push.
            $devices = $resp['response']['devices'] ?? [];
            $push = false;
            foreach ($devices as $device) {
                if (in_array('push', $device['capabilities'] ?? [], true)) { $push = true; break; }
            }
            if (!$push) {
                self::logAuthFailure('duo_push_unavailable', $username);
                return false;
            }
            $pushType = (string)($this->serverProperties['push_type'] ?? '');
            $firewallLabel = (string)($this->serverProperties['push_firewall'] ?? '');
            if ($firewallLabel === '') {
                $firewallLabel = $fqdn;
            }
            $serviceLabel = (string)($this->serverProperties['push_service'] ?? 'Web GUI');
            $pushInfo = ['Firewall' => $firewallLabel];
            if ($serviceLabel !== '') {
                $pushInfo['Service'] = $serviceLabel;
            }
            $loginUrl = self::loginUrl($system);
            if ($loginUrl !== null) {
                $pushInfo['Login URL'] = $loginUrl;
            }
            if ($ip !== null) {
                $pushInfo['Source IP'] = $ip;
            }
            $args = [
                'username' => (string)$username,
                'factor' => 'push',
                'device' => 'auto',
                'display_username' => (string)$username,
                'hostname' => $fqdn,
                'pushinfo' => http_build_query($pushInfo, '', '&', PHP_QUERY_RFC3986)
            ];
            if ($pushType !== '') { $args['type'] = $pushType; }
            if ($ip !== null) { $args['ipaddr'] = $ip; }
            $result = self::request($config, 'POST', '/auth/v2/auth', $args);
            $approval = $result['response'] ?? null;
            if (($approval['result'] ?? null) === 'allow' &&
                ($approval['status'] ?? null) === 'allow') {
                return true;
            }
            // Only fixed status values are mapped; never log Duo's status_msg.
            $status = $approval['status'] ?? null;
            $reasons = [
                'deny' => 'duo_push_denied',
                'timeout' => 'duo_push_timeout',
                'fraud' => 'duo_push_fraud',
                'locked_out' => 'duo_push_locked_out'
            ];
            $reason = is_string($status) && isset($reasons[$status])
                ? $reasons[$status]
                : (($approval['result'] ?? null) === 'deny'
                    ? 'duo_push_denied' : 'duo_push_not_approved');
            self::logAuthFailure($reason, $username);
            return false;
        } catch (\Throwable $e) {
            self::logAuthFailure(self::diagnosticReason($e), $username);
            return false;
        }
    }


    /**
     * Show only the origin (scheme, host, optional port) used for this request.
     *
     * HTTP_HOST is untrusted input. Accept literal IP addresses or configured
     * firewall hostnames/aliases; never use forwarded headers, URI paths,
     * query strings or credentials. If verification fails, omit the detail.
     */
    private static function loginUrl($system): ?string
    {
        $authority = $_SERVER['HTTP_HOST'] ?? null;
        if (!is_string($authority) || $authority === '' || strlen($authority) > 200) {
            return null;
        }

        $hostname = '';
        $port = null;
        if (preg_match('/^\[([0-9a-f:.]+)\](?::([0-9]{1,5}))?$/iD', $authority, $parts)) {
            if (!filter_var($parts[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return null;
            }
            $hostname = strtolower($parts[1]);
            $displayHost = '[' . $hostname . ']';
            $port = $parts[2] ?? null;
        } elseif (preg_match('/^([a-z0-9.-]+)(?::([0-9]{1,5}))?$/iD', $authority, $parts)) {
            $hostname = strtolower($parts[1]);
            $displayHost = $hostname;
            $port = $parts[2] ?? null;
            if (!filter_var($hostname, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $labels = explode('.', $hostname);
                if (strlen($hostname) > 253) {
                    return null;
                }
                foreach ($labels as $label) {
                    if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $label)) {
                        return null;
                    }
                }
                $name = strtolower(trim((string)($system->hostname ?? '')));
                $domain = strtolower(trim((string)($system->domain ?? '')));
                $allowed = [$name, 'localhost'];
                if ($name !== '' && $domain !== '' && strpos($name, '.') === false) {
                    $allowed[] = $name . '.' . $domain;
                }
                $aliases = trim((string)($system->webgui->althostnames ?? ''));
                if ($aliases !== '') {
                    $allowed = array_merge($allowed, preg_split('/\s+/', strtolower($aliases)));
                }
                if (!in_array($hostname, $allowed, true)) {
                    return null;
                }
            }
        } else {
            return null;
        }

        if ($port !== null && ((int)$port < 1 || (int)$port > 65535)) {
            return null;
        }
        // The configured Web GUI protocol is trusted. Do not accept a
        // client-controlled X-Forwarded-Proto header.
        $scheme = strtolower((string)($system->webgui->protocol ?? 'https'));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        return $scheme . '://' . $displayHost . ($port !== null ? ':' . (int)$port : '');
    }

    /** Validate an explicit, bounded list of trusted immediate proxy peers. */
    public static function validProxyRanges(string $ranges): bool
    {
        $ranges = trim($ranges);
        if ($ranges === '') {
            return true; // Disabled by default; an empty list never trusts a header.
        }
        if (strlen($ranges) > 1024) {
            return false;
        }
        $items = preg_split('/[\s,]+/', $ranges);
        if (count($items) > 32) {
            return false;
        }
        foreach ($items as $item) {
            $parts = explode('/', $item);
            if (count($parts) > 2 || !filter_var($parts[0], FILTER_VALIDATE_IP)) {
                return false;
            }
            $bits = strpos($parts[0], ':') !== false ? 128 : 32;
            if (count($parts) === 2 &&
                (!preg_match('/^(0|[1-9][0-9]{0,2})$/D', $parts[1]) ||
                    (int)$parts[1] > $bits)) {
                return false;
            }
        }
        return true;
    }

    /** Compare address bytes against an exact IP or CIDR, for IPv4 and IPv6. */
    private static function ipInRange(string $ip, string $range): bool
    {
        $parts = explode('/', $range, 2);
        $peer = inet_pton($ip);
        $network = inet_pton($parts[0]);
        if ($peer === false || $network === false || strlen($peer) !== strlen($network)) {
            return false;
        }
        $prefix = isset($parts[1]) ? (int)$parts[1] : strlen($network) * 8;
        $whole = intdiv($prefix, 8);
        $partial = $prefix % 8;
        if (substr($peer, 0, $whole) !== substr($network, 0, $whole)) {
            return false;
        }
        if ($partial === 0) {
            return true;
        }
        $mask = (0xff << (8 - $partial)) & 0xff;
        return (ord($peer[$whole]) & $mask) === (ord($network[$whole]) & $mask);
    }

    /**
     * Use CF-Connecting-IP only from a configured trusted immediate peer.
     * Neither forwarded headers nor a local/loopback connection is
     * automatically trusted. If anything is invalid, use REMOTE_ADDR.
     */
    private function sourceIp(): ?string
    {
        $peer = $_SERVER['REMOTE_ADDR'] ?? null;
        if (!is_string($peer) || !filter_var($peer, FILTER_VALIDATE_IP)) {
            return null;
        }
        if (!$this->serverProperties['trust_cf_client_ip']) {
            return $peer;
        }
        $ranges = $this->serverProperties['trusted_proxy_ips'];
        if (!self::validProxyRanges($ranges) || $ranges === '') {
            return $peer;
        }
        $trusted = false;
        foreach (preg_split('/[\s,]+/', trim($ranges)) as $range) {
            if (self::ipInRange($peer, $range)) {
                $trusted = true;
                break;
            }
        }
        if (!$trusted) {
            return $peer;
        }
        $forwarded = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null;
        // Only one address; reject lists, whitespace, ports and zone identifiers.
        if (!is_string($forwarded) || $forwarded === '' ||
            $forwarded !== trim($forwarded) ||
            !filter_var($forwarded, FILTER_VALIDATE_IP)) {
            return $peer;
        }
        return $forwarded;
    }

    /** Credentials are exclusively from the selected native authentication server. */
    private function credentials(): array
    {
        $ikey = (string)($this->serverProperties['ikey'] ?? '');
        $skey = (string)($this->serverProperties['skey'] ?? '');
        $host = (string)($this->serverProperties['host'] ?? '');
        self::assertCredentials($ikey, $skey, $host);
        return compact('ikey', 'skey', 'host');
    }

    public static function assertCredentials(string $ikey, string $skey, string $host): void
    {
        $host = strtolower(trim($host));
        if (!preg_match('/^DI[A-Za-z0-9]{12,38}$/', $ikey) ||
            !preg_match('/^[A-Za-z0-9]{20,120}$/', $skey) ||
            !preg_match('/^api-[a-z0-9-]+\.duosecurity\.com$/', $host)) {
            throw new \RuntimeException('duo_configuration_invalid');
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
            if ($raw === false) {
                // Numeric cURL error only; curl_error() can include sensitive data.
                switch (curl_errno($ch)) {
                    case CURLE_OPERATION_TIMEDOUT:
                        $reason = 'duo_api_timeout'; break;
                    case CURLE_COULDNT_RESOLVE_HOST:
                        $reason = 'duo_dns_failure'; break;
                    case CURLE_COULDNT_CONNECT:
                        $reason = 'duo_connection_failed'; break;
                    case CURLE_SSL_CONNECT_ERROR:
                    case CURLE_PEER_FAILED_VERIFICATION:
                        $reason = 'duo_tls_failed'; break;
                    default:
                        $reason = 'duo_network_error';
                }
                throw new \RuntimeException($reason);
            }
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode !== 200) {
                if ($httpCode === 401 || $httpCode === 403) {
                    $reason = 'duo_api_unauthorized';
                } elseif ($httpCode === 429) {
                    $reason = 'duo_api_rate_limited';
                } elseif ($httpCode >= 500) {
                    $reason = 'duo_api_server_error';
                } else {
                    $reason = 'duo_api_http_error';
                }
                throw new \RuntimeException($reason);
            }
            try {
                $result = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new \RuntimeException('duo_invalid_response');
            }
            if (!is_array($result) || !isset($result['stat'])) {
                throw new \RuntimeException('duo_invalid_response');
            }
            if ($result['stat'] !== 'OK') {
                throw new \RuntimeException('duo_api_rejected');
            }
            return $result;
        } finally {
            curl_close($ch);
        }
    }
}
