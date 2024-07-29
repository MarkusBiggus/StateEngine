<?php

use MarkusBiggus\StateEngine\Workflow\StateEngine;
use MarkusBiggus\StateEngine\Workflow\Examples\MergeComboWorkflow;

//uses(Tests\CreatesApplication::class);

// Emulate Controller actions, but don't run  workflow
test('MergeCombo WorkflowApp created properly', function () {
//    $this->createApplication(); // dunno why Pest isn't doing this!!!

    $Engine = StateEngine::Workflow(MergeComboWorkflow::class);

    $appContext = $Engine->StartWorkflow('merge');

    $this->assertObjectHasProperty('TestSEQ', $appContext);
    $this->assertIsString($appContext->TestSEQ);
    $this->assertSame('merge', $appContext->TestSEQ);

    $this->assertObjectHasProperty('StatePath', $appContext);
    $this->assertIsString($appContext->StatePath);
    $this->assertSame('I', $appContext->StatePath);

    $this->assertObjectHasProperty('TestSEQTransitions', $appContext);
    $this->assertIsArray($appContext->TestSEQTransitions);
    $this->assertArrayHasKey('S1', $appContext->TestSEQTransitions, "Array TestSEQTransitions missing 'S1' key");
    $this->assertSame('T1_2_3_4_5', $appContext->TestSEQTransitions['S1']['merge']);

    $VB = $Engine->GetVersionBuild();
    $this->assertIsArray($VB);
    $this->assertSame('1.0', $VB["SEVersion"]); // See StateEngine class for current values to test
    $this->assertSame('1', $VB["SEBuild"]);
});

// Emulate Controller actions
test('MergeCombo merge works', function () {
    $Engine = StateEngine::Workflow(MergeComboWorkflow::class);

    $appContext = $Engine->StartWorkflow('merge');
    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->S1->S2->S3->S4->S5->S3->S3a->S5->S3->S6', $appContext->StatePath);
});
test('MergeCombo merge0 works', function () {
    $Engine = StateEngine::Workflow(MergeComboWorkflow::class);

    $appContext = $Engine->StartWorkflow('merge0');
    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->S1->S2->S3->S4->S5->S3->S4->S3->S3a->S3->S3->S6', $appContext->StatePath);
});
test('MergeCombo merge1', function () {
    $Engine = StateEngine::Workflow(MergeComboWorkflow::class);

    $appContext = $Engine->StartWorkflow('merge1');
    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->S1->S2->S3->S4->S5->S3->S3a->S4->S3a->S6', $appContext->StatePath);
});
