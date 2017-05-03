<?php
namespace PhpExceptionFlow;

use PhpExceptionFlow\Path\Catches;
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
		$this->assertEquals([new Raises($caused_in)], $propagation_paths[0]);
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
		$this->assertEquals([new Raises($caused_in)], $propagation_paths[0]);
		$this->assertEquals([new Raises($caused_in), new Propagates($caused_in, $propagated_to)], $propagation_paths[1]);
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
		$this->assertEquals([new Raises($caused_in)], $propagation_paths[0]);
		$this->assertEquals([new Raises($caused_in), new Propagates($caused_in, $propagated_to_1)], $propagation_paths[1]);
		$this->assertEquals([new Raises($caused_in), new Propagates($caused_in, $propagated_to_2)], $propagation_paths[2]);
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
		$this->assertEquals([new Raises($caused_in)], $propagation_paths[0]);
		$this->assertEquals([new Raises($caused_in), new Propagates($caused_in, $propagated_to_1)], $propagation_paths[1]);
		$this->assertEquals([new Raises($caused_in), new Propagates($caused_in, $propagated_to_1), new Propagates($propagated_to_1, $propagated_to_2)], $propagation_paths[2]);
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
		$this->assertEquals([new Raises($caused_in)], $propagation_paths[0]);
		$this->assertEquals([new Raises($caused_in), new Propagates($caused_in, $propagated_to)], $propagation_paths[1]);
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
		$this->assertEquals([new Raises($caused_in)], $propagation_paths[0]);
		$this->assertEquals([new Raises($caused_in), new Uncaught($guarded, $ends_up_in)], $propagation_paths[1]);
	}

	public function testCatches() {
		$type = $this->createMock(Type::class);
		$initial_cause = $this->createMock(Node\Stmt::class);
		$caused_in = new Scope("a");
		$enclosing_scope = new Scope("b");
		$guarded = new GuardedScope($enclosing_scope, $caused_in);
		$catch_clause = $this->createMock(Node\Stmt\Catch_::class);
		$guarded->addCatchClause($catch_clause);

		$exception = new Exception_($type, $initial_cause, $caused_in);
		$exception->catches($caused_in, $catch_clause);

		$propagation_paths = $exception->getPropagationPaths();
		$this->assertCount(2, $propagation_paths);
		$this->assertEquals([new Raises($caused_in)], $propagation_paths[0]);
		$this->assertEquals([new Raises($caused_in), new Catches($caused_in, $catch_clause)], $propagation_paths[1]);
	}

	public function testPathEndsIn() {
		$type = $this->createMock(Type::class);
		$initial_cause = $this->createMock(Node\Stmt::class);
		$caused_in = new Scope("a");
		$enclosing_scope = new Scope("b");
		$guarded = new GuardedScope($enclosing_scope, $caused_in);
		$catch_clause = $this->createMock(Node\Stmt\Catch_::class);
		$guarded->addCatchClause($catch_clause);

		$exception = new Exception_($type, $initial_cause, $caused_in);
		$exception->catches($caused_in, $catch_clause);

		$expected_catches = new Catches($caused_in, $catch_clause);
		$this->assertTrue($exception->pathEndsIn($caused_in)->equals($expected_catches));
		$this->assertFalse($exception->pathEndsIn($enclosing_scope));
	}

	public function testCauseDetection() {
		$type = $this->createMock(Type::class);
		$initial_cause = $this->createMock(Node\Stmt::class);
		$caused_in = new Scope("a");
		$propagated_to = new Scope("b");
		$final_destination = new Scope("c");
		$uncaught_in = new GuardedScope($final_destination, $propagated_to);

		$exception = new Exception_($type, $initial_cause, $caused_in);
		$exception->propagate($caused_in, $propagated_to);
		$exception->uncaught($uncaught_in, $final_destination);

		$scope_a_cause = $exception->getCauses($caused_in);
		$scope_b_cause = $exception->getCauses($propagated_to);
		$scope_c_cause = $exception->getCauses($final_destination);

		$this->assertCount(1, $scope_a_cause["raises"]);
		$this->assertEquals($caused_in, $scope_a_cause["raises"][0]);
		$this->assertCount(0, $scope_a_cause["propagates"]);
		$this->assertCount(0, $scope_a_cause["uncaught"]);

		$this->assertCount(0, $scope_b_cause["raises"]);
		$this->assertCount(1, $scope_b_cause["propagates"]);
		$this->assertEquals($caused_in, $scope_b_cause["propagates"][0]); //the exception is propagated from caused_in to propagated_to
		$this->assertCount(0, $scope_b_cause["uncaught"]);

		$this->assertCount(0, $scope_c_cause["raises"]);
		$this->assertCount(0, $scope_c_cause["propagates"]);
		$this->assertCount(1, $scope_c_cause["uncaught"]);
		$this->assertEquals($propagated_to, $scope_c_cause["uncaught"][0]);//the exception is uncaught in propagated_to and then ends up in final_destination
	}

	/**
	 * This test is just here to see if the exception propagation mechanism is fast enough, which was previously not the case
	 */
	public function testPathCollectionWithLargeNumberOfPaths() {
		$nr = 0;
		$caused_in = new Scope((string)$nr);
		$last_scope = $caused_in;
		$initial_cause = $this->createMock(Node\Stmt::class);
		$type = $this->createMock(Type::class);

		$exception = new Exception_($type, $initial_cause, $caused_in);
		while ($nr < 5000) {
			$nr += 1;
			$propagate_scope = new Scope((string)$nr);
			$exception->propagate($last_scope, $propagate_scope);
			$last_scope = $propagate_scope;
		}

		print "Ok, well done, now calculate the actual paths...\n";
		print count($exception->getPropagationPaths()) . " paths found\n";
	}

	/**
	 * This test is just here to see if the exception propagation mechanism is fast enough, which was previously not the case
	 */
	public function testPathCollectionWithLargeNumberOfPathsWithAllKindsOfBranches() {
		$nr = 0;
		$caused_in = new Scope((string)$nr);
		$last_scope = $caused_in;
		$initial_cause = $this->createMock(Node\Stmt::class);
		$type = $this->createMock(Type::class);
		$scopes = [];

		$exception = new Exception_($type, $initial_cause, $caused_in);
		while ($nr < 100) {
			$scopes[$nr] = $last_scope;

			$nr += 1;
			$propagate_scope = new Scope((string)$nr);
			$random_scope = $scopes[random_int(0, $nr - 1)];
			$exception->propagate($random_scope, $propagate_scope);
			$last_scope = $propagate_scope;
		}

		for ($i = 0; $i < 500; $i++) {
			$origin_scope = $scopes[random_int(0, 99)];
			$propagate_scope = $scopes[random_int(0, 99)];
			$exception->propagate($origin_scope, $propagate_scope);
		}

		print "Ok, well done, now calculate the actual paths...\n";
		print count($exception->getPropagationPaths()) . " paths found\n";
	}
}