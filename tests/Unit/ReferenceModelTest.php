<?php

use MarkusBiggus\StateEngine\Workflow\StateEngine;
use MarkusBiggus\StateEngine\Workflow\Examples\ReferenceWorkflow;

uses (DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts::class);

test('Engine refactored Workflow properly', function () {

    $Engine = StateEngine::Workflow(ReferenceWorkflow::class);

    // Get the model after Engine refactored it
    $Workflow = $Engine->GetWorkflow();

    $this->assertObjectHasProperty('TransitionMasks', $Workflow);
    $this->assertIsArray($Workflow->TransitionMasks);
    $this->assertNotEmpty($Workflow->TransitionMasks);
    $this->assertArrayHasKey('InitialT', $Workflow->TransitionMasks);

    $this->assertObjectHasProperty('MaskTransitions', $Workflow);
    $this->assertIsArray($Workflow->MaskTransitions);
    $this->assertNotEmpty($Workflow->MaskTransitions);
    $this->assertArrayHasKey(0, $Workflow->MaskTransitions);

    $this->assertObjectHasProperty('StateModel', $Workflow);
    $this->assertIsArray($Workflow->StateModel);
    $this->assertNotEmpty($Workflow->StateModel);

    $this->assertArrayHasKey('Workflow', $Workflow->StateModel, 'No Workflow name in model');
    $this->assertIsString($Workflow->StateModel['Workflow'], 'Workflow name not text');
    $this->assertSame('Reference', $Workflow->StateModel['Workflow'], 'Test Workflow name not "Reference"');

    $this->assertArrayHasKey('States', $Workflow->StateModel);
    $this->assertIsArray($Workflow->StateModel['States']);
    $this->assertNotEmpty($Workflow->StateModel['States']);
    $this->assertArrayHasKey('InitialS', $Workflow->StateModel['States']);

    $this->assertArrayHasKey('StateTransitions', $Workflow->StateModel);
    $this->assertIsArray($Workflow->StateModel['StateTransitions']);
    $this->assertNotEmpty($Workflow->StateModel['StateTransitions']);
    $this->assertArrayHasKey('InitialS', $Workflow->StateModel['StateTransitions']);
    $this->assertArraySubset(
        ['S3' =>
            [
            ['Transition' => 'T3_8', 'TargetStates' => ['S8']],
            ],
        ],
        $Workflow->StateModel['StateTransitions']
    );
    // Auto not set for Idle state
    $this->assertArraySubset(
        ['S4' =>
            [
            ['Transition' => 'T4_8', 'TargetStates' => ['S8']],
            ],
        ],
        $Workflow->StateModel['StateTransitions']
    );
    $this->assertArraySubset(
        ['S7' =>
            [
            ['Transition' => 'T7_8', 'TargetStates' => ['S8']],
            ],
        ],
        $Workflow->StateModel['StateTransitions']
    );
    // Auto set when it should be
    $this->assertArraySubset(
        ['S5' =>
            [
            ['Transition' => 'T2_SplitMerge', 'TargetStates' => ['S7']],
            "autoTransitionMask" => 128
            ],
        ],
        $Workflow->StateModel['StateTransitions']
    );
    // Auto not set when it shouldn't
    $this->assertArraySubset(
        ['SZ' =>
            [
            ['Transition' => 'TZ_Y_Z', 'TargetStates' => ['SY','SZ']],
            ],
        ],
        $Workflow->StateModel['StateTransitions']
    );

    $this->assertArrayHasKey('StartState', $Workflow->StateModel);
    $this->assertNotEmpty($Workflow->StateModel['StartState']);
    $this->assertIsString($Workflow->StateModel['StartState']);
    $this->assertSame('S1', $Workflow->StateModel['StartState']);

    $this->assertArrayHasKey('TerminalState', $Workflow->StateModel);
    $this->assertNotEmpty($Workflow->StateModel['TerminalState']);
    $this->assertIsInt($Workflow->StateModel['TerminalState']['Index']);
    $this->assertSame('S8', $Workflow->StateModel['TerminalState']['State']);
    $this->assertSame(9, $Workflow->StateModel['TerminalState']['Index']);

    // Whether used or not, these keys must exist in the model
    $this->assertArrayHasKey('Idle', $Workflow->StateModel);
    $this->assertIsArray($Workflow->StateModel['Idle']);
    $this->assertArrayHasKey('Splits', $Workflow->StateModel);
    $this->assertIsArray($Workflow->StateModel['Splits']);
    $this->assertArrayHasKey('Syncs', $Workflow->StateModel);
    $this->assertIsArray($Workflow->StateModel['Syncs']);
    $this->assertArrayHasKey('Forks', $Workflow->StateModel);
    $this->assertIsArray($Workflow->StateModel['Forks']);
    $this->assertArrayHasKey('Merges', $Workflow->StateModel);
    $this->assertIsArray($Workflow->StateModel['Merges']);

    // Check Idle
    $this->assertArraySubset(
        ['States' => 'S4,S7',
        'StatesMask' => 72,
        ],
        $Workflow->StateModel['Idle']
    );
    // Check Splits
//ddd( $Workflow->StateModel['Splits']);
    $this->assertArraySubset(
        [
        0=>    ['OriginState' => 'S2',
             'Transition' => ['T2_3_4'],
             'OriginMask' => 2,
             'TargetsMask' => 12,
             'TransitionMask' => 64,
            ],
        1=>    ['OriginState' => 'S2',
             'Transition' => ['T2_SplitMerge'],
             'OriginMask' => 2,
             'TargetsMask' => 1088,
             'TransitionMask' => 128,
            ],
        4=>    ['OriginState' => 'SZ',
             'Transition' => ['TZ_Y_Z'],
             'OriginMask' => 128,
             'TargetsMask' => 1152,
             'TransitionMask' => 32768,
            ],
        ],
        $Workflow->StateModel['Splits']
    );
 //   $this->assertArraySubset(
 //       [
 //       ],
 //       $Workflow->StateModel['Splits']
  //   );
//    ddd( $Workflow->StateModel['Syncs']);
    // Check Syncs
    $this->assertArraySubset(
        [
            ['TargetState' => 'S7',
             'Transitions' => ['T2_SplitMerge','T6_7'],
             'OriginsMask' => 50,
             'TargetMask' => 64,
             'TransitionsMask' => 8320,
            ],
            ['TargetState' => 'S8',
            'Transitions' => ['T1_8a','T1_8b'],
            'OriginsMask' => 1,
            'TargetMask' => 256,
    //        'OriginStates' => ['S3','S4'],
            'TransitionsMask' => 6,
            ],
            ['TargetState' => 'S8',
            'Transitions' => ['T3_8','T4_8'],
            'OriginsMask' => 12,
            'TargetMask' => 256,
    //        'OriginStates' => ['S3','S4'],
            'TransitionsMask' => 6144,
            ],
        ],
        $Workflow->StateModel['Syncs']
    );
    // Check Forks
    $this->assertArraySubset(
        [
            ['OriginState' => 'S1',
            'Transitions' => ['T1_8b','T1_X'],
            ],
            ['OriginState' => 'S2',
            'Transitions' => ['T2_SplitMerge','T2_Z'],
            ],
            ['OriginState' => 'S2',
            'Transitions' => ['T2_3','T2_4'],
            ],
        ],
        $Workflow->StateModel['Forks']
    );
    // Check Merges
    $this->assertArraySubset(
        [
            ['TargetState' => 'S7',
            'Transition' => ['T2_SplitMerge'],
            'OriginsMask' => 18,
     //       'OriginStates' => ['S2','S5'],
            'TargetMask' => 64,
            ],
        ],
        $Workflow->StateModel['Merges']
    );

    $this->assertIsArray($Workflow->StateHandlers);
    $this->assertArrayHasKey(3, $Workflow->StateHandlers);
    $this->assertSame(false, $Workflow->StateHandlers[3]['singleton']); // S3
    $this->assertSame(true, $Workflow->StateHandlers[4]['singleton']); // S4
    $this->assertSame(true, $Workflow->StateHandlers[6]['singleton']); // S6
    $this->assertSame(false, $Workflow->StateHandlers[8]['singleton']); // SZ
});
