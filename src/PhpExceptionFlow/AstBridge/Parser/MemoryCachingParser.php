<?php
/**
 * This implementation is taken from https://github.com/mwijngaard/php-pdg/
 */
namespace PhpExceptionFlow\AstBridge\Parser;

use PhpParser\Error;

class MemoryCachingParser implements FileParserInterface {
	/** @var FileParserInterface */
	private $wrapped_parser;
	private $cache = [];
	/** @var Error[] */
	private $errors = [];
	/**
	 * FileCachingParser constructor.
	 * @param FileParserInterface $wrapped_parser
	 */
	public function __construct(FileParserInterface $wrapped_parser) {
		$this->wrapped_parser = $wrapped_parser;
	}

	public function parse($filename) {
		if (isset($this->cache[$filename]) === true) {
			list($ast, $this->errors) = $this->cache[$filename];
			return $ast;
		}
		$ast = $this->wrapped_parser->parse($filename);
		$this->errors = $this->wrapped_parser->getErrors();
		$this->cache[$filename] = [$ast, $this->errors];
		return $ast;
	}

	public function getErrors() {
		return $this->errors;
	}

	public function clear() {
		$this->cache = [];
	}
}