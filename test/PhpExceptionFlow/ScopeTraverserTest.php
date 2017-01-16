<?php
namespace PhpExceptionFlow;

class ScopeTraverserTest extends \PHPUnit_Framework_TestCase {

	/** @var Scope */
	private $scope;
	/** @var  Scope */
	private $inclosed_scope;
	/** @var  GuardedScope */
	private $guarded_scope;

	/** @var  ScopeTraverserInterface */
	private $traverser;


	public function setUp() {
		$this->scope = new Scope("abcdef");
		$this->inclosed_scope = new Scope("kaas");
		$this->guarded_scope = new GuardedScope($this->scope, $this->inclosed_scope);
		$this->scope->addGuardedScope($this->guarded_scope);

		$this->traverser = new ScopeTraverser();
	}

	public function testCorrectCallsToVisitor() {
		$visitor = $this->getMockBuilder(ScopeVisitorInterface::class)->getMock();

		$visitor->expects($this->once())
			->method('beforeTraverse')
			->with($this->equalTo(array($this->scope)));

		$visitor->expects($this->exactly(2))
			->method('enterScope')
			->withConsecutive(array($this->scope), array($this->inclosed_scope));

		$visitor->expects($this->exactly(1))
			->method('enterGuardedScope')
			->with($this->guarded_scope);

		$visitor->expects($this->exactly(1))
			->method('leaveGuardedScope')
			->with($this->guarded_scope);

		$visitor->expects($this->exactly(2))
			->method('leaveScope')
			->withConsecutive(array($this->inclosed_scope), array($this->scope));


		$visitor->expects($this->once())
			->method('beforeTraverse')
			->with($this->equalTo(array($this->scope)));

		$this->traverser->addVisitor($visitor);

		$this->traverser->traverse(array($this->scope));
	}
}