<?php
namespace PhpExceptionFlow;

use PhpExceptionFlow\AstVisitor;
use PhpParser;
use PHPCfg;
use PHPTypes;

class PipelineTestHelper {
	/**
	 * @param PhpParser\Parser $php_parser
	 * @param $code
	 * @return null|PhpParser\Node[]
	 */
	public static function getAst(PhpParser\Parser $php_parser, $code) {
		return $php_parser->parse($code);
	}

	/**
	 * @param PhpParser\Parser $php_parser
	 * @param PhpParser\Node[] $ast
	 * @return PHPCfg\Script
	 */
	public static function simplifyingCfgPass(PhpParser\Parser $php_parser, array $ast) {
		/** @var \PHPCfg\Parser $cfg_parser */
		$cfg_parser = new PHPCfg\Parser($php_parser);
		$simplifier = new PHPCfg\Visitor\Simplifier;

		$initial_cfg_traverser = new PHPCfg\Traverser;
		$initial_cfg_traverser->addVisitor($simplifier);

		/** @var PHPCfg\Script $script */
		$script = $cfg_parser->parseAst($ast, "foo.php");
		$initial_cfg_traverser->traverse($script);
		return $script;
	}

	public static function calculateState(PHPCfg\Script $script) {
		$type_reconstructor = new PHPTypes\TypeReconstructor();
		$state = new PHPTypes\State(array($script));
		$type_reconstructor->resolve($state);
		return $state;
	}

	/**
	 * @param PHPCfg\Script $script
	 * @return PHPCfg\Visitor\AstNodeToCfgNodesCollector
	 */
	public static function linkingCfgPass(PHPCfg\Script $script) {
		$linking_cfg_traverser = new PHPCfg\Traverser;
		$operand_ast_node_linker = new PHPCfg\Visitor\OperandAstNodeLinker();
		$ast_nodes_collector = new PHPCfg\Visitor\AstNodeToCfgNodesCollector();
		$linking_cfg_traverser->addVisitor($operand_ast_node_linker);
		$linking_cfg_traverser->addVisitor($ast_nodes_collector);
		$linking_cfg_traverser->traverse($script);
		return $ast_nodes_collector;
	}

	/**
	 * @param PHPTypes\State $state
	 * @param PHPCfg\Visitor\AstNodeToCfgNodesCollector $ast_nodes_collector
	 * @param PhpParser\Node[] $ast
	 * @return Scope[]
	 */
	public static function calculateScopes(PHPTypes\State $state, PHPCfg\Visitor\AstNodeToCfgNodesCollector $ast_nodes_collector, array $ast) {
		// now do a walk over the AST to collect the scopes
		$scope_collector = new AstVisitor\ScopeCollector($state);
		$ast_traverser = new PhpParser\NodeTraverser;
		$ast_traverser->addVisitor(new AstVisitor\TypesToAstVisitor($ast_nodes_collector->getLinkedOps(), $ast_nodes_collector->getLinkedOperands()));
		$ast_traverser->addVisitor($scope_collector);
		$ast_traverser->traverse($ast);

		return $scope_collector->getTopLevelScopes();
	}
}