<?php

namespace Modules\Sviat\LogViewer;

use Okay\Modules\Sviat\LogViewer\Services\LogParser;
use PHPUnit\Framework\TestCase;

class LogParserTest extends TestCase
{
    /** @var LogParser */
    private $parser;

    protected function setUp(): void
    {
        $this->parser = new LogParser();
    }

    public function testParsesSingleEntry(): void
    {
        $content = "[2026-05-05 14:23:12] app.ERROR: Cannot connect to DB\n";
        $entries = $this->parser->parse($content, '2026-05-05');

        self::assertCount(1, $entries);
        self::assertSame('2026-05-05 14:23:12', $entries[0]['timestamp']);
        self::assertSame('app', $entries[0]['channel']);
        self::assertSame('ERROR', $entries[0]['level']);
        self::assertSame('Cannot connect to DB', $entries[0]['full_message']);
        self::assertSame('Cannot connect to DB', $entries[0]['message']);
        self::assertFalse($entries[0]['is_long_message']);
        self::assertFalse($entries[0]['has_trace']);
        self::assertSame('2026-05-05', $entries[0]['file_date']);
        self::assertNotEmpty($entries[0]['id']);
    }

    public function testParsesMultipleEntries(): void
    {
        $content = "[2026-05-05 14:23:12] app.ERROR: First\n"
            . "[2026-05-05 14:24:00] app.WARNING: Second\n"
            . "[2026-05-05 14:25:00] app.INFO: Third\n";

        $entries = $this->parser->parse($content, '2026-05-05');

        self::assertCount(3, $entries);
        self::assertSame('ERROR', $entries[0]['level']);
        self::assertSame('WARNING', $entries[1]['level']);
        self::assertSame('INFO', $entries[2]['level']);
    }

    public function testParsesMultilineTrace(): void
    {
        $content = "[2026-05-05 14:23:12] app.ERROR: Boom\n"
            . "#0 /path/to/file.php(10): foo()\n"
            . "#1 /path/to/file.php(20): bar()\n"
            . "[2026-05-05 14:24:00] app.INFO: Next\n";

        $entries = $this->parser->parse($content, '2026-05-05');

        self::assertCount(2, $entries);
        self::assertTrue($entries[0]['has_trace']);
        self::assertStringContainsString('#0', $entries[0]['trace']);
        self::assertStringContainsString('#1', $entries[0]['trace']);
        self::assertFalse($entries[1]['has_trace']);
    }

    public function testDetectsInlineTrace(): void
    {
        $content = "[2026-05-05 14:23:12] app.ERROR: Failure #0 frame1();-line 10 #1 frame2();-line 20\n";
        $entries = $this->parser->parse($content, '2026-05-05');

        self::assertCount(1, $entries);
        self::assertTrue($entries[0]['has_trace']);
        self::assertStringContainsString('#0', $entries[0]['trace']);
        self::assertStringContainsString('#1', $entries[0]['trace']);
    }

    public function testTruncatesLongMessage(): void
    {
        $longText = str_repeat('a', 250);
        $content = "[2026-05-05 14:23:12] app.ERROR: {$longText}\n";

        $entries = $this->parser->parse($content, '2026-05-05');

        self::assertTrue($entries[0]['is_long_message']);
        self::assertNotSame($entries[0]['full_message'], $entries[0]['message']);
        self::assertStringEndsWith('…', $entries[0]['message']);
    }

    public function testIgnoresOrphanLinesBeforeFirstEntry(): void
    {
        $content = "garbage line without timestamp\n"
            . "another orphan\n"
            . "[2026-05-05 14:23:12] app.ERROR: First real\n";

        $entries = $this->parser->parse($content, '2026-05-05');

        self::assertCount(1, $entries);
        self::assertSame('First real', $entries[0]['full_message']);
    }

    public function testHandlesEmptyContent(): void
    {
        self::assertSame([], $this->parser->parse('', '2026-05-05'));
        self::assertSame([], $this->parser->parse("\n\n\n", '2026-05-05'));
    }

    public function testHandlesSchedulerFormat(): void
    {
        $content = "[2026-05-05 10:36:00] Scheduler.INFO: Task #6 (Ringostat): Start [] []\n";
        $entries = $this->parser->parse($content, '2026-05-05');

        self::assertCount(1, $entries);
        self::assertSame('Scheduler', $entries[0]['channel']);
        self::assertSame('INFO', $entries[0]['level']);
    }

