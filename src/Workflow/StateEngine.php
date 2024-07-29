<?php

namespace MarkusBiggus\StateEngine\Workflow;

/**
 * MIT License
 *
 * Copyright (c) 2024 Mark Charles
 */

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

use MarkusBiggus\StateEngine\Contracts\WorkflowAppContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowLoggerContract;
use MarkusBiggus\StateEngine\Contracts\WorkflowContract;

/**
 * masks are Big Endian, fyi
 */
class StateEngine
{
    /**
     * EngineDebug
     * echo key details to trace execution
     *
     * @const Bool
     */
    private const EngineDebug = false; // true; //

    /**
     * MaskLimit
     * Maximum number of bits per mask for this implementation
     * (limited by number of bits Int type has)
     *
     * @const Int
     */
    private const MaskLimit = 62;

    /**
     * SEVersion
     * Engine version Major.Minor, previous builds are compatible with same Minor version
     *
     * @const String
     */
    private const SEVersion = '1.0';

    /**
     * SEBuild
     * Engine Build increment every release to production - a build may not be be a new version
     *
     * @const String
     */
    private const SEBuild = '1';

    /**
     * ValidModel
     * true when Model has been refactored and validated,
     * Or model has been restored from disk and version
     * numbers match current model.
     *
     * @var Bool
     */
    private bool $ValidModel = false;

    /**
     * StateMAX
     * Highest State mask bit used
     *
     * @var Int
     */
    private int $StateMAX = 0;

    /**
     * Logger (Optional)
     *
     * Workflow logger required to suspend/resume workflow between transitions
     * and Hand offs to other agent to resume
     *
     * @var WorkflowLogger
     */
    private $Logger;

    /**
     * StallCycles
     *
     * How many dispatch cycles are allowed with no transition or executed states,
     * before the workflow is considered stalled and further dispatch cycles futile.
     * (same states and transitions emitted always stalls after two cycles)
     * When specfied, 'Parameters' => ['StallCycles' => 2] is recommended to be single digit.
     * Actual cycles before stall is StallCycles+1 due to loop logic. Supplied parameter is adjusted.
     *
     * @var Int
     */
    private int $StallCycles = 1; // actually 2 cycles before stall

    /**
     * StateHandlers
     * metadata
     * Indexed by State Index.
     * Pivots States to Index with mask & handler functions.
     * When multiple states are executable, they run in index order.
     * Terminal state handler is always last to run in its own cycle.
     *
     * @var Array
     */
    private array $StateHandlers = [];

    /**
     * StopEngineStates
     * metadata
     * current model Idle states & Terminal state mask.
     * Engine will be in a subset of these states when it stops properly.
     * Other states set in WorkflowStateMask when Engine stops will cause an exception.
     *
     * @var Int
     */
    private int $StopEngineStates = 0;

    /**
     * DispatchMaxCount
     * metadata
     * To end infinite loops, set an upper limit to number of $DispatchCycle.
     * DispatchMaxCount - number of transitions in total multiplied by MaxTransitionFactor
     * (default 0 = unlimited)
     *
     * @var Int
     */
    private int $DispatchMaxCount = 0;

    /**
     * MaskTransitions
     * metadata
     * reverse index StateModel TransitionsMasks keyed by TransitionMask, OriginMask
     *   map all transitons from agregated Origins to their Targets.
     *
     * @var Array
     */
    private array $MaskTransitions = [];

    /**
     * MaskStates
     * metadata
     * reverse index StateModel States keyed by OriginState bitmask.
     *
     * @var Array
     */
    private array $MaskStates = [];

    /**
     * ForkStates
     * Remember progress between Dispatch Cycles
     *
     * Fork Origin state transitions emitted.
     * Keyed by OriginState mask with TransitionsMask, records current progress of transitions and states signalled so far.
     * When All transitions have been emitted, ForkTransitionsMask === TransitionsMask,
     * the TargetsMask is folded into EngineReadyStates and the OrginState cleared from EngineStateMask.
     * scanInProgress may put OriginState back in EngineReadyStates when there are other special transitions.
     * format: [OrginMask => [ForkTransitionsMask => ['TransitionsMask' => int, 'TargetsMask' => int]]
     *
     * @var Array
     */
    private array $ForkStates = [];

    /**
     * SyncStates
     * Remember progress between Dispatch Cycles
     *
     * Sync Origin states transitions emitted
     * Keyed by TargetMask & Transitions of the Sync. (allows multiple Syncs to same TargetState)
     * Current progress of Transitions and OriginStates signalled so far.
     * When All transitions have been emitted, the TargetState Mask is folded into EngineReadyStates
     * OrginStates are removed from EngineStateMask as they emit their transition.
     * scanInProgress may put OriginState back in EngineReadyStates when there are other special transitions
     * in progress.
     * format: [TargetMask => [SyncTransitionsMask => ProgressTransitionsMask]]
     *
     * @var Array
     */
    private array $SyncStates = [];

    /**
     * MergeStates
     * Remember progress between Dispatch Cycles
     *
     * Merge Origin states, same transition emitted by each Origin.
     * Keyed by TransitionMask with current progress of Origin states signalled so far.
     * When All OriginStates have emitted the transition, TargetMask is folded into EngineReadyStates
     * OrginStates are removed from EngineStateMask as they emit their transition.
     * scanInProgress may put OriginState back in EngineReadyStates when there are other special transitions
     * in progress.
     * format: [TargetMask => [MergeTransitionMask => OriginsMask]]
     *
     * @var Array
     */
    private array $MergeStates = [];

    /**
     * WorkflowStateMask
     *
     * Represents the State of the Workflow overall.
     * Workflow state is static during a dispatch cycle, it represents
     * the Workflow state during the entire dispatch cycle.
     * Transitions add new states to EngineReadyStates.
     * Start of Dispatch Cycle: States that will run this cycle from EngineReadyStates plus
     * Idle states from previous cycles.
     * When Engine stops properly, Workflow state is Terminal state or only Idles state(s).
     *
     * @var Int
     */
    private int $WorkflowStateMask = 0;

    /**
     * EngineStateMask
     *
     * Current Engine States used by Dispatcher each cycle.
     * EngineState is set at the start of each dispatch cycle to current EngineStateMask leftovers
     *  and new states from EngineReadyStates.
     * WorkflowStateMask is a snapshot of EngineStateMask for the remainder of the dispatch cycle.
     *
     * Each State executed is cleared as they emit a transition. Fork & Sync require multiple states to do so,
     *  possibly over mutiple dispatch cycles.
     * No new States are set during a dispatch cycles, they are accumulated in EngineReadyStates.
     *
     * @var Int
     */
    private int $EngineStateMask = 0;

    /**
     * EngineExecutedStates
     *
     * Executed states mask from this dispatch cycle.
     * Set as states are executed by the dispatcher.
     * Used by InProgress evaluation to rerun a state from
     * the current DispatchCycle.
     *
     * @var Int
     */
    private int $EngineExecutedStates = 0;

    /**
     * EngineReadyStates
     *
     * The set of States ready to execute next cycle.
     * Working Mask for transitions during current Dispatch cycle.
     * End of Dispatch Cycle: any remaining States will also run next dispatch cycle.
     *
     * @var Int
     */
    private int $EngineReadyStates = 0;

    /**
     * DeferTerminalState
     * When Terminal set is set with other states, it is deferred to next cycle
     * Will keep being deferred until Terminal state runs alone in the last cycle.
     *
     * @var Int
     */
    private int $DeferTerminalState = 0;

    /**
     * DispatchStatesMask
     *
     * State mask for new states being set during the current Dispatch cycle - States to run next cycle.
     * Current Dispatch Status is what states will execute next dispatch cycle.
     *
     * @var Int
     */
    private int $DispatchStateMask = 0;

    /**
     * DispatchState
     * Name of State being dispatched used as OriginState for transitions this DispatchCycle
     *
     * @var String
     */
    private string $DispatchState;

    /**
     * DispatchCycle
     *
     * State handlers run per cycle (must not transition into a cycle already set!)
     *
     * @var Int
     */
    private int $DispatchCycle = 0;

    /**
     * DispatchLastTransitions
     * History of State Transitions (bitmask) for each Dispatch Cycle
     * Index is $DispatchCycle
     *  Each Entry is array indexed by OriginState bitmask of emitted transition(s)
     * Last StallCycles+1 are kept - older purged each dispatch cycle
     * format: [DispatchCycle => [OriginMask => TransitionsMask]]
     *
     * @var Array
     */
    private array $DispatchLastTransitions = [];

    /**
     * readyCount
     *
     * How many states will be executed next dispatch cycle
     * set by makeExecReady()
     *
     * @var Int
     */
    private int $readyCount = 0;

    /**
     * execCount
     * Count States executed each Dispatch Cycle
     *
     * @var Int
     */
    private int $execCount = -1;

    /**
     * ResumedStateMask
     * resumed states (mask) from WFInstance
     * (only Logged Workflow can be resumed when paused in Idle states)
     *
     * @var Int
     */
    private int $ResumedStateMask = 0;

    /**
     * ResumedTransitions
     * Transitions that lead to states in ResumedStateMask
     * resumed Transitions (mask) from WFInstance is last DispatchLastTransitions
     * from Workflow Engine when StateSuspend() was called
     *
     * @var Int
     */
    private int $ResumedTransitions = 0;

    /**
     * Workflow
     * Usually called from a controller, last element of namespace is replaced with Workflow folder name
     * Instantiate the Workflow model and pass name to this method
     * to make the Engine to run it.
     *
     * @param String modelClass of Workflow
     * @ param String folder - append to caller namespace for subfolder location of Workflow - no leading or trailing slashes
     *
     * @return Self Engine with Workflow mode instantiated
     *
     */
    public static function Workflow(string $modelClass): self
    {
        if (class_exists($modelClass)) {
            $Workflow = new $modelClass();
        } else {
            throw new \RuntimeException("Invalid workflow! No such class: $modelClass");
        }
        if (self::EngineDebug) {
            echo "<strong>Workflow:</strong> $modelClass <br/>";
        }
        return App::make(self::class)($Workflow);
    }

