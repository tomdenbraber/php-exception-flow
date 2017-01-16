<?php

namespace PhpExceptionFlow\AstVisitor;

use PHPCfg\Op;
use PHPCfg\Operand;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPTypes\Type;

class TypesToAstVisitor extends NodeVisitorAbstract {
	/** @var  \SplObjectStorage $ast_node_to_ops */
	private $ast_node_to_ops;
	/** @var  \SplObjectStorage $ast_node_to_operands */
	private $ast_node_to_operands;

	public function __construct(\SplObjectStorage $ast_node_to_ops, \SplObjectStorage $ast_node_to_operands ) {
		$this->ast_node_to_ops = $ast_node_to_ops;
		$this->ast_node_to_operands = $ast_node_to_operands;
	}

	public function enterNode(Node $node) {
		$related_ops = isset($this->ast_node_to_ops[$node]) === true ? $this->ast_node_to_ops[$node] : array();
		$related_operands = isset($this->ast_node_to_operands[$node]) === true ? $this->ast_node_to_operands[$node] : array();

		/** @var Type[] $types */
		$types = array();
		/** @var Op[] $related_ops */
		foreach ($related_ops as $related_op) {
			if ($related_op instanceof Op\Expr) {
				$types[] = $related_op->result->type;
			}
		}
		/** @var Operand[] $related_operandss */
		foreach ($related_operands as $related_operand) {
			$types[] = $related_operand->type;
		}

		$type = Type::union($types);
		if ($type !== null) {
			$node->setAttribute("type", $type);
		}
	}
}