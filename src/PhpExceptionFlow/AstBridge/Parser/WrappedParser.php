<?php
/**
 * This implementation is taken from https://github.com/mwijngaard/php-pdg/
 */
namespace PhpExceptionFlow\AstBridge\Parser;

use PhpParser\Parser;

class WrappedParser implements FileParserInterface {
	private $string_parser;

	public function __construct(Parser $string_parser) {
		$this->string_parser = $string_parser;
	}

	public function parse($filename) {
		if (file_exists($filename) === false) {
			throw new \InvalidArgumentException("No such file: `$filename`");
		}
		return $this->string_parser->parse(file_get_contents($filename));
	}

	public function getErrors() {
		return $this->string_parser->getErrors();
	}
}