<?php

test('salesforce env example documents ginesys sid exchange setup', function () {
    $example = file_get_contents(dirname(__DIR__, 2).'/.env.example');

    expect($example)->toContain('SALESFORCE_LIGHTNING_URL')
        ->and($example)->toContain('SALESFORCE_SID')
        ->and($example)->toContain('ginesys-one.my.salesforce.com');
});
