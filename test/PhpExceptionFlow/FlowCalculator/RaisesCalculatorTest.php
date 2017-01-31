<?php
namespace PhpExceptionFlow\FlowCalculator;

use PhpExceptionFlow\AstVisitor\ThrowsCollector;
use PhpExceptionFlow\Scope;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\Throw_;
use PhpParser\NodeTraverser;

class RaisesCalculatorTest extends \PHPUnit_Framework_TestCase {

	/** @var  RaisesCalculator $raises_calculator */
	private $raises_calculator;
	/** @var NodeTraverser $ast_traverser */
	private $ast_traverser;
	/** @var ThrowsCollector $ast_throws_collector */
	private $ast_throws_collector;

	public function setUp() {
		$this->ast_traverser = $this->createMock(NodeTraverser::class);
		$this->ast_throws_collector = $this->createMock(ThrowsCollector::class);
		$this->raises_calculator = new RaisesCalculator($this->ast_traverser, $this->ast_throws_collector);
	}

	public function testGetTypeReturnsRaises() {
		$this->assertEquals("raises", $this->raises_calculator->getType());
	}

	public function testWithUndeterminedScopeThrowsException() {
		$scope_mock = $this->createMock(Scope::class);
		$this->expectException(\UnexpectedValueException::class);
		$this->raises_calculator->getForScope($scope_mock);
	}

	public function testNoThrowsReturnsFromCollectorReturnsNoExceptions() {
		$scope_mock = $this->createMock(Scope::class);
		$scope_mock->expects($this->once())
			->method("getInstructions")
			->willReturn(array()); //does not really matter here, as the ast_throws_collector is mocked

		$this->ast_throws_collector->expects($this->exactly(1))
			->method("getThrows")
			->with()
			->willReturn(array());

		$this->raises_calculator->determineForScope($scope_mock);
		$this->assertEmpty($this->raises_calculator->getForScope($scope_mock));
	}

	public function testWithOneScopeAndThrowsReturnsCorrectExceptions() {
		$scope_mock = $this->createMock(Scope::class);
		$scope_mock->expects($this->once())
			->method("getInstructions")
			->willReturn(array()); //does not really matter here, as the ast_throws_collector is mocked

		$throw_stmt1_mock = $this->createMock(Throw_::class);
		$throw_stmt1_mock->expr = $this->createMock(Expr::class);
		$throw_stmt1_mock->expr->expects($this->once())
			->method("getAttribute")
			->with($this->equalTo("type"), $this->anything())
			->willReturn("xyz");

		$throw_stmt2_mock = $this->createMock(Throw_::class);
		$throw_stmt2_mock->expr = $this->createMock(Expr::class);
		$throw_stmt2_mock->expr->expects($this->once())
			->method("getAttribute")
			->with($this->equalTo("type"), $this->anything())
			->willReturn("abc");


		$this->ast_throws_collector->expects($this->exactly(1))
			->method("getThrows")
			->with()
			->willReturn(array($throw_stmt1_mock, $throw_stmt2_mock));

		$this->raises_calculator->determineForScope($scope_mock);
		$this->assertEquals(array("xyz", "abc"), $this->raises_calculator->getForScope($scope_mock));
	}

	public function testWithMultipleAndThrowsReturnsCorrectExceptions() {
		$scope_1_mock = $this->createMock(Scope::class);
		$scope_1_mock->expects($this->once())
			->method("getInstructions")
			->willReturn(array()); //does not really matter here, as the ast_throws_collector is mocked

		$scope_2_mock = $this->createMock(Scope::class);
		$scope_2_mock->expects($this->once())
			->method("getInstructions")
			->willReturn(array());

		$throw_stmt1_mock = $this->createMock(Throw_::class);
		$throw_stmt1_mock->expr = $this->createMock(Expr::class);
		$throw_stmt1_mock->expr->expects($this->once())
			->method("getAttribute")
			->with($this->equalTo("type"), $this->anything())
			->willReturn("xyz");

		$throw_stmt2_mock = $this->createMock(Throw_::class);
		$throw_stmt2_mock->expr = $this->createMock(Expr::class);
		$throw_stmt2_mock->expr->expects($this->once())
			->method("getAttribute")
			->with($this->equalTo("type"), $this->anything())
			->willReturn("abc");


		$this->ast_throws_collector->expects($this->exactly(2))
			->method("getThrows")
			->will($this->onConsecutiveCalls(array($throw_stmt1_mock), array($throw_stmt2_mock)));

		$this->raises_calculator->determineForScope($scope_1_mock);
		$this->raises_calculator->determineForScope($scope_2_mock);

		$this->assertEquals(array("xyz"), $this->raises_calculator->getForScope($scope_1_mock));
		$this->assertEquals(array("abc"), $this->raises_calculator->getForScope($scope_2_mock));
	}

