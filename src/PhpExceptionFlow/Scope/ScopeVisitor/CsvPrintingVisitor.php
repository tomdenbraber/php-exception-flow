<?php
namespace PhpExceptionFlow\Scope\ScopeVisitor;

use PhpExceptionFlow\FlowCalculator\CombiningCalculatorInterface;
use PhpExceptionFlow\Path\PathEntryInterface;
use PhpExceptionFlow\Scope\Scope;

class CsvPrintingVisitor extends AbstractScopeVisitor {
	private $result = [];

	/** @var  CombiningCalculatorInterface $encounters_calculator */
	private $encounters_calculator;

	public function __construct(CombiningCalculatorInterface $encounters_calculator) {
		$this->encounters_calculator = $encounters_calculator;
	}

	public function beforeTraverse(array $scopes) {
		$this->result[] = "method;raises;propagates;uncaught\n";
	}

	public function enterScope(Scope $scope) {
		if ($scope->isEnclosed() === false) {
			$encounters = $this->encounters_calculator->getForScope($scope);

			$raises = [];
			$propagates = [];
			$uncaught = [];
			foreach ($encounters as $exception) {

				$exception_causes = $exception->getCauses($scope);
				if ($exception_causes["raises"] === true) {
					$raises[] = $exception;
				}
				if ($exception_causes["propagates"] === true) {
					$propagates[] = $exception;
				}
				if ($exception_causes["uncaught"] === true) {
					$uncaught[] = $exception;
				}
			}
			$this->result[] = sprintf("%s;%s;%s;%s\n", $scope->getName(), implode(",", array_unique($raises)), implode(",", array_unique($propagates)),implode(",", array_unique($uncaught)));
		}
	}

	public function getResult() {
		return $this->result;
	}

	public function writeToFile($filename) {
		$handle = fopen($filename, "w+");
		foreach ($this->result as $line) {
			fwrite($handle, $line);
		}
		fclose($handle);
	}
}