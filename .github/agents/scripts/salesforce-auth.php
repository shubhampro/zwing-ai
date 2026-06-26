#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Test or refresh Salesforce authentication.
 *
 * Usage:
 *   php salesforce-auth.php --test
 *   php salesforce-auth.php --exchange-jwt
 */

require_once __DIR__.'/lib/salesforce-client.php';

function main(array $argv): int
{
    $testOnly = in_array('--test', $argv, true);
    $exchangeJwt = in_array('--exchange-jwt', $argv, true);

    if ($exchangeJwt) {
        $sid = (string) envValue('SALESFORCE_SID', '');
        $lightningUrl = (string) envValue('SALESFORCE_LIGHTNING_URL', 'https://ginesys-one.lightning.force.com');

        if ($sid === '') {
            fwrite(STDERR, "Error: Set SALESFORCE_SID in .env first.\n");

            return 1;
        }

        try {
            $exchange = SalesforceClient::exchangeSidForJwt($lightningUrl, $sid);
        } catch (RuntimeException $exception) {
            fwrite(STDERR, 'Error: '.$exception->getMessage()."\n");

            return 1;
        }

        echo json_encode([
            'ok' => true,
            'note' => 'Yeh JWT sirf Lightning/CDN ke liye hai — REST API ke liye use mat karo.',
            'instance_url' => $exchange['instance_url'],
            'access_token_prefix' => substr($exchange['access_token'], 0, 40).'...',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";

        return 0;
    }

    try {
        $client = SalesforceClient::fromEnvironment();
    } catch (RuntimeException $exception) {
        fwrite(STDERR, 'Error: '.$exception->getMessage()."\n");
        fwrite(STDERR, "\nSetup options:\n");
        fwrite(STDERR, "  Local:  SALESFORCE_SID=<browser sid cookie>\n");
        fwrite(STDERR, "  Server: SALESFORCE_CLIENT_ID + SECRET + USERNAME + PASSWORD\n");

        return 1;
    }

    if ($testOnly) {
        try {
            $result = $client->query('SELECT Id, CaseNumber, Subject, Status FROM Case ORDER BY LastModifiedDate DESC LIMIT 1');
        } catch (RuntimeException $exception) {
            fwrite(STDERR, 'Error: '.$exception->getMessage()."\n");

            return 1;
        }

        $record = $result['records'][0] ?? null;

        echo json_encode([
            'ok' => true,
            'message' => 'Salesforce REST API connection successful',
            'sample_case' => $record,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";

        return 0;
    }

    echo "Salesforce auth config OK.\n";
    echo "Run with --test to verify REST API query access.\n";

    return 0;
}

exit(main($argv));
