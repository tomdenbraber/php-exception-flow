<?php

namespace PhpExceptionFlow\CfgBridge;

use PHPCfg\Visitor as CfgNodeVisitor;

interface SystemTraverserInterface {
	public function traverse(System $cfg_system);

	public function addVisitor(CfgNodeVisitor $visitor);
}