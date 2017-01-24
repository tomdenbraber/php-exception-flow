<?php
namespace PhpExceptionFlow\ScopeVisitor;

use PhpExceptionFlow\AstVisitor\CallCollector;
use PhpExceptionFlow\Scope;
use PhpParser\NodeTraverser;
use PhpParser\NodeTraverserInterface;

//todo: this can maybe better be done in the ScopeCollector, as looping again over all scopes
// and their instructions is maybe not super efficient
class CallToScopeLinkingVisitor extends AbstractScopeVisitor {
	/** @var \SplObjectStorage $scope_to_call */
	private $scope_to_call;
	/** @var NodeTraverserInterface $ast_traverser */
	private $ast_traverser;

	public function __construct() {
		$this->scope_to_call = new \SplObjectStorage;
		$this->ast_traverser = new NodeTraverser;
	}

	public function enterScope(Scope $scope) {
		$call_collector = new CallCollector;
		$this->ast_traverser->addVisitor($call_collector);
		$this->ast_traverser->traverse($scope->getInstructions());
		$this->scope_to_call[$scope] = $this->collectCalls($call_collector);
		$this->ast_traverser->removeVisitor($call_collector);
	}

	/**
	 * @return \SplObjectStorage
	 */
	public function getScopeToCallMap() {
		return $this->scope_to_call;
	}

	private function collectCalls(CallCollector $call_collector) {
		$calls = [];
		foreach ($call_collector->getFunctionCalls() as $func_call) {
			$calls[] = $func_call;
		}
		foreach ($call_collector->getMethodCalls() as $method_call) {
			$calls[] = $method_call;
		}
		foreach ($call_collector->getStaticCalls() as $static_call) {
			$calls[] = $static_call;
		}
		return $calls;
	}
}