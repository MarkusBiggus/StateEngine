<?php

namespace MarkusBiggus\StateEngine\Workflow\Examples;
class DynoFactory
{
    /**
     * Dynamic model definitions used for testing.
     * Doesn't require complete definition of Workflow, WorkflowApp & State classes
     * for each model.
     * Limited by fitting into a standard test model structure of 6 states with S6 being terminal.
     */
    static array $Models = [
        'DualIdle' => [
            'Workflow' => 'DualIdle',
            'StartState' => 'S1',
            'States' => [
                'S4' => [
                    'handler' => 'ExmplS4IdleState',
                ],
                'S5' => [
                    'handler' => 'ExmplS5IdleState',
                ],
            ],
            'Idle' => [
                'States' => ['S4','S5'],
            ],
            'TerminalState' => 'S6',
            'StateTransitions' => [
                'S1' => [
                    ['Transition' => 'T1_2_3', 'TargetStates' => ['S2','S3']],
                ],
                'S2' => [
                    ['Transition' => 'T2_Fail', 'TargetStates' => ['S4']],
                    ['Transition' => 'T2_3_6', 'TargetStates' => ['S6']],
                ],
                'S3' => [
                    ['Transition' => 'T3_Fail', 'TargetStates' => ['S5']],
                    ['Transition' => 'T2_3_6', 'TargetStates' => ['S6']],
                ],
                'S4' => [
                    ['Transition' => 'T_Retry', 'TargetStates' => ['S2']],
                ],
                'S5' => [
                    ['Transition' => 'T_Retry', 'TargetStates' => ['S3']],
                ],
            ],
            'Splits' => [
                    ['OriginState' => 'S1', 'Transition' => ['T1_2_3']],
            ],
            'Merges' => [
                    ['TargetState' => 'S6', 'Transition' => ['T2_3_6']]
            ],
        ],

        'IfElse' => [
            'Workflow' => 'IfElse',
            'StatePrefix' => 'Exmpl',
            'StartState' => 'S1',
            'Idle' => [
                'States' => '', //  no Idle states
            ],
            'TerminalState' => 'S4',
            'StateTransitions' => [
                'S1' => [
                    ['Transition' => 'T1_2', 'TargetStates' => ['S2']],
                    ['Transition' => 'T1_3', 'TargetStates' => ['S3']],
                    ['Transition' => 'T1_2_3', 'TargetStates' => ['S2','S3']],
                ],
                'S2' => [
                    ['Transition' => 'T2_3', 'TargetStates' => ['S3']],
                    ['Transition' => 'T2_4', 'TargetStates' => ['S4']],
                    ['Transition' => 'T2_3_4', 'TargetStates' => ['S4']],
                ],
                'S3' => [
                    ['Transition' => 'T3_2', 'TargetStates' => ['S2']],
                    ['Transition' => 'T3_4', 'TargetStates' => ['S4']],
                    ['Transition' => 'T2_3_4', 'TargetStates' => ['S4']],
                ],
            ],
            'Splits' => [
                    ['OriginState' => 'S1', 'Transition' => ['T1_2_3']],
            ],
            'Forks' => [
                    ['OriginState' => 'S1', 'Transitions' => ['T1_2','T1_3']],
            ],
            'Merges' => [
                    ['TargetState' => 'S4', 'Transition' => ['T2_3_4']],
            ],
        ],

        'MultiSplitSync' => [
            'Workflow' => 'MultiSplitSync',
            'TerminalState' => 'S6',
            'StateTransitions' => [
                'S1' => [
                    ['Transition' => 'T1_2_3_6', 'TargetStates' => ['S2','S3','S6']],
                    ['Transition' => 'T1_4_5_6', 'TargetStates' => ['S4','S5','S6']],
                ],
                'S2' => [
                ],
                'S3' => [
                ],
                'S4' => [
                ],
                'S5' => [
                ],
            ],
            'Splits' => [
                    ['OriginState' => 'S1', 'Transition' => ['T1_2_3_6']],
                    ['OriginState' => 'S1', 'Transition' => ['T1_4_5_6']],
            ],
            'Syncs' => [
                    ['TargetState' => 'S6', 'Transitions' => ['T1_2_3_6', 'T1_4_5_6']], // Sync: Dual Splits
            ],
        ],

        'DualMergeSync' => [
            'Workflow' => 'DualMergeSync',
            'TerminalState' => 'S6',
            'StateTransitions' => [
                'S1' => [
                    ['Transition' => 'T1_2_4', 'TargetStates' => ['S1','S2','S4']],
                    ['Transition' => 'T1_3', 'TargetStates' => ['S3']],
                    ['Transition' => 'T1_5', 'TargetStates' => ['S5']],
                ],
                'S2' => [
                    ['Transition' => 'T2_3_5_6', 'TargetStates' => ['S6']],
                ],
                'S3' => [
                    ['Transition' => 'T2_3_5_6', 'TargetStates' => ['S6']],
                ],
                'S4' => [
                    ['Transition' => 'T2_4_5_6', 'TargetStates' => ['S6']],
                ],
                'S5' => [
                    ['Transition' => 'T2_4_5_6', 'TargetStates' => ['S6']],
                ],
            ],
            'Splits' => [
                ['OriginState' => 'S1', 'Transition' => ['T1_2_4']], // Split into two Merges
            ],
            'Syncs' => [
                ['TargetState' => 'S6', 'Transitions' => ['T2_3_5_6', 'T2_4_5_6']], // Sync: Dual Merge
            ],
            'Merges' => [
                ['TargetState' => 'S6', 'Transition' => ['T2_3_5_6']],
                ['TargetState' => 'S6', 'Transition' => ['T2_4_5_6']],
            ],
        ],

        'MultiSplitMerge' => [
            'Workflow' => 'MultiSplitMerge',
            'TerminalState' => 'S6',
            'StateTransitions' => [
                'S1' => [
                    ['Transition' => 'T1_2', 'TargetStates' => ['S2']],
                    ['Transition' => 'T1_3', 'TargetStates' => ['S3']],
                ],
                'S2' => [
                    ['Transition' => 'T1_SplitMerge', 'TargetStates' => ['S4','S6']],
                ],
                'S3' => [
                    ['Transition' => 'T1_SplitMerge', 'TargetStates' => ['S5','S6']],
                ],
                'S4' => [
                ],
                'S5' => [
                ],
            ],
            'Splits' => [
                ['OriginState' => 'S2', 'Transition' => ['T1_SplitMerge']],
                ['OriginState' => 'S3', 'Transition' => ['T1_SplitMerge']],
            ],
            'Merges' => [
                ['TargetState' => 'S6', 'Transition' => ['T1_SplitMerge']], // Merge: Dual Split
            ],
        ],
    ];
    /**
     * Test sequences for each model.
     * Defeault name used by Dynoflow controller is 'testSEQ'
     */
    static array $TestSEQs = [
        /**
         * Sequences for each test
         *
         *    nofail : I->S1->S2->S3->S6
         * failretry : I->S1->S2->S3->S4->S5->S2->S3->S6
         * idlestop  : I->S1->S2->S3->S4->S5->S2->S3->S4->S5
         */
        'DualIdle' => [
            'S1' => [
                'testSEQ' => 'T1_2_3',
                'failretry' => 'T1_2_3',
                'idlestop' => 'T1_2_3',
            ],
            'S2' => [
                'testSEQ' => 'T2_3_6',
                'failretry' => ['T2_Fail', 'T2_3_6'],
                'idlestop' => 'T2_Fail',
            ],
            'S3' => [
                'testSEQ' => 'T2_3_6',
                'failretry' => ['T3_Fail','T3_Fail','T2_3_6'],
                'idlestop' => 'T3_Fail',
            ],
            'S4' => [
                'failretry' => ['', 'T_Retry'],
                'idlestop' => 0, // no transition
            ],
            'S5' => [
                'failretry' => 'T_Retry', // retry every time
                'idlestop' => '', // no transition
            ],
        ],
        /**
         * Sequences for each test
         *
         * forkxover    : I->S1->S2->S3->S2->S3->S4
         * splitxover   : I->S1->S2->S3->S2->S3->S4
         * forkconverge : I->S1->S2->S3->S4
         * xovermerge   : stalls
         * leadlag      : I->S1->S2->S3->S2->S4
         * mergefail    : stalls
         */
        'IfElse' => [
            'S1' => [
                'forkxover'    => 'T1_2, T1_3',
                'splitxover'   => 'T1_2_3',
                'forkconverge' => 'T1_2, T1_3',
                'xovermerge'   => 'T1_2_3',
                'leadlag'      => 'T1_2_3',
                'mergefail'    => 'T1_2, T1_3',
            ],
            'S2' => [
                'forkxover'    => ['T2_3','T2_4'], // index is visits to state
                'splitxover'   => ['T2_3','T2_4'],
                'forkconverge' => ['T2_4'],
                'xovermerge'   => ['T2_3','T2_3_4'], // only S2 emits Merge transition, it runs first
                'leadlag'      => ['T2_4','T2_4'],
                'mergefail'    => ['','',''], // opposite of xovermerge test, only S3 emits Merge transition
            ],
            'S3' => [
                'forkxover'    => ['T3_2','T3_4'],
                'splitxover'   => ['T3_2','T3_4'],
                'forkconverge' => ['T3_4'],
                'xovermerge'   => ['T3_2','','',''], // go back to S2 for Merge, S3 doesn't emit Merge transition, S3 runs twice alone before stalling
                'leadlag'      => ['T3_2'],
                'mergefail'    => ['T2_3_4'], // not emitted by S2 as required - ABORTED: No ready states!
            ],
        ],
        'MultiSplitSync' => [
            'S1' => [
                'testSEQ' => ['T1_2_3_6', 'T1_4_5_6'],
            ],
        ],
        'DualMergeSync' => [
            'S1' => [
                'testSEQ' => ['T1_2_4', 'T1_3, T1_5'], // execute S2 & S4 1st cycle & loop, S3 & S5 2nd cycle
            ],
        ],
        'MultiSplitMerge' => [
            'S1' => [
                'testSEQ' => 'T1_2, T1_3', // execute both S2 & S3 next cycle
            ],
            'S2' => [
                'testSEQ' => ['T1_SplitMerge'],
            ],
            'S3' => [
                'testSEQ' => ['T1_SplitMerge'],
            ],
        ],
    ];

    /**
     * Instantiate the Model factory
     *
     * @param String $model workflow name
     *
     * @return Array ['model' => array, 'testSEQ' => array]
     */
    public static function __callStatic($method, $args)
    {
        return call_user_func_array(
            array(get_called_class(), $method),
            $args
        );
    }
    protected static function new(string $model)
    {
        if (! isset(self::$Models[$model])) {
            throw new \RuntimeException("Error: '$model' is not a test model!");
        }
        return ['model' => self::$Models[$model], 'testSEQ' => self::$TestSEQs[$model]];
    }
}