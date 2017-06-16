<?php
namespace PhpExceptionFlow;

use PHPCfg\Traverser as CfgTraverser;
use PHPCfg\Visitor\AstNodeToCfgNodesCollector;
use PHPCfg\Visitor\OperandAstNodeLinker;
use PHPCfg\Visitor\Simplifier as CfgSimplifier;
use PhpExceptionFlow\AstBridge\Parser\FileCachingParser as AstFileCachingParser;
use PhpExceptionFlow\AstBridge\Parser\WrappedParser as AstWrappedParser;
use PhpExceptionFlow\CallGraphConstruction\AstCallNodeToScopeResolver;
use PhpExceptionFlow\CallGraphConstruction\MethodComparator;
use PhpExceptionFlow\CallGraphConstruction\MethodResolver;
use PhpExceptionFlow\Collection\PartialOrder\PartialOrder;
use PhpExceptionFlow\Collection\PartialOrderInterface;
use PhpExceptionFlow\FlowCalculator\CombiningCalculatorInterface;
use PhpExceptionFlow\FlowCalculator\UncaughtCalculator;
use PhpExceptionFlow\Scope\ScopeVisitor\CalculatorWrappingVisitor;
use PhpExceptionFlow\Scope\ScopeVisitor\CallToScopeLinkingVisitor;
use PhpExceptionFlow\Scope\ScopeVisitor\CatchesPathVisitor;
use PhpExceptionFlow\Scope\ScopeVisitor\CaughtExceptionTypesCalculator;
use PhpExceptionFlow\Scope\ScopeVisitor\JsonPrintingVisitor;
use PhpParser\NodeTraverser as AstNodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PHPTypes\State;
use PhpExceptionFlow\AstBridge\System as AstSystem;
use PhpExceptionFlow\AstBridge\SystemTraverser as AstSystemTraverser;
use PhpExceptionFlow\CfgBridge;
use PhpExceptionFlow\CfgBridge\System as CfgSystem;
use PhpExceptionFlow\CfgBridge\SystemFactory as CfgSystemFactory;
use PhpExceptionFlow\CfgBridge\SystemFactoryInterface as CfgSystemFactoryInterface;
use PhpExceptionFlow\AstVisitor\ScopeCollector;
use PHPTypes\TypeReconstructor;

class Runner {
	/** @var AstSystem $ast_system */
	public $ast_system;
	/** @var CfgSystem $cfg_system */
	public $cfg_system;
	/** @var State $state */
	public $state;
	/** @var PartialOrderInterface $method_partial_order */
	public $method_partial_order;
	/** @var ScopeCollector $scope_collector*/
	public $scope_collector;
	/** @var array $class_method_to_method_map */
	public $class_method_to_method_map;
	/** @var string[] $output_files */
	public $output_files = [];

	/** @var string $path_to_project  */
	private $path_to_project;

	/** @var string $path_to_output_folder */
	private $path_to_output_folder;

	/** @var string $path_to_project_specific_output */
	private $path_to_project_specific_output;

	public function __construct(string $path_to_project, string $path_to_output_folder) {
		if (is_dir($path_to_project) === false) {
			throw new \UnexpectedValueException(sprintf("Cannot find path %s", $path_to_project));
		}

		if (!is_dir($path_to_output_folder)) {
			mkdir($path_to_output_folder);
		}

		$this->path_to_project = $path_to_project;
		$this->path_to_output_folder = $path_to_output_folder;
		$this->path_to_project_specific_output = $path_to_output_folder . "/" . basename(realpath($path_to_project));
	}


