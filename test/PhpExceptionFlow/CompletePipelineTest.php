<?php
namespace PhpExceptionFlow;

use PhpExceptionFlow\AstBridge\Parser\WrappedParser;
use PhpExceptionFlow\AstVisitor\CallCollector;
use PhpExceptionFlow\AstVisitor\ThrowsCollector;
use PhpExceptionFlow\CallGraphConstruction\AstCallNodeToScopeResolver;
use PhpExceptionFlow\CfgBridge\SystemFactory;
use PhpExceptionFlow\FlowCalculator\CombiningCalculator;
use PhpExceptionFlow\FlowCalculator\PropagatesCalculator;
use PhpExceptionFlow\FlowCalculator\RaisesCalculator;
use PhpExceptionFlow\FlowCalculator\TraversingCalculator;
use PhpExceptionFlow\FlowCalculator\UncaughtCalculator;
use PhpExceptionFlow\Scope\ScopeVisitor\CalculatorWrappingVisitor;
use PhpExceptionFlow\Scope\ScopeVisitor\CallToScopeLinkingVisitor;
use PhpExceptionFlow\Scope\ScopeVisitor\CaughtExceptionTypesCalculator;
use PhpExceptionFlow\Scope\ScopeVisitor\PrintingVisitor;
use PhpExceptionFlow\Scope\ScopeTraverser;
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
	public function testSetsCalculation($filename, $expected_output) {
		$php_parser = (new PhpParser\ParserFactory)->create(PhpParser\ParserFactory::PREFER_PHP7);
		$wrapped_parser = new WrappedParser($php_parser);

		$system_fact = new SystemFactory($php_parser);

		$ast_system = PipelineTestHelper::getAstSystem($wrapped_parser, $filename);
		$cfg_system = PipelineTestHelper::simplifyingCfgPass($system_fact, $ast_system);
		$state = PipelineTestHelper::calculateState($cfg_system);
		$ast_nodes_collector = PipelineTestHelper::linkingCfgPass($cfg_system);
		$scope_collector = PipelineTestHelper::calculateScopes($state, $ast_nodes_collector, $ast_system);
		$class_method_to_method = PipelineTestHelper::calculateMethodMap($ast_system, $state);

		$scopes = $scope_collector->getTopLevelScopes();

		$call_resolver = new AstCallNodeToScopeResolver($scope_collector->getMethodScopes(), $scope_collector->getFunctionScopes(), $class_method_to_method, $state);

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
		$propagates_calculator = new PropagatesCalculator($call_to_scope_linker->getCallerCallsCalleeScopes(), $combining);

		$combining_mutable->addCalculator($uncaught_calculator);
		$combining_mutable->addCalculator($propagates_calculator);
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
		$dir = __DIR__ . '/../assets/code';
		$res_dir = __DIR__ . '/../assets/expected';
		$iter = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir), \RecursiveIteratorIterator::LEAVES_ONLY);

		foreach ($iter as $file) {
			if ($file->isFile() === false) {
				continue;
			}

			$expected_outcome = file_get_contents($res_dir . "/" . basename($file, ".test") . ".result");
			yield $file->getBasename() => array($file->getPathname(), $expected_outcome);
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