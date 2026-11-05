<?php
namespace OPNsense\LocalDuo\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Config;
use OPNsense\Auth\LocalDuo;

/** Only a separately ACL-protected GUI/API should access these methods. */
class SettingsController extends ApiControllerBase
{
    public function getAction()
    {
        $root = Config::getInstance()->object();
        $general = $root->OPNsense->LocalDuo->general ?? null;
        return [
            'ikey' => (string)($general->ikey ?? ''),
            'host' => (string)($general->host ?? ''),
            'skey_configured' => (string)($general->skey ?? '') !== '',
            'firewall' => trim((string)($root->system->hostname ?? '')) . '.' . trim((string)($root->system->domain ?? ''))
        ];
    }

    public function setAction()
    {
        if (!$this->request->isPost()) { return ['status' => 'failed', 'message' => 'POST required']; }
        $data = $this->request->getPost();
        $ikey = trim((string)($data['ikey'] ?? ''));
        $host = strtolower(trim((string)($data['host'] ?? '')));
        $newSecret = (string)($data['skey'] ?? '');
        if (!preg_match('/^[A-Za-z0-9]{15,40}$/', $ikey) ||
            !preg_match('/^api-[a-z0-9-]+\.duosecurity\.com$/', $host) ||
            ($newSecret !== '' && !preg_match('/^[A-Za-z0-9]{20,120}$/', $newSecret))) {
            return ['status' => 'failed', 'message' => 'Invalid credentials or API hostname'];
        }
        $cfg = Config::getInstance();
        $cfg->lock();
        try {
            $root = $cfg->object();
            if (!isset($root->OPNsense)) { $root->addChild('OPNsense'); }
            if (!isset($root->OPNsense->LocalDuo)) { $root->OPNsense->addChild('LocalDuo'); }
            if (!isset($root->OPNsense->LocalDuo->general)) { $root->OPNsense->LocalDuo->addChild('general'); }
            $node = $root->OPNsense->LocalDuo->general;
            $existing = (string)($node->skey ?? '');
            if ($newSecret === '' && $existing === '') {
                return ['status' => 'failed', 'message' => 'Duo secret is required'];
            }
            $node->ikey = $ikey;
            $node->host = $host;
            if ($newSecret !== '') { $node->skey = $newSecret; }
            $cfg->save(['description' => 'LocalDuo settings updated']);
            return ['status' => 'ok'];
        } finally {
            $cfg->unlock();
        }
    }

    public function checkAction()
    {
        if (!$this->request->isPost()) { return ['status' => 'failed']; }
        try {
            $config = LocalDuo::credentials();
            LocalDuo::request($config, 'GET', '/auth/v2/check');
            return ['status' => 'ok', 'message' => 'Duo API credentials verified'];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'message' => 'Duo API check failed'];
        }
    }
}
