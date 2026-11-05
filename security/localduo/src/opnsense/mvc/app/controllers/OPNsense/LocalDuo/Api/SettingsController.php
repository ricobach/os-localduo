<?php
namespace OPNsense\LocalDuo\Api;

use OPNsense\Auth\LocalDuo;
use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Config;

/**
 * Credential manager for Duo Auth API integrations.
 * ACL: page-system-localduo. SKEY is write-only.
 */
class SettingsController extends ApiControllerBase
{
    private function input(): array
    {
        return $this->request->getPost();
    }

    public function getAction()
    {
        $ikey = trim((string)($this->request->get('ikey') ?? ''));
        if (!preg_match('/^DI[A-Za-z0-9]{12,38}$/', $ikey)) {
            return ['status' => 'failed', 'message' => 'Enter a valid integration key'];
        }
        return [
            'status' => 'ok',
            'ikey' => $ikey,
            'skey_configured' => LocalDuo::lookupSecret($ikey) !== ''
        ];
    }

    public function setAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed', 'message' => 'POST required'];
        }
        $input = $this->input();
        $ikey = trim((string)($input['ikey'] ?? ''));
        $skey = (string)($input['skey'] ?? '');
        if (!preg_match('/^DI[A-Za-z0-9]{12,38}$/', $ikey) ||
            !preg_match('/^[A-Za-z0-9]{20,120}$/', $skey)) {
            return ['status' => 'failed', 'message' => 'Invalid Duo integration or secret key'];
        }

        $cfg = Config::getInstance();
        $cfg->lock();
        try {
            $root = $cfg->object();
            if (!isset($root->OPNsense)) {
                $root->addChild('OPNsense');
            }
            if (!isset($root->OPNsense->LocalDuo)) {
                $root->OPNsense->addChild('LocalDuo');
            }
            if (!isset($root->OPNsense->LocalDuo->secrets)) {
                $root->OPNsense->LocalDuo->addChild('secrets');
            }
            $entries = $root->OPNsense->LocalDuo->secrets;
            $found = null;
            foreach ($entries->children() as $entry) {
                if ((string)($entry->ikey ?? '') === $ikey) {
                    $found = $entry;
                    break;
                }
            }
            if ($found === null) {
                $found = $entries->addChild('entry');
                $found->addChild('ikey', $ikey);
                $found->addChild('skey', $skey);
            } else {
                $found->skey = $skey;
            }
            $cfg->save(['description' => 'LocalDuo credential updated']);
            return ['status' => 'ok'];
        } finally {
            $cfg->unlock();
        }
    }

    public function checkAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed', 'message' => 'POST required'];
        }
        $data = $this->input();
        $ikey = trim((string)($data['ikey'] ?? ''));
        $host = strtolower(trim((string)($data['host'] ?? '')));
        $skey = LocalDuo::lookupSecret($ikey);
        try {
            LocalDuo::assertCredentials($ikey, $skey, $host);
            LocalDuo::request(
                compact('ikey', 'skey', 'host'),
                'GET',
                '/auth/v2/check'
            );
            return ['status' => 'ok', 'message' => 'Duo Auth API credentials verified'];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'message' => 'Duo API connection or credentials failed'];
        }
    }
}
