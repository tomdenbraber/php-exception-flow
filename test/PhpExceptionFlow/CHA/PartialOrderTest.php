<?php
namespace PhpExceptionFlow\CHA;

use PhpExceptionFlow\CHA\Test\Number;
use PhpExceptionFlow\CHA\Test\TestDivisibilityComparator;

class PartialOrderTest extends \PHPUnit_Framework_TestCase {
	/** @var PartialOrder */
	private $partial_order;

	public function setUp() {
		$comparator = new TestDivisibilityComparator();
		$this->partial_order = new PartialOrder($comparator);
	}

	public function testGetExtremesWithEmptyOrder() {
		$this->assertNull($this->partial_order->getGreatestElement());
		$this->assertNull($this->partial_order->getLeastElement());
		$this->assertEmpty($this->partial_order->getMaximalElements());
		$this->assertEmpty($this->partial_order->getMinimalElements());
	}

	public function testGetExtremesWithOneItem() {
		$five = new Number(5);
		$this->partial_order->addElement($five);
		$this->assertEquals($five, $this->partial_order->getGreatestElement());
		$this->assertEquals($five, $this->partial_order->getLeastElement());
		$this->assertEquals(array($five), $this->partial_order->getMaximalElements());
		$this->assertEquals(array($five), $this->partial_order->getMinimalElements());
	}

	public function testGetExtremesWithTwoNonRelatedItems() {
		$five = new Number(5);
		$six = new Number(6);
		$this->partial_order->addElement($five);
		$this->partial_order->addElement($six);
		$this->assertEquals(null, $this->partial_order->getGreatestElement());
		$this->assertEquals(null, $this->partial_order->getLeastElement());
		$this->assertEquals(array($five, $six), $this->partial_order->getMaximalElements());
		$this->assertEquals(array($five, $six), $this->partial_order->getMinimalElements());
	}

	public function testGetExtremesWithTwoRelatedItems() {
		$five = new Number(5);
		$twentyfive = new Number(25);
		$this->partial_order->addElement($five);
		$this->partial_order->addElement($twentyfive);

		$this->assertEquals(array($twentyfive), $this->partial_order->getMaximalElements());
		$this->assertEquals(array($five), $this->partial_order->getMinimalElements());
		$this->assertEquals($twentyfive, $this->partial_order->getGreatestElement());
		$this->assertEquals($five, $this->partial_order->getLeastElement());
	}

	public function testCorrectStructureWithThreeRelatedItems() {
		$five = new Number(5);
		$fifteen = new Number(15);
		$twentyfive = new Number(25);
		$this->partial_order->addElement($five);
		$this->partial_order->addElement($fifteen);
		$this->partial_order->addElement($twentyfive);

		$this->assertEquals(array(), $this->partial_order->getChildren($five));
		$this->assertEquals(array($five), $this->partial_order->getChildren($fifteen));
		$this->assertEquals(array($five), $this->partial_order->getChildren($twentyfive));

		$this->assertEquals(array(), $this->partial_order->getParents($twentyfive));
		$this->assertEquals(array(), $this->partial_order->getParents($fifteen));
		$this->assertEquals(array($fifteen, $twentyfive), $this->partial_order->getParents($five));
	}

	public function testCorrectStructureWhenItemGetsInsertedBetweenTwoOthers() {
		$five = new Number(5);
		$fifteen = new Number(15);
		$fourtyfive = new Number(45);

		$this->partial_order->addElement($five);
		$this->partial_order->addElement($fourtyfive);
		$this->partial_order->addElement($fifteen);

		$this->assertEquals(array($fourtyfive), $this->partial_order->getMaximalElements());
		$this->assertEquals(array($five), $this->partial_order->getMinimalElements());
		$this->assertEquals($fourtyfive, $this->partial_order->getGreatestElement());
		$this->assertEquals($five, $this->partial_order->getLeastElement());

		$this->assertEquals(array($fifteen, $fourtyfive), $this->partial_order->getAncestors($five));
		$this->assertEquals(array($fourtyfive), $this->partial_order->getAncestors($fifteen));
		$this->assertEquals(array(), $this->partial_order->getAncestors($fourtyfive));

		$this->assertEquals(array($fifteen, $five), $this->partial_order->getDescendants($fourtyfive));
		$this->assertEquals(array($five), $this->partial_order->getDescendants($fifteen));
		$this->assertEquals(array(), $this->partial_order->getDescendants($five));

		$this->assertEquals(array(), $this->partial_order->getChildren($five));
		$this->assertEquals(array($five), $this->partial_order->getChildren($fifteen));
		$this->assertEquals(array($fifteen), $this->partial_order->getChildren($fourtyfive));

		$this->assertEquals(array($fifteen), $this->partial_order->getParents($five));
		$this->assertEquals(array($fourtyfive), $this->partial_order->getParents($fifteen));
		$this->assertEquals(array(), $this->partial_order->getParents($fourtyfive));

	}
}