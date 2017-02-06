<?php
/**
 * This implementation is taken from https://github.com/mwijngaard/php-pdg/
 */
namespace PhpExceptionFlow\CfgBridge;

use PhpExceptionFlow\AstBridge\System as AstSystem;

interface SystemFactoryInterface {
	/**
	 * @param AstSystem $ast_system
	 * @return System
	 */
	public function create(AstSystem $ast_system);
}