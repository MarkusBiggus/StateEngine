<?php

use MarkusBiggus\StateEngine\Workflow\StateEngine;
use MarkusBiggus\StateEngine\Facades\DynoModel;
use MarkusBiggus\StateEngine\Workflow\Examples\DynoFactory;

//uses(Tests\CreatesApplication::class);
// Emulate Controller actions, but don't run  workflow
test('Workflow MultiSplitSync works', function ()
{
    $modelTests = DynoFactory::new('MultiSplitSync');
    $Workflow = DynoModel::new()->EditModel($modelTests['model']);
    $Engine = new StateEngine($Workflow);

    $appContext = $Engine->StartWorkflow('testSEQ');
    $testSEQ = $modelTests['testSEQ'];
    $appContext->EditTestSEQ($testSEQ);

    $this->assertObjectHasProperty('TestSEQ', $appContext);
    $this->assertIsString($appContext->TestSEQ);
    $this->assertSame('testSEQ', $appContext->TestSEQ);

    $this->assertObjectHasProperty('TestSEQTransitions', $appContext);
    $this->assertIsArray($appContext->TestSEQTransitions);
    $this->assertArrayHasKey('S1', $appContext->TestSEQTransitions, "Array TestSEQTransitions doesn't have 'S1' key");

    $appContext = $Engine->RunWorkflow($appContext);

    // two splits from same Origin Sync'd to common Target S6
    $this->assertSame('I->S1->S1->S2->S3->S4->S5->S6', $appContext->StatePath);
    $this->assertSame(4, $appContext->LastCycle);
});

// Emulate Controller actions
test('Workflow DualMergeSync works', function ()
{
    $modelTests = DynoFactory::new('DualMergeSync');
    $Workflow = DynoModel::new()->EditModel($modelTests['model']);

    $Engine = new StateEngine($Workflow);

    $appContext = $Engine->StartWorkflow('testSEQ');

    $testSEQ = $modelTests['testSEQ'];
    $appContext->EditTestSEQ($testSEQ);

    $this->assertObjectHasProperty('TestSEQ', $appContext);
    $this->assertIsString($appContext->TestSEQ);
    $this->assertSame('testSEQ', $appContext->TestSEQ);

    $this->assertObjectHasProperty('TestSEQTransitions', $appContext);
    $this->assertIsArray($appContext->TestSEQTransitions);
    $this->assertArrayHasKey('S1', $appContext->TestSEQTransitions, "Array TestSEQTransitions doesn't have 'S1' key");

    $appContext = $Engine->RunWorkflow($appContext);

    // split into two merges Sync'd to common Target S6
    $this->assertSame('I->S1->S1->S2->S4->S3->S5->S6', $appContext->StatePath);
    $this->assertSame(4, $appContext->LastCycle);
});

// Emulate Controller actions
test('Workflow MultiSplitMerge works', function () {

    $modelTests = DynoFactory::new('MultiSplitMerge');
    $Workflow = DynoModel::new()->EditModel($modelTests['model']);

    $Engine = new StateEngine($Workflow);

    $appContext = $Engine->StartWorkflow('testSEQ');

    $testSEQ = $modelTests['testSEQ'];
    $appContext->EditTestSEQ($testSEQ);

    $this->assertObjectHasProperty('TestSEQ', $appContext);
    $this->assertIsString($appContext->TestSEQ);
    $this->assertSame('testSEQ', $appContext->TestSEQ);

    $this->assertObjectHasProperty('TestSEQTransitions', $appContext);
    $this->assertIsArray($appContext->TestSEQTransitions);
    $this->assertArrayHasKey('S1', $appContext->TestSEQTransitions, "Array TestSEQTransitions doesn't have 'S1' key");

    $appContext = $Engine->RunWorkflow($appContext);

    //  split from two Origins Merged to Target S6
    $this->assertSame('I->S1->S2->S3->S4->S5->S6', $appContext->StatePath);
    $this->assertSame(4, $appContext->LastCycle);
});
// Emulate Controller actions
test('Workflow DualIdle works', function () {

    $modelTests = DynoFactory::new('DualIdle');
    $Workflow = DynoModel::new()->EditModel($modelTests['model']);

    $Engine = new StateEngine($Workflow);

    $appContext = $Engine->StartWorkflow('testSEQ');

    $testSEQ = $modelTests['testSEQ'];
    $appContext->EditTestSEQ($testSEQ);

    $this->assertObjectHasProperty('TestSEQ', $appContext);
    $this->assertIsString($appContext->TestSEQ);
    $this->assertSame('testSEQ', $appContext->TestSEQ);

    $this->assertObjectHasProperty('TestSEQTransitions', $appContext);
    $this->assertIsArray($appContext->TestSEQTransitions);
    $this->assertArrayHasKey('S1', $appContext->TestSEQTransitions, "Array TestSEQTransitions doesn't have 'S1' key");

    $appContext = $Engine->RunWorkflow($appContext);

    //  split from two Origins Merged to Target S6
    $this->assertSame('I->S1->S2->S3->S6', $appContext->StatePath);
    $this->assertSame(3, $appContext->LastCycle);
});
// Emulate Controller actions
test('Workflow DualIdle failretry: Idle states retry', function () {

    $modelTests = DynoFactory::new('DualIdle');
    $Workflow = DynoModel::new()->EditModel($modelTests['model']);

    $Engine = new StateEngine($Workflow);

    $appContext = $Engine->StartWorkflow('failretry');

    $testSEQ = $modelTests['testSEQ'];
    $appContext->EditTestSEQ($testSEQ);

    $this->assertObjectHasProperty('TestSEQ', $appContext);
    $this->assertIsString($appContext->TestSEQ);
    $this->assertSame('failretry', $appContext->TestSEQ);

    $this->assertObjectHasProperty('TestSEQTransitions', $appContext);
    $this->assertIsArray($appContext->TestSEQTransitions);
    $this->assertArrayHasKey('S1', $appContext->TestSEQTransitions, "Array TestSEQTransitions doesn't have 'S1' key");

    $appContext = $Engine->RunWorkflow($appContext);

    // Idle state S4 transition on 2nd pass
    $this->assertSame('I->S1->S2->S3->S4->S5->S4->S3->S5->S2->S3->S6', $appContext->StatePath);
    $this->assertSame(7, $appContext->LastCycle);
});

