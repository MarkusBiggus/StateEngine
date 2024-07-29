<?php

use MarkusBiggus\StateEngine\Workflow\StateEngine;
use MarkusBiggus\StateEngine\Workflow\Examples\PipelineWorkflow;

uses (DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts::class);

test('Engine refactored Pipeline Workflow properly', function () {

    $Engine = StateEngine::Workflow(PipelineWorkflow::class);

    $Workflow = $Engine->GetWorkflow(); // Get the model after Engine refactored it

    $this->assertObjectHasProperty('StateModel', $Workflow);
    $this->assertIsArray($Workflow->StateModel);
    $this->assertNotEmpty($Workflow->StateModel);

    $this->assertArrayHasKey('Workflow', $Workflow->StateModel, 'No Workflow name in model');
    $this->assertIsString($Workflow->StateModel["Workflow"], 'Workflow name not text');
    $this->assertSame('Pipeline', $Workflow->StateModel["Workflow"], 'Workflow name not "Pipeline"');

    $this->assertObjectHasProperty('TransitionMasks', $Workflow);
    $this->assertIsArray($Workflow->TransitionMasks);
    $this->assertNotEmpty($Workflow->TransitionMasks);
    $this->assertArrayHasKey('InitialT', $Workflow->TransitionMasks); // implied initial state

    $this->assertObjectHasProperty('MaskTransitions', $Workflow);
    $this->assertIsArray($Workflow->MaskTransitions);
    $this->assertNotEmpty($Workflow->MaskTransitions);
    $this->assertArrayHasKey(0, $Workflow->MaskTransitions); // implied transition

    $this->assertArrayHasKey('States', $Workflow->StateModel);
    $this->assertIsArray($Workflow->StateModel["States"]);
    $this->assertNotEmpty($Workflow->StateModel["States"]);

    $this->assertArrayHasKey('StateTransitions', $Workflow->StateModel);
    $this->assertIsArray($Workflow->StateModel["StateTransitions"]);
    $this->assertNotEmpty($Workflow->StateModel["StateTransitions"]);
    $this->assertArrayHasKey('InitialS', $Workflow->StateModel["StateTransitions"]);
    $this->assertArraySubset(
                            ['P4' =>
                              [
                                ['Transition' => 'T4_6',
                                'TargetStates' => ['P6']
                                ],
                                "autoTransitionMask" => 4
                              ],
                            ],
        $Workflow->StateModel['StateTransitions'],
        "[StateTransitions] hasn't Origin 'P4'"
    ) ;

    $this->assertArrayHasKey('StartState', $Workflow->StateModel);
    $this->assertNotEmpty($Workflow->StateModel["StartState"]);
    $this->assertIsString($Workflow->StateModel["StartState"]);
    $this->assertSame('P1', $Workflow->StateModel["StartState"]);

    $this->assertArrayHasKey('TerminalState', $Workflow->StateModel);
    $this->assertNotEmpty($Workflow->StateModel["TerminalState"]);
    $this->assertIsInt($Workflow->StateModel["TerminalState"]['Index']);
    $this->assertSame(7, $Workflow->StateModel["TerminalState"]['Index']);

    // Whether used or not, these keys must exist in the model
    $this->assertArrayHasKey('Idle', $Workflow->StateModel);
    $this->assertIsArray($Workflow->StateModel["Idle"]);

    // This model has inferred Split - confirm it is corect
    $this->assertArrayHasKey('Splits', $Workflow->StateModel);
    $this->assertIsArray($Workflow->StateModel["Splits"]);
    $this->assertArraySubset(
            [
                ['OriginState' => 'P2',
                 'Transition' => ['T2_3_4'],
                 'TargetsMask' => 12,
                 'TransitionMask' => 2
                ],
            ],
            $Workflow->StateModel["Splits"], "[Splits] doesn't have OriginState 'P2'") ;

    $this->assertArrayHasKey('Syncs', $Workflow->StateModel);
    $this->assertIsArray($Workflow->StateModel["Syncs"]);
    $this->assertArraySubset(
            [
                ['TargetState' => 'P6',
                 'Transitions' => ['T5_6', 'T4_6'],
                 'OriginsMask' => 24,
                 'TargetMask' => 32,
        //         'OriginStates' => ['P5','P4'],
                 'TransitionsMask' => 12
                ]
            ],
            $Workflow->StateModel["Syncs"], "[Syncs] doesn't have TargetState 'P6'") ;

    $this->assertArrayHasKey('Forks', $Workflow->StateModel);
    $this->assertIsArray($Workflow->StateModel["Forks"]);

    $this->assertArrayHasKey('Merges', $Workflow->StateModel);
    $this->assertIsArray($Workflow->StateModel["Merges"]);
});
