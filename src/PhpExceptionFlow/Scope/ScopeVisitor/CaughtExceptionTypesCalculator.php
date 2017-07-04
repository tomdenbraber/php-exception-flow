<?php
namespace PhpExceptionFlow\Scope\ScopeVisitor;

use PhpExceptionFlow\Scope\GuardedScope;
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

			if (isset($this->state->classResolvedBy[$caught_type]) === false) {
				//todo: this is a really bad idea
				//Apparently, this type is unknown (probably because it is an installed extension and not a native PHP/included package type)
				//a type always resolves to itself, so just add it like that.
				//this might result in uncaught exceptions that are actually caught...
				$this->state->classResolvedBy[$caught_type] = [$caught_type => $caught_type];
				$this->state->classResolves[$caught_type] = [$caught_type => $caught_type];
				print sprintf("Added type to type matrix, as it was unknown: %s\n", $caught_type);
			}
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