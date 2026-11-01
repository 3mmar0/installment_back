<?php

use App\Helpers\GovernorateHelper;

test('same governorate is not a geographic risk', function () {
    expect(GovernorateHelper::distanceBand('cairo', 'cairo'))->toBe(GovernorateHelper::SAME);
});

test('same region is nearby', function () {
    expect(GovernorateHelper::distanceBand('cairo', 'giza'))->toBe(GovernorateHelper::NEARBY);
});

test('adjacent region is a moderate geographic risk', function () {
    expect(GovernorateHelper::distanceBand('cairo', 'alexandria'))->toBe(GovernorateHelper::ADJACENT);
});

test('far upper egypt from cairo is distant', function () {
    expect(GovernorateHelper::distanceBand('cairo', 'aswan'))->toBe(GovernorateHelper::DISTANT);
});

test('missing governorate is unknown', function () {
    expect(GovernorateHelper::distanceBand('cairo', null))->toBe(GovernorateHelper::UNKNOWN);
});
