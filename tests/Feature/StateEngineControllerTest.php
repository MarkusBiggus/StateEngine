<?php

test('Instantiates StateEngine controller', function () {
    $response = $this->get('/workflow');
    //$response->dd();
    $response->assertOk();
    $response->assertContent('State Engine');
});
test('Instantiate workflow model: Reference', function () {
    $response = $this->get('/workflow/Reference');

    $response->assertOk();
    $response->assertContent('Workflow Reference');
});
