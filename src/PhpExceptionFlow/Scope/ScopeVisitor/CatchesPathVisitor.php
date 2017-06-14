<?php
namespace PhpExceptionFlow\Scope\ScopeVisitor;

use PhpExceptionFlow\Exception_;
use PhpExceptionFlow\FlowCalculator\UncaughtCalculator;
use PhpExceptionFlow\Path\PathEntryInterface;
use PhpExceptionFlow\Scope\GuardedScope;

class CatchesPathVisitor extends AbstractScopeVisitor {
	/** @var UncaughtCalculator $encounters_calculator */
	private $uncaught_calculator;

	/** @var array */
	private $paths;

	public function __construct(UncaughtCalculator $uncaught_calculator) {
		$this->uncaught_calculator = $uncaught_calculator;
	}

	public function beforeTraverse(array $scopes) {
		$this->paths = [];
	}

	public function enterGuardedScope(GuardedScope $guarded_scope) {
		foreach ($guarded_scope->getCatchClauses() as $catch_) {
			try {
				/** @var Exception_[] $caught_exceptions */
				$caught_exceptions = $this->uncaught_calculator->getCaughtExceptions($catch_);
			} catch (\UnexpectedValueException $e) { //catch clause does not catch anything, so just ignore
				$caught_exceptions = [];
			}

			foreach ($caught_exceptions as $caught_exception) {
				$scope_name = $guarded_scope->getInclosedScope()->getName();
				if (isset($this->paths[$scope_name]) === false) {
					$this->paths[$scope_name] = [];
				}
				if (isset($this->paths[$scope_name][(string)$caught_exception]) === false) {
					$this->paths[$scope_name][(string)$caught_exception] = [];
				}
				$exception_idx = count($this->paths[$scope_name][(string)$caught_exception]);

				foreach ($caught_exception->getPathsToCatchClause($catch_) as $path) {
					$this->paths[$scope_name][(string)$caught_exception][$exception_idx][] = $this->pathToJson($path);
				}
			}
		}
	}

	public function getPaths() {
		return $this->paths;
	}

	/**
	 * @param PathEntryInterface[] $path
	 * @return array
	 */
	private function pathToJson(array $path) {
		$result = [];
		foreach ($path as $entry) {
			$result[] = [
				$entry->getToScope()->getName() => $entry->getType()
			];
		}
		return $result;
	}
}