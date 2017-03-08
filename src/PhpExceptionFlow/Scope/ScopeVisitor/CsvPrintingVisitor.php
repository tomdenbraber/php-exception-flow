<?php
namespace PhpExceptionFlow\Scope\ScopeVisitor;

use PhpExceptionFlow\FlowCalculator\CombiningCalculatorInterface;
use PhpExceptionFlow\Scope\Scope;

class CsvPrintingVisitor extends AbstractScopeVisitor {
	private $result = [];

	/** @var  CombiningCalculatorInterface $encounters_calculator */
	private $encounters_calculator;

	public function __construct(CombiningCalculatorInterface $encounters_calculator) {
		$this->encounters_calculator = $encounters_calculator;
	}

	public function beforeTraverse(array $scopes) {
		$this->result[] = "method;raises;propagates\n";
	}

	public function enterScope(Scope $scope) {
		if ($scope->isEnclosed() === false) {
			$encounters = $this->encounters_calculator->getForScope($scope);

			$raises = [];
			$propagates = [];
			foreach ($encounters as $exception) {
				$paths = $exception->getPropagationPaths();
				foreach ($paths as $path) {
					if ($path->getLastScopeInChain() === $scope && count($path->getScopeChain()) === 1) {
						$raises[] = $exception;
					} else { //todo: handle nested scopes correctly, add uncaughts to exception chain.
						$propagates[] = $exception;
					}
				}
			}
			$this->result[] = sprintf("%s;%s;%s\n", $scope->getName(), implode(",", array_unique($raises)), implode(",", array_unique($propagates)));
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