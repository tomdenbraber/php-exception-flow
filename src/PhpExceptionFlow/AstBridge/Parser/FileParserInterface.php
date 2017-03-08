<?php
/**
 * This implementation is taken from https://github.com/mwijngaard/php-pdg/
 */
namespace PhpExceptionFlow\AstBridge\Parser;

use PhpParser\Error;
use PhpParser\Node;

interface FileParserInterface {
	/**
	 * @param string $filename
	 * @return Node[]|null
	 */
	public function parse($filename);
	/**
	 * @return Error[]
	 */
	public function getErrors();
}