    /**
     * __construct
     *
     * @param WorkflowContract Workflow model to run
     *
     * @return self $this StateEngine
     */
    public function __construct(private WorkflowContract $Workflow)
    {
        if ($this->CacheModel()) {
            $Workflow->SetEngine($this);
            return; //restore worked
        }

        $Workflow->SetEngine($this);
        /**
         * Validation:
         *
         * All states must be a target state of some transition.
         * No transitions from $StateModel['TerminalState'] (not OriginState)
         * StartState defaults to first of StateTransitions.
         * TerminaState must be declared.
         * Hints for Idle, Fork, Split, Sync & Merge are all optional.
         */
        if (! Arr::exists($this->Workflow->StateModel, 'Workflow')) {
            throw new \RuntimeException("Model contains no [Workflow]");
        }
        if (! Arr::exists($this->Workflow->StateModel, 'StatePrefix')) {
            $this->Workflow->StateModel['StatePrefix'] = $this->Workflow->StateModel['Workflow'];
        }
        if (! isset($this->Workflow->StateModel['StatePrefix'])) {
            $this->Workflow->StateModel['StatePrefix'] = $this->Workflow->StateModel['Workflow'];
        }
        if (! Arr::exists($this->Workflow->StateModel, 'StateTransitions')) {
            throw new \RuntimeException("Workflow contains no [StateTransitions]");
        }
        if (! Arr::exists($this->Workflow->StateModel, 'StartState')) {
            $this->Workflow->StateModel['StartState'] = array_key_first($this->Workflow->StateModel['StateTransitions']);
        }
        if (! Arr::exists($this->Workflow->StateModel, 'TerminalState')) {
            throw new \RuntimeException("Workflow contains no [TerminalState]");
        }

        // Idle states are optional
        if (! Arr::exists($this->Workflow->StateModel, 'Idle')) {
            $this->Workflow->StateModel['Idle']['States'] = '';
        }

        // Advanced Transitions are optional - ensure empty ones exist
        if (! Arr::exists($this->Workflow->StateModel, 'Splits')) {
            $this->Workflow->StateModel['Splits'] = [];
        }
        if (! Arr::exists($this->Workflow->StateModel, 'Forks')) {
            $this->Workflow->StateModel['Forks'] = [];
        }
        if (! Arr::exists($this->Workflow->StateModel, 'Syncs')) {
            $this->Workflow->StateModel['Syncs'] = [];
        }
        if (! Arr::exists($this->Workflow->StateModel, 'Merges')) {
            $this->Workflow->StateModel['Merges'] = [];
        }

        /**
         * Quick validation - unique special transitions
         */
        $checkTransitions = Arr::flatten(Arr::pluck($this->Workflow->StateModel['Syncs'], 'Transitions'));
        if (count($checkTransitions) != count(array_flip($checkTransitions))) { // dup transition?
            throw new \RuntimeException('Workflow Sync Transitions are not unique! Found: '. implode(', ', $checkTransitions));
        }
        $checkTransitions = Arr::flatten(Arr::pluck($this->Workflow->StateModel['Forks'], 'Transitions'));
        if (count($checkTransitions) != count(array_flip($checkTransitions))) { // dup transition?
            throw new \RuntimeException('Workflow Fork Transitions are not unique! Found: '. implode(', ', $checkTransitions));
        }

        /**
         * Internal:
         * InitialState is used to kickstart the Engine, it is not required in the Workflow model.
         * Add required InitialState transition when not present.
         * Absent InitialState suggests StateTransitions also added.
         */
        if (! Arr::exists($this->Workflow->StateModel, 'States')) {
            $this->Workflow->StateModel['States'] = [];
        }
        /**
         * Refactor TerminalState string to StateModel['States'] attributes
         */
        if (is_string($this->Workflow->StateModel['TerminalState'])) {
            $TerminalState = $this->Workflow->StateModel['TerminalState'];
            if (isset($this->Workflow->StateModel['StateTransitions'][$TerminalState])
            && count($this->Workflow->StateModel['StateTransitions'][$TerminalState])) {
                throw new \RuntimeException("Workflow [TerminalState] $TerminalState must not have Transition: ". Arr::join(Arr::pluck($this->Workflow->StateModel['StateTransitions'][$TerminalState], 'Transition'), ', '));
            }
        }
        /**
         * Extract all StateTransitions from StateModel to reindex
         * A Transition maybe reused in multiple places in the model between unrelated pairs of states
         * Record any undeclared Origin or Target states found in transitions.
         */
        $targetStates['InitialS'] = 'InitialS'; // fake the virtual state
        $idx = 0;
        foreach ($this->Workflow->StateModel['StateTransitions'] as $state => $transitions) {
            if (! Arr::exists($this->Workflow->StateModel['States'], $state)) {
                $this->Workflow->StateModel['States'][$state] = []; // populate if needed
            }
            foreach ($transitions as $transition) {
                if (! isset($Transitions[$transition['Transition']]['Mask'])) {
                    $Transitions[$transition['Transition']]['Mask'] = (1 << $idx++); // dodgey logic!
                }
                foreach ($transition['TargetStates'] as $target) {
                    $targetStates[$target] = [];
                }
            }
        }
        $undeclaredStates = array_diff_key($targetStates, $this->Workflow->StateModel['States']);
        $this->Workflow->StateModel['States'] = array_merge($this->Workflow->StateModel['States'],$undeclaredStates);
        // Add Terminal State - has no transitions, so not in StateTransitions (aborted already, if so)
        if (isset($TerminalState)) {
            // All optional - keep what is specified
            $attributes = $this->Workflow->StateModel['States'][$TerminalState] ?? [];
            $this->Workflow->StateModel['States'][$TerminalState] = $attributes; // fixed later
        } else {
            throw new \RuntimeException('Workflow [TerminalState] must be string State name. Found: '.var_export($this->Workflow->StateModel['TerminalState'], true));
        }
        if ($idx > self::MaskLimit) {
            throw new \RuntimeException("Workflow contains too many transitions! $idx exceeds limit of ".self::MaskLimit);
        }
        /**
         * Validation:
         * Not possible to (easily) map transitions to confirm they will work properly
         */

        // Reindex States table by Index number
        unset($this->Workflow->StateModel['States']['InitialS']); // just incase
        $idx = 0;
        foreach ($this->Workflow->StateModel['States'] as $state => $attributes) {
            $stateMask = (1 << $idx++);
            $handler = $attributes['handler'] ?? $this->Workflow->StateModel['StatePrefix'].$state.'State';
            $attributes['Index']  = $idx;
            $attributes['Mask']  = $stateMask;
            $this->Workflow->StateModel['States'][$state] = $attributes;
            $this->MaskStates[$stateMask] = ['State' => $state,'Index' => $idx];
            // fill any state with no transitions
            if (! Arr::exists($this->Workflow->StateModel['StateTransitions'], $state)) {
                $this->Workflow->StateModel['StateTransitions'][$state] = [];
            }
            // set up dispatcher table before adding virtual state
            $this->StateHandlers[$idx] = ['state' => $state, 'handler' => $handler, 'execReady' => false, 'dispatchCycle' => 0, 'lastDispatchCycle' => 0, 'mask' => $stateMask];
            $this->StateHandlers[$idx]['singleton'] = isset($attributes['prototype']) ? !((bool) $attributes['prototype'] && $attributes['prototype'] !== 'false') : true;
        }
        if ($idx > self::MaskLimit) {
            throw new \RuntimeException("Workflow contains too many states! $idx exceeds limit of ".self::MaskLimit);
        }
        $this->StateMAX = $idx;
        $this->Workflow->StateModel['TerminalState'] = $this->Workflow->StateModel['States'][$TerminalState];
        $this->Workflow->StateModel['TerminalState']['State'] = $TerminalState;

        // Implied initial (virtual) state kickstarts Engine (not counted for StateMAX)
        $this->Workflow->StateModel['States'] = Arr::prepend(
            $this->Workflow->StateModel['States'],
            [
                    'Index' => 0,
                    'Mask'  => 0,
                ],
            'InitialS'
        );

        // reindex transitions to be single reference to execute all transitions
        $Transitions['InitialT']['Mask'] = 0; // Implied initial transition
        $this->Workflow->StateModel['StateTransitions'] = Arr::prepend(
            $this->Workflow->StateModel['StateTransitions'],
            [
                    ['Transition' => 'InitialT',
                        'TargetStates' => [$this->Workflow->StateModel['StartState']], // Implied initial transition
                    ],
                ],
            'InitialS'
        );
        foreach ($Transitions as $name => $attributes) {
            $this->Workflow->TransitionMasks[$name] = $attributes['Mask'];
            $this->MaskTransitions[$attributes['Mask']]['Name'] = $name;
        }
        unset($Transitions);

        /**
         * Identify all Idle states for Engine stop condition
         */
        $this->Workflow->StateModel['Idle']['StatesMask'] = 0;
        $idleStates = [];
        if (is_string($this->Workflow->StateModel['Idle']['States']) && !empty($this->Workflow->StateModel['Idle']['States'])) {
            $idleStates = explode(',', str_replace(' ', '', $this->Workflow->StateModel['Idle']['States']));
        } elseif (is_array($this->Workflow->StateModel['Idle']['States']) && !empty($this->Workflow->StateModel['Idle']['States'])) {
            $idleStates = $this->Workflow->StateModel['Idle']['States'];
        }
        foreach ($idleStates as $IdleState) {
            $this->Workflow->StateModel['Idle']['StatesMask'] |= $this->Workflow->StateModel['States'][$IdleState]['Mask'];
        }
        $this->StopEngineStates = $this->Workflow->StateModel['TerminalState']['Mask'] | $this->Workflow->StateModel['Idle']['StatesMask'];  // All terminal or Idle

        /**
         * reindex Transitions collecting multi-state transitions along the way.
         * TransitionStates format: [Transition => [ ['Origin'=>string,'Target'=>string,'OriginMask'=>int,'TargetMask'=>int]]]
         * gather all Target states for validation later.
         * Set 'autoTransition' on single transitions that qualify to be auto
         */
        $terminalTransitions = [];
        foreach ($this->Workflow->StateModel['StateTransitions'] as $originState => $transitions) {
            $originMask = $this->Workflow->StateModel['States'][$originState]['Mask'];
            // null transition auto defaults to only possible transition when not Idle state
            $singleTarget = count($transitions) == 1;
            $autoTransition = ($singleTarget && ($this->Workflow->StateModel['Idle']['StatesMask'] & $originMask) === 0);
            $transitionsMask = 0;
            foreach ($transitions as $idx => $transition) {
                $transitionMask = $this->Workflow->TransitionMasks[$transition['Transition']];
                $transitionsMask |= $transitionMask;
                // override default transition being set when it qualifies to be auto
                $noAutoTransition = isset($transition['autoTransition']) ? ($transition['autoTransition'] === false || strtolower($transition['autoTransition']) === 'false') : false;
                if ($autoTransition && ! $noAutoTransition) {
                    $this->Workflow->StateModel['StateTransitions'][$originState]['autoTransitionMask'] = $this->Workflow->TransitionMasks[$transition['Transition']];
                } else {
                    unset($this->Workflow->StateModel['StateTransitions'][$originState]['autoTransitionMask']); // Ignore if set
                }
                unset($this->Workflow->StateModel['StateTransitions'][$originState][$idx]['autoTransition']); // not needed now
                if (isset($this->MaskTransitions[$transitionMask]['OriginsMask'])) { // for validation
                    $this->MaskTransitions[$transitionMask]['OriginsMask'] |= $originMask;
                } else {
                    $this->MaskTransitions[$transitionMask]['OriginsMask'] = $originMask;
                }
                $targetsMask = 0;
                $splitTargets = [];
                $targetState = null; // needed when none
                foreach ($transition['TargetStates'] as $targetState) {
                    $TransitionStates[$transition['Transition']][] = ['Origin' => $originState, 'Target' => $targetState,
                                                                      'OriginMask' => $this->Workflow->StateModel['States'][$originState]['Mask'],
                                                                      'TargetMask' => $this->Workflow->StateModel['States'][$targetState]['Mask'],
                                                                     ];
                    $targetsMask |= $this->Workflow->StateModel['States'][$targetState]['Mask'];
                    $targetStates[$targetState] = $originState; // will validate every state is a target
                    $splitTargets[] = $targetState;
                    if ($targetState == $TerminalState) {
                        $terminalTransitions[] = $transition['Transition'];
                    }
                }
                // Must not Auto to self - infinite loop
                if (($originMask & $targetsMask) !== 0) {
                    unset($this->Workflow->StateModel['StateTransitions'][$originState]['autoTransitionMask']); // Ignore if set
                    log::warning("AutoTransition to self cancelled for state $originState");
                }
                $Split = [];
                if (count($transition['TargetStates']) > 1) { // only Split has more than 1 target
                    $Split['TargetsMask'] = $targetsMask;
                }
                $this->MaskTransitions[$transitionMask][$originMask] = ['Origin' => $originState,
                                                                        'TargetMask' => $targetsMask];
                // make a model entry
                if (isset($Split['TargetsMask'])) {
                    $this->Workflow->StateModel['Splits'][] = ['OriginState' => $originState, 'Transition' => [$transition['Transition']]];
                    $this->Workflow->StateModel['Splits'] = array_unique($this->Workflow->StateModel['Splits'], SORT_REGULAR);
                }
            }
            $this->MaskStates[$originMask]['TransitionsMask'] = $transitionsMask; // All the transitions this Origin can do
        }

        /**
         * check every state is a transition target
         */
        foreach ($this->Workflow->StateModel['States'] as $state => $attributes) {
            $invalidStates = [];
            if (empty($targetStates[$state])) {
                $invalidStates[] = $state;
            }
            if (!empty($invalidStates)) {
                $states = implode(',', $invalidStates);
                throw new \RuntimeException("Workflow state(s) $states not a transition target!");
            }
        }
        unset($targetStates);

        // StateModel refactoring complete, now optimise hints

        /**
         * Splits & Forks are similar with single Origin state with single or multiple transitions
         *  required to multiple Target states.
         *
         * Syncs & Merges are similar with single Target state from multiple origin States
         *  with single or multiple transitions required.
         * When Sync & Merge are combined, Merge waits for Sync to transition.
         *
         * Collect multi-state transitions into reindexed Transitions (Canonicalise, as they say)
         *
         * Precedece: Sync > Merge > Fork > Split
         */

        /**
         * process Syncs to gather Origin States for each Sync transition
         * and combined mask of all transitions (its identity)
         */
        foreach ($this->Workflow->StateModel['Syncs'] as $idx => $Sync) {
            $SyncOriginsMask = 0;
            $SyncOrigins = [];
            $SyncTransitionsMask = 0;
            $SyncTargetMask = $this->Workflow->StateModel['States'][$Sync['TargetState']]['Mask'];
            if (count($Sync['Transitions']) < 2) {
                throw new \RuntimeException('Sync target state '. $Sync['TargetState'] .' has too few transitions!');
            }
            foreach ($Sync['Transitions'] as $transition) {
                $thisTransitionMask = $this->Workflow->TransitionMasks[$transition];
                $SyncTransitionsMask |= $thisTransitionMask;
                foreach ($TransitionStates[$transition] as $StateTransition) {
                    $SyncOrigins[] = $StateTransition['Origin'];
                    $SyncOriginsMask |= $StateTransition['OriginMask'];
                }
            }
            if (self::EngineDebug) {
                $Sync['OriginStates'] = array_unique($SyncOrigins, SORT_REGULAR);
            }
            $Sync['OriginsMask'] = $SyncOriginsMask;
            $Sync['TargetMask'] = $SyncTargetMask;
            $Sync['TransitionsMask'] = $SyncTransitionsMask;
            $this->Workflow->StateModel['Syncs'][$idx] = $Sync;
            $Sync['Metadata'] = $idx; // quick ref to Model $this->Workflow->StateModel['Syncs'][$Sync['Metadata']]
            $SyncTransitions = $Sync['Transitions'];
            unset($Sync['Transitions']); //redundant
            foreach ($SyncTransitions as $transition) {
                $thisTransitionMask = $this->Workflow->TransitionMasks[$transition];
                $precedentSyncTargets[$thisTransitionMask] = $SyncTargetMask;
                $this->MaskTransitions[$thisTransitionMask]['Sync'][$SyncTransitionsMask] = $Sync;
                foreach ($TransitionStates[$transition] as $StateTransition) {
                    $originMask = $StateTransition['OriginMask'];
                    // is TargetMask already there?
                    if (! isset($this->MaskStates[$originMask]['SyncOrigins'][$SyncTransitionsMask])) {
                        $this->MaskStates[$originMask]['SyncOrigins'][$SyncTransitionsMask] = [
                                                                'TargetMask' => $SyncTargetMask,
                                                                'TransitionsMask' => $thisTransitionMask, // state's sync
                                                                ];
                    } else {
                        $this->MaskStates[$originMask]['SyncOrigins'][$SyncTransitionsMask]['TransitionsMask'] |= $thisTransitionMask; // state's multiple sync
                    }
                }
            }
        }
        // Validate
        foreach ($this->Workflow->StateModel['Syncs'] as $idx => $Sync) {
            if (($Sync['OriginsMask'] & $Sync['TargetMask']) !== 0) {
                throw new \RuntimeException('Sync state error: Target '.$Sync['TargetState'].' is Origin!');
            }
        }
        if (self::EngineDebug) {
            unset($idx); // bomb copy/paste fails
            unset($Sync);
        }

        /**
         * process Merges to gather Origin States for each Merge transition
         * When Sync & Merge are combined, Sync waits for Merge to transition.
         * Sync updated with Merge transitions so scanSyncProgress can ignore
         * while Merge is in progress.
         */
        $mergeTargetTransitions = [];
        foreach ($this->Workflow->StateModel['Merges'] as $idx => $Merge) {
            $transition = reset($Merge['Transition']); // only ever 1
            $thisTransitionMask = $this->Workflow->TransitionMasks[$transition];
            if (isset($mergeTargetTransitions[$Merge['TargetState']])) {
                $mergeTargetTransitions[$Merge['TargetState']] |= $thisTransitionMask;
            } else {
                $mergeTargetTransitions[$Merge['TargetState']] = $thisTransitionMask;
            }
        }
        foreach ($this->Workflow->StateModel['Merges'] as $idx => $Merge) {
            $MergeOriginsMask = 0;
            $MergeOrigins = [];
            $MergeTargetState = $Merge['TargetState'];
            $transition = reset($Merge['Transition']); // only ever 1
            $thisTransitionMask = $this->Workflow->TransitionMasks[$transition];
            foreach ($TransitionStates[$transition] as $StateTransition) {
                $MergeOrigins[] = $StateTransition['Origin'];
                $MergeOriginsMask |= $this->Workflow->StateModel['States'][$StateTransition['Origin']]['Mask'];
            }
            if (count($MergeOrigins) < 2) {
                throw new \RuntimeException("Merge transition $transition has too few Origin states!");
            }
            if (self::EngineDebug) {
                $Merge['OriginStates'] = array_unique($MergeOrigins, SORT_REGULAR);
            }
            $Merge['OriginsMask'] = $MergeOriginsMask;
            $mergeTargetMask = $this->Workflow->StateModel['States'][$MergeTargetState]['Mask'];
            $Merge['TargetMask'] = $mergeTargetMask;
            $this->Workflow->StateModel['Merges'][$idx] = $Merge; // Model has truth
            $Merge['Metadata'] = $idx; // quick ref to Model $this->Workflow->StateModel['Merges'][$Merge['Metadata']]
            $metadataTargetMask = $mergeTargetMask; // for MaskStates
            $precedentTargets = $precedentSyncTargets[$thisTransitionMask] ?? 0;
            $mergeTargetMask &= ~$precedentTargets; // remove any precedence targets
            $Merge['TargetMask'] = $mergeTargetMask; // Transition is optimised in combo
            $Merge['TransitionMask'] = $thisTransitionMask;
            unset($Merge['Transition']); //redundant
            $precedentMergeTargets[$thisTransitionMask] = $mergeTargetMask;
            $this->MaskTransitions[$thisTransitionMask]['Merge'][$MergeOriginsMask] = $Merge;
            foreach ($TransitionStates[$transition] as $StateTransition) {
                $originMask = $StateTransition['OriginMask'];
                $this->MaskStates[$originMask]['MergeOrigins'][$thisTransitionMask] = [
                                                            'OriginsMask' => $MergeOriginsMask,
                                                            'TargetMask' => $metadataTargetMask,
                                                            ];
                $this->MaskStates[$originMask]['MergeTargetTransitions'][$metadataTargetMask] = $mergeTargetTransitions[$MergeTargetState];
            }
        }
        // Validate
        foreach ($this->Workflow->StateModel['Merges'] as $idx => $Merge) {
            if (($Merge['OriginsMask'] & $Merge['TargetMask']) !== 0) {
                throw new \RuntimeException('Merge state error: Target '.$Merge['TargetState'].' is Origin!');
            }
        }
        if (self::EngineDebug) {
            unset($idx); // bomb copy/paste fails
            unset($Merge);
        }

        /**
         * process Forks to gather Target States for each Fork transition
         * and combined mask of all transitions (its identity).
         * When combined with Sync or Merge, Fork transition leaves target for Sync or Merge to set. Sync has precedence.
         * TransitionStates format: [Transition => [['Origin'=>string,'Target'=>string,'OriginMask'=>int,'TargetMask'=>int]]]
         */
        foreach ($this->Workflow->StateModel['Forks'] as $idxFork => $Fork) {
            $ForkOriginMask = $this->Workflow->StateModel['States'][$Fork['OriginState']]['Mask'];
            $ForkTargets = [];
            $ForkTargetsMask = 0;
            $ForkTransitionsMask = 0;
            $precedentTargets = 0; // precedence for all Fork transitions
            foreach ($Fork['Transitions'] as $transition) {
                if (! Arr::exists($this->Workflow->TransitionMasks, $transition)) {
                    throw new \RuntimeException("Fork transition error: $transition not defined in StateTransitions!");
                }
                $thisTransitionMask = $this->Workflow->TransitionMasks[$transition];
                $precedentTargets |= $precedentSyncTargets[$thisTransitionMask] ?? 0;
                $precedentTargets |= $precedentMergeTargets[$thisTransitionMask] ?? 0;
                $ForkTransitionsMask |= $thisTransitionMask;
                foreach ($TransitionStates[$transition] as $idx => $StateTransition) {
                    if ($StateTransition['Origin'] == $Fork['OriginState']) {
                        $ForkTargetsMask |= $StateTransition['TargetMask'];
                        $ForkTargets[] = $StateTransition['Target'];
                    }
                }
            }
            // redundant info 'cause order of transitions is random, each transition/origin must know what to do
            if (self::EngineDebug) {
                $Fork['TargetStates'] = $ForkTargets;
            }
            $Fork['OriginMask'] = $ForkOriginMask;
            $Fork['TransitionsMask'] = $ForkTransitionsMask;
            $Fork['TargetsMask'] = $ForkTargetsMask; // Model has truth
            $this->Workflow->StateModel['Forks'][$idxFork] = $Fork;
            $metadataTargetMask = $ForkTargetsMask; // for MaskStates
            $ForkTransitions = $Fork['Transitions'];
            unset($Fork['Transitions']);
            /**
             * Even though it loops, ForkTargetsMask should end up the same for each transition
             */
            $ForkTargetsMask &= ~$precedentTargets; // remove any precedence targets
            $Fork['TargetsMask'] = $ForkTargetsMask; // transition is optimised
            foreach ($ForkTransitions as $transition) {
                $thisTransitionMask = $this->Workflow->TransitionMasks[$transition];
                $precedentForkTargets[$thisTransitionMask] = $ForkTargetsMask;
                $this->MaskTransitions[$thisTransitionMask]['Fork'][$ForkTransitionsMask] = $Fork;
                $this->MaskStates[$ForkOriginMask]['ForkTargets'][$ForkTransitionsMask] = $metadataTargetMask;
            }
        }
        // Validate
        foreach ($this->Workflow->StateModel['Forks'] as $idxFork => $Fork) {
            if (($Fork['OriginMask'] & $Fork['TargetsMask']) !== 0) {
                throw new \RuntimeException('Fork state error: Origin '.$Fork['OriginState'].' is Target!');
            }
        }
        if (self::EngineDebug) {
            unset($idxFork); // bomb copy/paste fails
            unset($Fork);
        }

        /**
         * process Splits to gather Target States for each Split transition
         * Split is lowest precedence, when Split transition is also Merge or Sync transition
         * remove from Split targets to allow Merge or Sync to take precedence.
         */
        foreach ($this->Workflow->StateModel['Splits'] as $splitIdx => $Split) {
            $SplitOriginState = $Split['OriginState'];
            if (count($Split['Transition']) != 1) {
                throw new \RuntimeException("Split transition error: Origin $SplitOriginState must have exactly one Transiton!");
            }
            $SplitOriginMask = $this->Workflow->StateModel['States'][$SplitOriginState]['Mask'];
            $SplitTransition = reset($Split['Transition']);
            $SplitTargetStates = [];
            foreach ($this->Workflow->StateModel['StateTransitions'][$SplitOriginState] as $idx => $transition) {
                if ($idx == 'autoTransitionMask') {
                    break;
                }
                if ($SplitTransition == $transition['Transition']) {
                    $SplitTargetStates = $transition['TargetStates'];
                    continue;
                }
            }
            if (empty($SplitTargetStates)) {
                throw new \RuntimeException("Split '$SplitTransition' transition error: Origin $SplitOriginState has no StateTransition!");
            }
            if (count($SplitTargetStates) < 2) {
                throw new \RuntimeException("Split '$SplitTransition' transition error: Origin $SplitOriginState must split to multiple target states!");
            }
            $SplitTargetsMask = 0;
            $thisTransitionMask = $this->Workflow->TransitionMasks[$SplitTransition];
            foreach ($SplitTargetStates as $splitTargetstate) {
                $SplitTargetsMask |= $this->Workflow->StateModel['States'][$splitTargetstate]['Mask'];
            }
            if (self::EngineDebug) {
                $Split['TargetStates'] = $SplitTargetStates;
            }
            $Split['OriginMask'] = $SplitOriginMask;
            $Split['TargetsMask'] = $SplitTargetsMask;
            $Split['TransitionMask'] = $thisTransitionMask;
            $this->Workflow->StateModel['Splits'][$splitIdx] = $Split;
            $this->MaskStates[$SplitOriginMask]['SplitTargets'][$thisTransitionMask] = $SplitTargetsMask; // metadata for MaskStates

            $precedentTargets = $precedentSyncTargets[$thisTransitionMask] ?? 0;
            $precedentTargets |= $precedentMergeTargets[$thisTransitionMask] ?? 0;
            $precedentTargets |= $precedentForkTargets[$thisTransitionMask] ?? 0;
            $SplitTargetsMask &= ~$precedentTargets; // remove any precedence targets
            $Split['TargetsMask'] = $SplitTargetsMask;
            unset($Split['Transition']); //redundant
            $this->MaskTransitions[$thisTransitionMask]['Split'][$SplitOriginMask] = $Split;
        }

        /**
         * Set Workflow Engine parameters, if specified
         */
        if (isset($this->Workflow->StateModel['Parameters']['MaxTransitionFactor'])) {
            // Allow for some loops, unlimited unless specified
            foreach ($TransitionStates as $transitions) {
                $this->DispatchMaxCount += count($transitions);
            }
            $this->DispatchMaxCount *= (int) $this->Workflow->StateModel['Parameters']['MaxTransitionFactor'];
        }

        $this->StallCycles = isset($this->Workflow->StateModel['Parameters']['StallCycles'])
            ? (int) $this->Workflow->StateModel['Parameters']['StallCycles'] - 1
            : 1; // (default: 2 - 1)

        /**
         * Model has been refactored and validated.
         *  Can be saved to cache.
         */
        $this->ValidModel = true;
        $this->CacheModel();
    }

