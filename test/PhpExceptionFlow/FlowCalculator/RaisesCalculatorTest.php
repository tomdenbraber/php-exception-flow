<?php
namespace PhpExceptionFlow\FlowCalculator;

use PhpExceptionFlow\AstVisitor\ThrowsCollector;
use PhpExceptionFlow\Scope\Scope;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\Throw_;
use PhpParser\NodeTraverser;
use PHPTypes\Type;

class RaisesCalculatorTest extends \PHPUnit_Framework_TestCase {

	/** @var  RaisesCalculator $raises_calculator */
	private $raises_calculator;
	/** @var NodeTraverser $ast_traverser */
	private $ast_traverser;
	/** @var ThrowsCollector $ast_throws_collector */
	private $ast_throws_collector;

	/** @var Type $xyz_type */
	private $xyz_type;
	/** @var Type $abc_type */
	private $abc_type;

	public function setUp() {
		$this->ast_traverser = $this->createMock(NodeTraverser::class);
		$this->ast_throws_collector = $this->createMock(ThrowsCollector::class);
		$this->raises_calculator = new RaisesCalculator($this->ast_traverser, $this->ast_throws_collector);

		$this->xyz_type = $this->createType("xyz");
		$this->abc_type = $this->createType("abc");
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
			->willReturn($this->xyz_type);

		$throw_stmt2_mock = $this->createMock(Throw_::class);
		$throw_stmt2_mock->expr = $this->createMock(Expr::class);
		$throw_stmt2_mock->expr->expects($this->once())
			->method("getAttribute")
			->with($this->equalTo("type"), $this->anything())
			->willReturn($this->abc_type);


		$this->ast_throws_collector->expects($this->exactly(1))
			->method("getThrows")
			->with()
			->willReturn(array($throw_stmt1_mock, $throw_stmt2_mock));

		$this->raises_calculator->determineForScope($scope_mock);
		$this->assertEquals(array($this->xyz_type, $this->abc_type), $this->raises_calculator->getForScope($scope_mock));
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
			->willReturn($this->xyz_type);

		$throw_stmt2_mock = $this->createMock(Throw_::class);
		$throw_stmt2_mock->expr = $this->createMock(Expr::class);
		$throw_stmt2_mock->expr->expects($this->once())
			->method("getAttribute")
			->with($this->equalTo("type"), $this->anything())
			->willReturn($this->abc_type);


		$this->ast_throws_collector->expects($this->exactly(2))
			->method("getThrows")
			->will($this->onConsecutiveCalls(array($throw_stmt1_mock), array($throw_stmt2_mock)));

		$this->raises_calculator->determineForScope($scope_1_mock);
		$this->raises_calculator->determineForScope($scope_2_mock);

		$this->assertEquals(array($this->xyz_type), $this->raises_calculator->getForScope($scope_1_mock));
		$this->assertEquals(array($this->abc_type), $this->raises_calculator->getForScope($scope_2_mock));
	}

	/**
	 * @param string $userType
	 * @return Type
	 */
	private function createType($userType) {
		return new Type(Type::TYPE_OBJECT, array(), $userType);
	}
}