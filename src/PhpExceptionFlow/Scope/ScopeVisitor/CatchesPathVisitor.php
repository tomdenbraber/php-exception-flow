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
		$scope_name = $guarded_scope->getInclosedScope()->getName();

		fwrite($this->file_resource, sprintf("\"%s\": {\n", $scope_name));


		$first_exception = true;

		foreach ($guarded_scope->getCatchClauses() as $catch_) {
			try {
				/** @var Exception_[] $caught_exceptions */
				$caught_exceptions = $this->uncaught_calculator->getCaughtExceptions($catch_);
			} catch (\UnexpectedValueException $e) { //catch clause does not catch anything, so just ignore
				$caught_exceptions = [];
			}

			if (empty($caught_exceptions) === false) {
				$exception_type_occurrences = [];
				foreach ($caught_exceptions as $caught_exception) {
					if (isset($exception_type_occurrences[(string)$caught_exception]) === false) {
						$exception_type_occurrences[(string)$caught_exception] = 0;
					}

					if ($first_exception === true) {
						$first_exception = false;
					} else {
						fwrite($this->file_resource, ",\n");
					}

					fwrite($this->file_resource, sprintf("\"%s#%d\":\n", (string)$caught_exception, $exception_type_occurrences[(string)$caught_exception]));
					$exception_type_occurrences[(string)$caught_exception] += 1;

					$first_path = true;
					foreach ($caught_exception->getPathsToCatchClause($catch_) as $path) {
						if ($first_path === true) {
							$first_path = false;
						} else {
							fwrite($this->file_resource, ",\n");
						}

						fwrite($this->file_resource, sprintf("%s", json_encode($this->pathToJsonSerialiazable($path), JSON_PRETTY_PRINT)));
					}
				}
			}
		}
		fwrite($this->file_resource, "},\n");
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