    /**
     * CacheModel
     * Save or restore refactored model from disk.
     * When EngineDebug mode, cache is always deleted so the
     *  model is refactored and saved each time.
     *
     * @return Bool true when valid model saved or restored
     */
    private function CacheModel(): bool
    {
        $CacheFileName = $this->Workflow->GetName().'Workflow.model';

        if ($this->ValidModel) {
        // 2nd call
            if (self::EngineDebug || App::environment('testing')) {
                return false;
            }
        /**
         * Save model to cache for next time
         */
            $modelContext = [self::SEVersion,
                             $this->Workflow->GetVersionBuild(),
                             $this->Workflow,
                             $this->MaskTransitions,
                             $this->MaskStates,
                             $this->StateHandlers,
                             $this->StopEngineStates,
                             $this->DispatchMaxCount,
                             $this->StateMAX,
                             $this->StallCycles,
                            ];
            Storage::disk('local')->put($CacheFileName,
                    serialize($modelContext));
            return true;
        }
        // 1st call
        if (self::EngineDebug || App::environment('testing')) {
            $eMessage = 'cache save/restore disabled for Testing/EngineDebug';
            Log::debug("\n\rCacheModel $eMessage");
            if (self::EngineDebug) {
                echo "<strong>CacheModel</strong> $eMessage<br/>";
            }
            Storage::delete($CacheFileName);
            return false;
        }

        $WFBuild = $this->Workflow->GetVersionBuild();
        /**
         * Restore model from cache and validate versions
         */
        if (Storage::disk('local')->exists($CacheFileName)) {
            [$SEVersionCache,
             $WFCache,
             $this->Workflow,
             $this->MaskTransitions,
             $this->MaskStates,
             $this->StateHandlers,
             $this->StopEngineStates,
             $this->DispatchMaxCount,
             $this->StateMAX,
             $this->StallCycles,
            ] = unserialize(Storage::get($CacheFileName));
        } else {
            return false;
        }
        // Same Engine version?
        if (Str::before($SEVersionCache, '.') != Str::before(self::SEVersion, '.')) {
            Storage::delete($CacheFileName);
            return false;
        }
        // Same Workflow version?
        if (Str::before($WFCache['WFVersion'], '.') != Str::before($WFBuild['WFVersion'], '.')) {
            Storage::delete($CacheFileName);
            return false;
        }
        $this->ValidModel = true;
        return true;
    }

    /**
     * StartEngine
     *  Called by CreateAppContext
     * Do special transition into Workflow StartState ready to RunWorkflow
     *
     * @return Bool true when started Engine
     */
    public function StartEngine(): bool
    {
        if (self::EngineDebug) {
            echo "<strong>StartEngine</strong><br/>";
        }
        $transitionMask = $this->Workflow->TransitionMasks['InitialT']; // InitialT - implied transition to StartState
        $originMask = 0; // InitialS
        $this->DispatchState = $this->MaskTransitions[$transitionMask][$originMask]['Origin'];
        if (self::EngineDebug) {
            echo "<strong>StartEngine</strong> Transition: ". $this->TransitionNamesFromMask($transitionMask)."<br/>";
        }
        return $this->StateTransition($transitionMask); //  transition to StartState
    }

