<?php

test('Idle state stops', function () {

    $response = $this->get('/workflow/Reference/idlestop');

    $response->assertStatus(200);
    $response->assertContent('I->S1x->S2->S3->S4->S4->S4'); // controller Response
});
