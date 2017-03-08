<?php
namespace PhpExceptionFlow;

use PhpExceptionFlow\Scope\Scope;
use PhpParser\Node;
use PHPTypes\Type;

class Exception_Test extends \PHPUnit_Framework_TestCase {

	public function testOnePropagationPathByDefault() {
		$type = $this->createMock(Type::class);
		$initial_cause = $this->createMock(Node\Stmt::class);
		$caused_in = new Scope("a");

		$exception = new Exception_($type, $initial_cause, $caused_in);

		$propagation_paths = $exception->getPropagationPaths();
		$this->assertCount(1, $propagation_paths);
		$this->assertEquals([$caused_in], $propagation_paths[0]->getScopeChain());
	}


	public function testPropagateOnce() {
		$type = $this->createMock(Type::class);
		$initial_cause = $this->createMock(Node\Stmt::class);
		$caused_in = new Scope("a");
		$propagated_to = new Scope("b");

		$exception = new Exception_($type, $initial_cause, $caused_in);
		$exception->propagate($caused_in, $propagated_to);

		$propagation_paths = $exception->getPropagationPaths();
		$this->assertCount(2, $propagation_paths);
		$this->assertEquals([$caused_in], $propagation_paths[0]->getScopeChain());
		$this->assertEquals([$caused_in, $propagated_to], $propagation_paths[1]->getScopeChain());
	}

	public function testPropagateTwiceFromSameStartingPoint() {
		$type = $this->createMock(Type::class);
		$initial_cause = $this->createMock(Node\Stmt::class);
		$caused_in = new Scope("a");
		$propagated_to_1 = new Scope("b");
		$propagated_to_2 = new Scope("c");

		$exception = new Exception_($type, $initial_cause, $caused_in);
		$exception->propagate($caused_in, $propagated_to_1);
		$exception->propagate($caused_in, $propagated_to_2);

		$propagation_paths = $exception->getPropagationPaths();

		$this->assertCount(3, $propagation_paths);
		$this->assertEquals([$caused_in], $propagation_paths[0]->getScopeChain());
		$this->assertEquals([$caused_in, $propagated_to_1], $propagation_paths[1]->getScopeChain());
		$this->assertEquals([$caused_in, $propagated_to_2], $propagation_paths[2]->getScopeChain());
	}
}