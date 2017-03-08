<?php

namespace PhpExceptionFlow\CfgBridge;

use PHPCfg\Traverser as CfgTraverser;
use PHPCfg\Visitor as CfgNodeVisitor;

class SystemTraverser implements SystemTraverserInterface {
	/** @var CfgTraverser */
	private $traverser;

	public function __construct(CfgTraverser $traverser) {
		$this->traverser = $traverser;
	}

	public function addVisitor(CfgNodeVisitor $visitor) {
		$this->traverser->addVisitor($visitor);
	}

	public function traverse(System $cfg_system) {
		foreach ($cfg_system->getFilenames() as $filename) {
			$this->traverser->traverse($cfg_system->getScript($filename));
		}
	}
}