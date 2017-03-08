<?php
/**
 * This implementation is taken from https://github.com/mwijngaard/php-pdg/
 */
namespace PhpExceptionFlow\CfgBridge\Parser;

use PHPCfg\Script;

interface FileParserInterface {
	/**
	 * @param string $filename
	 * @return Script
	 */
	public function parse($filename);
}