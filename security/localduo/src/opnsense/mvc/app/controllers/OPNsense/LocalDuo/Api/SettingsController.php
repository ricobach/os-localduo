<?php
namespace OPNsense\LocalDuo\Api;

use OPNsense\Auth\LocalDuo;
use OPNsense\Base\ApiControllerBase;

/** Test entered credentials, without reading or returning saved secrets. */
class SettingsController extends ApiControllerBase
{
    public function checkAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed', 'message' => 'POST required'];
        }

        $input = $this->request->getPost();
        $ikey = trim((string)($input['ikey'] ?? ''));
        $skey = (string)($input['skey'] ?? '');
        $host = strtolower(trim((string)($input['host'] ?? '')));

        try {
            LocalDuo::assertCredentials($ikey, $skey, $host);
            LocalDuo::request(compact('ikey', 'skey', 'host'), 'GET', '/auth/v2/check');
            return ['status' => 'ok', 'message' => 'Duo API connection successful'];
        } catch (\Throwable $e) {
            $reason = LocalDuo::diagnosticReason($e);
            syslog(LOG_NOTICE, 'LocalDuo: context=connection_test outcome=failed reason=' . $reason);
            return ['status' => 'failed', 'message' => 'Duo API connection or credentials failed'];
        }
    }
}
