<?php
/**
 * This implementation is taken from https://github.com/mwijngaard/php-pdg/
 */
namespace PhpExceptionFlow\CfgBridge\Parser;

use PHPCfg\Parser;
use PhpParser\ParserFactory;
use PhpExceptionFlow\AstBridge\Parser\FileParserInterface as AstFileParserInterface;

class WrappedParser implements FileParserInterface {
	/** @var AstFileParserInterface */
	private $ast_file_parser;
	/** @var Parser */
	private $cfg_parser;

	public function __construct(AstFileParserInterface $ast_file_parser) {
		$this->ast_file_parser = $ast_file_parser;
		$this->cfg_parser = new Parser((new ParserFactory())->create(ParserFactory::PREFER_PHP7));
	}

	public function parse($filename) {
		return $this->cfg_parser->parseAst($this->ast_file_parser->parse($filename), $filename);
	}
}