    /**
     * StartWorkflow
     *  Create the appContext object
     *  The Workflow object in the Engine contains the method to create this app's context.
     *
     * Called by Controller: $appContext = $Engine->StartWorkflow($request, <params>);

     * @param array $params - anything to be passed through to Workflow
     *
     * @return WorkflowAppContract $appContext or null to abandon this workflow before starting
     */
    public function StartWorkflow(...$params): ?WorkflowAppContract
    {
        $eMessage = $this->Workflow->StateModel['Workflow'];
        Log::info("StartWorkflow $eMessage");
        if (self::EngineDebug) {
            echo "<br/><strong>StartWorkflow</strong> $eMessage<br/>";
        }
        $this->DispatchLastTransitions[0] = []; // Dispatch Cycle zero is LastTransition of a previous Workflow run when ResumeWorkflow
        $CreateAppContext = 'CreateAppContext';
        if (is_callable([$this->Workflow, $CreateAppContext], false, $appHandler)) {
            $appContext = call_user_func([$this->Workflow, $CreateAppContext], $this, $params);
        } else {
            throw new \RuntimeException(__METHOD__.": Undefined Workflow Handler: $CreateAppContext");
        }
        if (! $this->StartEngine()) {
            throw new \RuntimeException(__METHOD__.": Engine start fail!");
        }
        return $appContext;
    }

    /**
     * RunWorkflow
     *  Loop until only Terminal or Idle states are left
     *
     * @param WorkflowAppContract $appContext
     *
     * @return WorkflowAppContract $appContext
     */
    public function RunWorkflow(WorkflowAppContract $appContext): WorkflowAppContract
    {
        $eMessage = 'with State prefix: '. $this->Workflow->StateModel['StatePrefix'];
        Log::info("RunWorkflow $eMessage");
        if (self::EngineDebug) {
            echo "<br/><strong>RunWorkflow</strong> $eMessage StopEngineStates mask: ".decbin($this->StopEngineStates).'<br/>';
        }
        $this->DispatchCycle = 0; // fresh workflow
        $lastCycle = 0; // keep going
        do {
            $appContext = $this->dispatcher($appContext); // run each active state handler once

            if (self::EngineDebug) {
                echo "<strong>RunWorkflow LOOPed</strong> Cycle $this->DispatchCycle DeferTerminalState mask: ".decbin($this->DeferTerminalState)." execCount: $this->execCount <br/>";
            }
            /**
             * No Transitions might mean we're finished
             *   Only Terminal or Idle states set - need one more DispatchCycle to run Terminal State once or run Idle states 2nd time - might transition
             *   Otherwise finish up this cycle - only ordinary states means an Error condition
             * Transitions happened - reset last cycle in case an Idle state just emitted transition on its 2nd run
             * Check DispatchLastTransition isn't a repeat of previous cycle, indicates a loop.
             * Stalled: requires non-Idle states to be active
             *    Idle: Only Idle states with no new transitions - allowed to stop since in a resumable WorkflowState
             *         (requires Logger to save state for Resume to work)
             */
            $workflowIdle = (
                             $this->DeferTerminalState === 0
                             &&  $this->EngineReadyStates === 0
                            ) // No new states or only Idle ready?
                        ||  ($this->DispatchLastTransitions[$this->DispatchCycle] === $this->DispatchLastTransitions[$this->DispatchCycle - 1]
                        ||  ($this->WorkflowStateMask & $this->StopEngineStates) === $this->WorkflowStateMask
                            ); // same states same transitions?

            $workflowStalled = ($workflowIdle
                            && ($this->WorkflowStateMask & ~$this->StopEngineStates) !== 0); // Non-Idle states active

            /**
             * Is Workflow ready to end?
             * Stalled: same states/transitions for 2 cycles - error
             * Only Terminal/Idle with no transitions: Finish - correct end state
             * Other states ready - run more cycles
             */
            if ($workflowStalled) { //maybe error state
                $lastCycle = $this->DispatchCycle; // finish up
                if (self::EngineDebug) {
                    echo "<strong>RunWorkflow stalled!</strong> DispatchCycle: $lastCycle No ready states.<br/>";
                }
            } elseif ($workflowIdle) {
                if (self::EngineDebug) {
                    echo '<strong>RunWorkflow idle:</strong> true <br/>';
                }
                /**
                 * Terminal state has already run when DispatchLastTransitions is empty, so finish.
                 * Otherwise, one more DispatchCycle for Terminal state execution will make DispatchLastTransitions empty
                 */
                if (($this->WorkflowStateMask & $this->Workflow->StateModel['TerminalState']['Mask']) === $this->WorkflowStateMask) {
                    $lastCycle = $this->zeroArray($this->DispatchLastTransitions[$this->DispatchCycle]) ? $this->DispatchCycle : $this->DispatchCycle + $this->StallCycles;
                } else {
                    $lastCycle = $lastCycle ?: $this->DispatchCycle + $this->StallCycles; // finish up after Idle states run one more
                }
                if (self::EngineDebug) {
                    echo "<strong>RunWorkflow lastCycle</strong> DispatchCycle: $lastCycle Terminal/Idle only.<br/>"; // StateHandlers dispatchCycle: ".$this->StateHandlers[$this->DispatchStateIndex]['dispatchCycle']."<br/>";
                }
            } else {
                $lastCycle = 0; // non Terminal/Idle states or not stalled, keep going
                if (self::EngineDebug) {
                    echo "<strong>RunWorkflow moreCycles</strong> lastCycle: $lastCycle <br/>";
                }
            }
        } while ($lastCycle != $this->DispatchCycle);

        if (self::EngineDebug) {
            echo "<strong>RunWorkflow endCycle</strong> Cycle: $this->DispatchCycle Final WorkflowStateMask: ".decbin($this->WorkflowStateMask)."<br/>";
        }
        if ($workflowStalled) {  // Did same twice and not Idle?
            if ($this->EngineReadyStates === 0) {
                $eMessage = "STALLED: No ready states! DispatchCycle: $this->DispatchCycle WorkflowState(s): ".$this->StateNamesFromMask($this->WorkflowStateMask).
                     ' LastTransitions: '.$this->formatLastTransitions($this->DispatchLastTransitions[$this->DispatchCycle]);
            } else {
                $eMessage = "STALLED: Same state transitions! DispatchCycle: $this->DispatchCycle WorkflowState(s): ".$this->StateNamesFromMask($this->WorkflowStateMask).
                     ' LastTransitions: '.$this->formatLastTransitions($this->DispatchLastTransitions[$this->DispatchCycle]);
            }
            if ($ePending = $this->terminalPendingTransitions()) {
                $eMessage .= "\n\r$ePending";
            }
            Log::critical($eMessage);
            throw new \RuntimeException(str_replace("| \n\r", '. ',$eMessage));
        }
        if ($workflowIdle
        && ($this->WorkflowStateMask & $this->StopEngineStates) !== $this->WorkflowStateMask) { // Any non-idle states?
            $eMessage = "STALLED: Workflow is idle in non-idle state(s)! DispatchCycle: $this->DispatchCycle WorkflowState(s): ".$this->StateNamesFromMask($this->WorkflowStateMask).
                ' LastTransitions: '.$this->formatLastTransitions($this->DispatchLastTransitions[$this->DispatchCycle]);
            Log::critical($eMessage);
            throw new \RuntimeException($eMessage);
        }
        /**
         * Idle state(s) can stop with pending transitions.
         * The assumption being the workflow can resume to complete
         * properly.
         * (Workflow Resume functions not implmented in this version - requires DB)
         */
        if (($this->WorkflowStateMask ^ $this->Workflow->StateModel['TerminalState']['Mask']) === 0
         && $ePending = $this->terminalPendingTransitions()) {
            $eMessage = "TERMINAL: Pending transitions! DispatchCycle: $this->DispatchCycle \n\r$ePending";
            Log::critical($eMessage);
            throw new \RuntimeException(str_replace("| \n\r", '. ',$eMessage));
        }
        return $appContext; // return to calling WorkflowApp State handler
    }

    /**
     * terminalPendingTransitions
     * Check special transition states for any incomplete transition.
     * Such a state may indicate an error in the model/app or an error in
     * the Engine for a specific topology of special transitions.
     *
     * @return String Error message for exception
     */
    private function terminalPendingTransitions(): string
    {
        $ePending = '';

        if (! empty($this->ForkStates)) {
            $ePending .= 'Fork pending: ';
            foreach ($this->ForkStates as $OrginState => $forkProgress) {
                $ePending .= $OrginState ;
                foreach ($forkProgress as $progress) {
                    $ePending .= "progress Transition(s): ". $this->TransitionNamesFromMask($progress['TransitionsMask']).'| ';
                }
            }
            $ePending .= "\n\r";
        }

        if (! empty($this->SyncStates) && ! empty(reset($this->SyncStates))) {
            $ePending .= 'Sync pending: ';
            foreach ($this->SyncStates as $TargetMask => $SyncTargets) {
                $targetState = $this->MaskStates[$TargetMask]['State'];
                $ePending .= $targetState;
                foreach ($SyncTargets as $SyncTransitionsMask => $ProgressTransitionsMask) {
                    $ePending .= ' progress Transition(s): '. $this->TransitionNamesFromMask($ProgressTransitionsMask).
                        ' required: '. $this->TransitionNamesFromMask(($ProgressTransitionsMask ^ $SyncTransitionsMask)).'| ';
                }
            }
            $ePending .= "\n\r";
        }

        if (! empty($this->MergeStates)) {
            $ePending .= 'Merge pending: ';
            foreach ($this->MergeStates as $TargetMask => $mergeProgress) {
                foreach ($mergeProgress as $TransitionMask => $OriginsMask) {
                    $ePending .= 'transition: '.$this->MaskTransitions[$TransitionMask]['Name'].' progress Origins: '. $this->StateNamesFromMask($OriginsMask).'| ';
                }
            }
            $ePending .= "\n\r";
        }

        return $ePending;
    }

    /**
     * zeroArray
     *
     * @param Array $haystack
     * @return Bool Every value in the array is (int) zero
     */
    private function zeroArray(array $haystack): bool
    {
        if (empty($haystack)) return true;

        foreach ($haystack as $needle) {
            if ($needle !== 0) return false;
        }
        return true;
    }

    /**
     * dispatcher
     * main activity of Engine to execute State.
     *  Dispatch is discrete execution of all current states ($this->WorkflowStateMask) before next Transitions.
     * EngineStateMask - A dispatch cycle is execution of these State handlers. New States are set in DispatchStatesMask for next cycle
     *
     * Allow Idle states to run each cycle until they emit a transition.
     * This allows for an Idle state to wait for another branch of workflow to do something
     * the Idle state needs to proceed.
     * Idle states implicitly do a Null transition when they don't emit a transition.
     * When only Idle states, 2 consecutive cycles of no transition Engine stops normally (not in Terminal state).
     * Assumnption is an Idle Workflow can be resumed after some external event.
     *
     * @param WorkflowAppContract $appContext usually an object passed in from main app, contains all the global data need by State handlers
     *
     * @return WorkflowAppContract $appContext  updated object passed back to main app
     */
    private function dispatcher(WorkflowAppContract $appContext): WorkflowAppContract
    {
        if ($this->DispatchCycle++ == $this->DispatchMaxCount && $this->DispatchMaxCount > 0) {
            $eMessage = "ABORTED: Excess Dispatch Cycles! $this->DispatchCycle EngineReadyStates: ".$this->StateNamesFromMask($this->EngineReadyStates);
            Log::critical($eMessage);
            throw new \RuntimeException($eMessage);
        }

        $this->preDispatch();
        if (self::EngineDebug) {
            $eMessage = "WorkflowState(s): ".$this->StateNamesFromMask($this->WorkflowStateMask);
            echo "<br/><strong>Dispatcher new Cycle: $this->DispatchCycle</strong> $eMessage<br/>";
            Log::debug("Dispatcher new Cycle: $this->DispatchCycle $eMessage \n\r");
        }
        $this->makeExecReady($this->EngineStateMask);

        /**
         * Main Dispatch loop
         * Run each State Handler in turn, in order they are defined in the StateModel.
         * They must have no dependency on each other, order of execution within a dispatch cycle must not matter.
         */
        foreach ($this->StateHandlers as $stateIndex => $stateHandler) {
            if ($stateHandler['execReady'] && $stateHandler['dispatchCycle'] == $this->DispatchCycle) { // ready to run in this cycle?
                $this->execCount++;
                $handler = $stateHandler['handler'];
                $singleton = $stateHandler['singleton'];
                $this->StateHandlers[$stateIndex]['execReady'] = false; // allows state transition to self
                $this->StateHandlers[$stateIndex]['lastDispatchCycle'] = $this->DispatchCycle;
                $this->DispatchState = $stateHandler['state'];
                $this->DispatchStateMask = $this->StateHandlers[$stateIndex]['mask'];
                $this->EngineExecutedStates |= $this->StateHandlers[$stateIndex]['mask'];
                $this->DispatchLastTransitions[$this->DispatchCycle][$this->DispatchStateMask] = 0; // No transition yet
                if (self::EngineDebug) {
                    echo "<br/><strong>Dispatcher</strong>Cycle: $this->DispatchCycle RunState: $handler <br/>";
                }
                $appContext = call_user_func([$this->Workflow, 'RunState'], $this, $appContext, $handler, $singleton); // Handler runs Engine->StateTranstion
            }
        }
        $this->EngineReadyStates |= $this->scanInProgress($this->DispatchStateMask, $this->DispatchLastTransitions[$this->DispatchCycle][$this->DispatchStateMask]);
        if (self::EngineDebug) {
            $eMessage = "$this->DispatchCycle EngineReadyStates: ".$this->StateNamesFromMask($this->EngineReadyStates).
                " execCount: $this->execCount EngineExecutedStates: ".$this->StateNamesFromMask($this->EngineExecutedStates).
                ' WorkflowStates: '.$this->StateNamesFromMask($this->WorkflowStateMask);
            echo "<strong>Dispatched Cycle:</strong> $eMessage <br/>";
            Log::debug("Dispatched Cycle: $eMessage \n\r");
        }
        Log::info("Dispatch Cycle: $this->DispatchCycle executed state(s): ". $this->StateNamesFromMask($this->EngineExecutedStates));

        return $appContext;
    }

