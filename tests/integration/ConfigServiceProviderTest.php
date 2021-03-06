<?php

/*
 * This file is part of ConfigServiceProvider.
 *
 * (c) Igor Wiedler <igor@wiedler.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Igorw\Silex\ConfigServiceProvider;
use Silex\Application;

/**
 * @author Igor Wiedler <igor@wiedler.ch>
 * @author Jérôme Macias <jerome.macias@gmail.com>
 */
class ConfigServiceProviderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider provideFilenames
     */
    public function testRegisterWithoutReplacement($filename)
    {
        $app = new Application();

        $app->register(new ConfigServiceProvider($filename));

        $this->assertTrue($app['debug']);
        $this->assertSame('%data%', $app['data']);
    }

    /**
     * @dataProvider provideFilenames
     */
    public function testRegisterWithReplacement($filename)
    {
        $app = new Application();

        $app->register(new ConfigServiceProvider($filename, [
            'data' => 'test-replacement',
        ]));

        $this->assertSame(true, $app['debug']);
        $this->assertSame('test-replacement', $app['data']);
    }

    /**
     * @dataProvider provideEmptyFilenames
     */
    public function testEmptyConfigs($filename)
    {
        $readConfigMethod = new \ReflectionMethod('Igorw\Silex\ConfigServiceProvider', 'readConfig');
        $readConfigMethod->setAccessible(true);

        $this->assertSame(
            [],
            $readConfigMethod->invoke(new ConfigServiceProvider($filename))
        );
    }

    /**
     * @dataProvider provideReplacementFilenames
     */
    public function testInFileReplacements($filename)
    {
        $app = new Application();

        $app->register(new ConfigServiceProvider($filename));

        $this->assertSame('/var/www', $app['%path%']);
        $this->assertSame('/var/www/web/images', $app['path.images']);
        $this->assertSame('/var/www/upload', $app['path.upload']);
        $this->assertSame('http://example.com', $app['%url%']);
        $this->assertSame('http://example.com/images', $app['url.images']);
    }

    /**
     * Currently not tested via testMergeConfigs as TOML seems to have problems
     * to create 'db.options' keys.
     */
    public function testTomlMergeConfigs()
    {
        $app = new Application();

        $filenameBase = __DIR__.'/Fixtures/config_base.toml';
        $filenameExtended = __DIR__.'/Fixtures/config_extend.toml';

        $app->register(new ConfigServiceProvider($filenameBase));
        $app->register(new ConfigServiceProvider($filenameExtended));

        $this->assertSame('pdo_mysql', $app['db']['driver']);
        $this->assertSame('utf8', $app['db']['charset']);
        $this->assertSame('127.0.0.1', $app['db']['host']);
        $this->assertSame('mydatabase', $app['db']['dbname']);
        $this->assertSame('root', $app['db']['user']);
        $this->assertSame('', $app['db']['password']);

        $this->assertSame('123', $app['myproject']['param1']);
        $this->assertSame('456', $app['myproject']['param2']);
        $this->assertSame('456', $app['myproject']['param3']);
        $this->assertSame([4, 5, 6], $app['myproject']['param4']);
        $this->assertSame('456', $app['myproject']['param5']);

        $this->assertSame([1, 2, 3, 4], $app['keys']['set']);
    }

    /**
     * @dataProvider provideMergeFilenames
     */
    public function testMergeConfigs($filenameBase, $filenameExtended)
    {
        $app = new Application();
        $app->register(new ConfigServiceProvider($filenameBase));
        $app->register(new ConfigServiceProvider($filenameExtended));

        $this->assertSame('pdo_mysql', $app['db.options']['driver']);
        $this->assertSame('utf8', $app['db.options']['charset']);
        $this->assertSame('127.0.0.1', $app['db.options']['host']);
        $this->assertSame('mydatabase', $app['db.options']['dbname']);
        $this->assertSame('root', $app['db.options']['user']);
        $this->assertSame(null, $app['db.options']['password']);

        $this->assertSame('123', $app['myproject.test']['param1']);
        $this->assertSame('456', $app['myproject.test']['param2']);
        $this->assertSame('123', $app['myproject.test']['param3']['param2A']);
        $this->assertSame('456', $app['myproject.test']['param3']['param2B']);
        $this->assertSame('456', $app['myproject.test']['param3']['param2C']);
        $this->assertSame([4, 5, 6], $app['myproject.test']['param4']);
        $this->assertSame('456', $app['myproject.test']['param5']);

        $this->assertSame([1, 2, 3, 4], $app['test.noparent.key']['test']);
    }

    /**
     * @dataProvider provideTreeFilenames
     */
    public function testRecursive($filename)
    {
        $app = new Application();

        $app->register(new ConfigServiceProvider($filename));

        $this->assertSame('/etc', $app['%dir%']);
        $this->assertSame('/etc/apache', $app['%subdir%']);
        $this->assertSame('/etc/apache/config', $app['%target%']);
        $this->assertSame('/etc/apache/config/httpd.conf', $app['%main%']);
    }

    /**
     * @test
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Invalid JSON provided "Syntax error" in
     */
    public function invalidJsonShouldThrowException()
    {
        $app = new Application();
        $app->register(new ConfigServiceProvider(__DIR__.'/Fixtures/broken.json'));
    }

    /**
     * @test
     * @expectedException \Symfony\Component\Yaml\Exception\ParseException
     */
    public function invalidYamlShouldThrowException()
    {
        $app = new Application();
        $app->register(new ConfigServiceProvider(__DIR__.'/Fixtures/broken.yml'));
    }

    /**
     * @test
     * @expectedException \Exception
     */
    public function invalidTomlShouldThrowException()
    {
        $app = new Application();
        $app->register(new ConfigServiceProvider(__DIR__.'/Fixtures/broken.toml'));
    }

    public function provideFilenames()
    {
        return [
            [__DIR__.'/Fixtures/config.php'],
            [__DIR__.'/Fixtures/config.json'],
            [__DIR__.'/Fixtures/config.yml'],
            [__DIR__.'/Fixtures/config.toml'],
        ];
    }

    public function provideReplacementFilenames()
    {
        return [
            [__DIR__.'/Fixtures/config_replacement.php'],
            [__DIR__.'/Fixtures/config_replacement.json'],
            [__DIR__.'/Fixtures/config_replacement.yml'],
            [__DIR__.'/Fixtures/config_replacement.toml'],
        ];
    }

    public function provideEmptyFilenames()
    {
        return [
            [__DIR__.'/Fixtures/config_empty.php'],
            [__DIR__.'/Fixtures/config_empty.json'],
            [__DIR__.'/Fixtures/config_empty.yml'],
            [__DIR__.'/Fixtures/config_empty.toml'],
        ];
    }

    public function provideMergeFilenames()
    {
        return [
            [__DIR__.'/Fixtures/config_base.php', __DIR__.'/Fixtures/config_extend.php'],
            [__DIR__.'/Fixtures/config_base.json', __DIR__.'/Fixtures/config_extend.json'],
            [__DIR__.'/Fixtures/config_base.yml', __DIR__.'/Fixtures/config_extend.yml'],
        ];
    }

    public function provideTreeFilenames()
    {
        return [
            [__DIR__.'/Fixtures/config_tree.php'],
        ];
    }
}
