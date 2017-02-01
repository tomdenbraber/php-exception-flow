<?php
namespace PhpExceptionFlow;

use PhpExceptionFlow\AstVisitor\CallCollector;
use PhpExceptionFlow\AstVisitor\MethodCollectingVisitor;
use PhpExceptionFlow\AstVisitor\ThrowsCollector;
use PhpExceptionFlow\CHA\AppliesToCalculator;
use PhpExceptionFlow\CHA\AppliesToVisitor;
use PhpExceptionFlow\CHA\MethodComparator;
use PhpExceptionFlow\Collection\PartialOrder\PartialOrder;
use PhpExceptionFlow\Collection\PartialOrder\TopDownBreadthFirstTraverser;
use PhpExceptionFlow\FlowCalculator\CombiningCalculator;
use PhpExceptionFlow\FlowCalculator\PropagatesCalculator;
use PhpExceptionFlow\FlowCalculator\RaisesCalculator;
use PhpExceptionFlow\FlowCalculator\TraversingCalculator;
use PhpExceptionFlow\FlowCalculator\UncaughtCalculator;
use PhpExceptionFlow\ScopeVisitor\CalculatorWrappingVisitor;
use PhpExceptionFlow\ScopeVisitor\CallToScopeLinkingVisitor;
use PhpExceptionFlow\ScopeVisitor\CaughtExceptionTypesCalculator;
use PhpExceptionFlow\ScopeVisitor\PrintingVisitor;
use PhpParser;



class CompletePipelineTest extends \PHPUnit_Framework_TestCase {
	/** @var ScopeTraverser */
	private $traverser;
	/** @var PrintingVisitor */
	private $printing_visitor;


	public function setUp() {
		$this->traverser = new ScopeTraverser();
	}

	/** @dataProvider provideTestSetsCalculation */
	public function testSetsCalculation($code, $expected_output) {
		$php_parser = (new PhpParser\ParserFactory)->create(PhpParser\ParserFactory::PREFER_PHP7);

		$ast = PipelineTestHelper::getAst($php_parser, $code);
		$script = PipelineTestHelper::simplifyingCfgPass($php_parser, $ast);
		$state = PipelineTestHelper::calculateState($script);
		$ast_nodes_collector = PipelineTestHelper::linkingCfgPass($script);
		$scope_collector = PipelineTestHelper::calculateScopes($state, $ast_nodes_collector, $ast);
		$applies_to = PipelineTestHelper::calculateAppliesTo($ast, $state);

		$scopes = $scope_collector->getTopLevelScopes();

		$call_resolver = new ParserCallNodeToScopeResolver($scope_collector->getMethodScopes(), $scope_collector->getFunctionScopes(), $applies_to);

		$call_to_scope_linker = new CallToScopeLinkingVisitor(new PhpParser\NodeTraverser(), new CallCollector(), $call_resolver);


		$catch_clause_type_resolver = new CaughtExceptionTypesCalculator($state);
		$this->traverser->addVisitor($catch_clause_type_resolver);
		$this->traverser->addVisitor($call_to_scope_linker);
		$this->traverser->traverse($scopes);
		$this->traverser->removeVisitor($catch_clause_type_resolver);

		$combining_mutable = new CombiningCalculator();
		$combining_immutable = new CombiningCalculator();

		$encounters_calc = new EncountersCalculator($combining_mutable, $combining_immutable, $call_to_scope_linker->getCalleeCalledByCallerScopes());

		$raises_calculator = new RaisesCalculator(new PhpParser\NodeTraverser(), new ThrowsCollector(true));
		$raises_scope_traverser = new ScopeTraverser();
		$raises_wrapping_visitor = new CalculatorWrappingVisitor($raises_calculator, CalculatorWrappingVisitor::CALCULATE_ON_ENTER);
		$raises_scope_traverser->addVisitor($raises_wrapping_visitor);
		$traversing_raises_calculator = new TraversingCalculator($raises_scope_traverser, $raises_wrapping_visitor, $raises_calculator);

		$combining = new CombiningCalculator();

		$uncaught_calculator = new UncaughtCalculator($catch_clause_type_resolver, $combining);
		$uncaught_scope_traverser = new ScopeTraverser();
		$uncaught_wrapping_visitor = new CalculatorWrappingVisitor($uncaught_calculator, CalculatorWrappingVisitor::CALCULATE_ON_LEAVE);
		$uncaught_scope_traverser->addVisitor($uncaught_wrapping_visitor);
		$traversing_uncaught_calculator = new TraversingCalculator($uncaught_scope_traverser, $uncaught_wrapping_visitor, $uncaught_calculator);

		$propagates_calculator = new PropagatesCalculator($call_to_scope_linker->getCallerCallsCalleeScopes(), $combining);
		$propagates_scope_traverser = new ScopeTraverser();
		$propagates_wrapping_visitor = new CalculatorWrappingVisitor($propagates_calculator, CalculatorWrappingVisitor::CALCULATE_ON_ENTER);
		$propagates_scope_traverser->addVisitor($propagates_wrapping_visitor);
		$traversing_propagates_calculator = new TraversingCalculator($propagates_scope_traverser, $propagates_wrapping_visitor, $propagates_calculator);


		$combining_mutable->addCalculator($traversing_uncaught_calculator);
		$combining_mutable->addCalculator($traversing_propagates_calculator);
		$combining_immutable->addCalculator($traversing_raises_calculator);

		$combining->addCalculator($combining_immutable);
		$combining->addCalculator($combining_mutable);

		$encounters_calc->calculateEncounters($scopes);

		$this->printing_visitor = new PrintingVisitor($combining, $uncaught_calculator);
		$this->traverser->addVisitor($this->printing_visitor);
		$this->traverser->traverse($scopes);
		$this->traverser->removeVisitor($this->printing_visitor);

		$this->assertEquals(
			$this->canonicalize($expected_output),
			$this->canonicalize($this->printing_visitor->getResult())
		);
	}

	public function provideTestSetsCalculation() {
		$dir = __DIR__ . '/../assets/code/exception_flow';
		$iter = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir), \RecursiveIteratorIterator::LEAVES_ONLY);

		foreach ($iter as $file) {
			if ($file->isFile() === false) {
				continue;
			}

			$contents = file_get_contents($file);
			yield $file->getBasename() => explode('-----', $contents);
		}
	}

	private function canonicalize($str) {
		// trim from both sides
		$str = trim($str);

		// normalize EOL to \n
		$str = str_replace(["\r\n", "\r"], "\n", $str);

		// trim right side of all lines
		return implode("\n", array_map('rtrim', explode("\n", $str)));
	}
}