    /**
     * preDispatch
     * If Terminal state is set with other states, it is deferred to next cyle.
     * makeExecReady() will defer when necessary.
     * Terminal state must run on its own after all others.
     * Workflow state is the Engine state at the start of dispatch cycle and remains so until next cycle starts.
     * When Engine stops properly, Workflow state is Terminal state or only Idles state(s).
     * Purge DispatchLastTransitions to reduce memory use with loops.
     */
    private function preDispatch()
    {
        if ($this->DispatchCycle > $this->StallCycles) {
            unset($this->DispatchLastTransitions[$this->DispatchCycle - $this->StallCycles - 2]);
        }
        $this->DispatchLastTransitions[$this->DispatchCycle] = [];
        $this->EngineExecutedStates = 0;
        $this->execCount = 0;

        $this->EngineStateMask |= $this->EngineReadyStates;
        $this->EngineReadyStates = $this->DeferTerminalState;
        $this->DeferTerminalState = 0;
        $this->WorkflowStateMask = $this->EngineStateMask;
    }

    /**
     * makeExecReady
     * using Engine StateHandlers
     * Set the execReady attribute on each state set Ready
     * Set the DispatchCycle so they run this cycle
     *
     * @param Int $EngineReadyStates new states set by last dispatch cycle transitions
     */
    private function makeExecReady(int $EngineReadyStates)
    {
        if (self::EngineDebug) {
            $eMessage = 'States: '.$this->StateNamesFromMask($EngineReadyStates);
            echo "<strong>makeExecReady</strong> $eMessage<br/>";
            Log::debug("makeExecReady $eMessage \n\r");
        }
        /**
         * If Terminal state is set with other states, defer Terminal state to next cyle.
         * Terminal state must run on its own after all others.
         */
        if (($EngineReadyStates & $this->Workflow->StateModel['TerminalState']['Mask']) !== 0
        &&  ($EngineReadyStates ^ $this->Workflow->StateModel['TerminalState']['Mask']) !== 0) {
            $EngineReadyStates = $this->deferTerminalState($EngineReadyStates);
        }

        $this->readyCount = 0;
        for ($idx = 1; $idx <= $this->StateMAX; $idx++) {
            if (($EngineReadyStates & 1) === 1) {
                $this->readyCount++;
                if (! $this->StateHandlers[$idx]['execReady']) {
                    if (self::EngineDebug) {
                        echo 'makeReady: StateHandler:'.$this->StateHandlers[$idx]['handler'].'<br/>';
                    }
                    $this->StateHandlers[$idx]['execReady'] = true;
                    $this->StateHandlers[$idx]['dispatchCycle'] = $this->DispatchCycle;
                }
            }
            $EngineReadyStates >>= 1;
        }
        if (self::EngineDebug) {
            echo "makeReady: readyCount: $this->readyCount DeferTerminalState: ".$this->StateNamesFromMask($this->DeferTerminalState)." DispatchMaxCount: $this->DispatchMaxCount<br/>";
        }
        /**
         * Something wrong with Model if we get here.
         * No States to execute when there should be
         */
        if ($this->readyCount == 0) {
            $eMessage = "STALLED: No ready states! DispatchCycle: $this->DispatchCycle DispatchState: $this->DispatchState DispatchLastTransitions: ".
                var_export($this->DispatchLastTransitions[$this->DispatchCycle - 1], true);
            Log::critical($eMessage);
            throw new \RuntimeException($eMessage);
        }
    }

    /**
     * StateTransition
     * Method: Find the Transitions for current state
     * Check for valid transition - allow for waits required by Sync or Merge states to permit transition.
     *
     * a NULL transition is emitted when there are no target states for the transition being processed, or
     *  the state does not emit a transition itself.
     * This is useful after a Fork or Split creates multiple paths, a path can end leaving others
     * to proceed to terminal state.
     * Null transition will emit a the only transition possible, when there is only 1.
     *  A pipeline can be implemented without any state explicitly emitting a transition.
     *
     * Called by Workflow->RunState() (run in the context of Engine->dispatcher)
     *
     * @param Int|String|Array $TransitionMask the transitions we are to do, multiple bits chould be set (Fork or branch), could be 0 for Null transition
     *
     * @return Bool result of transition, true = new states are set
     */
    public function StateTransition(int|string|array $Transition): bool
    {
        if (is_int($Transition)) {
            $TransitionMask = ($Transition) ?: $this->Workflow->StateModel['StateTransitions'][$this->DispatchState]['autoTransitionMask'] ?? 0;
        } else {
            $TransitionMask = $this->TransitionNameToMask($Transition);
        }
        if (self::EngineDebug) {
            $eMessage = 'Transition: '.$this->TransitionNamesFromMask($TransitionMask).' EngineReadyStates: '.$this->StateNamesFromMask($this->EngineReadyStates)." DispatchState: $this->DispatchState";
            echo "<strong>StateTransition</strong> $eMessage<br/>";
            echo "EngineStates: ".$this->StateNamesFromMask($this->EngineStateMask)." WorkflowStates: ".$this->StateNamesFromMask($this->WorkflowStateMask)."<br/>";
            Log::debug("StateTransition $eMessage \n\r");
        }
        /**
         * A null transition is default when no transition emitted, maybe emitted by any non-Idle state to end a branch of the Workflow.
         * This maybe any state that has no next state and is not an Idle state.
         * Maybe a Fork, Merge or Sync origin that isn't ready to emit a transition yet,
         *  it will be run again next cycle.
         */
        if ($TransitionMask === 0
        &&  $this->EngineStateMask === 0) {
            /**
             * InitialState with Initial transition,
             * Kick start Engine.
             */
            return $this->initialTransition($this->MaskTransitions[$TransitionMask]);
        }
        if ($this->isTerminal()) { // No transition from Terminal state!
            if ($TransitionMask === 0) {
                return false; // terminal state just executed
            }
            $eMessage = "isTerminal - no transition allowed! Transition: ".$this->TransitionNamesFromMask($TransitionMask).
                " EngineStates: ".$this->StateNamesFromMask($this->EngineStateMask);
            Log::alert($eMessage);
            throw new \RuntimeException($eMessage);
        }
        /**
         * Can have multiple transitions set in $Transition, such as Fork or plain branch.
         * $TransitionMask may have multiple bits set
         */
        if ($TransitionMask === 0) {
            $foundOrigin = true;
            $transitioned = $this->nullTransition();
        } else {
            $transitioned = false; // will end up true so long as one state emits a transition
            $foundOrigin = false;
            foreach ($this->MaskTransitions as $transitionMask => $transitionStates) {
                if (($transitionMask & $TransitionMask) !== 0) {// Found transition to do
                    if (self::EngineDebug) {
                        $eMessage = 'Next: '. $this->MaskTransitions[$transitionMask]['Name'] .' transitionMask: '. decbin($transitionMask);
                        echo "<strong>StateTransition</strong> $eMessage<br/>";
                        Log::debug("StateTransition $eMessage \n\r");
                    }
                    if (($this->DispatchStateMask & $transitionStates['OriginsMask']) !== 0) {
                        $foundOrigin = true;
                        $transitioned = ($this->nextTransition($transitionMask, $transitionStates)) || $transitioned;
                    }
                }
            }
        }
        /**
         * When this state is part of an active Fork, Sync or Merge, run this state again
         *  to give it a chance to emit the missing transition expected.
         * Combined special transitions may not transition each dispatch cycle as it would
         * when not combined.
         */
        $transitioned = ($this->DispatchStateMask & $this->EngineReadyStates !== 0) || $transitioned; // rerun Origin counts as a transition
        if ($foundOrigin) {
            return $transitioned;
        }
        $eMessage = "Invalid Transition for state: '$this->DispatchState' ! Transition: ".$this->TransitionNamesFromMask($TransitionMask)." not a valid Origin";
        Log::alert($eMessage);
        throw new \RuntimeException($eMessage);
    }

    /**
     * initialTransition
     * Method: Find the Transitions to start state
     * must transition to StartState.
     * TransitionMask = 0 this call.
     * Engine has no state.
     * Don't record DispatchLastTransitions
     *
     * @param Array $Transitions - element of $this->MaskTransitions. Key is OriginMask.
     *
     * @return Bool result of transition, false = no Origin State to reset
     */
    private function initialTransition(array $Transitions): bool
    {
        if (self::EngineDebug) {
            echo "<strong>initialTransition</strong> Transitions: ".var_export($Transitions, true)."<br/>";
            Log::debug('initialTransition '.$Transitions['Name'] ."\n\r");
        }
        $startStateMask = $this->findOrigin(0, $Transitions);
        $this->SetStateReadyMask($startStateMask);
        return true;
    }

    /**
     * nullTransition
     * Clear current state but sets no new states.
     * The non-transition is recorded in DispatchLastTransitions
     *
     * NULL transition (mask=0) may be emitted by an Idle state to indicate it remains idle.
     *
     * NULL transition will also be emitted when there are no target states for the state being processed.
     * This is useful after a Fork or Split creates multiple paths, paths can end leaving others
     * to proceed to terminal state.
     *
     * @return Bool result of transition, true = Origin state was cleared
     */
    private function nullTransition(): bool
    {
        $this->ResetStateMask($this->DispatchStateMask);

        if (($this->DispatchStateMask & $this->Workflow->StateModel['Idle']['StatesMask']) !== 0) {
            $this->SetStateReadyMask($this->DispatchStateMask);
        } // Idle states transition to self (runs again next cycle)

        if (self::EngineDebug) {
            $eMessage = "DispatchState $this->DispatchState EngineState(s): ".$this->StateNamesFromMask($this->EngineStateMask);
            echo "<strong>nullTransition</strong> $eMessage<br/>";
            Log::debug("nullTransition $eMessage \n\r");
        }
        return true;
    }

    /**
     * nextTransition
     * Method: Find the Transitions for current state
     * Check we are allowed to transition - not waiting for any Sync states to permit transition
     * Set new state, set StateHandlers['execReady'] to true for new states
     * Clear previous state or if Merge, clear previous states
     *
     * @param Int $TransitionMask the transition we are to do, only one bit should be set
     * @param Array $TransitionStates - element of $this->MaskTransitions. Key is OriginMask.
     *
     * @return Bool result of transition, true = new states are set
     */
    private function nextTransition(int $TransitionMask, array $TransitionStates): bool
    {
        if (self::EngineDebug) {
            echo "<strong>nextTransition</strong> EngineStateMask: ".decbin($this->EngineStateMask)." WorkflowStateMask: ".decbin($this->WorkflowStateMask)."<br/>";
            echo "TransitionMask: ".decbin($TransitionMask)." EngineReadyStates ".decbin($this->EngineReadyStates)." DispatchState: $this->DispatchState<br/>";
        }

        if (self::EngineDebug) {
            echo "<strong>nextTransition</strong> Transitions: ".var_export($TransitionStates, true)."<br/>";
        }

        $EngineStateMask = $this->EngineStateMask;
        $newStatesMask = $this->executeTransition($TransitionMask, $TransitionStates);
        /**
         * transitioned true when
         *  Origin cleared from $this->EngineStateMask or
         *  Target State changed in $newStatesMask
         */
        $transitioned = ($EngineStateMask !== $this->EngineStateMask)
                        || ($newStatesMask !== 0);
        $this->DispatchLastTransitions[$this->DispatchCycle][$this->DispatchStateMask] |= $TransitionMask; // collect all Transitions emitted by State this DispatchCycle

        /**
         * When transitioned and this state is part of an active Sync or Merge, run this state again
         *  to give it a chance to emit the transition Sync or Merge is waiting for.
         */
        $this->EngineReadyStates |= $newStatesMask; // Fold in new states to run next cycle
        if (self::EngineDebug) {
            $eMessage = 'transitioned: '. ($transitioned ? 'True' : 'False') .' EngineReadyStates: '.$this->StateNamesFromMask($this->EngineReadyStates).' WorkflowState(s): '.$this->StateNamesFromMask($this->WorkflowStateMask).' EngineState(s): '.$this->StateNamesFromMask($this->EngineStateMask);
            echo "<strong>&nbsp;endTransition</strong> $eMessage <br/>";
            Log::debug(" endTransition $eMessage \n\r");
        }
        return $transitioned;
    }

    /**
     * executeTransition
     * Find the candidate states for the transition being emitted
     * and do the transition. Origin state will be cleared & progress recorded for special transitions.
     * There maybe no Candidate states for combined special transitions, such as Merge being Synced.
     * Target States are optimised so highest precendet special trantions will set target States.
     * Precedence: Sync>Merge>Fork>Split
     *
     * @param String $TransitionName the transition we are to do, only one bit should be set
     * @param Int $TransitionMask the transition we are to do, only one bit should be set
     * @param Array $TransitionStates - element of $this->MaskTransitions. Key is OriginMask.
     *
     * @return Int result of transition; new States set considering Fork/Merge/Split/Sync rule with current WorkFlow state
     */
    private function executeTransition($TransitionMask, $TransitionStates): int
    {
        if (self::EngineDebug) {
            echo "<strong>executeTransition</strong> TransitionMask: ".decbin($TransitionMask) ."<br/>";
        }

        $candidateStatesMask = $this->findOrigin($this->DispatchStateMask, $TransitionStates);
        if (self::EngineDebug) {
            echo "<strong>executeTransition</strong> found OriginState: $this->DispatchState candidateStatesMask: ".decbin($candidateStatesMask)." EngineStateMask: ".decbin($this->EngineStateMask)."<br/>";
        }
        $newStatesMask = $this->doTransition($TransitionMask, $TransitionStates, $candidateStatesMask);
        if (self::EngineDebug) {
            echo("&nbsp;executedTransition: newStatesMask: ".decbin($newStatesMask)." EngineReadyStates: ".decbin($this->EngineReadyStates)." EngineStateMask: ".decbin($this->EngineStateMask)."  <br/>");
        }

        return $newStatesMask;
    }

