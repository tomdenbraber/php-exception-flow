<?php
namespace PhpExceptionFlow;

use PhpExceptionFlow\Path\Propagates;
use PhpExceptionFlow\Path\Raises;
use PhpExceptionFlow\Path\Uncaught;
use PhpExceptionFlow\Scope\GuardedScope;
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
		$this->assertEquals([new Raises($caused_in)], $propagation_paths[0]->getChain());
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
		$this->assertEquals([new Raises($caused_in)], $propagation_paths[0]->getChain());
		$this->assertEquals([new Raises($caused_in), new Propagates($propagated_to)], $propagation_paths[1]->getChain());
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
		$this->assertEquals([new Raises($caused_in)], $propagation_paths[0]->getChain());
		$this->assertEquals([new Raises($caused_in), new Propagates($propagated_to_1)], $propagation_paths[1]->getChain());
		$this->assertEquals([new Raises($caused_in), new Propagates($propagated_to_2)], $propagation_paths[2]->getChain());
	}

	public function testPropagateTwiceToFormChainOfLengthThree() {
		$type = $this->createMock(Type::class);
		$initial_cause = $this->createMock(Node\Stmt::class);
		$caused_in = new Scope("a");
		$propagated_to_1 = new Scope("b");
		$propagated_to_2 = new Scope("c");

		$exception = new Exception_($type, $initial_cause, $caused_in);
		$exception->propagate($caused_in, $propagated_to_1);
		$exception->propagate($propagated_to_1, $propagated_to_2);

		$propagation_paths = $exception->getPropagationPaths();

		$this->assertCount(3, $propagation_paths);
		$this->assertEquals([new Raises($caused_in)], $propagation_paths[0]->getChain());
		$this->assertEquals([new Raises($caused_in), new Propagates($propagated_to_1)], $propagation_paths[1]->getChain());
		$this->assertEquals([new Raises($caused_in), new Propagates($propagated_to_1), new Propagates($propagated_to_2)], $propagation_paths[2]->getChain());
	}

	public function testPropagateSameCallIsOnlyAddedOnce() {
		$type = $this->createMock(Type::class);
		$initial_cause = $this->createMock(Node\Stmt::class);
		$caused_in = new Scope("a");
		$propagated_to = new Scope("b");

		$exception = new Exception_($type, $initial_cause, $caused_in);
		$exception->propagate($caused_in, $propagated_to);
		$exception->propagate($caused_in, $propagated_to);

		$propagation_paths = $exception->getPropagationPaths();
		$this->assertCount(2, $propagation_paths);
		$this->assertEquals([new Raises($caused_in)], $propagation_paths[0]->getChain());
		$this->assertEquals([new Raises($caused_in), new Propagates($propagated_to)], $propagation_paths[1]->getChain());
	}

	public function testUncaught() {
		$type = $this->createMock(Type::class);
		$initial_cause = $this->createMock(Node\Stmt::class);
		$caused_in = new Scope("a");
		$ends_up_in = new Scope("b");
		$guarded = new GuardedScope($ends_up_in, $caused_in);

		$exception = new Exception_($type, $initial_cause, $caused_in);
		$exception->uncaught($guarded, $ends_up_in);

		$propagation_paths = $exception->getPropagationPaths();
		$this->assertCount(2, $propagation_paths);
		$this->assertEquals([new Raises($caused_in)], $propagation_paths[0]->getChain());
		$this->assertEquals([new Raises($caused_in), new Uncaught($ends_up_in, $guarded)], $propagation_paths[1]->getChain());
	}
}