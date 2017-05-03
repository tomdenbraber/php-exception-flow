<?php
namespace PhpExceptionFlow\Scope\ScopeVisitor;

use PHPCfg\Printer;
use PhpExceptionFlow\FlowCalculator\CombiningCalculatorInterface;
use PhpExceptionFlow\Path\Catches;
use PhpExceptionFlow\Scope\GuardedScope;
use PhpExceptionFlow\Scope\Scope;

class JsonPrintingVisitor extends AbstractScopeVisitor {
	/** @var  CombiningCalculatorInterface $encounters_calculator */
	private $encounters_calculator;

	private $top_level_entries = [];
	private $key_stack = [];

	public function __construct(CombiningCalculatorInterface $encounters_calculator) {
		$this->encounters_calculator = $encounters_calculator;
	}

	public function enterScope(Scope $scope) {
		$encounters = $this->encounters_calculator->getForScope($scope);
		$scope_entry = [
			"raises" => [],
			"propagates" => [],
			"uncaught" => [],
			"guarded scopes" => []
		];

		foreach ($encounters as $exception) {
			$causes = $exception->getCauses($scope);

			foreach ($causes["raises"] as $original_scope) {
				$scope_entry["raises"][] = (string)$exception;
			}

			/** @var Scope $original_scope */
			foreach ($causes["propagates"] as $original_scope) {
				$original_scope_name = $original_scope->getName();
				$propagated_exceptions = $scope_entry["propagates"][$original_scope_name] ?? [];
				$propagated_exceptions[] = (string)$exception;
				$scope_entry["propagates"][$original_scope_name] = $propagated_exceptions;
			}
			/** @var Scope $escaped_from_scope */
			foreach ($causes["uncaught"] as $escaped_from_scope) {
				$escaped_from_scope_name = $escaped_from_scope->getName();
				$escaped_exceptions = $scope_entry["uncaught"][$escaped_from_scope_name] ?? [];
				$escaped_exceptions[] = (string)$exception;
				$scope_entry["uncaught"][$escaped_from_scope_name] = $escaped_exceptions;
			}
		}

		$add_to = &$this->top_level_entries;
		foreach ($this->key_stack as $key) {
			$add_to = &$add_to[$key];
		}
		$add_to[$scope->getName()] = $scope_entry;
		$this->key_stack[] = $scope->getName();
		$this->key_stack[] = "guarded scopes";
	}

	public function enterGuardedScope(GuardedScope $guarded_scope) {
		$guarded_scope_entry = [
			"inclosed" => [],
			"catch clauses" => [],
		];

		$inclosed = $guarded_scope->getInclosedScope();
		$inclosed_encounters = $this->encounters_calculator->getForScope($inclosed);

		foreach ($guarded_scope->getCatchClauses() as $catch_clause) {
			$guarded_scope_entry["catch clauses"][(string)$catch_clause->type] = [];
		}

		foreach ($inclosed_encounters as $exception) {
			if (($catches_path_entry = $exception->pathEndsIn($inclosed)) !== false) {
				if ($catches_path_entry instanceof Catches === false) {
					throw new \LogicException(sprintf("Unknown type %s apparently terminates the Exception Flow, but it is unknown how to handle it.", get_class($catches_path_entry)));
				}
				/** @var Catches $catches_path_entry */
				$guarded_scope_entry["catch clauses"][(string)$catches_path_entry->getCaughtBy()->type][] = (string)$exception;
			}
		}

		$add_to = &$this->top_level_entries;
		foreach ($this->key_stack as $key) {
			$add_to = &$add_to[$key];
		}
		$guarded_scope_name = sprintf("guarded{%s}", $guarded_scope->getInclosedScope()->getName());
		$add_to[$guarded_scope_name] = $guarded_scope_entry;
		$this->key_stack[] = $guarded_scope_name;
		$this->key_stack[] = "inclosed";
	}

	public function leaveGuardedScope(GuardedScope $guarded_scope) {
		array_pop($this->key_stack);
		array_pop($this->key_stack);
	}

	public function leaveScope(Scope $scope) {
		array_pop($this->key_stack);
		array_pop($this->key_stack);
	}

	public function getResult() {
		return json_encode($this->top_level_entries, JSON_PRETTY_PRINT);
	}
}