test('IfElse forkxover', function ()
{
    $modelTests = DynoFactory::new('IfElse');
    $Workflow = DynoModel::new()->EditModel($modelTests['model']);

    $Engine = new StateEngine($Workflow);

    $appContext = $Engine->StartWorkflow('forkxover');

    $testSEQ = $modelTests['testSEQ'];
    $appContext->EditTestSEQ($testSEQ);

    $this->assertObjectHasProperty('TestSEQ', $appContext);
    $this->assertIsString($appContext->TestSEQ);
    $this->assertSame('forkxover', $appContext->TestSEQ);

    $this->assertObjectHasProperty('StatePath', $appContext);
    $this->assertIsString($appContext->StatePath);
    $this->assertSame('I', $appContext->StatePath);

    $this->assertObjectHasProperty('TestSEQTransitions', $appContext);
    $this->assertIsArray($appContext->TestSEQTransitions);
    $this->assertArrayHasKey('S1', $appContext->TestSEQTransitions, "Array TestSEQTransitions doesn't have 'S1' key");

    $VB = $Engine->GetVersionBuild();
    $this->assertIsArray($VB);
    $this->assertSame('1.0', $VB["SEVersion"]); // See StateEngine class for current values to test
    $this->assertSame('1', $VB["SEBuild"]);

    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->S1->S2->S3->S2->S3->S4', $appContext->StatePath);
});
test('IfElse splitxover', function ()
{
    $modelTests = DynoFactory::new('IfElse');
    $Workflow = DynoModel::new()->EditModel($modelTests['model']);

    $Engine = new StateEngine($Workflow);

    $appContext = $Engine->StartWorkflow('splitxover');

    $testSEQ = $modelTests['testSEQ'];
    $appContext->EditTestSEQ($testSEQ);

    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->S1->S2->S3->S2->S3->S4', $appContext->StatePath);
});
test('IfElse forkconverge', function ()
{
    $modelTests = DynoFactory::new('IfElse');
    $Workflow = DynoModel::new()->EditModel($modelTests['model']);

    $Engine = new StateEngine($Workflow);

    $appContext = $Engine->StartWorkflow('forkconverge');

    $testSEQ = $modelTests['testSEQ'];
    $appContext->EditTestSEQ($testSEQ);

    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->S1->S2->S3->S4', $appContext->StatePath);
});
test('IfElse xovermerge', function ()
{
    $modelTests = DynoFactory::new('IfElse');
    $Workflow = DynoModel::new()->EditModel($modelTests['model']);

    $Engine = new StateEngine($Workflow);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('STALLED: Same state transitions! DispatchCycle: 5');

    $appContext = $Engine->StartWorkflow('xovermerge');

    $testSEQ = $modelTests['testSEQ'];
    $appContext->EditTestSEQ($testSEQ);

    $appContext = $Engine->RunWorkflow($appContext);
});
test('IfElse leadlag', function ()
{
    $modelTests = DynoFactory::new('IfElse');
    $Workflow = DynoModel::new()->EditModel($modelTests['model']);

    $Engine = new StateEngine($Workflow);

    $appContext = $Engine->StartWorkflow('leadlag');

    $testSEQ = $modelTests['testSEQ'];
    $appContext->EditTestSEQ($testSEQ);

    $appContext = $Engine->RunWorkflow($appContext);
    $this->assertSame('I->S1->S2->S3->S2->S4', $appContext->StatePath);
});
test('IfElse mergefail', function ()
{
    $modelTests = DynoFactory::new('IfElse');
    $Workflow = DynoModel::new()->EditModel($modelTests['model']);

    $Engine = new StateEngine($Workflow);
    $appContext = $Engine->StartWorkflow('mergefail');

    $testSEQ = $modelTests['testSEQ'];
    $appContext->EditTestSEQ($testSEQ);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('STALLED: Same state transitions! DispatchCycle: 4');

    $appContext = $Engine->RunWorkflow($appContext);
});
