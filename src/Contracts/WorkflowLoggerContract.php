<?php

namespace MarkusBiggus\StateEngine\Contracts;

use DateTime;

use MarkusBiggus\StateEngine\Models\WFWorkflow;
use MarkusBiggus\StateEngine\Models\WFInstance;
use MarkusBiggus\StateEngine\Models\WFInstanceHandOff;

/**
 * WorkflowLogger object is the AppContext for running the model.
 * The optional service provider will write log records to database
 * when set in StateEngine. State transistions are then recorded by StateEngine using WorkflowLogger.
 *
 * WorkflowLogger service provider must be used if Suspend/Resume or HandOff
 * methods are needed by the Workflow model.
 *
 * Basic operation without logging does not require the service provider but will
 * still instantiate this object to as AppContext whilst StateEngine runs the Workflow model.
 */
interface WorkflowLoggerContract
{
    /**
     * Fundamental methods of (optional) Workflow Logger Service Provider.
     *
     * State Engine will use WorkflowLogger to log State transitions and Workflow handoffs.
     * A HandOff happens when Workflow state is Idle and requires another agent to continue processing.
     * For exmaple, Purchase Order workflow and started by staff requesting a purchase order
     * is suspended until staff in Accounts resume the workflow to make the Purchase Order.
     * Workflow may HandOff again to a Manager for approval before final HandOff to the Staff requesting the Purchase Order
     * with its approval or reason for not being approved.
     */

    /**
     * Find
     * Instantiate a Workflow object for the name provided
     * Workflow Instance must be created or rebuilt, next
     * use:  $WFLogger = Workflow::Find($Workflow);
     *
     * @param string $Workflow Workflow name to find
     *
     * @return WorkflowLogger new Workflow Logger object ($this)
     */
    public static function Find(String $Workflow): WorkflowLoggerContract;

    /**
     * SetSEVersion
     * State Engine version in WFInstance
     * Workflow Instance is not persisted until it Logs its first State
     * use:  $WFLogger = new Workflow::NewInstance($Workflow);
     *
     * @param string $SEVersion State Engine Verion calling this logger
     * @param string $SEVersionBuild State Engine version Build
     *
     * @return WorkflowLogger Workflow Logger object ($this)
     */
    public function SetSEVersion(String $SEVersion, String $SEVersionBuild): WorkflowLoggerContract;

    /**
     * SetWFVersion
     * Record Workflow Model version in WFInstance
     * Workflow Instance is not persisted until it Logs its first State
     *
     * @param string $WFVersion State Model version Engine is using
     * @param string $WFVersionBuild State Model version Build
     *
     * @return WorkflowLogger Workflow Logger object ($this)
     */
    public function SetWFVersion(String $WFVersion, String $WFVersionBuild): WorkflowLoggerContract;

    /**
     * SetInstance
     * Instantiate a logger object for the Workflow provided
     * Workflow Instance is not persisted until it Logs its first State
     * use:  $WFLogger->NewInstance();
     *
     * @param[0] WFInstance $WFInstance (optional) Workflow Instance to resume
     *
     * @return WorkflowLogger Workflow Logger object ($this)
     */
    public function SetInstance(array $params): WorkflowLoggerContract;

    /**
     * Abandon
     * Abandon current Workflow Instance removes all Handoff records
     * Workflow Instance must be transitionwd to a Terminal state by the caller
     * use:  $WFLogger->Abandon($WFInstance);
     *
     * @param WFInstance $WFInstance Workflow Instance to resume
     *
     * @return WorkflowLogger current Workflow Logger object ($this)
     */
    public function Abandon(): WorkflowLoggerContract;

    /**
     * Complete
     * Complete current Workflow  - Instance removes all Handoff records, Set WF_COMPLETION_DATE timestamp
     * Workflow Terminal state calls this method for orderly to end the workflow
     * use:  $WFLogger->Complete($WFInstance);
     *
     * @param WFInstance $WFInstance Workflow Instance to resume
     *
     * @return WorkflowLogger current Workflow Logger object ($this)
     */
    public function Complete(): WorkflowLoggerContract;

    /**
     * GetWorkflow
     * Return WFWorkflow
     *
     * @return int current Workflow Instance Key value (its ID)
     */
    public function GetWorkflow(): WFWorkflow;

    /**
     * GetWorkflowState
     * Return current State and Status to resume the StateEngine
     * [ResumeStateMask, ResumeTransitions] both from WFInstance record
     *
     * @return array
     */
    public function GetWorkflowState(): array;

    /**
     * GetInstance
     * Return WFInstance
     *
     * @return int current Workflow Instance Key value (its ID)
     */
    public function GetInstance(): WFInstance;
    /**
     * GetDispatchStatus
     * Current DispatchStatus from this Worflow Instance being logged
     *
     * @return Int DispatchStatus
     */
    public function GetDispatchStatus(): int;

    /**
     * GetLogTimeStamp
     *
     * @return DateTime last Workflow Instance Logged time stamp
     */
    public function GetLogTimeStamp(); // : DateTime;

    /**
     * GetLastTransition
     *
     * Get the Last transition mask set by most recent Dispatch Cycle
     *
     * @return int last Engine Dispatch Cycle Workflow TransistionMask
     */
    public function GetLastTransition(): int;

    /**
     * SetLastTransition
     * Set the transition mask and return WFInstance log
     * Called after each Dispatch Cycle by the State Engine with
     * transitions that created the current state mask.
     * (some current states may be from transitions of previous cycles not in this transition mask)
     *
     * @param int $WFTransitionMask Workflow Transition bitmask to replace
     *
     * @return WorkflowLogger current Workflow Logger object ($this)
     */
    public function SetLastTransition(int $WFTransitionMask): WorkflowLoggerContract;

    /**
     * GetState
     *
     * @return int current Workflow StateMask
     */
    public function GetState(): int; // Get Mask

    /**
     * SetState
     * Set the state and return WFInstance log
     *
     * @param int $WFStateMask Workflow State bitmask to replace
     *
     * @return WorkflowLogger current Workflow Logger object ($this)
     */
    public function SetState(int $WFStateMask): WorkflowLoggerContract;

    /**
     * AddState
     * Add Mask bits in State

     * @param int $WFStateMask Workflow State bitmask to OR
     *
     * @return WorkflowLogger current Workflow Logger object ($this)
     */
    public function AddState(int $WFStateMask): WorkflowLoggerContract;

    /**
     * ClrState
     * Unset Mask bits in State
     * @param int $WFStateMask Workflow State bitmask to XOR
     *
     * @return WorkflowLogger current Workflow Logger object ($this)
     */
    public function ClrState(int $WFStateMask): WorkflowLoggerContract;

    /**
     * LogState
     *
     * Set the state mask and return the saved WFInstance log
     *
     * @param int $WFStateMask Workflow State bitmask to replace
     *
     * @return WorkflowLogger current Workflow Logger object ($this)
     */
    public function LogState(int $WFStateMask = null): WorkflowLoggerContract;
}
