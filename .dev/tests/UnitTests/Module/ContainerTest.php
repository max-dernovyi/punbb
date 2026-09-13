<?php
/**
 * The container builds what the modules wired and nothing else: no entry is
 * inferred from a class name, every service is created once, and a failure
 * says which service it was.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use PunBB\Module\Framework\Container\Container;
use PunBB\Module\Framework\Container\ContainerException;
use PunBB\Module\Framework\Container\NotFoundException;

class ContainerTest extends TestCase {
	public function testItIsAPsr11Container(): void {
		$this->assertInstanceOf(ContainerInterface::class, new Container(array()));
	}

	public function testAServiceIsBuiltByItsFactoryOnceAndShared(): void {
		$built = 0;
		$container = new Container(array(
			'clock' => function () use (&$built): object {
				$built++;
				return new stdClass();
			}
		));

		$first = $container->get('clock');

		$this->assertSame($first, $container->get('clock'));
		$this->assertSame(1, $built);
	}

	public function testAFactoryInjectsItsDependenciesThroughTheConstructor(): void {
		$container = new Container(array(
			'items'				=> fn (): object => new ArrayObject(array('a', 'b')),
			ArrayIterator::class	=> fn (Container $c): object => new ArrayIterator((array) $c->get('items')),
		));

		$iterator = $container->get(ArrayIterator::class);

		$this->assertInstanceOf(ArrayIterator::class, $iterator);
		$this->assertSame(array('a', 'b'), iterator_to_array($iterator));
	}

	public function testHasAnswersForWiredIdsOnly(): void {
		$container = new Container(array('wired' => fn (): object => new stdClass()));

		$this->assertTrue($container->has('wired'));
		$this->assertFalse($container->has('unwired'));
	}

	public function testNothingIsAutowiredFromAClassName(): void {
		$container = new Container(array());

		$this->assertFalse($container->has(stdClass::class));

		$this->expectException(NotFoundException::class);
		$container->get(stdClass::class);
	}

	public function testAnUnknownIdIsNotFound(): void {
		try {
			(new Container(array()))->get('nowhere');
			$this->fail('an unknown id was resolved');
		}
		catch (NotFoundExceptionInterface $e) {
			$this->assertStringContainsString('"nowhere"', $e->getMessage());
		}
	}

	/** PSR-11: a wired entry whose dependency is missing is an error, not "not found". */
	public function testAMissingDependencyIsAContainerErrorNotANotFound(): void {
		$container = new Container(array('needy' => fn (Container $c): object => $c->get('absent')));

		try {
			$container->get('needy');
			$this->fail('a service with a missing dependency was resolved');
		}
		catch (ContainerExceptionInterface $e) {
			$this->assertNotInstanceOf(NotFoundExceptionInterface::class, $e);
			$this->assertStringContainsString('"needy"', $e->getMessage());
			$this->assertStringContainsString('"absent"', $e->getMessage());
			$this->assertInstanceOf(NotFoundException::class, $e->getPrevious());
		}
	}

	public function testACircularDependencyNamesTheChain(): void {
		$container = new Container(array(
			'a'	=> fn (Container $c): object => $c->get('b'),
			'b'	=> fn (Container $c): object => $c->get('a'),
		));

		foreach (array(1, 2) as $attempt)
		{
			try {
				$container->get('a');
				$this->fail('a circular dependency was resolved');
			}
			catch (ContainerException $e) {
				// The same message on the second attempt: nothing is left marked as in progress.
				$this->assertSame('Circular service dependency: a -> b -> a', $e->getMessage(), 'attempt '.$attempt);
			}
		}
	}

	public function testAFailingFactoryIsReportedWithItsCause(): void {
		$cause = new RuntimeException('database unreachable');
		$container = new Container(array('broken' => function () use ($cause): object { throw $cause; }));

		try {
			$container->get('broken');
			$this->fail('a failing factory was reported as a service');
		}
		catch (ContainerException $e) {
			$this->assertSame($cause, $e->getPrevious());
			$this->assertStringContainsString('"broken"', $e->getMessage());
		}
	}

	public function testAFailedCreationIsNotCached(): void {
		$attempts = 0;
		$container = new Container(array(
			'flaky' => function () use (&$attempts): object {
				if (++$attempts === 1)
					throw new RuntimeException('first attempt fails');

				return new stdClass();
			}
		));

		try {
			$container->get('flaky');
		}
		catch (ContainerException) {
		}

		$this->assertInstanceOf(stdClass::class, $container->get('flaky'));
		$this->assertSame(2, $attempts);
	}

	public function testAClassNamedServiceMustBeAnInstanceOfThatClass(): void {
		$container = new Container(array(ArrayObject::class => fn (): object => new stdClass()));

		$this->expectException(ContainerException::class);
		$this->expectExceptionMessage('Service "ArrayObject" was wired to a stdClass');
		$container->get(ArrayObject::class);
	}
}
