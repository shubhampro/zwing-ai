<?php

use App\Support\SafeHtml;
use Tests\TestCase;

uses(TestCase::class);

it('keeps safe tags and strips scripts', function () {
    $html = SafeHtml::from('<p>Please share <b>store id</b></p><script>alert(1)</script>');

    expect($html)->toContain('<p>Please share <b>store id</b></p>')
        ->and($html)->not->toContain('script')
        ->and($html)->not->toContain('alert');
});

it('turns plain text into escaped line breaks', function () {
    expect(SafeHtml::from("line one\nline two"))->toBe('line one<br>'."\n".'line two');
});

it('builds a plain preview from html', function () {
    expect(SafeHtml::plain('<p>Please share <b>store id</b></p><script>alert(1)</script>'))
        ->toBe('Please share store id');
});
