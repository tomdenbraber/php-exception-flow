<?php
namespace PhpExceptionFlow\AstVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * @package PhpExceptionFlow\AstVisitor
 */
class ThrowsCollector extends NodeVisitorAbstract {
	/** @var Node\Stmt\Throw_[] $throws */
	private $throws = [];
	/** @var bool $empty_collection_before_traverse */
	private $empty_collection_before_traverse;

	/**
	 * ThrowsCollector constructor.
	 * @param bool $empty_collection_before_traverse; if set to true, on beforeTraverse calls the collection of
	 *     this visitor will be emptied.
	 */
	public function __construct($empty_collection_before_traverse = false) {
		$this->empty_collection_before_traverse = $empty_collection_before_traverse;
	}

	public function beforeTraverse(array $nodes) {
		if ($this->empty_collection_before_traverse === true) {
			$this->throws = [];
		}
	}

	public function enterNode(Node $node) {
		if ($node instanceof Node\Stmt\Throw_) {
			$this->throws[] = $node;
		}
	}

	/**
	 * @return Node\Stmt\Throw_[]
	 */
	public function getThrows() {
		return $this->throws;
	}
}