    /**
     * Monolog 3 (прийшов із переносом форку) пише ISO8601 з мікросекундами й
     * зоною замість "Y-m-d H:i:s". Рядок узятий дослівно з прод-логу.
     */
    public function testParsesMonolog3IsoTimestamp(): void
    {
        $content = "[2026-08-05T23:56:00.261559+03:00] Scheduler.INFO: (2026-08-05 23:56:00) Task #6 (Ringostat: sync calls from API): Start [] []\n";
        $entries = $this->parser->parse($content, '2026-08-05');

        self::assertCount(1, $entries);
        self::assertSame('Scheduler', $entries[0]['channel']);
        self::assertSame('INFO', $entries[0]['level']);
    }

    public function testParsesMonolog3IsoTimestampInAppChannel(): void
    {
        $content = "[2026-08-05T22:56:42.815678+03:00] channel-name.ERROR: Sql query error: \"SQLSTATE[23000]\"\n";
        $entries = $this->parser->parse($content, '2026-08-05');

        self::assertCount(1, $entries);
        self::assertSame('channel-name', $entries[0]['channel']);
        self::assertSame('ERROR', $entries[0]['level']);
    }

    /**
     * Показ і сортування (LogService::readAllEntries порівнює timestamp через
     * strcmp) мають лишатись однорідними, тож ISO зводимо до старого вигляду.
     */
    public function testNormalizesMonolog3TimestampToLegacyFormat(): void
    {
        $content = "[2026-08-05T23:56:00.261559+03:00] Scheduler.INFO: Start\n";
        $entries = $this->parser->parse($content, '2026-08-05');

        self::assertSame('2026-08-05 23:56:00', $entries[0]['timestamp']);
    }

    /**
     * Файл за день деплою містить обидва формати — до і після рестарту.
     */
    public function testParsesFileWithBothTimestampFormats(): void
    {
        $content = "[2026-08-05 00:23:20] channel-name.ERROR: Before deploy\n"
            . "[2026-08-05T22:56:42.815678+03:00] channel-name.ERROR: After deploy\n";

        $entries = $this->parser->parse($content, '2026-08-05');

        self::assertCount(2, $entries);
        self::assertSame('Before deploy', $entries[0]['full_message']);
        self::assertSame('After deploy', $entries[1]['full_message']);
        self::assertSame('2026-08-05 00:23:20', $entries[0]['timestamp']);
        self::assertSame('2026-08-05 22:56:42', $entries[1]['timestamp']);
        // strcmp-сортування у LogService має лишитись коректним
        self::assertLessThan(0, strcmp($entries[0]['timestamp'], $entries[1]['timestamp']));
    }

    public function testFilterByLevelIsCaseInsensitive(): void
    {
        $content = "[2026-05-05 14:23:12] app.ERROR: A\n"
            . "[2026-05-05 14:24:00] app.WARNING: B\n"
            . "[2026-05-05 14:25:00] app.ERROR: C\n";
        $entries = $this->parser->parse($content, '2026-05-05');

        $errors = $this->parser->filterByLevel($entries, 'error');
        self::assertCount(2, $errors);
        self::assertSame('A', $errors[0]['full_message']);
        self::assertSame('C', $errors[1]['full_message']);

        // Empty level returns all
        self::assertCount(3, $this->parser->filterByLevel($entries, ''));
    }

    public function testFilterByTextIsCaseInsensitive(): void
    {
        $content = "[2026-05-05 14:23:12] app.ERROR: Cannot connect to DB\n"
            . "[2026-05-05 14:24:00] app.WARNING: Cache miss\n";
        $entries = $this->parser->parse($content, '2026-05-05');

        $hits = $this->parser->filterByText($entries, 'CANNOT');
        self::assertCount(1, $hits);
        self::assertSame('Cannot connect to DB', $hits[0]['full_message']);

        // Empty needle returns all
        self::assertCount(2, $this->parser->filterByText($entries, ''));
        self::assertCount(2, $this->parser->filterByText($entries, '   '));
    }

    public function testIdsAreStable(): void
    {
        $content = "[2026-05-05 14:23:12] app.ERROR: Same\n";
        $a = $this->parser->parse($content, '2026-05-05');
        $b = $this->parser->parse($content, '2026-05-05');

        self::assertSame($a[0]['id'], $b[0]['id']);
    }
}
