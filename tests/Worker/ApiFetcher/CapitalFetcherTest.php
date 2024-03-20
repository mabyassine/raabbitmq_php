<?php

namespace Worker\ApiFetcher;

use PHPUnit\Framework\TestCase;

// Mock the global function within the same namespace
function file_get_contents($url) {
    return CapitalFetcherTest::$mockFileGetContentsResponse ?? false;
}

class CapitalFetcherTest extends TestCase
{
    public static $mockFileGetContentsResponse = null;

    public function testFetchCapitalReturnsCapitalCity()
    {
        self::$mockFileGetContentsResponse = json_encode([['capital' => ['Paris']]]);
        $fetcher = new CapitalFetcher();
        $capital = $fetcher->fetchCapital("France");

        $this->assertEquals("Paris", $capital);
    }

    public function testFetchCapitalThrowsExceptionOnFailure()
    {
        self::$mockFileGetContentsResponse = false;
        $this->expectException(\Exception::class);
        $fetcher = new CapitalFetcher();
        $fetcher->fetchCapital("Unknown");
    }

    protected function tearDown(): void
    {
        // Reset mock response after each test
        self::$mockFileGetContentsResponse = null;
    }
}
