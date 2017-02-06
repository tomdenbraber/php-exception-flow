<?php
/**
 * This implementation is taken from https://github.com/mwijngaard/php-pdg/
 */
namespace PhpExceptionFlow\AstBridge;

use PhpParser\Node;

class System {
	private $asts = [];
	/**
	 * @param string $filename
	 * @param Node[] $ast
	 * @throws \InvalidArgumentException if the AST of a certain file already has been loaded or if the AST does not consist of all Nodes.
	 */
	public function addAst($filename, array $ast) {
		if (isset($this->asts[$filename]) === true) {
			throw new \InvalidArgumentException("AST with filename `$filename` already exists");
		}
		foreach ($ast as $node) {
			if (is_object($node) === false || ($node instanceof Node) === false) {
				throw new \InvalidArgumentException("AST must be an array of Node objects");
			}
		}
		$this->asts[$filename] = $ast;
	}
	public function getFilenames() {
		return array_keys($this->asts);
	}
	public function getAst($filename) {
		if (isset($this->asts[$filename]) === false) {
			throw new \InvalidArgumentException("No AST with filename `$filename`");
		}
		return $this->asts[$filename];
	}
}