	public function run() {
		$this->createNeededDirectories(realpath($this->path_to_project), realpath($this->path_to_output_folder));

		$this->ast_system = $this->parseProject();
		$cfg_system_factory = CfgSystemFactory::createDefault();
		$this->cfg_system = $this->createCfgSystem($cfg_system_factory, $this->ast_system);
		$this->state = $this->calculateState($this->cfg_system);
		$ast_nodes_collector = $this->linkingCfgPass($this->cfg_system);
		$this->scope_collector = $this->calculateScopes($this->state, $ast_nodes_collector, $this->ast_system);
		$this->method_partial_order = $this->calculatePartialOrder($this->ast_system, $this->state);
		$this->class_method_to_method_map = $this->calculateClassMethodToMethodMap($this->method_partial_order, $this->state);


		$builtin_collector = new \PhpExceptionFlow\Scope\Collector\BuiltInCollector($this->state->internalTypeInfo);

		$combining_scope_collector = new \PhpExceptionFlow\Scope\Collector\CombiningScopeCollector([
			$this->scope_collector,
			$builtin_collector,
		]);

		$scopes = $combining_scope_collector->getTopLevelScopes();

		$call_resolver = new AstCallNodeToScopeResolver($combining_scope_collector->getMethodScopes(), $combining_scope_collector->getFunctionScopes(), $this->class_method_to_method_map, $this->state);
		$call_to_scope_linker = new CallToScopeLinkingVisitor(new AstNodeTraverser, new AstVisitor\CallCollector(), $call_resolver);

		$scope_traverser = new \PhpExceptionFlow\Scope\ScopeTraverser();
		$catch_clause_type_resolver = new CaughtExceptionTypesCalculator($this->state);
		$scope_traverser->addVisitor($catch_clause_type_resolver);
		$scope_traverser->addVisitor($call_to_scope_linker);
		$scope_traverser->traverse($scopes);
		$scope_traverser->removeVisitor($catch_clause_type_resolver);
		$scope_traverser->removeVisitor($call_to_scope_linker);

		$combining_calculator = $this->calculateEncounters($this->scope_collector, $call_to_scope_linker, $catch_clause_type_resolver);

		unset($this->ast_system);
		unset($this->cfg_system);

		file_put_contents($this->path_to_project_specific_output . "/method_order.json", json_encode($this->method_partial_order, JSON_PRETTY_PRINT));
		unset($this->method_partial_order);
		file_put_contents($this->path_to_project_specific_output . "/class_hierarchy.json", json_encode([
			"class resolves" => $this->state->classResolves,
			"class resolved by" => $this->state->classResolvedBy,
		], JSON_PRETTY_PRINT));
		unset($this->state);
		file_put_contents($this->path_to_project_specific_output . "/class_method_to_method.json", json_encode($this->serializeClassMethodToMethodMap($this->class_method_to_method_map), JSON_PRETTY_PRINT));
		unset($this->class_method_to_method_map);
		file_put_contents($this->path_to_project_specific_output . "/unresolved_calls.json", json_encode($this->serializeUnresolvedCalls($call_to_scope_linker->getUnresolvedCalls()), JSON_PRETTY_PRINT));
		file_put_contents($this->path_to_project_specific_output . "/scope_calls_scope.json", json_encode($this->serializeScopeCallsScopeMap($call_to_scope_linker), JSON_PRETTY_PRINT));
		unset($this->call_to_scope_linker);

		$json_printing_visitor = new JsonPrintingVisitor($combining_calculator);
		$scope_traverser->addVisitor($json_printing_visitor);
		$scope_traverser->traverse($this->scope_collector->getTopLevelScopes());
		$scope_traverser->removeVisitor($json_printing_visitor);
		file_put_contents($this->path_to_project_specific_output . "/exception_flow.json", $json_printing_visitor->getResult());
		unset($json_printing_visitor);


		/** @var UncaughtCalculator $uncaught_calculator */
		$uncaught_calculator = $combining_calculator->getCalculator("uncaught");
		$paths_file = fopen($this->path_to_project_specific_output . "/path_to_catch_clauses.json", "w");
		$caught_path_collecting_visitor = new CatchesPathVisitor($uncaught_calculator,$paths_file);
		$scope_traverser->addVisitor($caught_path_collecting_visitor);
		$scope_traverser->traverse($this->scope_collector->getTopLevelScopes());
		$scope_traverser->removeVisitor($caught_path_collecting_visitor);

		fclose($paths_file);

		//file_put_contents($this->path_to_project_specific_output . "/path_to_catch_clauses.json", json_encode($caught_path_collecting_visitor->getPaths(), JSON_PRETTY_PRINT));
		unset($caught_path_collecting_visitor);

		$this->output_files = [
			"exception flow" => $this->path_to_project_specific_output . "/exception_flow.json",
			"method order" => $this->path_to_project_specific_output . "/method_order.json",
			"class hierarchy" => $this->path_to_project_specific_output . "/class_hierarchy.json",
			"unresolved calls" => $this->path_to_project_specific_output . "/unresolved_calls.json",
			"class method to method" => $this->path_to_project_specific_output . "/class_method_to_method.json",
			"scope calls scope" => $this->path_to_project_specific_output . "/scope_calls_scope.json",
			"path to catch clauses" => $this->path_to_project_specific_output . "/path_to_catch_clauses.json",
			"ast system cache" => __DIR__ . "/../../cache/" .  basename(realpath($this->path_to_project)) . "/ast",
		];
	}

