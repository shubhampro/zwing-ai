<?php

declare(strict_types=1);

require_once __DIR__.'/env.php';

final class SalesforceClient
{
    private const API_VERSION = 'v59.0';

    public function __construct(
        private readonly string $instanceUrl,
        private string $accessToken,
        private readonly string $authMode = 'oauth',
    ) {}

    public static function fromEnvironment(): self
    {
        $instanceUrl = self::normalizeInstanceUrl(
            (string) envValue('SALESFORCE_INSTANCE_URL', 'https://ginesys-one.my.salesforce.com'),
        );

        $clientId = (string) envValue('SALESFORCE_CLIENT_ID', '');
        if ($clientId !== '') {
            return self::authenticateWithPasswordGrant();
        }

        $sid = (string) envValue('SALESFORCE_SID', '');
        if ($sid !== '') {
            return new self($instanceUrl, $sid, 'session');
        }

        $accessToken = (string) envValue('SALESFORCE_ACCESS_TOKEN', '');
        if ($accessToken !== '') {
            if (self::isSidExchangeJwt($accessToken)) {
                throw new RuntimeException(
                    'SALESFORCE_ACCESS_TOKEN browser JWT hai (sidexchange / x-cdn-authorization). '
                    .'Yeh REST API ke liye valid nahi. .env mein SALESFORCE_SID set karo (local run) '
                    .'ya Connected App OAuth credentials (SALESFORCE_CLIENT_ID...) add karo.',
                );
            }

            $resolvedUrl = self::resolveInstanceUrl((string) envValue('SALESFORCE_INSTANCE_URL', ''), $accessToken);

            return new self($resolvedUrl, $accessToken, 'oauth');
        }

        throw new RuntimeException(
            'Salesforce credentials missing. Set SALESFORCE_SID (browser cookie) or Connected App OAuth vars in .env.',
        );
    }

    /**
     * Exchange browser sid cookie for Lightning JWT (CDN/Aura only — not REST API).
     *
     * @return array{access_token: string, instance_url: string}
     */
    public static function exchangeSidForJwt(string $lightningUrl, string $sid): array
    {
        $lightningUrl = rtrim($lightningUrl, '/');

        $response = self::httpRequest(
            method: 'POST',
            url: $lightningUrl.'/services/auth/jwt/sidexchange',
            headers: ['Cookie: sid='.$sid],
        );

        if (($response['status'] ?? 0) !== 200 || ! is_array($response['body'])) {
            $message = is_array($response['body']) ? json_encode($response['body']) : (string) ($response['raw'] ?? 'Unknown sid exchange error');

            throw new RuntimeException('Salesforce sid exchange failed: '.$message);
        }

        $accessToken = (string) ($response['body']['access_token'] ?? '');
        if ($accessToken === '') {
            throw new RuntimeException('Salesforce sid exchange did not return access_token.');
        }

        return [
            'access_token' => $accessToken,
            'instance_url' => self::resolveInstanceUrl(
                (string) envValue('SALESFORCE_INSTANCE_URL', ''),
                $accessToken,
            ),
        ];
    }

    /** @deprecated Use exchangeSidForJwt — sidexchange tokens do not work with REST API. */
    public static function authenticateWithSidExchange(string $lightningUrl, string $sid): self
    {
        $exchange = self::exchangeSidForJwt($lightningUrl, $sid);

        return new self($exchange['instance_url'], $exchange['access_token'], 'jwt');
    }