    /**
     * scanInProgress
     * When there are other transitions required from this OriginState
     * check the special transitions in progress to see if this state
     * should run again to emit more transitions next dispatch cycle.
     * Goal to to ensure behavior is consistent despite order of transition emitted.
     * Transitions can be all emitted at once (executed in order of definition) or
     * emitted one at a time in any order.
     * Behaviour is not the same either way, combined special transitions
     * will be different, depending on order and whether a state is rerun or not as a result
     * of a particular transition. For example, a Fork transition first will rerun the state for the other
     * Fork transitions. Emit a non-Fork first, state is not rerun since Fork is not active.
     *
     *  Target states have been optimised based on transition precendece: Sync > Merge > Fork > Split.
     *
     * @param Int $OriginStateMask the current state in transition
     *
     * @return Int $OriginStateMask when transition in progress for this OriginState to run again
     */
    private function scanInProgress(int $OriginStateMask): int
    {
        if (self::EngineDebug) {
            $OriginState = $this->MaskStates[$OriginStateMask]['State'];
            echo "<strong>scanInProgress</strong>: OriginState: $OriginState OriginMask: ".decbin($OriginStateMask). '<br/>';
            echo "&nbsp;LastTransitions: ".$this->formatLastTransitions($this->DispatchLastTransitions[$this->DispatchCycle]). '<br/>';
        }
        /**
         * nextStatesMask will contain OriginStateMask when it needs to run again to emit transition
         * for an in progress special transition.
         */
        $nextStatesMask = 0;

        $nextStatesMask |= $this->scanMergeProgress();

        $nextStatesMask |= $this->scanSyncProgress();

        $nextStatesMask |= $this->scanForkProgress();

        return $nextStatesMask;
    }

    /**
     * scanMergeProgress
     * When OriginState is part of an active Merge, it must run until it emits the Merge transition.
     *
     * @param Int $OriginStateMask the current state in transition
     *
     * @return Int $nextStatesMask when transition in progress for any OriginState this dispatch cycle
     */
    private function scanMergeProgress(): int
    {
        $nextStatesMask = 0;
        foreach ($this->MergeStates as $TargetMask => $Progress) {
            foreach ($Progress as $MergeTransitionMask => $progressOriginsMask) {
                foreach ($this->DispatchLastTransitions[$this->DispatchCycle] as $dispatchOriginMask => $dispatchTransitionsMask) {
                    if (isset($this->MaskStates[$dispatchOriginMask]['MergeOrigins'][$MergeTransitionMask])) {
                        if (($progressOriginsMask & $dispatchOriginMask) === 0) { // not emitted transition yet?
                            if (self::EngineDebug) {
                                $eMessage = ': Merge '. $this->TransitionNamesFromMask($MergeTransitionMask).' rerun Origin: '.
                                    $this->StateNamesFromMask($dispatchOriginMask).' progress Origin(s): '.$this->StateNamesFromMask($progressOriginsMask);
                                echo "<strong>scanMergeProgress</strong>$eMessage<br/>";
                                Log::debug("scanMergeProgress$eMessage \n\r");
                            }
                            $nextStatesMask |= $dispatchOriginMask; //do it again
                        }
                    }
                }
            }
        }
        return $nextStatesMask;
    }

    /**
     * scanSyncProgress
     * When OriginState is part of an active Sync, it must run until it emits the Sync transition
     * without causing Workflow to stall.
     * Single Origin may have multiple Sync transitions emitted independently.
     * When part of an active Merge, let Merge handle it until Merge completes.
     *
     * @param Int $OriginStateMask the current state in transition
     *
     * @return Int $nextStatesMask when transition in progress for any OriginState this dispatch cycle
     */
    private function scanSyncProgress(): int
    {
        $nextStatesMask = 0;
        foreach ($this->SyncStates as $TargetMask => $Progress) { // any Sync in progress?
            foreach ($Progress as $syncTransitionsMaskInProgress => $syncProgress) {
                $progressTransitionsMask = $syncProgress; //['TransitionsMask'];
                foreach ($this->DispatchLastTransitions[$this->DispatchCycle] as $dispatchOriginMask => $dispatchTransitionsMask) {
                    foreach ($this->MaskStates[$dispatchOriginMask]['SyncOrigins'] ?? [] as $syncTransitionsMask => $sync) {
                        if ($TargetMask === $sync['TargetMask']  // same target?
                        &&  $syncTransitionsMaskInProgress === $syncTransitionsMask ) { // correct transitions to target?
                            $mergeTransitionsMask = $this->MaskStates[$dispatchOriginMask]['MergeTargetTransitions'][$TargetMask] ?? 0;
                            $requiredTransitionsMask = $sync['TransitionsMask'] & ~$progressTransitionsMask;
                            if ($requiredTransitionsMask !== 0 // required transition still?
                            && ($mergeTransitionsMask & $requiredTransitionsMask) === 0 ) { // not a Merge in progress?
                                $nextStatesMask |= $dispatchOriginMask; //do it again
                                if (self::EngineDebug) {
                                    $eMessage = ': Sync transitions '.decbin($syncTransitionsMask).' rerun Origin: '.$this->StateNamesFromMask($dispatchOriginMask).
                                    ' progress Transition(s): '.$this->TransitionNamesFromMask($progressTransitionsMask);
                                    echo "<strong>scanSyncOrigin</strong>$eMessage<br/>";
                                    Log::debug("scanSyncOrigin$eMessage \n\r");
                                }
                            }
                        }
                    }
                }
            }
        }
        return $nextStatesMask;
    }

    /**
     * scanForkProgress
     * When OriginState is part of an active Fork, it must run until it emits the Fork transitions.
     *
     * @param Int $OriginStateMask the current state in transition
     *
     * @return Int $nextStatesMask when transition in progress for any OriginState this dispatch cycle
     */
    private function scanForkProgress(): int
    {
        $nextStatesMask = 0;
        foreach ($this->DispatchLastTransitions[$this->DispatchCycle] as $dispatchOriginMask => $dispatchTransitionsMask) {
            foreach ($this->ForkStates[$dispatchOriginMask] ?? [] as $ForkTransitionsMask => $Progress) { // any Fork in progress?
                if (isset($this->MaskStates[$dispatchOriginMask]['ForkTargets'][$ForkTransitionsMask])) {
                    $requiredTransitionsMask = $Progress['TransitionsMask'] ^ $ForkTransitionsMask;
                    if (self::EngineDebug) {
                        $eMessage = ': Fork transitions '.decbin($ForkTransitionsMask).' rerun Origin: '.$this->StateNamesFromMask($dispatchOriginMask).
                            ' required Transition(s): '.$this->TransitionNamesFromMask($requiredTransitionsMask);
                        echo "<strong>scanForkProgress</strong>$eMessage<br/>";
                        Log::debug("scanForkProgress$eMessage \n\r");
                    }
                    $nextStatesMask |= $dispatchOriginMask; //do it again
                }
            }
        }
        return $nextStatesMask;
    }

    /**
     * findOrigin
     * find which Origin states match current StateTransition
     * return the Target States to be set by this StateTransition
     *
     * @param Int $OriginStateMask the current state to transition, it might not be possible to do
     * @param Array $Transitions element of $this->MaskTransitions for the current Transition - indexed by OriginMask
     *
     * @return Int $targetStatesMask possible TargetStates from this OriginState
     */
    private function findOrigin(int $OriginStateMask, array $Transitions): ?int
    {
        if (self::EngineDebug) {
            echo "<strong>findOrigin</strong>: OriginStateMask: ".decbin($OriginStateMask)."<br/>";
        }
        if (isset($Transitions['Split'][$OriginStateMask])) {
            return $Transitions['Split'][$OriginStateMask]['TargetsMask'];
        }
        if (! isset($Transitions[$OriginStateMask])) {
            throw new \RuntimeException(__METHOD__.":No Origin State '$this->DispatchState' in Transitions. EngineStates: ".
                $this->StateNamesFromMask($this->EngineStateMask));
        }

        if (self::EngineDebug) {
            echo "<strong>foundOrigin</strong>: TargetMask: ".decbin($Transitions[$OriginStateMask]['TargetMask'])."<br/>";
        }
        return $Transitions[$OriginStateMask]['TargetMask'];
    }

    /**
     * doTransition
     * Examine the Transition and apply relevant rules.
     * Try doing each kind of transition, if none of the special types work,
     * simple one state transition it is.
     *
     * Combination Precedence:
     * Sync > Merge > Fork > Split
     * Combination behaviours:
     *  Split transition can be a Fork, Sync or Merge, does not contain any Fork, Sync or Merge targets
     *  Fork transition can be a Split, Sync or Merge, does not contain any Merge or Sync targets, will contain any Split targets
     *  Merge transition can be a Split, Fork or Sync, does not contain any Sync targets, will contain any Split or Fork targets
     *  Sync transition can be a Split, Fork or Merge, will contain any Split, Fork or Merge targets
     *
     * @param String $Transition name of current transition to do
     * @param Int $TransitionMask the current transition, it might not be possible to do
     * @param Array $TransitionStates - element of $this->MaskTransitions. Key is OriginMask.
     * @param Int $candidateStatesMask candidate to check transitions rules comply
     *
     * @return Int $newStatesMask the new states for next Dispatch cycle
     */
    private function doTransition(int $TransitionMask, array $TransitionStates, $candidateStatesMask): int
    {
        if (self::EngineDebug) {
            $eMessage = "doTransition: OriginState: $this->DispatchState Transition: ". $this->MaskTransitions[$TransitionMask]['Name'];
            echo "<strong>$eMessage</strong> TransitionMask: ".decbin($TransitionMask).'<br/>';
            Log::debug("$eMessage TransitionMask: ".decbin($TransitionMask));
        }
        $newStatesMask = 0;

        if ($TransitionMask === 0) {
            $this->ResetStateMask($this->DispatchStateMask); // Declared Null transition - nothing to do
            return $newStatesMask;
        }

        $SimpleTransition = true;

        //Split is Transition based rule
        if (isset($TransitionStates['Split'][$this->DispatchStateMask])) {
            $newStatesMask |= $this->doSplit($TransitionMask, $TransitionStates['Split'][$this->DispatchStateMask]);
            $SimpleTransition = false;
        }

        //Fork is Origin/Transition based rule
        foreach ($TransitionStates['Fork'] ?? [] as $forkTransitionsMask => $Fork) {
            if (($forkTransitionsMask & $TransitionMask) !== 0
              && $Fork['OriginMask'] === $this->DispatchStateMask) {
                $newStatesMask |= $this->doFork($TransitionMask, $Fork);
                $SimpleTransition = false;
            }
        }

        //Merge is Transition based rule - must run before Sync
        foreach ($TransitionStates['Merge'] ?? [] as $mergeOriginsMask => $Merge) {
            if (($mergeOriginsMask & $this->DispatchStateMask) !== 0) {
                $newStatesMask |= $this->doMerge($TransitionMask, $Merge);
                $SimpleTransition = false;
            }
        }

        //Sync is Transition based rule
        foreach ($TransitionStates['Sync'] ?? [] as $syncTransitionsMask => $Sync) {
            if (($syncTransitionsMask & $TransitionMask) !== 0) {
                $newStatesMask |= $this->doSync($TransitionMask, $Sync);
                $SimpleTransition = false;
            }
        }

        if (! $SimpleTransition) {
            return $newStatesMask;
        }

        // to get here its basic 1 step transition, Origin off, Target on.

        $this->ResetStateMask($this->DispatchStateMask);
        return $candidateStatesMask;
    }

    /**
     * doFork
     * When in Fork state,
     * Fork Origin state will run each dispatch cycle until all Fork transitions emitted.
     * Target states not active until all Fork transitions are emitted.
     * Includes Split targets when a Fork is Split.
     *
     * Transitions can be emitted all at once, or one or more each dispatch cycle.
     * Origin state does not have to emit a transition provided other states in the same Dispatch Cycle do.
     * InProgress scan will reun this state to avoid stalling the Workflow.
     *
     * Caution: Engine stops when no new transitions emitted, this will abort if a non-Idle state is active,
     * like a Fork Origin state that has not emitted a required transition and no other active states
     * causes Workflow to stall.
     *
     * @param Int $TransitionMask the Fork transition to do
     * @param Array $Fork states
     *
     * @return Int TargetStates mask Forked from Origin State
     */
    private function doFork(int $TransitionMask, array $Fork): int
    {
        if (self::EngineDebug) {
            echo "<strong>doFork</strong> OriginState: $this->DispatchState Fork: ".var_export($Fork, true) ."<br/>";
        }

        $originMask = $Fork['OriginMask'];
        $forkTransitionsMask = $Fork['TransitionsMask'];
        if (! isset($this->ForkStates[$originMask][$forkTransitionsMask])) {
            $this->ForkStates[$originMask][$forkTransitionsMask]['TransitionsMask'] = 0;
            $this->ForkStates[$originMask][$forkTransitionsMask]['TargetsMask'] = $Fork['TargetsMask'];
        }
        $this->ForkStates[$originMask][$forkTransitionsMask]['TransitionsMask'] |= $TransitionMask;
        $progressTransitionsMask = $this->ForkStates[$originMask][$forkTransitionsMask]['TransitionsMask'];
        /**
         * When all Target States are set - do the transition, unset Origin & set Targets
         */
        if (($progressTransitionsMask ^ $forkTransitionsMask) === 0) { // All transitions emitted?
            $this->ResetStateMask($this->DispatchStateMask); // clear Origin state just transitioned
            unset($this->ForkStates[$originMask][$forkTransitionsMask]);
            if (empty($this->ForkStates[$originMask])) {
                unset($this->ForkStates[$originMask]);
            }
            return $Fork['TargetsMask']; // optimsed for combos
        }
        if (self::EngineDebug) {
            echo "<strong>doFork</strong> progress Transitions: ".$this->TransitionNamesFromMask($progressTransitionsMask)."<br/>";
        }
        return 0; // not ready
    }

