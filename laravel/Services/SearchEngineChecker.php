<?php

namespace App\Services;

use App\Contracts\ISearchEngineParser;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class SearchEngineChecker
{
    /**
     * Check for search engines.
     *
     * @param \Symfony\Component\DomCrawler\Crawler $crawler
     * @return string|null
     */
    public function checkForSearchEngines(Crawler $crawler)
    {
        $engines = config('search_engines.engines');

        foreach ($engines as $engineName => $selector) {
            Log::info("Search engine {$engineName}");

            if (
                is_array($selector)
                && !empty($selector['parse_class'])
                && app($selector['parse_class']) instanceof ISearchEngineParser
            ) {
                $parser = App::make($selector['parse_class'], ['crawler' => $crawler]);
                $result = $parser->getEngineName($selector);
                if ($result) return $result;
            } else {
                if ($crawler->filter($selector)->count() > 0) {
                    return $engineName;
                }
            }
        }

        return null;
    }
}