	private function parseProject() {
		$parsed_project = basename(realpath($this->path_to_project));
		$php_parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
		$wrapped_parser = new AstWrappedParser($php_parser);
		$caching_parser = new AstFileCachingParser(__DIR__ . "/../../cache/" . $parsed_project . "/ast", $wrapped_parser);

		$ast_system = new AstSystem();

		$dir = $this->path_to_project;
		$iter = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir), \RecursiveIteratorIterator::LEAVES_ONLY);

		$skipped_files = 0;
		/** @var \SplFileInfo $file */
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
		return $ast_system;
	}

	/**
	 * @param CfgSystemFactoryInterface $cfg_system_factory
	 * @param AstSystem $ast_system
	 * @return CfgSystem
	 */
	private function createCfgSystem(CfgSystemFactoryInterface $cfg_system_factory, AstSystem $ast_system) {
		$cfg_system = $cfg_system_factory->create($ast_system);
		$cfg_traverser = new CfgTraverser();
		$cfg_system_traverser = new CfgBridge\SystemTraverser($cfg_traverser);
		$simplifier = new CfgSimplifier;
		$cfg_system_traverser->addVisitor($simplifier);
		$cfg_system_traverser->traverse($cfg_system);
		return $cfg_system;
	}


	/**
	 * @param CfgSystem $cfg_system
	 * @return State
	 * @throws \InvalidArgumentException
	 */
	private function calculateState(CfgSystem $cfg_system) {
		$type_reconstructor = new TypeReconstructor;

		$scripts = [];
		foreach ($cfg_system->getFilenames() as $filename) {
			$scripts[] = $cfg_system->getScript($filename);
		}

		$state = new State($scripts);
		$type_reconstructor->resolve($state);
		return $state;
	}

	/**
	 * @param CfgSystem $cfg_system
	 * @return AstNodeToCfgNodesCollector
	 */
	private function linkingCfgPass(CfgSystem $cfg_system) {
		$cfg_traverser = new CfgTraverser;
		$cfg_system_traverser = new CfgBridge\SystemTraverser($cfg_traverser);
		$operand_ast_node_linker = new OperandAstNodeLinker();
		$ast_nodes_collector = new AstNodeToCfgNodesCollector;
		$cfg_system_traverser->addVisitor($operand_ast_node_linker);
		$cfg_system_traverser->addVisitor($ast_nodes_collector);
		$cfg_system_traverser->traverse($cfg_system);
		return $ast_nodes_collector;
	}

	/**
	 * @param State $state
	 * @param AstNodeToCfgNodesCollector $ast_nodes_collector
	 * @param AstSystem $ast_system
	 * @return AstVisitor\ScopeCollector
	 */
	private function calculateScopes(State $state, AstNodeToCfgNodesCollector $ast_nodes_collector, AstSystem $ast_system) {
		$ast_traverser = new AstNodeTraverser();
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
	* @param State $state
	* @return PartialOrderInterface
	*/
	private function calculatePartialOrder(AstSystem $ast_system, State $state) {
		$partial_order = new PartialOrder(new MethodComparator($state));
		$method_collecting_visitor = new AstVisitor\MethodCollectingVisitor($partial_order);

		$ast_traverser = new AstNodeTraverser();
		$ast_system_traverser = new AstSystemTraverser($ast_traverser);
		$ast_system_traverser->addVisitor($method_collecting_visitor);
		$ast_system_traverser->traverse($ast_system);

		return $partial_order;
	}

	/**
	 * @param PartialOrderInterface $partial_order
	 * @param State $state
	 * @return CallGraphConstruction\Method[][][]
	 */
	private function calculateClassMethodToMethodMap(PartialOrderInterface $partial_order, State $state) {
		$method_resolver = new MethodResolver($state);
		return $method_resolver->fromPartialOrder($partial_order);
	}

	/**
	 * @param $scope_collector
	 * @param $call_to_scope_linker
	 * @param $catch_clause_type_resolver
	 * @throws \LogicException
	 * @return CombiningCalculatorInterface
	 */
	private function calculateEncounters(ScopeCollector $scope_collector, CallToScopeLinkingVisitor $call_to_scope_linker, CaughtExceptionTypesCalculator $catch_clause_type_resolver) {
		$combining_mutable = new \PhpExceptionFlow\FlowCalculator\CombiningCalculator();
		$combining_immutable = new \PhpExceptionFlow\FlowCalculator\CombiningCalculator();

		$encounters_calc = new \PhpExceptionFlow\EncountersCalculator($combining_mutable, $combining_immutable, $call_to_scope_linker->getCalleeCalledByCallerScopes());

		$raises_calculator = new \PhpExceptionFlow\FlowCalculator\RaisesCalculator(new AstNodeTraverser(), new AstVisitor\ThrowsCollector(true));
		$raises_scope_traverser = new \PhpExceptionFlow\Scope\ScopeTraverser();
		$raises_wrapping_visitor = new CalculatorWrappingVisitor($raises_calculator, CalculatorWrappingVisitor::CALCULATE_ON_ENTER);
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

		$encounters_calc->calculateEncounters($scope_collector->getTopLevelScopes());

		return $combining;
	}


	/**
	 * @param array $map
	 * @return array
	 */
	private function serializeClassMethodToMethodMap(array $map) {
		$res = [];
		foreach ($map as $class => $call_sites) {
			$res[$class] = [];
			foreach ($call_sites as $call_site => $methods) {
				$res[$class][$call_site] = [];
				foreach ($methods as $method) {
					$res[$class][$call_site][] = (string)$method;
				}


				/*$res .= sprintf("%s->%s() resolves to: \n", array_pop($class_name), $call_site);
				foreach ($methods as $method) {
					$methods_class = explode("\\", $method->getClass());
					$res .= sprintf("\t%s->%s\n",  array_pop($methods_class), $method->getName());
				}*/
			}
		}

		return $res;
	}

	/**
	 * @param CallToScopeLinkingVisitor $call_linker
	 * @return array
	 */
	private function serializeScopeCallsScopeMap(CallToScopeLinkingVisitor $call_linker) {
		$res = [];
		$call_map = $call_linker->getCallerCallsCalleeScopes();
		foreach ($call_map as $caller) {
			$res[$caller->getName()] = [];
			foreach ($call_map[$caller] as $callee) {
				$res[$caller->getName()][] = $callee->getName();
			}
		}
		return $res;
	}

	/**
	 * @param $unresolved_calls
	 * @return array
	 */
	private function serializeUnresolvedCalls($unresolved_calls) {
		$prettyPrinter = new Standard();
		$res = [];
		/** @var \PhpExceptionFlow\Scope\Scope $caller */
		foreach ($unresolved_calls as $caller) {
			$res[$caller->getName()] = [];
			foreach ($unresolved_calls[$caller] as $call_node) {
				$res[$caller->getName()][] = [
					"message" => $unresolved_calls[$caller][$call_node],
					"code" => $prettyPrinter->prettyPrint([$call_node]),
				];
			}
		}
		return $res;
	}

	private function createNeededDirectories($path_to_project, $results_folder) {
		$project_name = basename(realpath($path_to_project));

		if (is_dir(__DIR__ . "/../../cache/" . $project_name) === false) {
			mkdir(__DIR__ . "/../../cache/" . $project_name, 0777, true);
			mkdir(__DIR__ . "/../../cache/" . $project_name . "/ast", 0777, true);
		}

		if (is_dir($results_folder . "/" . $project_name) === false) {
			mkdir($results_folder . "/" . $project_name, 0777, true);
		} else {
			throw new \LogicException(sprintf("The output folder %s already exists.", $results_folder . "/" . $project_name));
		}
	}
}