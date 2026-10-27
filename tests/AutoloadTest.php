<?php

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The class map in autoload.php matches src/: one entry per file, the file
 * name is the class name, and every class loads through the map.
 */
#[CoversClass(\Sayamaapps\Hikkoshi\Options::class)]
#[CoversClass(\Sayamaapps\Hikkoshi\Validator::class)]
#[CoversClass(\Sayamaapps\Hikkoshi\Path::class)]
#[CoversClass(\Sayamaapps\Hikkoshi\I18n::class)]
#[CoversClass(\Sayamaapps\Hikkoshi\Redirects::class)]
#[CoversClass(\Sayamaapps\Hikkoshi\Htaccess::class)]
#[CoversClass(\Sayamaapps\Hikkoshi\Resolver::class)]
#[CoversClass(\Sayamaapps\Hikkoshi\SiteCheck::class)]
#[CoversClass(\Sayamaapps\Hikkoshi\CheckCommand::class)]
#[CoversClass(\Sayamaapps\Hikkoshi\ConvertCommand::class)]
#[CoversClass(\Sayamaapps\Hikkoshi\ConfigException::class)]
final class AutoloadTest extends TestCase
{
	public function testEveryEntryLoadsItsClass(): void
	{
		$root = dirname(__DIR__);
		foreach (require $root . '/autoload.php' as $class => $path) {
			$this->assertFileExists($root . '/' . $path);
			$this->assertTrue(class_exists($class), 'class ' . $class . ' loads through the map');
		}
	}

	public function testLegacyNamesAreAliasesOfTheNewClasses(): void
	{
		require_once dirname(__DIR__) . '/legacy.php';

		foreach (require dirname(__DIR__) . '/autoload.php' as $class => $path) {
			$short = substr($class, strlen('sayamaapps\\hikkoshi\\'));
			$this->assertSame(
				strtolower((new ReflectionClass('Sayamaapps\\Hikkoshi\\' . $short))->getName()),
				strtolower((new ReflectionClass('Hikkoshi\\' . $short))->getName()),
				'Hikkoshi\\' . $short . ' and Sayamaapps\\Hikkoshi\\' . $short . ' are the same class'
			);
		}
	}

	public function testMapListsEveryClassFileAndNothingElse(): void
	{
		$root = dirname(__DIR__);
		$map = require $root . '/autoload.php';

		$files = glob($root . '/src/*.php');
		$this->assertIsArray($files);

		$expected = [];
		foreach ($files as $file) {
			$expected[strtolower('sayamaapps\\hikkoshi\\' . basename($file, '.php'))] = 'src/' . basename($file);
		}

		$this->assertSame($expected, $map);
	}

	public function testTheExceptionKeepsItsOneXClassName(): void
	{
		require_once dirname(__DIR__) . '/legacy.php';

		$this->assertSame('Hikkoshi\\ConfigException', (new ReflectionClass(\Sayamaapps\Hikkoshi\ConfigException::class))->getName());
	}
}
