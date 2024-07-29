<?php

use MarkusBiggus\StateEngine\Workflow\StateEngine;
use MarkusBiggus\StateEngine\Workflow\Examples\SplitComboWorkflow;

//uses(Tests\CreatesApplication::class);

// Emulate Controller actions, but don't run  workflow
test('SplitCombo WorkflowApp created properly', function () {
//    $this->createApplication(); // dunno why Pest isn't doing this!!!

    $Engine = StateEngine::Workflow(SplitComboWorkflow::class);

    $appContext = $Engine->StartWorkflow('split');

    $this->assertObjectHasProperty('TestSEQ', $appContext);
    $this->assertIsString($appContext->TestSEQ);
    $this->assertSame('split', $appContext->TestSEQ);

    $this->assertObjectHasProperty('StatePath', $appContext);
    $this->assertIsString($appContext->StatePath);
    $this->assertSame('I', $appContext->StatePath);

    $this->assertObjectHasProperty('TestSEQTransitions', $appContext);
    $this->assertIsArray($appContext->TestSEQTransitions);
    $this->assertArrayHasKey('S1', $appContext->TestSEQTransitions, "Array TestSEQTransitions missing 'S1' key");
    $this->assertSame('T1_2_3', $appContext->TestSEQTransitions['S1']['split']);

    $VB = $Engine->GetVersionBuild();
    $this->assertIsArray($VB);
    $this->assertSame('1.0', $VB["SEVersion"]); // See StateEngine class for current values to test
    $this->assertSame('1', $VB["SEBuild"]);
});

// Emulate Controller actions
test('SplitCombo Merge', function () {
    $Engine = StateEngine::Workflow(SplitComboWorkflow::class);

    $appContext = $Engine->StartWorkflow('split');
    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->S1->S2->S3->S4', $appContext->StatePath);
});
