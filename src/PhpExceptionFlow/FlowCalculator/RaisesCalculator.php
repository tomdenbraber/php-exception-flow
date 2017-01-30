<?php
namespace PhpExceptionFlow\FlowCalculator;

use PhpExceptionFlow\AstVisitor\ThrowsCollector;
use PhpExceptionFlow\Scope;
use PhpParser\NodeTraverser;
use PHPTypes\Type;

class RaisesCalculator implements ExceptionSetCalculatorInterface {

	/** @var Type[][]|\SplObjectStorage */
	private $scopes;

	/** @var NodeTraverser $ast_traverser */
	private $ast_traverser;
	/** @var ThrowsCollector $ast_throws_collector */
	private $ast_throws_collector;


	public function __construct(NodeTraverser $ast_traverser, ThrowsCollector $ast_throws_collector) {
		$this->scopes = new \SplObjectStorage;
		$this->ast_traverser = $ast_traverser;
		$this->ast_throws_collector = $ast_throws_collector;

		$this->ast_traverser->addVisitor($ast_throws_collector);
	}

	public function determineForScope(Scope $scope) {
		$instructions = $scope->getInstructions();
		$this->ast_traverser->traverse($instructions);
		$throw_nodes = $this->ast_throws_collector->getThrows();
		$throw_types = [];
		foreach ($throw_nodes as $throw) {
			$throw_types[] = $throw->expr->getAttribute("type", new Type(Type::TYPE_UNKNOWN));
		}
		$this->scopes[$scope] = $throw_types;
	}

	/**
	 * @param Scope $scope
	 * @throws \UnexpectedValueException
	 * @return Type[]
	 */
	public function getForScope(Scope $scope) {
		if ($this->scopes->contains($scope) === false) {
			throw new \UnexpectedValueException(sprintf("Scope with name %s could not be found in this set.", $scope->getName()));
		}
		return $this->scopes[$scope];
	}

	public function getType() {
		return "raises";
	}
}