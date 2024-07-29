<?php

use MarkusBiggus\StateEngine\Workflow\StateEngine;
use MarkusBiggus\StateEngine\Workflow\Examples\ReferenceWorkflow;

uses (DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts::class);

test('Reference WorkflowApp created properly', function () {

    $Engine = StateEngine::Workflow(ReferenceWorkflow::class);

    $appContext = $Engine->StartWorkflow('seq18a');

    $this->assertObjectHasProperty('StatePath', $appContext);
    $this->assertIsString($appContext->StatePath);
    $this->assertSame('I', $appContext->StatePath);

    $this->assertObjectHasProperty('TestSEQ', $appContext);
    $this->assertIsString($appContext->TestSEQ);

    $this->assertObjectHasProperty('TestSEQTransitions', $appContext);
    $this->assertIsArray($appContext->TestSEQTransitions);
    $this->assertArraySubset(
        ['seq18a' => ['T1_8a', 'T1_8b', 'T1_X']],
            $appContext->TestSEQTransitions['S1x']
        );

    $VB = $Engine->GetVersionBuild();
    $this->assertIsArray($VB);
    $this->assertSame('1.0', $VB["SEVersion"]); // See StateEngine class for current values to test
    $this->assertSame('1', $VB["SEBuild"]);
});
// Emulate Controller actions
test('Reference Workflow basic seq18a', function () {

    $Engine = StateEngine::Workflow(ReferenceWorkflow::class);

    $appContext = $Engine->StartWorkflow('seq18a');
    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->S1x->S1x->S1x->SX->S8', $appContext->StatePath);
});
test('Reference Workflow basic seq18b', function () {

    $Engine = StateEngine::Workflow(ReferenceWorkflow::class);

    $appContext = $Engine->StartWorkflow('seq18b');
    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->S1x->SX->S8', $appContext->StatePath);
});
test('Reference Workflow basic seq18c', function () {

    $Engine = StateEngine::Workflow(ReferenceWorkflow::class);

    $appContext = $Engine->StartWorkflow('seq18c');
    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->S1x->SX->S8', $appContext->StatePath);
});
test('Reference Workflow noAuto forknosync', function () {

    $this->withoutExceptionHandling();
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('STALLED: No ready states! DispatchCycle: 5 DispatchState: S4');

    $Engine = StateEngine::Workflow(ReferenceWorkflow::class);

    $appContext = $Engine->StartWorkflow('forknosync');
    $appContext = $Engine->RunWorkflow($appContext);
});
test('Reference Workflow invalid transition for state', function () {

    $this->withoutExceptionHandling();
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage("Invalid Transition for state: 'S2' ! Transition: T3_8");

    $Engine = StateEngine::Workflow(ReferenceWorkflow::class);

    $appContext = $Engine->StartWorkflow('dudstate');
    $appContext = $Engine->RunWorkflow($appContext);
});
test('Reference Workflow attempt Terminal transition', function () {

    $this->withoutExceptionHandling();
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('isTerminal - no transition allowed! Transition: T1_8b');

    $Engine = StateEngine::Workflow(ReferenceWorkflow::class);

    $appContext = $Engine->StartWorkflow('terminal');
    $appContext = $Engine->RunWorkflow($appContext);
});
test('Reference Workflow test sequence', function () {

    $this->withoutExceptionHandling();
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage("TestSEQ error: 'bung' not a test sequence for S1x !");

    $Engine = StateEngine::Workflow(ReferenceWorkflow::class);

    $appContext = $Engine->StartWorkflow('bung');
    $appContext = $Engine->RunWorkflow($appContext);
});
test('Reference Workflow invalid transition name', function () {

    $this->withoutExceptionHandling();
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage("ABORT: Invalid transition name: T1_8 from state S1");

    $Engine = StateEngine::Workflow(ReferenceWorkflow::class);

    $appContext = $Engine->StartWorkflow('invalid');
    $appContext = $Engine->RunWorkflow($appContext);
});
