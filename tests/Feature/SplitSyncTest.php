<?php

//uses(Tests\TestCase::class)->in('Feature');

test('SplitSync path', function () {
    //    $this->expectOutputString('I->S1x->S2->S3->S4->S8(Terminal)'); // output by ECHO
    //    expect($response->content)->toMatch('/^.* State Path: I-&gt;S8.*$/i');

    $response = $this->get('/workflow/Reference/splitsync');

    $response->assertStatus(200);
    $response->assertContent('I->S1x->S2->S3->S4->S4->S8'); // controller Response
});
