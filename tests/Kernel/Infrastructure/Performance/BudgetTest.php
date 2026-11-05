<?php

declare(strict_types=1);

namespace SightPress\Core\Tests\Kernel\Infrastructure\Performance;

use PHPUnit\Framework\TestCase;
use SightPress\Core\Kernel\Infrastructure\Performance\AssetMeasurer;
use SightPress\Core\Kernel\Infrastructure\Performance\Budget;
use SightPress\Core\Kernel\Infrastructure\Performance\BudgetCheck;
use SightPress\Core\Kernel\Infrastructure\Performance\BudgetChecker;
use SightPress\Core\Kernel\Infrastructure\Performance\BudgetTable;

final class BudgetTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/sightpress-budget-'.bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            \assert($file instanceof \SplFileInfo);
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->dir);
    }

    private function write(string $name, string $contents): string
    {
        $path = $this->dir.'/'.$name;
        if (!is_dir(\dirname($path))) {
            mkdir(\dirname($path), 0o777, true);
        }
        file_put_contents($path, $contents);

        return $path;
    }

    public function testRules(): void
    {
        $under = new Budget('a', 10, 'lt', 'A');
        $atMost = new Budget('b', 10, 'lte', 'B');

        self::assertTrue($under->isMet(9.9));
        self::assertFalse($under->isMet(10));
        self::assertTrue($atMost->isMet(10));
        self::assertFalse($atMost->isMet(11));
    }

    public function testTheShippedBudgetTableLoads(): void
    {
        $table = BudgetTable::default();

        self::assertArrayHasKey('web_vitals.cls', $table->budgets);
        self::assertSame(0.1, $table->budgets['web_vitals.cls']->limit);
        self::assertSame('lte', $table->budgets['assets.fonts_per_script']->rule);
    }

    public function testAMissingFileIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        BudgetTable::fromFile($this->dir.'/missing.yaml');
    }

    public function testDefaultsForRuleAndDescription(): void
    {
        $table = BudgetTable::fromFile($this->write('b.yaml', "budgets:\n  x.y:\n    limit: 5\n"));

        self::assertSame('lt', $table->budgets['x.y']->rule);
        self::assertSame('x.y', $table->budgets['x.y']->description);
    }

    public function testAFileWithoutBudgetsGivesAnEmptyTable(): void
    {
        self::assertSame([], BudgetTable::fromFile($this->write('b.yaml', "version: 1\n"))->budgets);
    }

    public function testANonNumericLimitIsRefused(): void
    {
        $this->expectExceptionMessage('Budget "x" needs a numeric limit.');

        BudgetTable::fromFile($this->write('b.yaml', "budgets:\n  x:\n    limit: lots\n"));
    }

    public function testAnUnknownRuleIsRefused(): void
    {
        $this->expectExceptionMessage('Budget "x" has an unknown rule; use "lt" or "lte".');

        BudgetTable::fromFile($this->write('b.yaml', "budgets:\n  x:\n    limit: 1\n    rule: gt\n"));
    }

    public function testCheckerReportsBrokenAndUnknownBudgetsInWords(): void
    {
        $table = BudgetTable::fromFile($this->write('b.yaml', "budgets:\n  html.dom_nodes:\n    limit: 1500\n    description: DOM nodes\n  fonts:\n    limit: 2\n    rule: lte\n    description: Fonts\n"));

        $violations = new BudgetChecker($table)->violations(['html.dom_nodes' => 1600, 'fonts' => 3, 'typo' => 1]);

        self::assertSame([
            'DOM nodes: measured 1600, but the budget is under 1500.',
            'Fonts: measured 3, but the budget is at most 2.',
            '"typo" has no budget. Add it to config/budgets.yaml or correct the name.',
        ], $violations);
        self::assertSame([], new BudgetChecker($table)->violations(['fonts' => 2]));
    }

    public function testAssetMeasurerReturnsNothingWithoutThemes(): void
    {
        self::assertSame([], new AssetMeasurer()->measure($this->dir));
    }

    public function testAssetMeasurerSumsCompressedCssAndJavaScript(): void
    {
        $this->write('themes/default/a.css', str_repeat('body{color:red}', 100));
        $this->write('themes/default/sub/b.css', 'p{margin:0}');
        $this->write('themes/default/app.js', 'console.log(1)');
        $this->write('themes/default/readme.txt', 'ignored');

        $sizes = new AssetMeasurer()->measure($this->dir);

        self::assertSame(['assets.theme_css_compressed_bytes', 'assets.public_js_compressed_bytes'], array_keys($sizes));
        self::assertGreaterThan(0, $sizes['assets.theme_css_compressed_bytes']);
        self::assertLessThan(1500, $sizes['assets.theme_css_compressed_bytes']);
    }

    public function testAssetMeasurerSkipsAMissingKind(): void
    {
        $this->write('themes/default/a.css', 'p{}');

        self::assertSame(['assets.theme_css_compressed_bytes'], array_keys(new AssetMeasurer()->measure($this->dir)));
    }

    public function testCheckPassesWithNothingToMeasure(): void
    {
        $result = new BudgetCheck(BudgetTable::default())->run($this->dir);

        self::assertTrue($result['passed']);
        self::assertSame(['Performance budgets: Passed (0 measurements checked against 11 budgets).'], $result['lines']);
    }

    public function testCheckAddsMeasurementsFromAFile(): void
    {
        $table = BudgetTable::fromFile($this->write('b.yaml', "budgets:\n  html.dom_nodes:\n    limit: 1500\n    description: DOM nodes\n"));
        $file = $this->write('m.json', '{"html.dom_nodes": 1600}');

        $result = new BudgetCheck($table)->run($this->dir, $file);

        self::assertFalse($result['passed']);
        self::assertSame([
            'DOM nodes: measured 1600, but the budget is under 1500.',
            'Performance budgets: Failed (1 measurement checked against 1 budget, 1 problem).',
        ], $result['lines']);
    }

    public function testCheckCountsSeveralProblems(): void
    {
        $file = $this->write('m.json', '{"html.dom_nodes": 1600, "web_vitals.cls": 0.5}');

        $result = new BudgetCheck(BudgetTable::default())->run($this->dir, $file);

        self::assertSame('Performance budgets: Failed (2 measurements checked against 11 budgets, 2 problems).', $result['lines'][2]);
    }

    public function testCheckRefusesAnUnreadableFile(): void
    {
        $this->expectExceptionMessage('can not be read');

        new BudgetCheck(BudgetTable::default())->run($this->dir, $this->dir.'/missing.json');
    }

    public function testCheckRefusesInvalidJson(): void
    {
        $this->expectExceptionMessage('is not a JSON object of budget names and numbers');

        new BudgetCheck(BudgetTable::default())->run($this->dir, $this->write('m.json', 'nope'));
    }

    public function testCheckRefusesANonNumericEntry(): void
    {
        $this->expectExceptionMessage('has an entry that is not a budget name and a number');

        new BudgetCheck(BudgetTable::default())->run($this->dir, $this->write('m.json', '{"html.dom_nodes": "many"}'));
    }
}
