<?php

use PHPUnit\Framework\TestCase;
use Worker\ApiFetcher\CapitalFetcher;

class CapitalFetcherTest extends TestCase
{
    private CapitalFetcher $capitalFetcher;

    protected function setUp(): void
    {
        $this->capitalFetcher = new CapitalFetcher();
    }

    public function testFetchCapitalReturnsCapitalCity()
    {
        // Assuming the response from the external API
        $mockResponse = json_encode([
            [
                'capital' => ['Washington, D.C.']
            ]
        ]);

        // Use PHP's built-in function overriding feature to mock `file_get_contents`
        $this->capitalFetcher = $this->getMockBuilder(CapitalFetcher::class)
            ->onlyMethods(['file_get_contents'])
            ->getMock();

        $this->capitalFetcher->method('file_get_contents')
            ->willReturn($mockResponse);

        $capital = $this->capitalFetcher->fetchCapital('United States');

        $this->assertEquals('Washington, D.C.', $capital);
    }

    public function testFetchCapitalThrowsExceptionOnFailure()
    {
        $this->expectException(Exception::class);

        $this->capitalFetcher = $this->getMockBuilder(CapitalFetcher::class)
            ->onlyMethods(['file_get_contents'])
            ->getMock();

        $this->capitalFetcher->method('file_get_contents')
            ->willReturn(false);

        $this->capitalFetcher->fetchCapital('Unknown Country');
    }
}