    public static function authenticateWithPasswordGrant(): self
    {
        $loginUrl = rtrim((string) envValue('SALESFORCE_LOGIN_URL', 'https://login.salesforce.com'), '/');
        $clientId = (string) envValue('SALESFORCE_CLIENT_ID', '');
        $clientSecret = (string) envValue('SALESFORCE_CLIENT_SECRET', '');
        $username = (string) envValue('SALESFORCE_USERNAME', '');
        $password = (string) envValue('SALESFORCE_PASSWORD', '');
        $securityToken = (string) envValue('SALESFORCE_SECURITY_TOKEN', '');

        foreach ([
            'SALESFORCE_CLIENT_ID' => $clientId,
            'SALESFORCE_CLIENT_SECRET' => $clientSecret,
            'SALESFORCE_USERNAME' => $username,
            'SALESFORCE_PASSWORD' => $password,
        ] as $name => $value) {
            if ($value === '') {
                throw new RuntimeException("Missing {$name} in environment. Set Salesforce credentials in .env.");
            }
        }

        $payload = http_build_query([
            'grant_type' => 'password',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'username' => $username,
            'password' => $password.$securityToken,
        ]);

        $response = self::httpRequest(
            method: 'POST',
            url: $loginUrl.'/services/oauth2/token',
            headers: ['Content-Type: application/x-www-form-urlencoded'],
            body: $payload,
        );

        if (($response['status'] ?? 0) !== 200) {
            $message = is_array($response['body']) ? json_encode($response['body']) : (string) ($response['raw'] ?? 'Unknown OAuth error');

            throw new RuntimeException('Salesforce authentication failed: '.$message);
        }

        $body = $response['body'];
        if (! is_array($body) || ! isset($body['access_token'], $body['instance_url'])) {
            throw new RuntimeException('Salesforce authentication returned an unexpected response.');
        }

        return new self(rtrim((string) $body['instance_url'], '/'), (string) $body['access_token']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCaseByNumber(string $caseNumber): ?array
    {
        $escaped = str_replace("'", "\\'", trim($caseNumber));
        $rcaField = $this->rcaFieldName();
        $rcaSelect = $rcaField !== null ? ", {$rcaField}" : '';

        $soql = "SELECT Id, CaseNumber, Subject, Status, Description, Account.Name{$rcaSelect} "
            ."FROM Case WHERE CaseNumber = '{$escaped}' LIMIT 1";

        $result = $this->query($soql);
        $record = $result['records'][0] ?? null;

        return is_array($record) ? $record : null;
    }

    public function getInternalComments(string $caseId, ?string $customRcaValue = null): string
    {
        $sections = [];

        if ($customRcaValue !== null && trim($customRcaValue) !== '') {
            $sections[] = trim($customRcaValue);
        }

        $feedSoql = "SELECT Body, Type, Visibility, CreatedDate FROM CaseFeed "
            ."WHERE ParentId = '{$caseId}' AND Visibility = 'InternalUsers' "
            .'ORDER BY CreatedDate DESC LIMIT 25';
        $feedItems = $this->query($feedSoql)['records'] ?? [];

        foreach ($feedItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            $body = trim(strip_tags((string) ($item['Body'] ?? '')));
            if ($body !== '') {
                $sections[] = $body;
            }
        }

        $commentSoql = "SELECT CommentBody, CreatedDate FROM CaseComment "
            ."WHERE ParentId = '{$caseId}' AND IsPublished = false "
            .'ORDER BY CreatedDate DESC LIMIT 25';
        $comments = $this->query($commentSoql)['records'] ?? [];

        foreach ($comments as $comment) {
            if (! is_array($comment)) {
                continue;
            }

            $body = trim((string) ($comment['CommentBody'] ?? ''));
            if ($body !== '') {
                $sections[] = $body;
            }
        }

        $emailSoql = 'SELECT TextBody, Subject, CreatedDate FROM EmailMessage '
            ."WHERE ParentId = '{$caseId}' AND IsInternallyVisible = true "
            .'ORDER BY CreatedDate DESC LIMIT 10';
        $emails = $this->query($emailSoql)['records'] ?? [];

        foreach ($emails as $email) {
            if (! is_array($email)) {
                continue;
            }

            $subject = trim((string) ($email['Subject'] ?? ''));
            $body = trim((string) ($email['TextBody'] ?? ''));
            $combined = trim($subject.($body !== '' ? "\n".$body : ''));

            if ($combined !== '') {
                $sections[] = $combined;
            }
        }

        $uniqueSections = [];
        foreach ($sections as $section) {
            $normalized = preg_replace('/\s+/u', ' ', $section) ?? $section;
            if ($normalized !== '' && ! in_array($normalized, $uniqueSections, true)) {
                $uniqueSections[] = $section;
            }
        }

        return implode("\n\n---\n\n", $uniqueSections);
    }

    /**
     * @return array{
     *     case_number: string,
     *     subject: string,
     *     description: string,
     *     status: string,
     *     account_name: string,
     *     internal_comments: string,
     *     fetch_error: string|null,
     *     subject_mismatch: bool
     * }
     */
    public function enrichCaseFromSalesforce(string $caseNumber, string $csvSubject = ''): array
    {
        $record = $this->getCaseByNumber($caseNumber);

        if ($record === null) {
            return [
                'case_number' => $caseNumber,
                'subject' => $csvSubject,
                'description' => '',
                'status' => '',
                'account_name' => '',
                'internal_comments' => '',
                'fetch_error' => "Case number '{$caseNumber}' Salesforce mein nahi mila.",
                'subject_mismatch' => false,
            ];
        }

        $salesforceSubject = trim((string) ($record['Subject'] ?? ''));
        $accountName = '';
        if (isset($record['Account']) && is_array($record['Account'])) {
            $accountName = trim((string) ($record['Account']['Name'] ?? ''));
        }

        $rcaField = $this->rcaFieldName();
        $customRca = null;
        if ($rcaField !== null) {
            $customRca = trim((string) ($record[$rcaField] ?? ''));
        }

        $internalComments = $this->getInternalComments((string) $record['Id'], $customRca);

        $subjectMismatch = $csvSubject !== ''
            && $salesforceSubject !== ''
            && strcasecmp($csvSubject, $salesforceSubject) !== 0;

        return [
            'case_number' => trim((string) ($record['CaseNumber'] ?? $caseNumber)),
            'subject' => $salesforceSubject !== '' ? $salesforceSubject : $csvSubject,
            'description' => trim((string) ($record['Description'] ?? '')),
            'status' => trim((string) ($record['Status'] ?? '')),
            'account_name' => $accountName,
            'internal_comments' => $internalComments,
            'fetch_error' => null,
            'subject_mismatch' => $subjectMismatch,
        ];
    }

    /**
     * @return array{records: list<array<string, mixed>>, totalSize: int}
     */
    public function query(string $soql): array
    {
        $url = $this->instanceUrl
            .'/services/data/'.self::API_VERSION
            .'/query?'.http_build_query(['q' => $soql]);

        $response = self::httpRequest(
            method: 'GET',
            url: $url,
            headers: $this->authorizationHeader(),
        );

        if (($response['status'] ?? 0) === 401) {
            throw new RuntimeException(self::describeAuthFailure($response));
        }

        if (($response['status'] ?? 0) !== 200 || ! is_array($response['body'])) {
            $message = is_array($response['body']) ? json_encode($response['body']) : (string) ($response['raw'] ?? 'Unknown query error');

            throw new RuntimeException('Salesforce query failed: '.$message);
        }

        /** @var array{records: list<array<string, mixed>>, totalSize: int} */
        return $response['body'];
    }

    private function rcaFieldName(): ?string
    {
        $field = trim((string) envValue('SALESFORCE_RCA_FIELD', ''));

        if ($field === '') {
            return null;
        }

        if (! preg_match('/^[A-Za-z0-9_]+(__c)?$/', $field)) {
            throw new RuntimeException('Invalid SALESFORCE_RCA_FIELD value.');
        }

        return $field;
    }

    private static function resolveInstanceUrl(string $configuredUrl, string $accessToken): string
    {
        if ($configuredUrl !== '') {
            return self::normalizeInstanceUrl($configuredUrl);
        }

        $fromJwt = self::instanceUrlFromJwt($accessToken);
        if ($fromJwt !== null) {
            return $fromJwt;
        }

        throw new RuntimeException('Set SALESFORCE_INSTANCE_URL to your my.salesforce.com URL (not lightning.force.com).');
    }

    private static function normalizeInstanceUrl(string $url): string
    {
        $url = rtrim($url, '/');

        if (preg_match('#^https://([a-z0-9-]+)\.lightning\.force\.com$#i', $url, $matches)) {
            return 'https://'.$matches[1].'.my.salesforce.com';
        }

        return $url;
    }

    private static function instanceUrlFromJwt(string $jwt): ?string
    {
        $parts = explode('.', $jwt);
        if (count($parts) < 2) {
            return null;
        }

        $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($payloadJson === false) {
            return null;
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($payloadJson, true);
        if (! is_array($payload)) {
            return null;
        }

        $audience = $payload['aud'] ?? null;
        if (is_array($audience)) {
            $audience = $audience[0] ?? null;
        }

        if (! is_string($audience) || $audience === '') {
            return null;
        }

        return rtrim(self::normalizeInstanceUrl($audience), '/');
    }

    private static function isSidExchangeJwt(string $token): bool
    {
        $parts = explode('.', $token);
        if (count($parts) < 2) {
            return false;
        }

        $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($payloadJson === false) {
            return false;
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($payloadJson, true);

        return is_array($payload) && ($payload['mty'] ?? '') === 'sidexchange';
    }

    /** @return list<string> */
    private function authorizationHeader(): array
    {
        if ($this->authMode === 'session') {
            return ['Authorization: OAuth '.$this->accessToken];
        }

        return ['Authorization: Bearer '.$this->accessToken];
    }

    /**
     * @param  array{status: int, body: mixed, raw: string}  $response
     */
    private static function describeAuthFailure(array $response): string
    {
        $rawMessage = is_array($response['body']) ? json_encode($response['body']) : (string) ($response['raw'] ?? '');

        if (str_contains($rawMessage, 'INVALID_SCOPES')) {
            return 'Browser JWT (sidexchange / x-cdn-authorization) REST API ke liye valid nahi. '
                .'SALESFORCE_SID use karo (local machine) ya Connected App OAuth setup karo.';
        }

        if (str_contains($rawMessage, 'INVALID_SESSION_ID')) {
            return 'Salesforce sid session expire ya invalid hai. Browser se fresh sid cookie copy karke .env update karo. '
                .'Note: sid often sirf same IP/machine se kaam karta hai — server ke liye Connected App OAuth best hai.';
        }

        return 'Salesforce authentication failed. Refresh SALESFORCE_SID or use Connected App OAuth credentials.';
    }

    /**
     * @param  list<string>  $headers
     * @return array{status: int, body: mixed, raw: string}
     */
    private static function httpRequest(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        if (! function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for Salesforce API calls.');
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialize cURL.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 30,
        ]);

        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($raw === false) {
            throw new RuntimeException('Salesforce HTTP request failed: '.$error);
        }

        $decoded = json_decode($raw, true);

        return [
            'status' => $status,
            'body' => $decoded ?? $raw,
            'raw' => $raw,
        ];
    }
}
