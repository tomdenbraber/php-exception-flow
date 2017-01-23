<?php
namespace PhpExceptionFlow\CHA;

interface AppliesToCalculatorInterface {
	public function calculateAppliesTo(Method $method);
}