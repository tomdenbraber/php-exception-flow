<?php
namespace PhpExceptionFlow;

use PhpExceptionFlow\ScopeVisitor\PrintingVisitor;
use PhpExceptionFlow\ScopeVisitor\ExceptionSetsCalculatingVisitor;
use PhpParser;



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

		$ast = PipelineTestHelper::getAst($php_parser, $code);
		$script = PipelineTestHelper::simplifyingCfgPass($php_parser, $ast);
		$state = PipelineTestHelper::calculateState($script);
		$ast_nodes_collector = PipelineTestHelper::linkingCfgPass($script);
		$scopes = PipelineTestHelper::calculateScopes($state, $ast_nodes_collector, $ast);

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