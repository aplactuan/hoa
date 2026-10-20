<?php

use App\Support\BillingMath;

test('monthly dues use exact cent arithmetic and round once', function () {
    expect(BillingMath::regularDues('125.50', '10.00'))->toBe(125500)
        ->and(BillingMath::decimal(125500))->toBe('1255.00')
        ->and(BillingMath::regularDues('1.25', '0.05'))->toBe(6);
});

test('penalty is two percent of the carried balance', function () {
    expect(BillingMath::penalty(125500, '2.00'))->toBe(2510)
        ->and(BillingMath::decimal(2510))->toBe('25.10');
});

test('decimal parsing rejects excess precision', function () {
    BillingMath::cents('10.005');
})->throws(InvalidArgumentException::class);
