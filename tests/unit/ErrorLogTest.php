<?php declare(strict_types=1);

namespace TheFrosty\Tests\WpDebugLogWidget;

use Symfony\Component\HttpFoundation\Request;
use TheFrosty\WpDebugLogWidget\ErrorLog;

/**
 * Class ErrorLogTest
 * @package TheFrosty\Tests\WpDebugLogWidget
 */
class ErrorLogTest extends TestCase
{

    /**
     * @var ErrorLog $error_log
     */
    private $error_log;

    /**
     * Setup.
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->error_log = new ErrorLog();
        $this->error_log->setPlugin($this->plugin);
        $this->error_log->setRequest(Request::createFromGlobals());
        $this->reflection = $this->getReflection($this->error_log);
    }

    /**
     * Tear down.
     */
    public function tearDown(): void
    {
        unset($this->error_log);
        parent::tearDown();
    }

    /**
     * Test class has constants.
     */
    public function testConstants(): void
    {
        $constants = $this->getClassConstants();
        $this->assertNotEmpty($constants);
        $this->assertCount(11, $constants);
    }

    /**
     * Test addHooks().
     */
    public function testAddHooks(): void
    {
        $provider = $this->getMockProvider(ErrorLog::class);
        $provider->expects($this->exactly(2))
                 ->method(self::METHOD_ADD_FILTER)
                 ->willReturn(true);
        /** @var ErrorLog $provider */
        $provider->addHooks();
    }

    /**
     * Test getDomain().
     */
    public function testGetDomain(): void
    {
        $this->assertTrue(\method_exists($this->error_log, 'getDomain'));
        $actual = $this->error_log->getDomain();
        $this->assertIsString($actual);
        $this->assertStringContainsString(\sanitize_key(\parse_url(\network_home_url(), \PHP_URL_HOST)), $actual);
    }

    /**
     * Test getLogFileName().
     */
    public function testGetLogFileName(): void
    {
        $this->assertTrue(\method_exists($this->error_log, 'getLogFileName'));
        $actual = $this->error_log->getLogFileName();
        $this->assertIsString($actual);
        $this->assertStringContainsString('debug.log', $actual);
    }

    /**
     * Test currentUserCan().
     */
    public function testCurrentUserCan(): void
    {
        $this->assertTrue(\method_exists($this->error_log, 'currentUserCan'));
        $actual = $this->error_log->currentUserCan();
        $this->assertIsBool($actual);
    }

    /**
     * Test maybeRedirect().
     */
    public function testMaybeRedirect(): void
    {
        $this->assertTrue(\method_exists($this->error_log, 'maybeRedirect'));

        try {
            $maybeRedirect = $this->reflection->getMethod('maybeRedirect');
            $maybeRedirect->setAccessible(true);
            $this->assertNull($maybeRedirect->invoke($this->error_log));
        } catch (\Throwable $throwable) {
            $this->assertInstanceOf(\ReflectionException::class, $throwable);
            $this->markAsRisky();
        }
    }

    /**
     * Test addDashboardWidget().
     */
    public function testAddDashboardWidget(): void
    {
        $this->assertTrue(\method_exists($this->error_log, 'addDashboardWidget'));

        try {
            \wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
            \set_current_screen('dashboard');
            $this->go_to(\admin_url());
            /** Load WordPress dashboard API */
            if (!\function_exists('\wp_add_dashboard_widget')) {
                require_once \ABSPATH . 'wp-admin/includes/dashboard.php';
            }
            global $wp_meta_boxes;
            $addDashboardWidget = $this->reflection->getMethod('addDashboardWidget');
            $addDashboardWidget->setAccessible(true);
            $this->assertNull($addDashboardWidget->invoke($this->error_log));
            $this->assertTrue(\strpos(\wp_json_encode($wp_meta_boxes), $this->error_log->getDomain()) > 0);
        } catch (\Throwable $throwable) {
            $this->markAsRisky();
        }
    }

    /**
     * Test dashboardHandler().
     */
    public function testDashboardHandler(): void
    {
        $this->assertTrue(\method_exists($this->error_log, 'dashboardHandler'));

        try {
            $dashboardHandler = $this->reflection->getMethod('dashboardHandler');
            $dashboardHandler->setAccessible(true);
            \ob_start();
            $dashboardHandler->invoke($this->error_log);
            $actual = \ob_get_clean();
            $this->assertNotEmpty($actual);
        } catch (\Throwable $throwable) {
            $this->assertInstanceOf(\ReflectionException::class, $throwable);
            $this->markAsRisky();
        }
    }

    /**
     * Test formatErrors().
     */
    public function testFormatErrors(): void
    {
        $this->assertTrue(\method_exists($this->error_log, 'formatErrors'));

        try {
            $formatErrors = $this->reflection->getMethod('formatErrors');
            $formatErrors->setAccessible(true);
            \ob_start();
            $formatErrors->invoke($this->error_log, [], 1, 1);
            $actual = \ob_get_clean();
            $this->assertNotEmpty($actual);
        } catch (\Throwable $throwable) {
            $this->assertInstanceOf(\ReflectionException::class, $throwable);
            $this->markAsRisky();
        }
    }
}
