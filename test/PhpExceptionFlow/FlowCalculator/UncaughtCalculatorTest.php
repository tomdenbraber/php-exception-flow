<?php

namespace PhpExceptionFlow\FlowCalculator;

use PhpExceptionFlow\GuardedScope;
use PhpExceptionFlow\Scope;
use PhpExceptionFlow\ScopeVisitor\CaughtExceptionTypesCalculator;
use PhpParser\Node\Stmt\Catch_;

class UncaughtCalculatorTest extends \PHPUnit_Framework_TestCase {
	/** @var UncaughtCalculator $uncaught_calculator */
	private $uncaught_calculator;
	/** @var CaughtExceptionTypesCalculator $catch_clause_type_resolver */
	private $catch_clause_type_resolver;
	/** @var MutableCombiningCalculator $encounters_calculator */
	private $encounters_calculator;

	public function setUp() {
		$this->catch_clause_type_resolver = $this->createMock(CaughtExceptionTypesCalculator::class);
		$this->encounters_calculator = $this->createMock(MutableCombiningCalculator::class);
		$this->uncaught_calculator = new UncaughtCalculator($this->catch_clause_type_resolver, $this->encounters_calculator);
	}

	public function testGetType() {
		$this->assertEquals("uncaught", $this->uncaught_calculator->getType());
	}

	public function testThrowsExceptionOnUndeterminedScope() {
		$scope_mock = $this->createMock(Scope::class);
		$this->expectException(\UnexpectedValueException::class);
		$this->uncaught_calculator->getForScope($scope_mock);
	}

	public function testUncaughtCorrectlyModeledWithOneGuardedScopeWithOneCatchClause() {
		$enclosing_scope_mock = $this->createMock(Scope::class);
		$guarded_scope_mock = $this->createMock(GuardedScope::class);
		$inclosed_scope_mock = $this->createMock(Scope::class);
		$catch_clause_mock = $this->createMock(Catch_::class);

		$enclosing_scope_mock->expects($this->once())
			->method("getGuardedScopes")
			->willReturn(array($guarded_scope_mock));

		$guarded_scope_mock->expects($this->once())
			->method("getInclosedScope")
			->willReturn($inclosed_scope_mock);
		$guarded_scope_mock->expects($this->once())
			->method("getCatchClauses")
			->willReturn(array($catch_clause_mock));

		$this->catch_clause_type_resolver->expects($this->once())
			->method("getCaughtTypesForClause")
			->with($catch_clause_mock)
			->willReturn(array("a", "b"));

		$this->encounters_calculator->expects($this->once())
			->method("getForScope")
			->with($inclosed_scope_mock)
			->willReturn(array("b", "c"));

		$this->uncaught_calculator->determineForScope($enclosing_scope_mock);
		$this->assertEquals(array("c"), $this->uncaught_calculator->getForScope($enclosing_scope_mock));
		$this->assertEquals(array("c"), $this->uncaught_calculator->getForGuardedScope($guarded_scope_mock));
		$this->assertEquals(array("b"), $this->uncaught_calculator->getCaughtExceptions($catch_clause_mock));
	}

	public function testUncaughtCorrectlyModeledWithMultipleGuardedScopesWithMultipleCatchClauses() {
		$enclosing_scope_mock = $this->createMock(Scope::class);
		$guarded_scope_1_mock = $this->createMock(GuardedScope::class);
		$guarded_scope_2_mock = $this->createMock(GuardedScope::class);
		$inclosed_scope_1_mock = $this->createMock(Scope::class);
		$inclosed_scope_2_mock = $this->createMock(Scope::class);
		$catch_clause_1_1_mock = $this->createMock(Catch_::class);
		$catch_clause_1_2_mock = $this->createMock(Catch_::class);
		$catch_clause_2_1_mock = $this->createMock(Catch_::class);

		$enclosing_scope_mock->expects($this->once())
			->method("getGuardedScopes")
			->willReturn(array($guarded_scope_1_mock, $guarded_scope_2_mock));

		//construct guarded scope 1, with two catch clauses
		$guarded_scope_1_mock->expects($this->once())
			->method("getInclosedScope")
			->willReturn($inclosed_scope_1_mock);
		$guarded_scope_1_mock->expects($this->once())
			->method("getCatchClauses")
			->willReturn(array($catch_clause_1_1_mock, $catch_clause_1_2_mock));

		//construct guarded scope 2, with one catch clause
		$guarded_scope_2_mock->expects($this->once())
			->method("getInclosedScope")
			->willReturn($inclosed_scope_2_mock);
		$guarded_scope_2_mock->expects($this->once())
			->method("getCatchClauses")
			->willReturn(array($catch_clause_2_1_mock));


		// set the encounters for the two inclosed scopes
		$this->encounters_calculator->expects($this->exactly(2))
			->method("getForScope")
			->withConsecutive(
				array($inclosed_scope_1_mock),
				array($inclosed_scope_2_mock))
			->will($this->onConsecutiveCalls(
				array("b", "c", "a"),
				array("b")
			));


		// set the types that each exception catches
		$this->catch_clause_type_resolver->expects($this->exactly(3))
			->method("getCaughtTypesForClause")
			->withConsecutive(
				array($catch_clause_1_1_mock),
				array($catch_clause_1_2_mock),
				array($catch_clause_2_1_mock))
			->will($this->onConsecutiveCalls(
				array("b"),
				array("a", "b", "c"),
				array("c")
			));

		$this->uncaught_calculator->determineForScope($enclosing_scope_mock);
		$this->assertEquals(array("b"), $this->uncaught_calculator->getForScope($enclosing_scope_mock)); //from second guarded scope
		$this->assertEquals(array(), $this->uncaught_calculator->getForGuardedScope($guarded_scope_1_mock));
		$this->assertEquals(array("b"), $this->uncaught_calculator->getForGuardedScope($guarded_scope_2_mock));

		$this->assertEquals(array("b"), $this->uncaught_calculator->getCaughtExceptions($catch_clause_1_1_mock));
		$this->assertEquals(array("c", "a"), $this->uncaught_calculator->getCaughtExceptions($catch_clause_1_2_mock));
		$this->assertEquals(array(), $this->uncaught_calculator->getCaughtExceptions($catch_clause_2_1_mock));
	}


