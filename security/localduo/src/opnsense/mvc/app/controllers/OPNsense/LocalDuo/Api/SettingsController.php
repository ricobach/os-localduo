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
            syslog(LOG_NOTICE, 'LocalDuo: context=connection_test outcome=success');
            return ['status' => 'ok', 'message' => 'Duo API connection successful'];
        } catch (\Throwable $e) {
            $reason = LocalDuo::diagnosticReason($e);
            syslog(LOG_NOTICE, 'LocalDuo: context=connection_test outcome=failed reason=' . $reason);
            // Predefined messages, never exception text or Duo response details.
            $messages = [
                'duo_configuration_invalid' => 'Enter a valid Duo IKEY, SKEY and API hostname.',
                'duo_api_timeout' => 'Duo API request timed out.',
                'duo_dns_failure' => 'Unable to resolve the Duo API hostname.',
                'duo_connection_failed' => 'Unable to connect to the Duo API.',
                'duo_tls_failed' => 'Duo API TLS validation failed.',
                'duo_api_unauthorized' => 'Duo rejected the API credentials or access.',
                'duo_api_rate_limited' => 'Duo API rate limit reached. Try again later.',
                'duo_api_server_error' => 'Duo API is returning a server error.',
                'duo_api_http_error' => 'Duo API returned an unexpected HTTP status.',
                'duo_api_rejected' => 'Duo API rejected the connection check.',
                'duo_invalid_response' => 'Duo API returned an invalid response.',
                'duo_network_error' => 'Duo API network request failed.'
            ];
            return [
                'status' => 'failed',
                'message' => $messages[$reason] ?? 'Duo connection test failed. Check the system log.'
            ];
        }
    }
    /**
     * Login-page patching is a global, privileged operation. Do not allow
     * ordinary users who have access to the Duo connection-test endpoint.
     */
    private function loginProgressAction(string $command): array
    {
        $this->throwNotFullAdmin();
        if (!$this->request->isPost()) {
            return ['status' => 'failed', 'state' => 'unknown', 'message' => 'POST required'];
        }
        try {
            $backend = new \OPNsense\Core\Backend();
            $output = trim($backend->configdRun('localduo ' . $command));
            $result = json_decode($output, true);
            if (!is_array($result) || !in_array($result['state'] ?? '', ['enabled', 'disabled', 'unsupported'], true)) {
                return ['status' => 'failed', 'state' => 'unknown', 'message' => 'Unable to inspect the login page.'];
            }
            return [
                'status' => ($result['status'] ?? '') === 'ok' ? 'ok' : 'failed',
                'state' => $result['state'],
                'message' => (string)($result['message'] ?? '')
            ];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'state' => 'unknown', 'message' => 'Login page operation failed.'];
        }
    }

    public function loginstatusAction(): array
    {
        return $this->loginProgressAction('status');
    }

    public function loginenableAction(): array
    {
        return $this->loginProgressAction('enable');
    }

    public function logindisableAction(): array
    {
        return $this->loginProgressAction('disable');
    }

}
