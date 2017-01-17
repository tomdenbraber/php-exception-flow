<?php
namespace PhpExceptionFlow;

use PhpExceptionFlow\ScopeVisitor\PrintingVisitor;
use PhpExceptionFlow\ScopeVisitor\ExceptionSetsCalculatingVisitor;
use PhpExceptionFlow\AstVisitor;
use PhpParser;
use PHPCfg;
use PHPTypes;


class CompletePipelineTest extends \PHPUnit_Framework_TestCase {
	/** @var ScopeTraverser */
	private $traverser;
	/** @var PrintingVisitor */
	private $printing_visitor;
	/** @var ExceptionSetsCalculatingVisitor */
	private $exception_calcultor;

	public function setUp() {
		$this->traverser = new ScopeTraverser();
		$this->printing_visitor = new PrintingVisitor();
	}

	/** @dataProvider provideTestSetsCalculation */
	public function testSetsCalculation($code, $expected_output) {
		$php_parser = (new PhpParser\ParserFactory)->create(PhpParser\ParserFactory::PREFER_PHP7);

		$ast = $this->getAst($php_parser, $code);
		$script = $this->simplifyingCfgPass($php_parser, $ast);
		$state = $this->calculateState($script);
		$ast_nodes_collector = $this->linkingCfgPass($script);
		$scopes = $this->calculateScopes($state, $ast_nodes_collector, $ast);

		$this->exception_calcultor = new ExceptionSetsCalculatingVisitor($state);
		$this->traverser->addVisitor($this->exception_calcultor);
		$this->traverser->traverse($scopes);
		$this->traverser->removeVisitor($this->exception_calcultor);
		$this->traverser->addVisitor($this->printing_visitor);
		$this->traverser->traverse($scopes);

		$this->assertEquals(
			$this->canonicalize($expected_output),
			$this->canonicalize($this->printing_visitor->getResult())
		);
	}

	public function provideTestSetsCalculation() {
		$dir = __DIR__ . '/../assets/code';
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

	/**
	 * @param PhpParser\Parser $php_parser
	 * @param $code
	 * @return null|PhpParser\Node[]
	 */
	private function getAst(PhpParser\Parser $php_parser, $code) {
		return $php_parser->parse($code);
	}

	/**
	 * @param PhpParser\Parser $php_parser
	 * @param PhpParser\Node[] $ast
	 * @return PHPCfg\Script
	 */
	private function simplifyingCfgPass(PhpParser\Parser $php_parser, array $ast) {
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

	private function calculateState(PHPCfg\Script $script) {
		$type_reconstructor = new PHPTypes\TypeReconstructor();
		$state = new PHPTypes\State(array($script));
		$type_reconstructor->resolve($state);
		return $state;
	}

	/**
	 * @param PHPCfg\Script $script
	 * @return PHPCfg\Visitor\AstNodeToCfgNodesCollector
	 */
	private function linkingCfgPass(PHPCfg\Script $script) {
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
	private function calculateScopes(PHPTypes\State $state, PHPCfg\Visitor\AstNodeToCfgNodesCollector $ast_nodes_collector, array $ast) {
		// now do a walk over the AST to collect the scopes
		$scope_collector = new AstVisitor\ScopeCollector($state);
		$ast_traverser = new PhpParser\NodeTraverser;
		$ast_traverser->addVisitor(new AstVisitor\TypesToAstVisitor($ast_nodes_collector->getLinkedOps(), $ast_nodes_collector->getLinkedOperands()));
		$ast_traverser->addVisitor($scope_collector);
		$ast_traverser->traverse($ast);

		return array_merge(array($scope_collector->getMainScope()), $scope_collector->getFunctionScopes());
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