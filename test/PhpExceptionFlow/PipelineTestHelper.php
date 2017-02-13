<?php
namespace PhpExceptionFlow;

use PhpExceptionFlow\AstBridge\Parser\FileParserInterface as AstParser;
use PhpExceptionFlow\AstBridge\System as AstSystem;
use PhpExceptionFlow\AstBridge\SystemTraverser;
use PhpExceptionFlow\AstVisitor;
use PhpExceptionFlow\CallGraphConstruction\AppliesToCalculator;
use PhpExceptionFlow\CallGraphConstruction\AppliesToVisitor;
use PhpExceptionFlow\CallGraphConstruction\ChaMethodResolver;
use PhpExceptionFlow\CallGraphConstruction\CombiningClassMethodToMethodResolver;
use PhpExceptionFlow\CallGraphConstruction\OverridingMethodResolver;
use PhpExceptionFlow\CallGraphConstruction\MethodComparator;
use PhpExceptionFlow\CfgBridge\SystemFactoryInterface;
use PhpExceptionFlow\CfgBridge\System as CfgSystem;
use PhpExceptionFlow\Collection\PartialOrder\PartialOrder;
use PhpExceptionFlow\Collection\PartialOrder\TopDownBreadthFirstTraverser;
use PhpParser;
use PHPCfg;
use PHPTypes;

class PipelineTestHelper {
	/**
	 * @param AstParser $php_parser
	 * @param $filename
	 * @return AstSystem
	 */
	public static function getAstSystem(AstParser $php_parser, $filename) {
		$system = new AstSystem();
		$ast = $php_parser->parse($filename);
		$system->addAst($filename, $ast);
		return $system;
	}

	/**
	 * @param SystemFactoryInterface $cfg_system_factory
	 * @param AstSystem $ast_system
	 * @return CfgSystem
	 */
	public static function simplifyingCfgPass(SystemFactoryInterface $cfg_system_factory, AstSystem $ast_system) {

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
	public static function calculateState(CfgSystem $cfg_system) {
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
	public static function linkingCfgPass(CfgSystem $cfg_system) {
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
	public static function calculateScopes(PHPTypes\State $state, PHPCfg\Visitor\AstNodeToCfgNodesCollector $ast_nodes_collector, AstSystem $ast_system) {
		$ast_traverser = new PhpParser\NodeTraverser;
		$ast_system_traverser = new SystemTraverser($ast_traverser);

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
	public static function calculateMethodMap(AstSystem $ast_system, PHPTypes\State $state) {
		$partial_order = new PartialOrder(new MethodComparator($state));
		$method_collecting_visitor = new AstVisitor\MethodCollectingVisitor($partial_order);

		$ast_traverser = new PhpParser\NodeTraverser();
		$ast_system_traverser = new SystemTraverser($ast_traverser);
		$ast_system_traverser->addVisitor($method_collecting_visitor);
		$ast_system_traverser->traverse($ast_system);

		$contract_method_resolver = new OverridingMethodResolver();
		$cha_method_resolver = new ChaMethodResolver($state->classResolvedBy);
		$combinig_method_resolver = new CombiningClassMethodToMethodResolver();
		$combinig_method_resolver->addResolver($contract_method_resolver);
		$combinig_method_resolver->addResolver($cha_method_resolver);

		return $combinig_method_resolver->fromPartialOrder($partial_order);
	}
}