    /**
     * doSplit
     * When in Split state,
     * the transition to the Target states all happen on one transition.
     * Simplest transition, turn off Origin turn on Targets.
     *
     * When used in combination with other special transitions, the combined target states
     * will execute according to the other special transition. (optimised during refactor)
     * Uncombine Split targets states will execute next cycle.
     *
     * @param String $Transition the Split transition to do
     * @param Array $Split transition Targets for this OriginState
     *
     * @return Int TargetStates mask split from current Origin State
     */
    private function doSplit(int $TransitionMask, array $Split): int
    {
        if (self::EngineDebug) {
            echo "<strong>doSplit</strong> OriginState: $this->DispatchState Split: ".var_export($Split, true) ."<br/>";
        }

        $this->ResetStateMask($this->DispatchStateMask); // clear Origin state just transitioned
        if (self::EngineDebug) {
            echo "<strong>doSplit</strong> TargetsMask ".decbin($Split['TargetsMask']) .'<br/>';
        }

        return $Split['TargetsMask']; // optimised for combos
    }

    /**
     * doMerge
     * All Merge OriginStates must emit the
     * same transition to the Target state.
     * Target state is active only after all Origin states have emitted Merge transition.
     *
     * @param Int $TransitionMask the Merge transition to do
     * @param Array $Merge state transition
     *
     * @return Int TargetState mask Merge from Origin States
     */
    private function doMerge(int $TransitionMask, array $Merge): int
    {
        if (self::EngineDebug) {
            echo "<strong>doMerge</strong> Merge: ".var_export($Merge, true)."<br/>";
        }
        if ($TransitionMask !== $Merge['TransitionMask']) {
            $eMessage = "FATAL: Engine fail! Merge Origin: $this->DispatchState Transition: ".$this->TransitionNamesFromMask($TransitionMask).
            " not Merge transition: ".$this->TransitionNamesFromMask($Merge['TransitionMask']);
            Log::critical($eMessage. "\n\r");
            throw new \RuntimeException($eMessage);
        }
        $mergeTargetMask = $this->Workflow->StateModel['Merges'][$Merge['Metadata']]['TargetMask'];
        if (! isset($this->MergeStates[$mergeTargetMask][$TransitionMask])) {
            $this->MergeStates[$mergeTargetMask][$TransitionMask] = 0;
        }
        $this->MergeStates[$mergeTargetMask][$TransitionMask] |= $this->DispatchStateMask;
        $progressOriginsMask = $this->MergeStates[$mergeTargetMask][$TransitionMask];
        /**
         * When all Origin States have transitioned, unset Origins & return Target
         */
        $this->ResetStateMask($this->DispatchStateMask); // don't run this state again
        if ($progressOriginsMask === $Merge['OriginsMask']) { // All Origin states set?
            unset($this->MergeStates[$mergeTargetMask][$TransitionMask]);
            if (empty($this->MergeStates[$mergeTargetMask])) {
                unset($this->MergeStates[$mergeTargetMask]);
            }
            return $Merge['TargetMask']; // not metadata, optimised for combos
        }

        if (self::EngineDebug) {
            echo '<strong>doMerge</strong> TargetState: '.$this->MaskStates[$mergeTargetMask]['State'] .' progress OriginsMask: '.decbin($progressOriginsMask)."<br/>";
        }

        return 0; // not ready
    }

    /**
     * doSync
     * When in multiple Sync states, the transition to the Target state only happens
     * on the last Origin state transition,
     * other transitions remove their OriginState only (transitions are synched, not states)
     * When Merge is in progress, do not count the Sync transition until Merge completes.
     * (doMerge runs before doSync)
     *
     * @param Int $TransitionMask the Sync transition to do
     * @param Array $Sync state transitions
     *
     * @return Int TargetState mask Synced from Origin States
     */
    private function doSync(int $TransitionMask, array $Sync): int
    {
        if (self::EngineDebug) {
            echo "<strong>doSync</strong> from $this->DispatchState  Sync: ".var_export($Sync, true) ."<br/>";
        }
        $syncTargetMask = $Sync['TargetMask'];
        $syncTransitionsMask = $Sync['TransitionsMask'];
        if (! isset($this->SyncStates[$syncTargetMask][$syncTransitionsMask])) {
            $this->SyncStates[$syncTargetMask][$syncTransitionsMask] = 0;
        }
        /**
         * When a Merge is being Synced, all Merge Origins must emit the transition being Synced.
         * After Merge in progress is complete, the Sync transition is included here
         */
        if (isset($this->MergeStates[$syncTargetMask][$TransitionMask])) {
            if (self::EngineDebug) {
                echo "<strong>doSync</strong>: Merge in progress - ". $this->MaskTransitions[$TransitionMask]['Name'] ." deferred.<br/>";
            }
        } else {
            $this->SyncStates[$syncTargetMask][$syncTransitionsMask] |= $TransitionMask;
        }
        $this->ResetStateMask($this->DispatchStateMask); // don't run this state again
        /**
         * When a Merge is being Synced, all Merge Origins must emit the transition being Synced
         */
        if (($this->SyncStates[$syncTargetMask][$syncTransitionsMask] ^ $Sync['TransitionsMask']) === 0) { // All Transitions done?
            unset($this->SyncStates[$syncTargetMask][$syncTransitionsMask]); // done with this Sync
            if (empty($this->SyncStates[$syncTargetMask])) {
                unset($this->SyncStates[$syncTargetMask]);
            }
            return $Sync['TargetMask'];
        }

        if (self::EngineDebug) {
            $targetState = $Sync['TargetState'];
            echo "<strong>doSync</strong> to $targetState progress Transition(s): ".
                $this->TransitionNamesFromMask($this->SyncStates[$syncTargetMask][$syncTransitionsMask])."<br/>";
        }
        return 0; // not ready
    }

    /**
     * formatLastTransitions
     * Translate OriginMask & TransitionMask into
     * human readable text for error display.
     *
     * @param Array $LastTransitions last entry from $this->DispatchLastTransitions
     * @return String Error message for exception
     */
    private function formatLastTransitions(array $LastTransitions): string
    {
        $message  = '';
        foreach ($LastTransitions as $OriginMask => $TransitionsMask) {
            $message .= $this->MaskStates[$OriginMask]['State'] .'->'. $this->TransitionNamesFromMask($TransitionsMask).'| ';
        }
        return rtrim($message, '| ');
    }

    /**
     * TransitionNamesFromMask
     * Translate TransitionsMask into
     * human readable text for error display.
     *
     * @param Int $TransitionsMask
     * @return String Error message for exception (hash for empty string)
     */
    private function TransitionNamesFromMask(int $TransitionsMask): string
    {
        if ($TransitionsMask === 0) {
            return 'null';
        }

        $transitionNames = '';
        $bitMask = 1;
        $bit = 1;
        $maskLimit = count($this->MaskTransitions);
        do {
            if ($TransitionsMask & $bitMask) {
                $transitionNames .= $this->MaskTransitions[$bitMask]['Name'] .', ';
            }
            $bitMask <<= 1;
        } while ($bit++ < $maskLimit);

        return empty($transitionNames) ? '#' : rtrim($transitionNames, ', ');
    }

    /**
     * TransitionNameToMask
     * return a bitmask that represent the names provided
     *
     * @param String|Array $Transitions Mask|Name|array of Names or comma separated list
     *
     * @return Int $TransitionsMask
     */
    public function TransitionNameToMask(string|array $Transitions): int
    {
        if (is_string($Transitions)) {
            if ($Transitions) {
                $Transitions = explode(',', str_replace(' ', '', $Transitions));
            } else {
                return 0;
            }
        }
        $TransitionsMask = 0;
        if (is_array($Transitions)) {
            if (self::EngineDebug) {
                echo "<strong>TransitionNameToMask</strong> Transitions: ".var_export($Transitions, true)."<br/>";
            }
            foreach ($Transitions as $Transition) {
                if (empty($Transition)) {
                    continue;
                } // skip extra commas
                if (! isset($this->Workflow->TransitionMasks[$Transition])) {
                    throw new \RuntimeException("ABORT: Invalid transition name: $Transition from state $this->DispatchState");
                }
                $TransitionsMask |= $this->Workflow->TransitionMasks[$Transition];
            }
        }
        return $TransitionsMask;
    }

    /**
     * StateNamesFromMask
     * Use $this->MaskStates to check against bitmask
     * and return matching name.
     * Used by error handler to provide human readable state information
     *
     * @param Int $StatesMask bit mask
     *
     * @return String stateNames comma separated list of State names (hash for empty string)
     */
    public function StateNamesFromMask(int $StatesMask): string
    {
        if ($StatesMask === 0) {
            return '';
        }

        $stateNames = '';
        $bitMask = 1;
        $bit = 1;
        $maskLimit = count($this->MaskStates);
        do {
            if ($StatesMask & $bitMask) {
                $stateNames .= $this->MaskStates[$bitMask]['State'] .', ';
            }
            $bitMask <<= 1;
        } while ($bit++ < $maskLimit);

        return empty($stateNames) ? '#' : rtrim($stateNames, ', ');
    }

    /**
     * StatesMaskFromNames
     * Use $this->Workflow->StateModel['States'] for each name
     * and return matching mask.
     * Used by error handler to provide human readable state information
     *
     * @param String $StateNames comma separated list of State names
     *
     * @return Int $StatesMask bit mask
     */
    public function StatesMaskFromNames(string $StateNames): int
    {
        if (! $StateNames) return 0;

        $stateNames = explode(',', str_replace(' ', '', $StateNames));
        $statesMask = 0;
        $invalidNames = [];
        foreach ($stateNames as $stateName) {
            if (! isset($this->Workflow->StateModel['States'][$stateName])) {
                $invalidNames[] = $stateName;
            } else {
                $statesMask |= $this->Workflow->StateModel['States'][$stateName]['Mask'];
            }
        }
        if (! empty($invalidNames)) {
            throw new \RuntimeException(__METHOD__.' Invalid State name(s): '. implode(', ', $invalidNames));
        }
        if (self::EngineDebug) {
            echo '<strong>StatesMaskFromNames</strong> States: $stateNames  Mask: '.decbin($statesMask).'<br/>';
        }
        return $statesMask;
    }

    /**
     * GetWorkflow
     * Get Workflow arrays for tests to examine
     *
     * @return WorkflowContract $Workflow
     */
    public function GetWorkflow()
    {
        /**
         * Assemble all refactored & reindexed Engine arrays
         * for tests to examine
         */
        $workflow = $this->Workflow;
        $workflow->MaskStates  = $this->MaskStates ;
        $workflow->MaskTransitions = $this->MaskTransitions;
        $workflow->StateHandlers = $this->StateHandlers;
        return $workflow;
    }

    /**
     * SetLogger
     *
     * @param WorkflowLoggerContract $Logger to persist state in DB
     *
     * @return Self
     */
    public function SetLogger(WorkflowLoggerContract $Logger)
    {
        $this->Logger = $Logger;

        return $this;
    }

    /**
     * GetVersionBuild
     *
     * @return Array [SEVersion,SEBuild]
     */
    public function GetVersionBuild(): array
    {
        return [
            'SEVersion' => self::SEVersion,
            'SEBuild' => self::SEBuild,
        ];
    }

    /**
     * GetLogger
     *
     * @return WorkflowLogger $Logger
     */
    public function GetLogger()
    {
        return $this->Logger;
    }

    /**
     * isTerminal
     * Terminal state may also be set with Idle states.
     * Engine stops when only Terminal state is set or only Idle states make no transition.
     * $this->StopEngineStates
     *
     * @return bool $isTerminal true when Engine state is in model Terminal state
     */
    public function isTerminal(): bool
    {
        if(self::EngineDebug) {
            echo "<strong>isTerminal</strong> WorkflowStateMask: ".decbin($this->WorkflowStateMask)." TerminalState mask: ". decbin($this->Workflow->StateModel['TerminalState']['Mask'])."<br/>";
        }
        $maybeTerminal = (($this->WorkflowStateMask & $this->Workflow->StateModel['TerminalState']['Mask']) !== 0); // terminal state set

        return $maybeTerminal && (($this->WorkflowStateMask ^ $this->Workflow->StateModel['TerminalState']['Mask']) === 0); // and no others
    }

