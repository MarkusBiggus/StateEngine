<?php

use MarkusBiggus\StateEngine\Workflow\StateEngine;
use MarkusBiggus\StateEngine\Workflow\Examples\PipelineWorkflow;

// Emulate Controller actions, but don't run  workflow
test('Pipeline WorkflowApp created properly', function () {
    $Engine = StateEngine::Workflow(PipelineWorkflow::class);

    $appContext = $Engine->StartWorkflow('pipe1');

    $this->assertObjectHasProperty('TestSEQ', $appContext);
    $this->assertIsString($appContext->TestSEQ);
    $this->assertSame('pipe1', $appContext->TestSEQ);

    $this->assertObjectHasProperty('StatePath', $appContext);
    $this->assertIsString($appContext->StatePath);
    $this->assertSame('I', $appContext->StatePath);

    $this->assertObjectHasProperty('TestSEQTransitions', $appContext);
    $this->assertIsArray($appContext->TestSEQTransitions);
    $this->assertArrayHasKey('P1', $appContext->TestSEQTransitions, "Array TestSEQTransitions missing 'P1' key");

    $VB = $Engine->GetVersionBuild();
    $this->assertIsArray($VB);
    $this->assertSame('1.0', $VB["SEVersion"]); // See StateEngine class for current values to test
    $this->assertSame('1', $VB["SEBuild"]);
});

// Emulate Controller actions
test('Pipeline split with sync', function () {
    $Engine = StateEngine::Workflow(PipelineWorkflow::class);

    $appContext = $Engine->StartWorkflow('pipe1');
    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->P1->P2->P3->P4->P5->P6->P7(Terminal)', $appContext->StatePath);
});
test('Pipeline split no sync', function () {
    $Engine = StateEngine::Workflow(PipelineWorkflow::class);

    $appContext = $Engine->StartWorkflow('pipe2');

    $Workflow = $Engine->GetWorkflow(); // Get the model after Engine refactored it
    // Model test confirms this entry was present
    $this->assertNotContains(['TargetState' => 'P6',
                              'Transitions' => ['T5_6', 'T4_6'],
                              'TargetMask' => 32,
                              'OriginStates' => ['P5','P4'],
                              'OriginsMask' => 24,
                              'TransitionsMask' => 24
                            ], $Workflow->StateModel["Syncs"], "[Syncs] still has TargetState 'P6'") ;
    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->P1->P2->P3->P4->P5->P6->P6->P7(Terminal)', $appContext->StatePath);
});
test('Pipeline split P4 path ends', function () {
    // $this->withoutExceptionHandling();
    // $this->expectException(\RuntimeException::class);
    // $this->expectExceptionMessage('ABORTED: No ready states!');

    $Engine = StateEngine::Workflow(PipelineWorkflow::class);

    $appContext = $Engine->StartWorkflow('pipe3');

    $Workflow = $Engine->GetWorkflow(); // Get the model after Engine refactored it
    // Model test confirms this entry was present

    $this->assertNotContains(
        ['P4' =>
            [
            ['Transition' => 'T4_6', 'TargetStates' => ['P6']],
            "autoTransitionMask" => 8
            ],
        ],
        $Workflow->StateModel['StateTransitions'],
        "[StateTransitions] still has Origin 'P4'"
    ) ;

    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->P1->P2->P3->P4->P5->P6->P7(Terminal)', $appContext->StatePath);
});
