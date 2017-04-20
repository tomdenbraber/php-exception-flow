<?php

require __DIR__ . '/../vendor/autoload.php';

use PhpExceptionFlow\AstBridge\System as AstSystem;
use PhpExceptionFlow\AstBridge\SystemTraverser as AstSystemTraverser;
use PhpExceptionFlow\AstBridge\Parser\FileCachingParser;
use PhpExceptionFlow\AstBridge\Parser\WrappedParser;
use PhpExceptionFlow\AstVisitor;
use PhpExceptionFlow\CfgBridge;
use PhpExceptionFlow\CfgBridge\System as CfgSystem;
use PhpExceptionFlow\CfgBridge\SystemFactory as CfgSystemFactory;
use PhpExceptionFlow\CfgBridge\SystemFactoryInterface as CfgSystemFactoryInterface;

use PhpExceptionFlow\Collection\PartialOrder\PartialOrder;
use PhpExceptionFlow\CallGraphConstruction\MethodComparator;
use PhpExceptionFlow\CallGraphConstruction\CombiningClassMethodToMethodResolver;
use PhpExceptionFlow\CallGraphConstruction\AppliesToMethodResolver;
use PhpExceptionFlow\CallGraphConstruction\MethodResolver;
use PhpExceptionFlow\CallGraphConstruction\AstCallNodeToScopeResolver;

use PhpExceptionFlow\Scope\ScopeVisitor;


if ($argc !== 2) {
	throw new \UnexpectedValueException("Expected exactly 2 input arguments");
}

$start_time = time();

$parsed_project = basename(realpath($argv[1]));

if (is_dir(__DIR__ . "/../cache/" . $parsed_project) === false) {
	mkdir(__DIR__ . "/../cache/" . $parsed_project);
	mkdir(__DIR__ . "/../cache/" . $parsed_project . "/ast");
	mkdir(__DIR__ . "/../cache/" . $parsed_project . "/cfg");
}

$php_parser = (new PhpParser\ParserFactory)->create(PhpParser\ParserFactory::PREFER_PHP7);
$wrapped_parser = new WrappedParser($php_parser);
$caching_parser = new FileCachingParser(__DIR__ . "/../cache/" . $parsed_project . "/ast", $wrapped_parser);


$ast_system = new AstSystem();

$dir = $argv[1];
$iter = new \RecursiveIteratorIterator(
	new \RecursiveDirectoryIterator($dir), \RecursiveIteratorIterator::LEAVES_ONLY);

$skipped_files = 0;
/** @var SplFileInfo $file */
foreach ($iter as $file) {
	if ($file->isFile() === false) {
		continue;
	}
	//skip tests
	if (preg_match('/[\\\\\/]test(s)?[\\\\\/]/i', $file->getRealPath(), $matches) === 1) {
		$skipped_files += 1;
		continue;
	}

	$extension = $file->getExtension();
	if ($extension === "php" || $extension === "inc") {
		$ast_system->addAst($file->getPathname(), $caching_parser->parse($file->getPathname()));
	}
}

print sprintf("skipped %d files because they were located in a folder called 'test'\n", $skipped_files);
print "parsing done\n";
$parse_finished_time = time();

$cfg_system_factory = CfgSystemFactory::createDefault();
$cfg_system = createCfgSystem($cfg_system_factory, $ast_system);
print "Cfg creation done\n";
$cfg_finished_time = time();


$state = calculateState($cfg_system);
print "type-inference done\n";
$type_inference_time = time();

$ast_nodes_collector = linkingCfgPass($cfg_system);
print "linking types back to ast done\n";
$type_linking_time = time();

$scope_collector = calculateScopes($state, $ast_nodes_collector, $ast_system);
print "scope collection done\n";
$scope_collection_time = time();

$class_method_to_method_map = calculateClassMethodToMethodMap($ast_system, $state);
print "call site calculation done\n";
$call_site_calculation_time = time();

$builtin_collector = new \PhpExceptionFlow\Scope\Collector\BuiltInCollector($state->internalTypeInfo);

$combining_scope_collector = new \PhpExceptionFlow\Scope\Collector\CombiningScopeCollector([
	$scope_collector,
	$builtin_collector,
]);

$scopes = $combining_scope_collector->getTopLevelScopes();

$call_resolver = new AstCallNodeToScopeResolver($combining_scope_collector->getMethodScopes(), $combining_scope_collector->getFunctionScopes(), $class_method_to_method_map, $state);

$call_to_scope_linker = new ScopeVisitor\CallToScopeLinkingVisitor(new PhpParser\NodeTraverser(), new AstVisitor\CallCollector(), $call_resolver);

$scope_traverser = new \PhpExceptionFlow\Scope\ScopeTraverser();
$catch_clause_type_resolver = new ScopeVisitor\CaughtExceptionTypesCalculator($state);
$scope_traverser->addVisitor($catch_clause_type_resolver);
$scope_traverser->addVisitor($call_to_scope_linker);
$scope_traverser->traverse($scopes);
$scope_traverser->removeVisitor($catch_clause_type_resolver);
$scope_traverser->removeVisitor($call_to_scope_linker);