    /**
     * isIdle
     * Idle states don't have to transition, Engine stops if only Idle states are set.
     * Previous transition will be the transition that lead to the Idle state, when its Idle.
     * If this is an Idle state and transition mask is same as LastTransition that happened (skip back over null transitions from other states)
     * nothing to do.
     * DispatchLastTransitions[0] has LastTransition of previous Workflow run when resumed, it counts towards not doing anything when idle state doesn't transition.
     * Reason for this check is to ensure the Idle State executes at least once each time Workflow runs. Give it a chance to transition to next State.
     *
     *  Once paused in Idle state, the controller (or other external module) must call the Engine to resume the Workflow.
     *  When a Logger is implemented, the program can exit and be restarted at a later time to resume from the saved Workflow state.
     *    This allows a handoff for workflow to be resumed at a later date, maybe by another person in a different job role.
     *
     * @return Bool $isIdle true when Engine state is only in Idle states
     */
    public function isIdle(): bool
    {
        if ($this->DispatchCycle == 0) {
            return false;
        }
        $LastTransitions = $this->GetWorkflowState();
        $LastTransitionMask = $LastTransitions['transitions'] ?? 0;
        if (self::EngineDebug) {
            echo "<strong>isIdle</strong> WorkflowState: ".decbin($this->WorkflowStateMask)." DispatchCycle: $this->DispatchCycle  StateModel IdleStatesMask: ".
                decbin($this->Workflow->StateModel['Idle']['StatesMask'])." LastTransitionMask: ".decbin($LastTransitionMask).
                " ".var_export($this->DispatchLastTransitions, true)."<br/>";
        }
        // All States are Idle and DispatchLastTransition is same as the current one. (going nowhere)
        return  (($this->WorkflowStateMask & $this->Workflow->StateModel['Idle']['StatesMask']) === $this->WorkflowStateMask);
    }

    /**
     * GetWorkflowState
     *  All transitions that lead to Engine stop (All Idle or Terminal)
     *  available to State procedures when Workflow resumed.
     *
     * Transitions from previous cycle available to State Handlers
     *  so they can make context based decisions how to proceed.
     *
     * Other states may have executed this dispatch cycle,
     * their transitions cannot effect any state running in current cycle.
     *
     * @return Array lastTransitionStates bitmask, LastDispatchTransitions bitmask
     */
    public function GetWorkflowState(): array
    {
        $DispatchLastTransitions = $this->GetLastTransitionHistory();
        $lastTransitionStates = 0;
        $lastTransitions = 0;
        foreach ($DispatchLastTransitions as $stateMask => $transitionMask) {
            $lastTransitions |= $transitionMask;
            $lastTransitionStates |= $stateMask;
        }
        return ['states' => $lastTransitionStates,
                'transitions' => $lastTransitions,
                'history' => $DispatchLastTransitions,
               ];
    }

    /**
     * GetTransitionNames
     * Return Transitions for current state.
     * Each Workflow State manages their transitions explicitly, unless
     *  running as a pipeline.
     *
     * @param Int $TransitionsMask
     *
     * @return Array $names
     */
    public function GetTransitionNames(int $TransitionsMask): array
    {
        if ($TransitionsMask) {
            $names = [];
        } else {
            return [];
        }
        foreach ($this->MaskTransitions as $TransitionMask => $Attributes) {
            if (($TransitionsMask & $TransitionMask) === $TransitionMask) {
                $names[] = $Attributes['Name'];
            }
        }
        if (self::EngineDebug) {
            echo '<strong>GetTransitionNames</strong> TransitionsMask: '.decbin($TransitionsMask).' Names: '. var_export($names, true).'<br/>';
        }
        return $names;
    }

    /**
     * GetLastTransitionHistory
     *
     * Get the Last Dispatch Cycle state transitions array from Engine.
     * Return array has one entry for each State that emitted a transition
     * during the previous dispatch cycle.
     * Must be previous cycle to get transition to current Origin state.
     *
     * @param Int $OriginMask - optional specific Origin history to return
     *
     * @return Array $DispatchLastTransitions [OriginMask => TransitionsMask]
     */
    public function GetLastTransitionHistory(int $OriginMask = null): array
    {
        if ($this->DispatchCycle < 2) {
            return ($OriginMask) ? [$OriginMask => 0] : [];
        }
        $LastTransitionIdx = $this->DispatchCycle - 1;

        if (self::EngineDebug) {
            echo "<strong>GetLastTransitionHistory</strong> Cycle: $LastTransitionIdx History: ".
                var_export($this->DispatchLastTransitions[$LastTransitionIdx], true).'<br/>';
        }
        if ($OriginMask) {
            $history = $this->DispatchLastTransitions[$LastTransitionIdx][$OriginMask] ?? 0;
            return [$OriginMask => $history];
        } else {
            return $this->DispatchLastTransitions[$LastTransitionIdx];
        }
    }

    /**
     * GetTransitionHistory
     *
     * Get the current Dispatch Cycle state transitions array from Engine.
     * Return array has one entry for each State that emitted a transition
     * so far this dispatch cycle.
     *
     * @param Int $OriginMask - optional specific Origin history to return
     *
     * @return Array current DispatchCycle and TransitionHistory
     */
    public function GetTransitionHistory(int $OriginMask = null): array
    {
        if (self::EngineDebug) {
            echo "<strong>GetLastTransitionHistory</strong> Cycle: $this->DispatchCycle History: ".
                var_export($this->DispatchLastTransitions[$this->DispatchCycle], true).'<br/>';
        }
        if ($OriginMask) {
            $history[$OriginMask] = $this->DispatchLastTransitions[$this->DispatchCycle][$OriginMask] ?? 0;
        } else {
            $history = $this->DispatchLastTransitions[$this->DispatchCycle];
        }
        $history['DispatchCycle'] = $this->DispatchCycle;
        return $history;
    }
    /**
     * GetEngineStateMask
     *
     * @return Int State bit mask
     */
    public function GetEngineStateMask(): int
    {
        return $this->EngineStateMask;
    }

    /**
     * GetExecutedStatesMask
     *
     * @return Int State bit mask
     */
    public function GetExecutedStatesMask(): int
    {
        return $this->EngineExecutedStates;
    }

    /**
     * GetWorkflowStateMask
     * (getStateMask)
     *
     * @return Int State bit mask
     */
    public function GetWorkflowStateMask(): int
    {
        return $this->WorkflowStateMask;
    }

    /**
     * SetStateReadyMask
     * Add new states to the current Engine Ready state for next dispatch cycle
     *
     * @param Int $StateMask the states being set for next cycle
     *
     * @return Self
     */
    private function SetStateReadyMask(int $StateMask): self
    {
        $this->EngineReadyStates |= $StateMask;

        return $this;
    }

    /**
     * ResetStateMask
     * Clear states in Engine working state, set in Executed state.
     * As transitions complete the Origin State is cleared
     * from Engine state.
     *
     * @param Int $StateMask Origin states cleared this cycle
     *
     * @return Self $StateEngine $this object
     */
    private function ResetStateMask(int $StateMask): self
    {
        $this->EngineStateMask &= ~$StateMask;
        return $this;
    }

    /**
     * deferTerminalState
     * Clear terminal state from Engine & Workflow state.
     *
     * @param Int $StateMask Engine states this dispatch cycle
     *
     * @return Int $StateMask with Terminal state removed
     */
    private function deferTerminalState(int $StateMask): int
    {
        $this->DeferTerminalState = $this->Workflow->StateModel['TerminalState']['Mask'];
        if (self::EngineDebug) {
            echo "<strong>deferTerminalState</strong> DispatchCycle: $this->DispatchCycle EngineReadyStates: $StateMask <br/>";
        }
        return ($StateMask & ~$this->DeferTerminalState);
    }

    /**
     * GetDispatchStatus
     * Current Dispatch Status is which state is executing this dispatch cycle.
     *
     * @return Int $DispatchStateMask
     */
    public function GetDispatchStatus(): int
    {
        return $this->DispatchStateMask;
    }

    /**
     * isResumed
     * Called by CreateAppContext to start in correct state (Resumed or Initial)
     *
     * @return Bool true if ResumedStateMask is set
     */
    public function isResumed(): bool
    {
        if (self::EngineDebug) {
            echo "<strong>isResumed</strong> DispatchCycle: $this->DispatchCycle ResumedStateMask: $this->ResumedStateMask <br/>";
        }

        return isset($this->ResumedStateMask) ? $this->ResumedStateMask : false;
    }

    /**
     * isResumedState
     *
     * @param Int $StateMask the state inquiring if its being resumed from a previous engine
     *
     * @return Bool true $StateMask a ResumedState
     */
    public function isResumedState(int $StateMask): bool
    {
        if (self::EngineDebug) {
            echo "<strong>isResumedState</strong> DispatchCycle: $this->DispatchCycle ".(($this->ResumedStateMask & $StateMask) !== 0) ? 'true' : 'false'.' <br/>';
        }

        return ($this->ResumedStateMask & $StateMask) !== 0;
    }

    /**
     * AlterWorkflow
     * Alter a workflow for testing.
     * Intended for test with minor changes to model rather than make a whole new model almost identical.
     * Alterations implemented so far:
     *      ['Delete' => ['StateTransition' => ['Sx','Tx']]] // State,Transition
     *      ['Delete' => ['SyncOrigin' => ['Sx','Tx']]]
     *      ['Delete' => ['Split' => 'Tx']] // Transition
     *
     * @param Array $Actions
     *
     * @return void
     */
    public function AlterWorkflow(array $Actions)
    {
        if (self::EngineDebug) {
            echo "<strong>AlterWorkflow</strong><br/>";
        }
        foreach ($Actions as  $function => $alteration) {
            foreach ($alteration as $object => $param) {
                switch ($function) {
                    case 'Replace':
                        switch ($object) {
                            case 'SyncTransitions':
                                foreach ($this->Workflow->StateModel['Syncs'] as $idx => $targetTransitions) {
                                    if ($targetTransitions['TargetState'] == $param['TargetState']) {
                                        $this->Workflow->StateModel['Syncs'][$idx] = $param;
                                        break;
                                    }
                                }
                                break;
                        }
                        break;
                    case 'Delete':
                        switch ($object) {
                            case 'StateTransition':
                                /**
                                 * delete entire StateTransition given one state/transition to identify
                                 */
                                [$State, $Transition] = $param;
                                $originMask = $this->Workflow->StateModel['States'][$State]['Mask'];
                                $transitionMask = $this->Workflow->TransitionMasks[$Transition];
                                foreach ($this->Workflow->StateModel['StateTransitions'][$State] as $idx => $transition) {
                                    if ($transition['Transition'] == $Transition) {
                                        unset($this->Workflow->StateModel['StateTransitions'][$State][$idx]);
                                        unset($this->Workflow->StateModel['StateTransitions'][$State]['autoTransitionMask']);
                                        break;
                                    }
                                }
                                if (empty($this->Workflow->StateModel['StateTransitions'][$State])) {
                                    unset($this->Workflow->StateModel['StateTransitions'][$State]);
                                }
                                foreach ($this->Workflow->StateModel['Splits'] as $idx => $transitions) {
                                    if ($transitions['OriginState'] == $State && reset($transitions['Transition']) == $Transition) {
                                        unset($this->Workflow->StateModel['Splits'][$idx]);
                                        break;
                                    }
                                }
                                foreach ($this->Workflow->StateModel['Merges'] as $idx => $transitions) {
                                    if (isset($this->MaskStates[$originMask]['MergeOrigins'][$transitionMask])) {
                                        unset($this->MaskStates[$originMask]['MergeOrigins'][$transitionMask]);
                                        if (empty($this->MaskStates[$originMask]['MergeOrigins'])) {
                                            unset($this->MaskStates[$originMask]['MergeOrigins']);
                                        }
                                        break;
                                    }
                                }
                                unset($this->MaskTransitions[$transitionMask][$originMask]);
                                $this->MaskStates[$originMask]['TransitionsMask'] ^= $transitionMask; // remove state transition
                                break;
                            case 'SyncOrigin':
                                /**
                                 * delete entire Sync given one state/transition to identify
                                 */
                                [$State, $Transition] = $param;
                                $originMask = $this->Workflow->StateModel['States'][$State]['Mask'];
                                $transitionMask = $this->Workflow->TransitionMasks[$Transition];
                                foreach ($this->Workflow->StateModel['Syncs'] as $idx => $syncTransition) {
                                    if (($originMask & $syncTransition['OriginsMask']) !== 0
                                     && ($transitionMask & $syncTransition['TransitionsMask']) !== 0) {
                                        unset($this->Workflow->StateModel['Syncs'][$idx]);
                                        $syncTransitionsMask = $syncTransition['TransitionsMask'];
                                        foreach ($syncTransition['Transitions'] as $Transition) {
                                            $transitionMask = $this->Workflow->TransitionMasks[$Transition];
                                            unset($this->MaskTransitions[$transitionMask]['Sync'][$syncTransitionsMask]);
                                            if (empty($this->MaskTransitions[$transitionMask]['Sync'])) {
                                                unset($this->MaskTransitions[$transitionMask]['Sync']);
                                            }
                                            unset($this->MaskStates[$originMask]['SyncOrigins'][$syncTransitionsMask]);
                                            if (empty($this->MaskStates[$originMask]['SyncOrigins'])) {
                                                unset($this->MaskStates[$originMask]['SyncOrigins']);
                                            }
                                        }
                                        break;
                                    }
                                }
                                break;

                            case 'Split':
                                /**
                                 * delete entire Split given one state/transition to identify
                                 */
                                [$State, $Transition] = $param;
                                $originMask = $this->Workflow->StateModel['States'][$State]['Mask'];
                                $transitionMask = $this->Workflow->TransitionMasks[$Transition];
                                foreach ($this->Workflow->StateModel['Splits'] as $idx => $splitTransition) {
                                    if ($splitTransition['OriginState'] == $State && reset($splitTransition['Transition']) == $Transition) {
                                        unset($this->Workflow->StateModel['Splits'][$idx]);
                                        unset($this->MaskTransitions[$transitionMask]['Split'][$originMask]);
                                        if (empty($this->MaskTransitions[$transitionMask]['Split'])) {
                                            unset($this->MaskTransitions[$transitionMask]['Split']);
                                        }
                                        break;
                                    }
                                }
                                break;
                        }
                        break;
                }
            }
        }

    }
}
