<?php

use MarkusBiggus\StateEngine\Workflow\StateEngine;
use MarkusBiggus\StateEngine\Workflow\Examples\AllComboWorkflow;

//uses(Tests\CreatesApplication::class);

// Emulate Controller actions, but don't run  workflow
test('AllCombo WorkflowApp created properly', function () {
//    $this->createApplication(); // dunno why Pest isn't doing this!!!

    $Engine = StateEngine::Workflow(AllComboWorkflow::class);

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
    $this->assertSame('T1_4', $appContext->TestSEQTransitions['S1']['fork'][0]);

    $VB = $Engine->GetVersionBuild();
    $this->assertIsArray($VB);
    $this->assertSame('1.0', $VB["SEVersion"]); // See StateEngine class for current values to test
    $this->assertSame('1', $VB["SEBuild"]);
});

// Emulate Controller actions
test('AllCombo fork works', function () {
    $Engine = StateEngine::Workflow(AllComboWorkflow::class);

    $appContext = $Engine->StartWorkflow('fork');
    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->S1->S1->S1->S2->S3->S4->S5->S6->S7', $appContext->StatePath);
});

test('AllCombo merge stalls', function () {
    $Engine = StateEngine::Workflow(AllComboWorkflow::class);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('STALLED: No ready states! DispatchCycle: 2');
//    $this->expectExceptionMessage('STALLED: Same state transitions! DispatchCycle: 4');

    $appContext = $Engine->StartWorkflow('merge');
    $appContext = $Engine->RunWorkflow($appContext);
});

test('AllCombo split works', function () {
    $Engine = StateEngine::Workflow(AllComboWorkflow::class);

    // $this->expectException(\RuntimeException::class);
    // $this->expectExceptionMessage('STALLED: No ready states! DispatchCycle: 1');

    $appContext = $Engine->StartWorkflow('split');
    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->S1->S1->S1->S2->S3->S4->S5->S6->S7', $appContext->StatePath);
});

test('AllCombo simple stalls', function () {
    $Engine = StateEngine::Workflow(AllComboWorkflow::class);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('STALLED: No ready states! DispatchCycle: 2');

    $appContext = $Engine->StartWorkflow('simple');
    $appContext = $Engine->RunWorkflow($appContext);
});
