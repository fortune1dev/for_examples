<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use App\Models\Site;
use Illuminate\Support\Facades\Log;
use App\Services\SearchEngineChecker;

class ParseWebsite extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parse:website {url?} {--from_db}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parse a website to check for search engines';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(SearchEngineChecker $searchEngineChecker)
    {
        if ($this->option('from_db')) {
            $sites = Site::where('status', 'NEW')->get();

            foreach ($sites as $site) {
                $this->parseSite($site, $searchEngineChecker);
            }
        } else {
            $url = $this->argument('url');
            if (!$url) {
                $this->error('URL is required when not using --from_db option.');
                return 1;
            }

            $site = Site::where('url', $url)->first();

            if (!$site) {
                $site = Site::create([
                    'url' => $url,
                    'status' => 'NEW',
                    'search_engine' => null,
                    'user_id' => 1, // нужно будет потом просто избавиться от привязки к пользователю
                    'error' => null,
                ]);
            }

            $this->parseSite($site, $searchEngineChecker);
        }

        return 0;
    }

    /**
     * Parse a single site.
     *
     * @param \App\Models\Site $site
     * @param \App\Services\SearchEngineChecker $searchEngineChecker
     * @return void
     */
    private function parseSite(Site $site, SearchEngineChecker $searchEngineChecker)
    {
        try {
            // Устанавливаем заголовки, характерные для Safari
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Safari/605.1.15',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-us',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive',
            ])->withOptions(['verify' => false])->get($site->url);

            // Получаем тело ответа
            $html = $response->body();

            $crawler = new Crawler($html);

            $searchEngine = $searchEngineChecker->checkForSearchEngines($crawler);

            if ($searchEngine) {
                $site->update([
                    'status' => 'HAS_SEARCH_ENGINE',
                    'search_engine' => $searchEngine,
                    'error' => '',
                ]);
                $this->info("{$searchEngine} search form found on {$site->url}.");
            } else {
                $site->update([
                    'status' => 'NO_SEARCH_ENGINE',
                    'error' => '',
                ]);
                $this->info("No search engine forms found on {$site->url}.");
            }
        } catch (\Exception $e) {
            $site->update([
                'status' => 'ERROR',
                'error' => $e->getMessage(),
            ]);
            Log::error("Error parsing {$site->url}: " . $e->getMessage());
            $this->error("Error parsing {$site->url}: " . $e->getMessage());
        }
    }
}
