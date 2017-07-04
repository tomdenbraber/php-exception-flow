<?php
namespace PhpExceptionFlow\Scope\ScopeVisitor;

use PhpExceptionFlow\Exception_;
use PhpExceptionFlow\FlowCalculator\UncaughtCalculator;
use PhpExceptionFlow\Path\PathEntryInterface;
use PhpExceptionFlow\Scope\GuardedScope;

class CatchesPathVisitor extends AbstractScopeVisitor {
	/** @var UncaughtCalculator $encounters_calculator */
	private $uncaught_calculator;

	/** @var resource $file_resource */
	private $file_resource;

	private $paths = [];

	public function __construct(UncaughtCalculator $uncaught_calculator, $file_resource) {
		$this->uncaught_calculator = $uncaught_calculator;
		$this->file_resource = $file_resource;
	}

	public function beforeTraverse(array $scopes) {
		$this->paths = [];
	}

	public function enterGuardedScope(GuardedScope $guarded_scope) {
		$scope_name = $guarded_scope->getInclosedScope()->getName();

		foreach ($guarded_scope->getCatchClauses() as $catch_) {
			try {
				/** @var Exception_[] $caught_exceptions */
				$caught_exceptions = $this->uncaught_calculator->getCaughtExceptions($catch_);
			} catch (\UnexpectedValueException $e) { //catch clause does not catch anything, so just ignore
				$caught_exceptions = [];
			}

			$exception_type_occurrences = [];
			if (empty($caught_exceptions) === false) {
				if (isset($this->paths[$scope_name]) === false) {
					$this->paths[$scope_name] = [];
				}

				foreach ($caught_exceptions as $caught_exception) {
					if(isset($exception_type_occurrences[(string)$caught_exception]) === false) {
						$exception_type_occurrences[(string)$caught_exception] = 0;
					} else {
						$exception_type_occurrences[(string)$caught_exception] += 1;
					}

					$exception_name = (string)$caught_exception . "#" . $exception_type_occurrences[(string)$caught_exception];
					foreach ($caught_exception->getPathsToCatchClause($catch_) as $path) {
						$this->paths[$scope_name][$exception_name] = $this->pathToJsonSerialiazable($path);
					}

				}
			}
		}
	}

	public function afterTraverse(array $scopes) {
		fwrite($this->file_resource, json_encode($this->paths, JSON_PRETTY_PRINT));
	}

	/**
	 * @param PathEntryInterface[] $path
	 * @return array
	 */
	private function pathToJsonSerialiazable(array $path) {
		$result = [];
		foreach ($path as $entry) {
			$result[] = [
				"scope" => $entry->getToScope()->getName(),
				"link" => $entry->getType()
			];
		}
		return $result;
	}
}