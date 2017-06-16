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

	public function __construct(UncaughtCalculator $uncaught_calculator, $file_resource) {
		$this->uncaught_calculator = $uncaught_calculator;
		$this->file_resource = $file_resource;
	}

	public function beforeTraverse(array $scopes) {
		fwrite($this->file_resource, "{\n");
	}

	public function enterGuardedScope(GuardedScope $guarded_scope) {
		$current_scope_paths = [];
		$scope_name = $guarded_scope->getInclosedScope()->getName();

		foreach ($guarded_scope->getCatchClauses() as $catch_) {
			try {
				/** @var Exception_[] $caught_exceptions */
				$caught_exceptions = $this->uncaught_calculator->getCaughtExceptions($catch_);
			} catch (\UnexpectedValueException $e) { //catch clause does not catch anything, so just ignore
				$caught_exceptions = [];
			}


			foreach ($caught_exceptions as $caught_exception) {
				if (isset($current_scope_paths[(string)$caught_exception]) === false) {
					$current_scope_paths[(string)$caught_exception] = [];
				}
				$exception_idx = count($current_scope_paths[(string)$caught_exception]);

				foreach ($caught_exception->getPathsToCatchClause($catch_) as $path) {
					$current_scope_paths[(string)$caught_exception][$exception_idx][] = $this->pathToJsonSerialiazable($path);
				}
			}
		}


		$str_to_write = sprintf("\"%s\": %s,\n", $scope_name, json_encode($current_scope_paths, JSON_PRETTY_PRINT));
		fwrite($this->file_resource, $str_to_write);
	}

	public function afterTraverse(array $scopes) {
		fseek($this->file_resource, - 2, SEEK_END); //overwrite last comma and newline
		fwrite($this->file_resource, "\n}");
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