<?php
namespace PhpExceptionFlow\ScopeVisitor;

use PhpExceptionFlow\AstVisitor\CallCollector;
use PhpExceptionFlow\CallGraphConstruction\CallResolverInterface;
use PhpExceptionFlow\Scope;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeTraverserInterface;

//todo: this can maybe better be done in the ScopeCollector, as looping again over all scopes
// and their instructions is maybe not super efficient
class CallToScopeLinkingVisitor extends AbstractScopeVisitor {
	/** @var NodeTraverserInterface $ast_traverser */
	private $ast_traverser;
	/**@var \SplObjectStorage|Scope[][] */
	private $scope_calls_scopes;
	/**@var \SplObjectStorage|Scope[][] */
	private $scope_called_by_scopes;
	/** @var CallResolverInterface $call_resolver*/
	private $call_resolver;
	/** @var CallCollector $call_collector */
	private $call_collector;

	/** @var \SplObjectStorage|Node[][] */
	private $unresolved_calls;

	public function __construct(NodeTraverser $ast_traverser, CallCollector $call_collector, CallResolverInterface $call_resolver) {
		$this->call_resolver = $call_resolver;

		$this->scope_calls_scopes = new \SplObjectStorage;
		$this->unresolved_calls = new \SplObjectStorage;
		$this->scope_called_by_scopes = new \SplObjectStorage;
		$this->ast_traverser = $ast_traverser;
		$this->call_collector = $call_collector;
		$this->ast_traverser->addVisitor($call_collector);
	}

	public function enterScope(Scope $scope) {
		$this->ast_traverser->traverse($scope->getInstructions());
		/** @var Node[] $calls */
		$calls = $this->collectCalls();
		$unresolved = [];
		$resolved = [];
		foreach ($calls as $call) {
			try {
				$resolved_call = $this->call_resolver->resolve($call);
				if (in_array($resolved_call, $resolved, true) === false) {
					$resolved[] = $resolved_call;
				}
			} catch (\UnexpectedValueException $exception) {
				$unresolved[] = $call;
			}
		}

		$this->scope_calls_scopes[$scope] = $resolved;
		$this->unresolved_calls[$scope] = $unresolved;
		foreach ($resolved as $resolved_scope) {
			$this->addCaller($resolved_scope, $scope);
		}

		$this->call_collector->reset();
	}

	public function getCallerCallsCalleeScopes() {
		return $this->scope_calls_scopes;
	}

	public function getCalleeCalledByCallerScopes() {
		return $this->scope_called_by_scopes;
	}

	public function getUnresolvedCalls() {
		return $this->unresolved_calls;
	}

	/**
	 * @return Node[]
	 */
	private function collectCalls() {
		$calls = [];
		foreach ($this->call_collector->getFunctionCalls() as $func_call) {
			$calls[] = $func_call;
		}
		foreach ($this->call_collector->getMethodCalls() as $method_call) {
			$calls[] = $method_call;
		}
		foreach ($this->call_collector->getStaticCalls() as $static_call) {
			$calls[] = $static_call;
		}
		return $calls;
	}

	/**
	 * Adds caller to the callers of callee if that is not already the case
	 * @param Scope $callee
	 * @param Scope $caller
	 */
	private function addCaller(Scope $callee, Scope $caller) {
		if ($this->scope_called_by_scopes->contains($callee) === true) {
			$callers = $this->scope_called_by_scopes[$callee];
			if (in_array($caller, $callers, true) === false) {
				$callers[] = $caller;
				$this->scope_called_by_scopes[$callee] = $callers;
			}
		} else {
			$this->scope_called_by_scopes->attach($callee, [$caller]);
		}
	}
}