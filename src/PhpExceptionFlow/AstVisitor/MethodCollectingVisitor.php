<?php
namespace PhpExceptionFlow\AstVisitor;

use PhpExceptionFlow\CallGraphConstruction\Method;
use PhpExceptionFlow\Collection\PartialOrderInterface;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Class MethodCollectingVisitor
 * Collects all methods defined and adds them to the given partial order
 * @package PhpExceptionFlow\AstVisitor
 */
class MethodCollectingVisitor extends NodeVisitorAbstract {
	/** @var PartialOrderInterface $partial_order */
	private $partial_order;

	private $current_namespace = "";

	public function __construct(PartialOrderInterface $partial_order) {
		$this->partial_order = $partial_order;
	}

	public function enterNode(Node $node) {
		if ($node instanceof Node\Stmt\Namespace_ && $node->name !== null) {
			$this->current_namespace = strtolower(implode("\\", $node->name->parts));
		} else if ($node instanceof Node\Stmt\ClassLike) {
			$cls_name = strlen($this->current_namespace) > 0 ? $this->current_namespace . "\\" . strtolower($node->name) : strtolower($node->name);
			foreach ($node->getMethods() as $method) {
				$this->partial_order->addElement(new Method($cls_name, $method));
			}
		}
	}

	public function leaveNode(Node $node) {
		if ($node instanceof Node\Stmt\Namespace_) {
			$this->current_namespace = "";
		}
	}
}