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

		$this->xyz_type = $this->createExceptionMock("xyz");
		$this->abc_type = $this->createExceptionMock("abc");
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

		$types_scope = [];
		foreach ($this->raises_calculator->getForScope($scope_mock) as $exception) {
			$types_scope[] = $exception->getType();
		}

		$this->assertEquals($this->xyz_type, $types_scope[0]);
		$this->assertEquals($this->abc_type, $types_scope[1]);
		$this->assertCount(2, $types_scope);
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

		$types_scope_1 = [];
		foreach ($this->raises_calculator->getForScope($scope_1_mock) as $exception) {
			$types_scope_1[] = $exception->getType();
		}
		$types_scope_2 = [];
		foreach ($this->raises_calculator->getForScope($scope_2_mock) as $exception) {
			$types_scope_2[] = $exception->getType();
		}

		$this->assertEquals($this->xyz_type, $types_scope_1[0]);
		$this->assertEquals($this->abc_type, $types_scope_2[0]);
		$this->assertCount(1, $types_scope_1);
		$this->assertCount(1, $types_scope_2);
	}

	/**
	 * @param string $userType
	 * @return Type
	 */
	private function createExceptionMock($userType) {
		return new Type(Type::TYPE_OBJECT, array(), $userType);
	}
}