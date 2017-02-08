<?php
namespace PhpExceptionFlow\Scope\ScopeVisitor;

use PhpExceptionFlow\AstVisitor\CallCollector;
use PhpExceptionFlow\CallGraphConstruction\CallResolverInterface;
use PhpExceptionFlow\Scope\Scope;
use PhpParser\Node;
use PhpParser\NodeTraverser;

class CallToScopeLinkingVisitorTest extends \PHPUnit_Framework_TestCase {
	/** @var NodeTraverser */
	private $node_traverser_mock;
	/** @var CallCollector */
	private $call_collector_mock;
	/** @var CallResolverInterface */
	private $call_resolver_mock;

	/** @var CallToScopeLinkingVisitor */
	private $visitor;

	public function setUp() {
		$this->node_traverser_mock = $this->createMock(NodeTraverser::class);
		$this->call_collector_mock = $this->createMock(CallCollector::class);
		$this->call_resolver_mock = $this->createMock(CallResolverInterface::class);

		$this->visitor = new CallToScopeLinkingVisitor($this->node_traverser_mock, $this->call_collector_mock, $this->call_resolver_mock);
	}

	public function testWithNoCallsFromScopeHasNoneResolved() {
		$scope = $this->createMock(Scope::class);
		$scope->expects($this->once())
			->method("getInstructions")
			->willReturn(array());

		$this->call_collector_mock->expects($this->once())
			->method("getFunctionCalls")
			->willReturn(array());

		$this->call_collector_mock->expects($this->once())
			->method("getMethodCalls")
			->willReturn(array());

		$this->call_collector_mock->expects($this->once())
			->method("getStaticCalls")
			->willReturn(array());


		$this->visitor->enterScope($scope);

		$caller_based = $this->visitor->getCallerCallsCalleeScopes();
		$callee_based = $this->visitor->getCalleeCalledByCallerScopes();
		$unresolved = $this->visitor->getUnresolvedCalls();

		$this->assertEquals(0, $callee_based->count());
		$this->assertEquals(1, $caller_based->count()); //automatically an empty entry is added for each scope that is entered
		$this->assertEquals(1, $unresolved->count());   //idem
		$this->assertEmpty($caller_based[$scope]);
		$this->assertEmpty($unresolved[$scope]);
	}

	public function testWithOneCallHasOneResolvedCall() {
		$scope = $this->createMock(Scope::class);
		$callee_scope = $this->createMock(Scope::class);

		$scope->expects($this->once())
			->method("getInstructions")
			->willReturn(array());

		$fn_call_mock = $this->createMock(Node::class);

		$this->call_collector_mock->expects($this->once())
			->method("getFunctionCalls")
			->willReturn(array(
				$fn_call_mock
			));

		$this->call_collector_mock->expects($this->once())
			->method("getMethodCalls")
			->willReturn(array());

		$this->call_collector_mock->expects($this->once())
			->method("getStaticCalls")
			->willReturn(array());

		$this->call_resolver_mock->expects($this->once())
			->method("resolve")
			->with($fn_call_mock)
			->willReturn([$callee_scope]);

		$this->visitor->enterScope($scope);

		$caller_based = $this->visitor->getCallerCallsCalleeScopes();
		$callee_based = $this->visitor->getCalleeCalledByCallerScopes();
		$unresolved = $this->visitor->getUnresolvedCalls();

		$this->assertEquals(1, $callee_based->count());
		$this->assertEquals(1, $caller_based->count());
		$this->assertEquals(1, $unresolved->count());
		$this->assertEquals(array($callee_scope), $caller_based[$scope]);
		$this->assertEquals(array($scope), $callee_based[$callee_scope]);
		$this->assertEquals(array(), $unresolved[$scope]);
	}

	public function testWithRecursiveMethodCall() {
		$scope = $this->createMock(Scope::class);

		$scope->expects($this->once())
			->method("getInstructions")
			->willReturn(array());

		$method_call_mock = $this->createMock(Node::class);

		$this->call_collector_mock->expects($this->once())
			->method("getFunctionCalls")
			->willReturn(array());

		$this->call_collector_mock->expects($this->once())
			->method("getMethodCalls")
			->willReturn(array($method_call_mock));

		$this->call_collector_mock->expects($this->once())
			->method("getStaticCalls")
			->willReturn(array());

		$this->call_resolver_mock->expects($this->once())
			->method("resolve")
			->with($method_call_mock)
			->willReturn([$scope]);

		$this->visitor->enterScope($scope);

		$caller_based = $this->visitor->getCallerCallsCalleeScopes();
		$callee_based = $this->visitor->getCalleeCalledByCallerScopes();
		$unresolved = $this->visitor->getUnresolvedCalls();

		$this->assertEquals(1, $callee_based->count());
		$this->assertEquals(1, $caller_based->count());
		$this->assertEquals(1, $unresolved->count());
		$this->assertEquals(array($scope), $caller_based[$scope]);
		$this->assertEquals(array($scope), $callee_based[$scope]);
		$this->assertEquals(array(), $unresolved[$scope]);
	}

	public function testWithMultipleCalls() {
		$caller_scope = $this->createMock(Scope::class);
		$callee_scope1 = $this->createMock(Scope::class);
		$callee_scope2 = $this->createMock(Scope::class);

		$caller_scope->expects($this->once())
			->method("getInstructions")
			->willReturn(array());

		$calls_scope_1_mock = $this->createMock(Node::class);
		$calls_scope_1_mock_again = $this->createMock(Node::class);
		$calls_scope_2_mock = $this->createMock(Node::class);

		$this->call_collector_mock->expects($this->once())
			->method("getFunctionCalls")
			->willReturn(array());

		$this->call_collector_mock->expects($this->once())
			->method("getMethodCalls")
			->willReturn(array($calls_scope_1_mock, $calls_scope_1_mock_again));

		$this->call_collector_mock->expects($this->once())
			->method("getStaticCalls")
			->willReturn(array($calls_scope_2_mock));

		$this->call_resolver_mock->expects($this->exactly(3))
			->method("resolve")
			->withConsecutive(
				array($calls_scope_1_mock),
				array($calls_scope_1_mock_again),
				array($calls_scope_2_mock))
			->will($this->onConsecutiveCalls(
				[$callee_scope1],
				[$callee_scope1],
				[$callee_scope2]
			));

		$this->visitor->enterScope($caller_scope);

		$caller_based = $this->visitor->getCallerCallsCalleeScopes();
		$callee_based = $this->visitor->getCalleeCalledByCallerScopes();
		$unresolved = $this->visitor->getUnresolvedCalls();

		$this->assertEquals(1, $caller_based->count());
		$this->assertEquals(1, $unresolved->count());
		$this->assertEquals(array($callee_scope1, $callee_scope2), $caller_based[$caller_scope]);
		$this->assertEquals(array($caller_scope), $callee_based[$callee_scope1]);
		$this->assertEquals(array($caller_scope), $callee_based[$callee_scope2]);
		$this->assertEquals(array(), $unresolved[$caller_scope]);
		$this->assertEquals(2, $callee_based->count());
	}
}