<?php

use MarkusBiggus\StateEngine\Workflow\StateEngine;
use MarkusBiggus\StateEngine\Workflow\Examples\ForkComboWorkflow;

//uses(Tests\CreatesApplication::class);

// Emulate Controller actions, but don't run  workflow
test('ForkCombo WorkflowApp created properly', function () {
//    $this->createApplication(); // dunno why Pest isn't doing this!!!

    $Engine = StateEngine::Workflow(ForkComboWorkflow::class);

    $appContext = $Engine->StartWorkflow('fork');

    $this->assertObjectHasProperty('TestSEQ', $appContext);
    $this->assertIsString($appContext->TestSEQ);
    $this->assertSame('fork', $appContext->TestSEQ);

    $this->assertObjectHasProperty('StatePath', $appContext);
    $this->assertIsString($appContext->StatePath);
    $this->assertSame('I', $appContext->StatePath);

    $this->assertObjectHasProperty('TestSEQTransitions', $appContext);
    $this->assertIsArray($appContext->TestSEQTransitions);

    $this->assertArrayHasKey('S1', $appContext->TestSEQTransitions, "Array TestSEQTransitions missing 'S1' key");
    $this->assertIsArray($appContext->TestSEQTransitions['S1']['splitsyncmerge']);
    $this->assertSame('T1_3_4', $appContext->TestSEQTransitions['S1']['splitsyncmerge'][0]);

    $this->assertIsArray($appContext->TestSEQTransitions['S1']['fork']);
    $this->assertSame('T1_3_4, T1_6, T1_5_2', $appContext->TestSEQTransitions['S1']['fork'][0]);

    $this->assertArrayHasKey('S5', $appContext->TestSEQTransitions, "Array TestSEQTransitions missing 'S5' key");
    $this->assertSame('T1_5_2, T5_6', $appContext->TestSEQTransitions['S5']['fork']);

    $VB = $Engine->GetVersionBuild();
    $this->assertIsArray($VB);
    $this->assertSame('1.0', $VB["SEVersion"]); // See StateEngine class for current values to test
    $this->assertSame('1', $VB["SEBuild"]);
});

// Emulate Controller actions
test('ForkCombo Fork works', function () {
    $Engine = StateEngine::Workflow(ForkComboWorkflow::class);

    $appContext = $Engine->StartWorkflow('fork');
    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->S1->S3->S4->S5->S2->S6', $appContext->StatePath);
});
test('ForkCombo Fork1 works', function () {
    $Engine = StateEngine::Workflow(ForkComboWorkflow::class);

    $appContext = $Engine->StartWorkflow('fork1');
    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->S1->S3->S4->S5->S2->S5->S6', $appContext->StatePath);
});
test('ForkCombo Fork2 works', function () {
    $Engine = StateEngine::Workflow(ForkComboWorkflow::class);

    $appContext = $Engine->StartWorkflow('fork2');
    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->S1->S3->S4->S5->S5->S2->S6', $appContext->StatePath);
});
test('ForkCombo ForkSyncMerge works', function () {
    $Engine = StateEngine::Workflow(ForkComboWorkflow::class);

    // $this->withoutExceptionHandling();
    // $this->expectException(\RuntimeException::class);
    // $this->expectExceptionMessage('STALLED: No ready states! DispatchCycle: 4');
    $appContext = $Engine->StartWorkflow('splitsyncmerge');
    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->S1->S1->S1->S3->S4->S5->S2->S6', $appContext->StatePath);
});
test('ForkCombo syncmergesplit works', function () {
    $Engine = StateEngine::Workflow(ForkComboWorkflow::class);

    // $this->withoutExceptionHandling();
    // $this->expectException(\RuntimeException::class);
    // $this->expectExceptionMessage('STALLED: No ready states! DispatchCycle: 1');

//    $this->get('/ForkCombo/syncmergeFail');
    $appContext = $Engine->StartWorkflow('syncmergesplit');
    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->S1->S1->S3->S4->S5->S2->S6', $appContext->StatePath);
});
