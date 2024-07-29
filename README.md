# stateengine
Finite State Automata for Laravel/PHP Framework

## Overview
This package is a State Engine that executes workflows based on a state model.
Essentially, a finite state automata.

It can work in a similar way to Laravel pipeline if each step in the pipeline is a state with at most one defined
transition to the next state(s). 
Imagine the pipeline twisted into a knot with splits and joins for fully dynamic logic flow.

The StateEngine allows multiple possible transitions from each state to one or more states, according to a state model.
Each State is a class object that has the responsibility for executing the state logic and choosing which transition to emit
when done. A state may be executed more than once, until it emits a transition.
The engine dispatch cycle runs each state in according to the transitions they emit until the Terminal state is executed.
Two dispatch cycles with no transition emitted or state changes will stall the workflow with a Runtime Exception.

If all states in a model have only a single transition choice, the state does not need to emit the transition, it will behave like a pipeline.
The 'Split' transition allows a pipeline to branch multiple paths, each a pipeline running in parallel. 
A state with no transition choice will end a path.


A reference model is included to demonstrate how the state model is implemented.

![Reference model (pdf)](https://github.com/MarkusBiggus/StateEngine/blob/7cdbb9c38f23b9268b1436b4fb7705391e19d9d5/StateEngine-ReferenceModel.pdf)

### Tests
There are tests using the reference model to demonstrate the a Workflow with several different paths through the model.
Additional tests demonstrate other aspects of combining special transitions as well as how the Engine can fail
when transitions are not emitted as required.

![Test models (pdf)](https://github.com/MarkusBiggus/StateEngine/blob/7cdbb9c38f23b9268b1436b4fb7705391e19d9d5/StateEngine-Test-Models.pdf)

### Installation

Use Composer to install the package and all necessary dependencies.

```
composer require markusbiggus/stateengine
```
### Running tests

Publish tests before running.

```
 php artisan vendor:publish --provider="MarkusBiggus\StateEngine\StateEngineProvider"
 php artisan test
```

## MIT License
Copyright (c) 2024 Mark Charles

stateengine is open-source software, licensed under the [MIT license] (LICENSE.md).
