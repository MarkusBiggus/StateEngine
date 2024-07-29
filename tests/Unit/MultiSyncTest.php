<?php

use MarkusBiggus\StateEngine\Workflow\StateEngine;
use MarkusBiggus\StateEngine\Workflow\Examples\MultiSyncWorkflow;

//uses(Tests\CreatesApplication::class);

// Emulate Controller actions, but don't run  workflow
test('MultiSync WorkflowApp created properly', function () {
//    $this->createApplication(); // dunno why Pest isn't doing this!!!

    $Engine = StateEngine::Workflow(MultiSyncWorkflow::class);

    $appContext = $Engine->StartWorkflow('multi');

    $this->assertObjectHasProperty('TestSEQ', $appContext);
    $this->assertIsString($appContext->TestSEQ);
    $this->assertSame('multi', $appContext->TestSEQ);

    $this->assertObjectHasProperty('StatePath', $appContext);
    $this->assertIsString($appContext->StatePath);
    $this->assertSame('I', $appContext->StatePath);

    $this->assertObjectHasProperty('TestSEQTransitions', $appContext);
    $this->assertIsArray($appContext->TestSEQTransitions);
    $this->assertArrayHasKey('S1', $appContext->TestSEQTransitions, "Array TestSEQTransitions missing 'S1' key");
    $this->assertSame('T1_2_5', $appContext->TestSEQTransitions['S1']['multi'][0]);

    $VB = $Engine->GetVersionBuild();
    $this->assertIsArray($VB);
    $this->assertSame('1.0', $VB["SEVersion"]); // See StateEngine class for current values to test
    $this->assertSame('1', $VB["SEBuild"]);
});

// Emulate Controller actions
test('MultiSync works', function () {
    $Engine = StateEngine::Workflow(MultiSyncWorkflow::class);

    $appContext = $Engine->StartWorkflow('multi');
    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->S1->S2->S5->S6', $appContext->StatePath);
});
test('MultiSync multi stalls', function () {
    $Engine = StateEngine::Workflow(MultiSyncWorkflow::class);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('STALLED: No ready states! DispatchCycle: 2');

    $appContext = $Engine->StartWorkflow('multistall');
    $appContext = $Engine->RunWorkflow($appContext);
});