print "resolved calls and catch clauses\n";
$calls_catches_resolved_time = time();

$combining_mutable = new \PhpExceptionFlow\FlowCalculator\CombiningCalculator();
$combining_immutable = new \PhpExceptionFlow\FlowCalculator\CombiningCalculator();

$encounters_calc = new \PhpExceptionFlow\EncountersCalculator($combining_mutable, $combining_immutable, $call_to_scope_linker->getCalleeCalledByCallerScopes());

$raises_calculator = new \PhpExceptionFlow\FlowCalculator\RaisesCalculator(new PhpParser\NodeTraverser(), new AstVisitor\ThrowsCollector(true));
$raises_scope_traverser = new \PhpExceptionFlow\Scope\ScopeTraverser();
$raises_wrapping_visitor = new ScopeVisitor\CalculatorWrappingVisitor($raises_calculator, ScopeVisitor\CalculatorWrappingVisitor::CALCULATE_ON_ENTER);
$raises_scope_traverser->addVisitor($raises_wrapping_visitor);
$traversing_raises_calculator = new \PhpExceptionFlow\FlowCalculator\TraversingCalculator($raises_scope_traverser, $raises_wrapping_visitor, $raises_calculator);

$combining = new \PhpExceptionFlow\FlowCalculator\CombiningCalculator();

$uncaught_calculator = new \PhpExceptionFlow\FlowCalculator\UncaughtCalculator($catch_clause_type_resolver, $combining);
$propagates_calculator = new \PhpExceptionFlow\FlowCalculator\PropagatesCalculator($call_to_scope_linker->getCallerCallsCalleeScopes(), $combining);

$combining_mutable->addCalculator($uncaught_calculator);
$combining_mutable->addCalculator($propagates_calculator);
$combining_immutable->addCalculator($traversing_raises_calculator);

$combining->addCalculator($combining_immutable);
$combining->addCalculator($combining_mutable);

print "calculating encounters\n";
$encounters_calc->calculateEncounters($scope_collector->getTopLevelScopes());
print "calculation done\n";
$calculation_done_time = time();


$printing_visitor = new ScopeVisitor\DetailedPrintingVisitor($raises_calculator, $uncaught_calculator, $propagates_calculator);
$csv_printing_visitor = new ScopeVisitor\CsvPrintingVisitor($combining);
$scope_traverser->addVisitor($printing_visitor);
$scope_traverser->addVisitor($csv_printing_visitor);
$scope_traverser->traverse($scope_collector->getTopLevelScopes());
$scope_traverser->removeVisitor($printing_visitor);
$scope_traverser->removeVisitor($csv_printing_visitor);



$result_file = fopen(sprintf("%s/../results/%s_encounters.txt", __DIR__, $parsed_project), 'w');
fwrite($result_file, $printing_visitor->getResult());
$class_method_to_method_file  = fopen(sprintf("%s/../results/%s_class_method_to_method.txt", __DIR__, $parsed_project), 'w');
fwrite($class_method_to_method_file, stringifyClassMethodToMethodMap($class_method_to_method_map));
$scope_calls_scope_file = fopen(sprintf("%s/../results/%s_scope_calls_scope_file.txt", __DIR__, $parsed_project), 'w');
fwrite($scope_calls_scope_file, stringifyScopeCallsScopeMap($call_to_scope_linker));
$unresolved_calls_file = fopen(sprintf("%s/../results/%s_unresolved_calls_file.txt", __DIR__, $parsed_project), 'w');
fwrite($unresolved_calls_file, stringifyUnresolvedCallsPerScope($call_to_scope_linker->getUnresolvedCalls()));

$csv_printing_visitor->writeToFile(sprintf("%s/../results/%s_encounters_eval.csv", __DIR__, $parsed_project));


print sprintf("Started at %d\n", $start_time);
print sprintf("Parsing done in:\t%d\n", $parse_finished_time - $start_time);
print sprintf("Cfg creation done in:\t%d\n", $cfg_finished_time - $parse_finished_time);
print sprintf("Type inference done in:\t%d\n", $type_inference_time - $cfg_finished_time);
print sprintf("Type linking done in:\t%d\n", $type_linking_time - $type_inference_time);
print sprintf("Scope collection done in:\t%d\n", $scope_collection_time - $type_linking_time);
print sprintf("Classmethod/method map done in:\t%d\n", $call_site_calculation_time - $scope_collection_time);
print sprintf("Call/catch resolution done in:\t%d\n", $calls_catches_resolved_time - $call_site_calculation_time);
print sprintf("Encounters calculation done in:\t%d\n", $calculation_done_time - $calls_catches_resolved_time);
print sprintf("Complete process:\t%d\n", $calculation_done_time - $start_time);



/**
 * @param CfgSystemFactoryInterface $cfg_system_factory
 * @param AstSystem $ast_system
 * @return CfgSystem
 */
