<?php

declare(strict_types=1);

namespace TheFrosty\Tests\WpDebugLogWidget;

use PHPUnit\Framework\MockObject\MockObject;
use TheFrosty\WpUtilities\Plugin\Plugin;
use TheFrosty\WpUtilities\Plugin\PluginFactory;

/**
 * Class TestCase
 * @package TheFrosty\Tests\WpLoginLocker
 */
class TestCase extends \WP_UnitTestCase
{

    public const string METHOD_ADD_FILTER = 'addFilter';

    /** @var Plugin $plugin */
    protected Plugin $plugin;

    /** @var \ReflectionObject $reflection */
    protected \ReflectionObject $reflection;

    /**
     * @internal Workaround to allow the tests to run on PHPUnit 10.
     * @link https://core.trac.wordpress.org/ticket/59486
     */
    public function expectDeprecated(): void
    {
    }

    /**
     * Setup.
     */
    public function setUp(): void
    {
        parent::setUp();
        // Set the filename to the root of the plugin (not the test plugin (so we have asset access without mocks).
        $filename = \dirname(__DIR__, 2) . '/wp-debug-log-widget.php';
        $this->plugin = PluginFactory::create('wp-debug-log-widget', $filename);
    }

    /**
     * Tear down.
     */
    public function tearDown(): void
    {
        parent::tearDown();
        unset($this->plugin, $this->reflection);
    }

    /**
     * Gets an instance of the \ReflectionObject.
     * @param object $argument
     * @return \ReflectionObject
     */
    protected function getReflection(object $argument): \ReflectionObject
    {
        static $reflector;

        if (!isset($reflector[\get_class($argument)]) ||
            !($reflector[\get_class($argument)] instanceof \ReflectionObject)
        ) {
            $reflector[\get_class($argument)] = new \ReflectionObject($argument);
        }

        return $reflector[\get_class($argument)];
    }

    /**
     * Get a Mock Provider.
     * @param string $className
     * @return MockObject
     */
    protected function getMockProvider(string $className): MockObject
    {
        return $this->getMockBuilder($className)
            ->setMethods([self::METHOD_ADD_FILTER])
            ->getMock();
    }

    /**
     * Get the constants for the current class, excluding the inherited class constants.
     * @return array
     */
    protected function getClassConstants(): array
    {
        return \array_flip(
            \array_diff(
                $this->reflection->getConstants(),
                $this->reflection->getParentClass()->getConstants()
            )
        );
    }
}