	public function testScopeHasChangedWhenNotCoveredYetReturnsTrue() {
		$scope_mock = $this->createMock(Scope::class);
		$this->assertTrue($this->uncaught_calculator->scopeHasChanged($scope_mock));
	}

	public function testScopeHasChangedWhenScopeHasChangedReturnsTrueAndAfterResetReturnsFalse() {
		$enclosing_scope_mock = $this->createMock(Scope::class);
		$guarded_scope_mock = $this->createMock(GuardedScope::class);
		$inclosed_scope_mock = $this->createMock(Scope::class);
		$catch_clause_mock = $this->createMock(Catch_::class);

		$enclosing_scope_mock->expects($this->once())
			->method("getGuardedScopes")
			->willReturn(array($guarded_scope_mock));

		$guarded_scope_mock->expects($this->once())
			->method("getInclosedScope")
			->willReturn($inclosed_scope_mock);
		$guarded_scope_mock->expects($this->once())
			->method("getCatchClauses")
			->willReturn(array($catch_clause_mock));

		$this->catch_clause_type_resolver->expects($this->once())
			->method("getCaughtTypesForClause")
			->with($catch_clause_mock)
			->willReturn(array("a", "b"));

		$this->encounters_calculator->expects($this->once())
			->method("getForScope")
			->with($inclosed_scope_mock)
			->willReturn(array("b", "c"));

		$this->uncaught_calculator->determineForScope($enclosing_scope_mock);

		$this->assertTrue($this->uncaught_calculator->scopeHasChanged($enclosing_scope_mock, false));
		$this->assertTrue($this->uncaught_calculator->scopeHasChanged($enclosing_scope_mock, true));
		$this->assertFalse($this->uncaught_calculator->scopeHasChanged($enclosing_scope_mock, true));
	}

	public function testScopeHasChangedWhenScopeHasNotChangedReturnsFalseAndAfterResetReturnsFalse() {
		$enclosing_scope_mock = $this->createMock(Scope::class);
		$guarded_scope_mock = $this->createMock(GuardedScope::class);
		$inclosed_scope_mock = $this->createMock(Scope::class);
		$catch_clause_mock = $this->createMock(Catch_::class);

		$enclosing_scope_mock->expects($this->exactly(2))
			->method("getGuardedScopes")
			->willReturn(array($guarded_scope_mock));

		$guarded_scope_mock->expects($this->exactly(2))
			->method("getInclosedScope")
			->willReturn($inclosed_scope_mock);
		$guarded_scope_mock->expects($this->exactly(2))
			->method("getCatchClauses")
			->willReturn(array($catch_clause_mock));

		$this->catch_clause_type_resolver->expects($this->exactly(2))
			->method("getCaughtTypesForClause")
			->with($catch_clause_mock)
			->willReturn(array("a", "b"));

		$this->encounters_calculator->expects($this->exactly(2))
			->method("getForScope")
			->with($inclosed_scope_mock)
			->willReturn(array("b", "c"));

		$this->uncaught_calculator->determineForScope($enclosing_scope_mock); //now changed,
		$this->assertTrue($this->uncaught_calculator->scopeHasChanged($enclosing_scope_mock));
		$this->uncaught_calculator->determineForScope($enclosing_scope_mock);

		$this->assertFalse($this->uncaught_calculator->scopeHasChanged($enclosing_scope_mock, false));
		$this->assertFalse($this->uncaught_calculator->scopeHasChanged($enclosing_scope_mock, true));
	}
}