function createCfgSystem(CfgSystemFactoryInterface $cfg_system_factory, AstSystem $ast_system) {
	$cfg_system = $cfg_system_factory->create($ast_system);
	$cfg_traverser = new PHPCfg\Traverser;
	$cfg_system_traverser = new CfgBridge\SystemTraverser($cfg_traverser);
	$simplifier = new PHPCfg\Visitor\Simplifier;
	$cfg_system_traverser->addVisitor($simplifier);
	$cfg_system_traverser->traverse($cfg_system);
	return $cfg_system;
}

/**
 * @param CfgSystem $cfg_system
 * @return PHPTypes\State
 * @throws \InvalidArgumentException
 */
function calculateState(CfgSystem $cfg_system) {
	$type_reconstructor = new PHPTypes\TypeReconstructor();

	$scripts = [];
	foreach ($cfg_system->getFilenames() as $filename) {
		$scripts[] = $cfg_system->getScript($filename);
	}

	$state = new PHPTypes\State($scripts);
	$type_reconstructor->resolve($state);
	return $state;
}

/**
 * @param CfgSystem $cfg_system
 * @return PHPCfg\Visitor\AstNodeToCfgNodesCollector
 */
function linkingCfgPass(CfgSystem $cfg_system) {
	$cfg_traverser = new PHPCfg\Traverser;
	$cfg_system_traverser = new CfgBridge\SystemTraverser($cfg_traverser);
	$operand_ast_node_linker = new PHPCfg\Visitor\OperandAstNodeLinker();
	$ast_nodes_collector = new PHPCfg\Visitor\AstNodeToCfgNodesCollector();
	$cfg_system_traverser->addVisitor($operand_ast_node_linker);
	$cfg_system_traverser->addVisitor($ast_nodes_collector);
	$cfg_system_traverser->traverse($cfg_system);
	return $ast_nodes_collector;
}

/**
 * @param PHPTypes\State $state
 * @param PHPCfg\Visitor\AstNodeToCfgNodesCollector $ast_nodes_collector
 * @param AstSystem $ast_system
 * @return AstVisitor\ScopeCollector
 */
function calculateScopes(PHPTypes\State $state, PHPCfg\Visitor\AstNodeToCfgNodesCollector $ast_nodes_collector, AstSystem $ast_system) {
	$ast_traverser = new PhpParser\NodeTraverser;
	$ast_system_traverser = new AstSystemTraverser($ast_traverser);

	$scope_collector = new AstVisitor\ScopeCollector($state);
	$ast_system_traverser->addVisitor(new AstVisitor\TypesToAstVisitor($ast_nodes_collector->getLinkedOps(), $ast_nodes_collector->getLinkedOperands()));
	$ast_system_traverser->addVisitor($scope_collector);

	// now do a walk over the AST to collect the scopes
	$ast_system_traverser->traverse($ast_system);

	return $scope_collector;
}

/**
 * @param AstSystem $ast_system
 * @param PHPTypes\State $state
 * @return array
 */
function calculateClassMethodToMethodMap(AstSystem $ast_system, PHPTypes\State $state) {
	$partial_order = new PartialOrder(new MethodComparator($state));
	$method_collecting_visitor = new AstVisitor\MethodCollectingVisitor($partial_order);

	$ast_traverser = new PhpParser\NodeTraverser();
	$ast_system_traverser = new AstSystemTraverser($ast_traverser);
	$ast_system_traverser->addVisitor($method_collecting_visitor);
	$ast_system_traverser->traverse($ast_system);

	print "Created partial order with methods\n";

	$method_resolver = new MethodResolver($state);
	return $method_resolver->fromPartialOrder($partial_order);
}

function stringifyClassMethodToMethodMap(array $map) {
	$res = "";
	foreach ($map as $class => $call_sites) {
		foreach ($call_sites as $call_site => $methods) {
			$class_name = explode("\\", $class);
			$res .= sprintf("%s->%s() resolves to: \n", array_pop($class_name), $call_site);
			foreach ($methods as $method) {
				$methods_class = explode("\\", $method->getClass());
				$res .= sprintf("\t%s->%s\n",  array_pop($methods_class), $method->getName());
			}
		}
	}

	return $res;
}

function stringifyScopeCallsScopeMap(ScopeVisitor\CallToScopeLinkingVisitor $call_linker) {
	$res = "";
	$call_map = $call_linker->getCallerCallsCalleeScopes();
	foreach ($call_map as $caller) {
		foreach ($call_map[$caller] as $callee) {
			$res .= sprintf("%s calls %s\n",  $caller->getName(), $callee->getName());
		}
	}
	return $res;
}

function stringifyUnresolvedCallsPerScope($unresolved_calls) {
	$prettyPrinter = new PhpParser\PrettyPrinter\Standard;

	$res = "";
	/** @var \PhpExceptionFlow\Scope\Scope $caller */
	foreach ($unresolved_calls as $caller) {
		if ($unresolved_calls[$caller]->count() === 0) continue;

		$res .= sprintf("Scope %s has unresolved: \n", $caller->getName());
		foreach ($unresolved_calls[$caller] as $call_node) {
			$message = $unresolved_calls[$caller][$call_node];
			$call_string = $prettyPrinter->prettyPrint([$call_node]);
			$res .= sprintf("\t%s was unresolved with message '%s'\n", $call_string, $message);
		}
	}
	return $res;
}