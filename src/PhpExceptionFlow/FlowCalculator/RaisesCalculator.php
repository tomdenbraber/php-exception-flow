<?php
namespace PhpExceptionFlow\FlowCalculator;

use PhpExceptionFlow\AstVisitor\ThrowsCollector;
use PhpExceptionFlow\Exception_;
use PhpExceptionFlow\Scope\Scope;
use PhpParser\NodeTraverser;
use PHPTypes\Type;

class RaisesCalculator extends AbstractFlowCalculator {
	/** @var NodeTraverser $ast_traverser */
	private $ast_traverser;
	/** @var ThrowsCollector $ast_throws_collector */
	private $ast_throws_collector;


	public function __construct(NodeTraverser $ast_traverser, ThrowsCollector $ast_throws_collector) {
		parent::__construct();

		$this->ast_traverser = $ast_traverser;
		$this->ast_throws_collector = $ast_throws_collector;

		$this->ast_traverser->addVisitor($ast_throws_collector);
	}

	public function determineForScope(Scope $scope) {
		$instructions = $scope->getInstructions();
		$this->ast_traverser->traverse($instructions);
		$throw_nodes = $this->ast_throws_collector->getThrows();
		$exceptions = [];
		foreach ($throw_nodes as $throw) {
			$exception_type = $this->lowerType($throw->expr->getAttribute("type", new Type(Type::TYPE_UNKNOWN)));
			$exceptions[] = new Exception_($exception_type, $throw, $scope);
		}

		$this->scopes[$scope] = $exceptions;
	}

	public function getType() {
		return "raises";
	}

	private function lowerType(Type $type) {
		$subtypes = [];
		if ($type->hasSubtypes() === true) {
			$subtypes = array_map(array($this, 'lowerType'), $type->subTypes);
		}
		if ($type->type === Type::TYPE_OBJECT) {
			$user_type = strtolower($type->userType);
		} else {
			$user_type = null;
		}

		return new Type($type->type, $subtypes, $user_type);
	}
}