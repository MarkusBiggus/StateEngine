<?php

//uses(Tests\TestCase::class)->in('Feature');

test('SplitMerge path', function () {
    //    $this->expectOutputString('I->S1x->S2->S5->S6->S7(idle)->S7->S8(Terminal)'); // output by ECHO
    //    expect($response->content)->toMatch('/^.* State Path: I-&gt;S8.*$/i');

    $response = $this->get('/workflow/Reference/splitmerge');

    $response->assertStatus(200);
    $response->assertContent('I->S1x->S2->S2->S2->S5->S6->S7Idle->SZ->SY->S7Idle->S8'); // controller Response
});