	public function testScopeHasChangedWhenNotCoveredYetReturnsTrue() {
		$scope_mock = $this->createMock(Scope::class);
		$this->assertTrue($this->raises_calculator->scopeHasChanged($scope_mock));
	}

	public function testScopeHasChangedWhenScopeHasChangedReturnsTrueAndAfterResetReturnsFalse() {
		$scope_mock = $this->createMock(Scope::class);
		$scope_mock->expects($this->once())
			->method("getInstructions")
			->willReturn(array()); //does not really matter here, as the ast_throws_collector is mocked

		$throw_stmt1_mock = $this->createMock(Throw_::class);
		$throw_stmt1_mock->expr = $this->createMock(Expr::class);
		$throw_stmt1_mock->expr->expects($this->once())
			->method("getAttribute")
			->with($this->equalTo("type"), $this->anything())
			->willReturn("xyz");

		$throw_stmt2_mock = $this->createMock(Throw_::class);
		$throw_stmt2_mock->expr = $this->createMock(Expr::class);
		$throw_stmt2_mock->expr->expects($this->once())
			->method("getAttribute")
			->with($this->equalTo("type"), $this->anything())
			->willReturn("abc");


		$this->ast_throws_collector->expects($this->exactly(1))
			->method("getThrows")
			->with()
			->willReturn(array($throw_stmt1_mock, $throw_stmt2_mock));

		$this->raises_calculator->determineForScope($scope_mock);
		$this->assertTrue($this->raises_calculator->scopeHasChanged($scope_mock, false));
		$this->assertTrue($this->raises_calculator->scopeHasChanged($scope_mock, true));
		$this->assertFalse($this->raises_calculator->scopeHasChanged($scope_mock, true));
	}

	public function testScopeHasChangedWhenScopeHasNotChangedReturnsFalseAndAfterResetReturnsFalse() {
		$scope_mock = $this->createMock(Scope::class);
		$scope_mock->expects($this->exactly(2))
			->method("getInstructions")
			->willReturn(array()); //does not really matter here, as the ast_throws_collector is mocked

		$throw_stmt1_mock = $this->createMock(Throw_::class);
		$throw_stmt1_mock->expr = $this->createMock(Expr::class);
		$throw_stmt1_mock->expr->expects($this->exactly(2))
			->method("getAttribute")
			->with($this->equalTo("type"), $this->anything())
			->willReturn("xyz");

		$throw_stmt2_mock = $this->createMock(Throw_::class);
		$throw_stmt2_mock->expr = $this->createMock(Expr::class);
		$throw_stmt2_mock->expr->expects($this->exactly(2))
			->method("getAttribute")
			->with($this->equalTo("type"), $this->anything())
			->willReturn("abc");

		$this->ast_throws_collector->expects($this->exactly(2))
			->method("getThrows")
			->with()
			->willReturn(array($throw_stmt1_mock, $throw_stmt2_mock));

		$this->raises_calculator->determineForScope($scope_mock); //now changed,
		$this->assertTrue($this->raises_calculator->scopeHasChanged($scope_mock));
		$this->raises_calculator->determineForScope($scope_mock);

		$this->assertFalse($this->raises_calculator->scopeHasChanged($scope_mock, false));
		$this->assertFalse($this->raises_calculator->scopeHasChanged($scope_mock, true));
	}
}