<?php
namespace PhpExceptionFlow\Collection\PartialOrder;

use PhpExceptionFlow\Collection\Test\AppendingVisitor;
use PhpExceptionFlow\Collection\PartialOrderInterface;
use PhpExceptionFlow\Collection\Test\Number;
use PhpExceptionFlow\Collection\Test\TestDivisibilityComparator;

class TopDownBreadthFirstTraverserTest extends \PHPUnit_Framework_TestCase {

	/** @var TopDownBreadthFirstTraverser $traverser */
	private $traverser;
	/** @var AppendingVisitor $appending_visitor*/
	private $visitor;
	/** @var PartialOrderInterface $partial_order */
	private $partial_order;

	public function setUp() {
		$this->visitor = new AppendingVisitor();
		$this->traverser = new TopDownBreadthFirstTraverser();
		$this->traverser->addVisitor($this->visitor);
		$comparator = new TestDivisibilityComparator();
		$this->partial_order = new PartialOrder($comparator);
	}

	public function testWithEmptyOrder() {
		$this->traverser->traverse($this->partial_order);
		$this->assertEmpty($this->visitor->element_stack);
	}

	public function testWithNonRelatedElements() {
		$five = new Number(5);
		$six = new Number(6);

		$this->partial_order->addElement($five);
		$this->partial_order->addElement($six);

		$this->traverser->traverse($this->partial_order);
		$this->assertEquals(array($five, $six), $this->visitor->element_stack);
	}

	public function testWithOnlyChildElements() {
		$fifteen = new Number(15);
		$five = new Number(5);
		$one = new Number(1);

		$this->partial_order->addElement($one);
		$this->partial_order->addElement($five);
		$this->partial_order->addElement($fifteen);

		$this->traverser->traverse($this->partial_order);
		$this->assertEquals(array($fifteen, $five, $one), $this->visitor->element_stack);
	}

	public function testIsReallyBreadfirst() {
		$one = new Number(1);
		$two = new Number(2);
		$three = new Number(3);
		$five = new Number(5);
		$six = new Number(6);

		$this->partial_order->addElement($one);
		$this->partial_order->addElement($two);
		$this->partial_order->addElement($three);
		$this->partial_order->addElement($five);
		$this->partial_order->addElement($six);

		$this->traverser->traverse($this->partial_order);
		$this->assertEquals(array(
			$five,      $six, //round 1
			$one,       $two, $three, // round 2
		), $this->visitor->element_stack);
	}

}