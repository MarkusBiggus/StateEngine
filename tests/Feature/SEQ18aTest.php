<?php

// run: ./vendor/bin/pest
//php artisan make:test SEQ18Test --pest

// php artisan test --filter=SEQ18a

test('path_SEQ18a', function () {
    //    $this->expectOutputString('I->S1x->S8(Terminal)'); // output by ECHO
    //    expect($response->content)->toMatch('/^.* State Path: I-&gt;S8.*$/i');

    $response = $this->get('/workflow/Reference/seq18a');

    $response->assertStatus(200);
    $response->assertContent('I->S1x->S1x->S1x->SX->S8'); // controller Response
});
