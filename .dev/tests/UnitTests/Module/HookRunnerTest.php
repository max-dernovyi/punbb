<?php
/**
 * The bridge's runners reproduce a legacy hook site: what the stored code does
 * to the variables a point exposes reaches the caller, a return ends the point,
 * and a markup point's output comes back exactly as the site would have
 * emitted it. The stored code is handed in directly; the fixture extension
 * drives the real hooks cache in BridgeFixtureExtensionTest.
 *
 * Both runners are deprecated entries: every test here expects the marker of
 * the one it calls, and only those two notices are exempt from failing the run.
 *
 * @copyright (C) 2008-2012 PunBB, partially based on code (C) 2008-2009 FluxBB.org
 * @license http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 * @package PunBB
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use PunBB\Module\Framework\Event\EventInterface;
use PunBB\Module\Framework\Modules\ModuleRegistry;
use PunBB\Module\LegacyBridge\Hook\HookException;
use PunBB\Module\LegacyBridge\Hook\HookMap;
use PunBB\Module\LegacyBridge\Hook\MarkupHookRunner;
use PunBB\Module\LegacyBridge\Hook\PointEvaluator;
use PunBB\Module\LegacyBridge\Hook\StatementHookRunner;
use PunBBFixture\Module\Greeting\Event\GreetingSending;
use PunBBFixture\Module\Greeting\Model\Greeting;

#[IgnoreDeprecations('^Method PunBB\\\\Module\\\\LegacyBridge\\\\Hook\\\\(StatementHookRunner::run|MarkupHookRunner::render)\\(\\) is deprecated since 2\\.0, ')]
class HookRunnerTest extends TestCase {
	private const RUN_NOTICE = 'Method PunBB\\Module\\LegacyBridge\\Hook\\StatementHookRunner::run() is deprecated since 2.0, use the event or the plugged contract method that replaces the point';

	private const RENDER_NOTICE = 'Method PunBB\\Module\\LegacyBridge\\Hook\\MarkupHookRunner::render() is deprecated since 2.0, use the event that replaces the point, rendered by the layout';

	/**
	 * @param array<string, list<string>> $stored point => the bodies attached to it, joined as get_hook() joins them
	 * @param array<string, string> $covered
	 */
	private static function points(array $stored, array $covered = array()): PointEvaluator {
		return new PointEvaluator(new HookMap($covered), static fn (string $point): string => implode("\n", $stored[$point] ?? array()));
	}

	/**
	 * @param array<string, list<string>> $stored
	 * @param array<string, string> $covered
	 */
	private function statements(array $stored, array $covered = array()): StatementHookRunner {
		$this->expectUserDeprecationMessage(self::RUN_NOTICE);

		return new StatementHookRunner(self::points($stored, $covered));
	}

	/** @param array<string, list<string>> $stored */
	private function markup(array $stored): MarkupHookRunner {
		$this->expectUserDeprecationMessage(self::RENDER_NOTICE);

		return new MarkupHookRunner(self::points($stored));
	}

	public function testAnExposedArrayChangedByTheHookReachesTheCaller(): void {
		$query = array('SELECT' => 'f.id', 'FROM' => 'forums AS f', 'WHERE' => 'f.cat_id=1');

		$this->statements(array('x_qr' => array('$query[\'WHERE\'] = \'(\'.$query[\'WHERE\'].\') AND f.hidden=0\';')))->run('x_qr', array('query' => &$query));

		$this->assertSame('(f.cat_id=1) AND f.hidden=0', $query['WHERE']);
	}

	/** An ArrayAccess object silently drops these appends; the hook has to receive a plain array. */
	public function testAppendsToANestedElementOfAnExposedArrayReachTheCaller(): void {
		$query = array('SELECT' => 'p.id', 'FROM' => 'posts AS p', 'JOINS' => array());

		$this->statements(array('x_qr' => array(
			'$query[\'JOINS\'][] = array(\'LEFT JOIN\' => \'topics AS t\', \'ON\' => \'t.id=p.topic_id\');',
			'$query[\'JOINS\'][] = array(\'LEFT JOIN\' => \'users AS u\', \'ON\' => \'u.id=p.poster_id\');',
		)))->run('x_qr', array('query' => &$query));

		$this->assertSame(array('topics AS t', 'users AS u'), array_column($query['JOINS'], 'LEFT JOIN'));
	}

	public function testEveryExposedVariableIsReadAndWrittenByName(): void {
		$id = 7;
		$cur_topic = array('subject' => 'Hello');

		$returned = $this->statements(array('x' => array('$cur_topic[\'subject\'] .= \' #\'.$id; $id = 8; return $id + 1;')))
			->run('x', array('id' => &$id, 'cur_topic' => &$cur_topic));

		$this->assertSame(9, $returned);
		$this->assertSame(8, $id);
		$this->assertSame('Hello #7', $cur_topic['subject']);
	}

	/** Written back is what the hook changed, so a change made meanwhile by other code survives. */
	public function testAVariableTheHookLeavesAloneIsNotWrittenBack(): void {
		$counter = 1;
		$bump = function () use (&$counter): void {
			$counter = 2;
		};

		$this->statements(array('x' => array('$bump();')))->run('x', array('counter' => &$counter, 'bump' => &$bump));

		$this->assertSame(2, $counter);
	}

	public function testAReturnEndsThePointForTheExtensionsAfterIt(): void {
		$trail = array();

		$returned = $this->statements(array('x' => array(
			'$trail[] = \'first\';',
			'$trail[] = \'second\'; return \'short-circuit\';',
			'$trail[] = \'third\';',
		)))->run('x', array('trail' => &$trail));

		$this->assertSame('short-circuit', $returned);
		$this->assertSame(array('first', 'second'), $trail);
	}

	public function testAPointThatDoesNotReturnReturnsNull(): void {
		$value = 'kept';

		$this->assertNull($this->statements(array('x' => array('$unused = 1;')))->run('x', array('value' => &$value)));
		$this->assertSame('kept', $value);
	}

	public function testAPointWithNothingStoredRunsNothing(): void {
		$value = 'kept';

		$this->assertNull($this->statements(array())->run('x', array('value' => &$value)));
		$this->assertSame('kept', $value);
	}

	public function testAChangeMadeBeforeAThrowStillReachesTheCaller(): void {
		$query = array('WHERE' => '1=1');

		try {
			$this->statements(array('x' => array('$query[\'WHERE\'] = \'0=1\'; throw new RuntimeException(\'hook failed\');')))->run('x', array('query' => &$query));
			$this->fail('the hook\'s exception did not reach the caller');
		}
		catch (RuntimeException $e) {
			$this->assertSame('hook failed', $e->getMessage());
		}

		$this->assertSame('0=1', $query['WHERE']);
	}

	public function testAnUnsetExposedVariableReadsAsNullForTheCaller(): void {
		$forum_page = array('crumbs' => array());

		$this->statements(array('x' => array('unset($forum_page);')))->run('x', array('forum_page' => &$forum_page));

		$this->assertNull($forum_page);
	}

	public function testAnUnsetThenReassignedVariableReachesTheCaller(): void {
		$value = 'original';

		$this->statements(array('x' => array('unset($value); $value = \'replaced\';')))->run('x', array('value' => &$value));

		$this->assertSame('replaced', $value);
	}

	public function testAnUnsetBeforeAThrowStillReachesTheCaller(): void {
		$forum_page = array('crumbs' => array());

		try {
			$this->statements(array('x' => array('unset($forum_page); throw new RuntimeException(\'hook failed\');')))->run('x', array('forum_page' => &$forum_page));
			$this->fail('the hook\'s exception did not reach the caller');
		}
		catch (RuntimeException $e) {
			$this->assertSame('hook failed', $e->getMessage());
		}

		$this->assertNull($forum_page);
	}

	/** Stored code was written for the global namespace and weak typing, not for the bridge's strict file. */
	public function testStoredCodeRunsInTheGlobalNamespaceWithWeakTypes(): void {
		$seen = array();

		$this->statements(array('x' => array('$seen = array(__NAMESPACE__, strlen(12345), forum_trim(\'  a  \'));')))->run('x', array('seen' => &$seen));

		$this->assertSame(array('', 5, 'a'), $seen);
	}

	public function testTheHookSeesOnlyWhatThePointExposesAndItsOwnSource(): void {
		$seen = array();
		$code = '$seen = array_keys(get_defined_vars());';

		$this->statements(array('x' => array($code)))->run('x', array('seen' => &$seen));

		$this->assertSame(array('hook', 'seen'), $seen);
	}

	public function testAValueExposedByValueIsRefused(): void {
		$query = array();

		$this->expectException(HookException::class);
		$this->expectExceptionMessage('exposes $query by value');

		$this->statements(array('x' => array('$query[] = 1;')))->run('x', array('query' => $query));
	}

	/** @return array<string, array{int|string}> */
	public static function unusableNameProvider(): array {
		return array(
			'this'			=> array('this'),
			'globals'		=> array('GLOBALS'),
			'the source'	=> array('hook'),
			'the values'	=> array('exposed'),
			'not a name'	=> array('forum-page'),
			'a position'	=> array(0),
		);
	}

	#[DataProvider('unusableNameProvider')]
	public function testANameAHookCannotReceiveIsRefused(int|string $name): void {
		$value = 1;
		$ran = false;
		$this->expectUserDeprecationMessage(self::RUN_NOTICE);
		$runner = new StatementHookRunner(new PointEvaluator(new HookMap(), static function () use (&$ran): string {
			$ran = true;

			return '';
		}));

		try {
			$runner->run('x', array($name => &$value));
			$this->fail('"'.$name.'" was accepted');
		}
		catch (HookException $e) {
			$this->assertStringContainsString('which a hook cannot receive as a variable', $e->getMessage());
		}

		$this->assertFalse($ran, 'the stored code was looked up for a point exposing an unusable name');
	}

	public function testAPointTheMapCoversIsRefusedAndItsCodeDoesNotRun(): void {
		$ran = false;
		$runner = $this->statements(array('x_moved' => array('$ran = true;'), 'x_legacy' => array('$ran = true;')), array('x_moved' => 'PunBBFixture\\Module\\Greeting\\Event\\GreetingSending'));

		try {
			$runner->run('x_moved', array('ran' => &$ran));
			$this->fail('a covered point ran its stored code');
		}
		catch (HookException $e) {
			$this->assertStringContainsString('x_moved is covered by PunBBFixture\\Module\\Greeting\\Event\\GreetingSending', $e->getMessage());
		}

		$this->assertFalse($ran);

		$runner->run('x_legacy', array('ran' => &$ran));
		$this->assertTrue($ran, 'a point the map does not cover did not fall back to the legacy path');
	}

	/** The bridge's observer of the covering event is where a covered point's stored code runs, and nowhere else. */
	public function testACoveredPointRunsWhereItsCoveringEventIsObserved(): void {
		$ran = 0;
		$points = self::points(array('x_moved' => array('$ran++; echo \'moved\';'), 'x_legacy' => array('$ran++;')), array('x_moved' => GreetingSending::class));
		$event = new GreetingSending(new Greeting('Rick', 'Hello, Rick'));

		ob_start();
		$points->run('x_moved', array('ran' => &$ran), $event);
		ob_end_clean();
		$this->assertSame(1, $ran);
		$this->assertSame('moved', $points->render('x_moved', array('ran' => &$ran), $event));
		$this->assertSame(2, $ran);

		foreach (array(
			'x_moved is covered by '.GreetingSending::class	=> fn () => $points->run('x_moved', array('ran' => &$ran), new class implements EventInterface {}),
			'x_legacy is not covered by '.GreetingSending::class	=> fn () => $points->run('x_legacy', array('ran' => &$ran), $event),
		) as $message => $run)
		{
			try {
				$run();
				$this->fail('ran: '.$message);
			}
			catch (HookException $e) {
				$this->assertStringContainsString($message, $e->getMessage());
			}
		}

		$this->assertSame(2, $ran);
	}

	/** A point a plugin covers runs from that plugin's contract method, and from nothing else. */
	public function testAPluggedPointRunsOnlyFromTheMethodCoveringIt(): void {
		$query = array('WHERE' => 'u.id > 1');
		$points = self::points(array('x_qr' => array('$query[\'WHERE\'] .= \' AND u.id=2\';')), array('x_qr' => 'PunBBFixture\\Module\\Greeting\\Api\\GreeterInterface::greet'));

		$points->runPlugged('x_qr', array('query' => &$query), 'PunBBFixture\\Module\\Greeting\\Api\\GreeterInterface::greet');
		$this->assertSame('u.id > 1 AND u.id=2', $query['WHERE']);

		foreach (array(
			'x_qr is covered by PunBBFixture\\Module\\Greeting\\Api\\GreeterInterface::greet, not by PunBBFixture\\Module\\Greeting\\Api\\GreeterInterface::farewell' => fn () => $points->runPlugged('x_qr', array('query' => &$query), 'PunBBFixture\\Module\\Greeting\\Api\\GreeterInterface::farewell'),
			'x_other is covered by nothing, not by PunBBFixture\\Module\\Greeting\\Api\\GreeterInterface::greet' => fn () => $points->runPlugged('x_other', array(), 'PunBBFixture\\Module\\Greeting\\Api\\GreeterInterface::greet'),
			'x_qr is covered by PunBBFixture\\Module\\Greeting\\Api\\GreeterInterface::greet, where its stored code runs' => fn () => $points->run('x_qr', array('query' => &$query)),
		) as $message => $run)
		{
			try {
				$run();
				$this->fail('ran: '.$message);
			}
			catch (HookException $e) {
				$this->assertStringContainsString($message, $e->getMessage());
			}
		}

		$this->assertSame('u.id > 1 AND u.id=2', $query['WHERE']);
	}

	/** A page script ran its points at global scope, so what one point creates the bridge keeps for the next. */
	public function testTheVariablesStoredCodeCreatesAreHandedOverByName(): void {
		$id = 3;
		$created = null;
		$points = self::points(array('x' => array('$id = 4; $new_thing = array($id); $another = \'text\'; unset($another);'), 'x_markup' => array('echo \'shown\'; $made = 1;')));

		$points->run('x', array('id' => &$id), null, function (array $variables) use (&$created): void { $created = $variables; });

		$this->assertSame(4, $id);
		$this->assertSame(array('new_thing' => array(4)), $created, 'neither an exposed variable nor one the code unset again');

		$markup = null;
		$this->assertSame('shown', $points->render('x_markup', array(), null, function (array $variables) use (&$markup): void { $markup = $variables; }));
		$this->assertSame(array('made' => 1), $markup);
	}

	public function testMarkupThatClosesPhpLandsAtTheSitesPosition(): void {
		$forum_page = array('item_count' => 3);
		$markup = $this->markup(array('x_markup' => array(
			'?>'."\n".'<p class="ext">Post <?php echo $forum_page[\'item_count\']++ ?></p>'."\n".'<?php',
			'echo "\t<p class=\"ext-after\">".$forum_page[\'item_count\']."</p>\n";',
		)));

		ob_start();
		$html = $markup->render('x_markup', array('forum_page' => &$forum_page));
		$this->assertSame('', ob_get_clean(), 'the runner emitted its output instead of handing it back');

		$page = "<div class=\"entry\">\n".$html."</div>\n";

		$this->assertSame("<div class=\"entry\">\n".'<p class="ext">Post 3</p>'."\n\t<p class=\"ext-after\">4</p>\n</div>\n", $page);
		$this->assertSame(4, $forum_page['item_count']);
	}

	public function testAReturnAtAMarkupPointEndsItAndKeepsWhatWasEmitted(): void {
		$markup = $this->markup(array('x_markup' => array('echo \'<p>first</p>\'; return \'ignored\';', 'echo \'<p>second</p>\';')));

		$this->assertSame('<p>first</p>', $markup->render('x_markup', array()));
	}

	public function testAThrowAtAMarkupPointLeavesItsOutputInPlaceAndTheBuffersBalanced(): void {
		$markup = $this->markup(array('x_markup' => array('echo \'<p>partial</p>\'; throw new RuntimeException(\'hook failed\');')));
		$level = ob_get_level();

		ob_start();
		echo '<div>';
		try {
			$markup->render('x_markup', array());
			$this->fail('the hook\'s exception did not reach the template');
		}
		catch (RuntimeException $e) {
			$this->assertSame('hook failed', $e->getMessage());
		}
		$page = (string) ob_get_clean();

		$this->assertSame('<div><p>partial</p>', $page);
		$this->assertSame($level, ob_get_level());
	}

	public function testTheModuleWiresBothRunnersOverTheHooksCache(): void {
		$container = ModuleRegistry::discover(FORUM_ROOT.'include/PunBB/Module', 'PunBB\\Module\\')->container();
		$this->expectUserDeprecationMessage(self::RUN_NOTICE);
		$this->expectUserDeprecationMessage(self::RENDER_NOTICE);

		// The unit bootstrap defines FORUM_DISABLE_HOOKS, and get_hook() honours it for the bridge too.
		$stored = $GLOBALS['forum_hooks'] ?? null;
		$GLOBALS['forum_hooks'] = array('x' => array('echo \'ran\'; return \'ran\';'));

		try {
			$this->assertNull($container->get(StatementHookRunner::class)->run('x', array()));
			$this->assertSame('', $container->get(MarkupHookRunner::class)->render('x', array()));
		}
		finally {
			$GLOBALS['forum_hooks'] = $stored;
		}
	}
}
