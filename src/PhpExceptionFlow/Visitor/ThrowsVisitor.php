<?php
namespace PhpExceptionFlow\Visitor;


use PhpParser\Node;
use PhpParser\Node\Stmt\Throw_;
use \PhpParser\Node\Expr\New_;
use PhpParser\NodeVisitorAbstract;
use PHPTypes\Type;


class ThrowsVisitor extends NodeVisitorAbstract {
	public function enterNode(Node $node) {
		if ($node instanceof Throw_) {
			//todo this is an ugly, temporary and limited way of trying to deduct the type of the exception that is thrown. Should be improved upon by using PHPCfg/PHPTypes.
			/** @var $node Throw_ */
			$expression = $node->expr;
			if ($expression instanceof New_) {
				/** @var New_ $expression */
				if ($expression->class instanceof Node\Name) {
					$type = new Type(Type::TYPE_OBJECT, array(), implode("\\", $expression->class->parts));
					$node->setAttribute("exceptionType", $type);
					return $node;
				}
			}
		}

		return null;
	}
}