<?php
namespace PhpExceptionFlow\ScopeVisitor;

use PhpExceptionFlow\GuardedScope;
use PhpParser\Node\Stmt\Catch_;
use PHPTypes\State;
use PHPTypes\Type;

class CaughtExceptionTypesCalculator extends AbstractScopeVisitor {
	/** @var State $state */
	private $state;
	/** @var Type[][]|\SplObjectStorage $catch_clauses */
	private $catch_clauses;

	public function __construct(State $state) {
		$this->state = $state;
		$this->catch_clauses = new \SplObjectStorage;
	}

	public function enterGuardedScope(GuardedScope $guarded_scope) {
		$already_caught = [];
		foreach ($guarded_scope->getCatchClauses() as $catch_clause) {
			$caught_type = strtolower(implode('\\', $catch_clause->type->parts));
			$caught_types = [];
			foreach ($this->state->classResolvedBy[$caught_type] as $resolved_by) {
				if (in_array($resolved_by, $already_caught, true) === false) {
					$caught_types[] = new Type(Type::TYPE_OBJECT, [], $resolved_by);
					$already_caught[] = $resolved_by;
				}
			}
			$this->catch_clauses[$catch_clause] = $caught_types;
		}
	}

	/**
	 * @param Catch_ $clause
	 * @return Type[]
	 * @throws \UnexpectedValueException
	 */
	public function getCaughtTypesForClause(Catch_ $clause) {
		if ($this->catch_clauses->contains($clause) === false) {
			throw new \UnexpectedValueException("Unknown catch clause supplied");
		}
		return $this->catch_clauses[$clause];
	}
}