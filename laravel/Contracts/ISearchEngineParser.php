<?php

namespace App\Contracts;

use Symfony\Component\DomCrawler\Crawler;

interface ISearchEngineParser
{
    /**
     * Check for search engines.
     *
     * @param \Symfony\Component\DomCrawler\Crawler $crawler
     */
    public function __construct(Crawler $crawler);
    public function getEngineName(array $params): string | null;
}
