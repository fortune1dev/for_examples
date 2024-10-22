<?php

namespace App\Services;

use App\Contracts\ISearchEngineParser;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class MultiSearchSearchParser implements ISearchEngineParser
{
    const engineName = 'MultiSearch';
    protected $crawler;

    /**
     * @inheritDoc
     */
    public function __construct(Crawler $crawler)
    {
        $this->crawler = $crawler;
    }

    /**
     * @inheritDoc
     */
    public function getEngineName(array $params): string|null
    {

        try {
            if ($this->crawler->filter('script')->reduce(function ($node) use ($params) {
                return  str_contains($node->text(), $params['script']);
            })->count() > 0) {
                return self::engineName;
            }

            $html = $this->crawler->html();
            if (
                str_contains($html, $params['api'])
                || str_contains($html, $params['api_url'])
            ) {
                return self::engineName;
            }

            return null;
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return null;
        }
    }
}
