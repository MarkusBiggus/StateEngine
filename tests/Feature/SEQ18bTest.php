<?php

//uses(Tests\TestCase::class)->in('Feature');

test('path_SEQ18b', function () {

    $response = $this->get('/workflow/Reference/seq18b');

    $response->assertStatus(200);
    $response->assertContent('I->S1x->SX->S8'); // controller Response
});
