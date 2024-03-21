<?php

namespace App\Command;

use App\Util\CrawlerWrapper;
use App\Util\ProxyRandomizer;
use App\Util\RequestAttempt;
use App\Util\TextUtil;
use App\Util\Writer;
use DateTime;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;

class ParseFinancyAZ extends Command
{
    use ProxyRandomizer, RequestAttempt;

    public const COMMAND_NAME = 'parse:financyPageAZ.bg';

    private const BASE_URI = 'https://finansi.bg';
    private const SEARCH_URL = self::BASE_URI.'/search?name=%s&page=%s';
    private const RESULT_PATTERN = "%s}##{%s}##{%s}##{%s}##{%s}##{%s}##{%s}##{%s\n";
    private const ERROR_PATTERN = "%s}##{%s\n";

    public function __construct()
    {
        parent::__construct();

        $this->client = new Client([
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Connection' => 'keep-alive',
                'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:123.0) Gecko/20100101 Firefox/123.0'
            ]
        ]);

        $this->proxies = file('proxies.txt', FILE_IGNORE_NEW_LINES);
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('This command runs parsing financy.bg')
            ->setHelp('Run this command to execute your custom tasks in the execute function.')
            ->addOption('combination', 'c', InputOption::VALUE_REQUIRED, 'Chars combination.')
        ;
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $combination = $input->getOption('combination');

        $resultWriter = new Writer('result/bg/financi/result.csv');
        $errorWriter = new Writer('result/bg/financi/error.csv');
        $profileErrorWriter = new Writer('result/bg/financi/profile_error.csv');

        for ($page = 1; $page < 500000; $page++) {
            $searchUrl = sprintf(self::SEARCH_URL, $combination, $page);

            try {
                $response = $this->sendGETRequest($searchUrl, [
                    'proxy' => $this->getRandomProxy(),
                ]);

                if ($response->getStatusCode() !== 200) {
                    $errorWriter->write(sprintf(self::ERROR_PATTERN, $searchUrl, $response->getStatusCode()));
                    continue;
                }

                $crawler = new CrawlerWrapper((string)$response->getBody());

                if($crawler->getTotalNodes('table#companies-table tr') <= 1){
                    return Command::SUCCESS;
                }

                $rows = $crawler->filter('table#companies-table tr');
                $rows->each(function (CrawlerWrapper $row) use ($resultWriter, $profileErrorWriter) {
                    $profileLink = $row->getNodeAttr('td > div > a', 'href');
                    $status = $row->getNodeText('td > div > a + span');

                    if($status !== 'Действащ' || !$profileLink){
                        return;
                    }

                    try {
                        $profileResponse = $this->sendGETRequest($profileLink);

                        if ($profileResponse->getStatusCode() !== 200) {
                            $profileErrorWriter->write(sprintf(self::ERROR_PATTERN, $profileLink));
                            return;
                        }

                        $crawler = new CrawlerWrapper((string)$profileResponse->getBody());
                        $name = $crawler->getXPathText('//div[contains(text(),"Наименование:")]/following-sibling::p/text()');
                        $originName = $crawler->getXPathText('//div[contains(text(),"Транслитерация:")]/following-sibling::p/text()');
                        $nip = $crawler->getXPathText('//div[contains(text(),"ЕИК:")]/following-sibling::p/text()');
                        $address = $crawler->getXPathText('//div[contains(text(),"Адрес:")]/following-sibling::p/text()');
                        $postalCode = $this->extractPostalCode($address);
                        $city = $crawler->getXPathText('//p[contains(@class, "company-datum") and contains(., "ЕИК, град, статус")]/span[@class="company-datum-value d-inline-block"]/b/text()');

                        $street = $crawler->getNodeAttr('meta[property="business:contact_data:street_address"]', 'content');
                        $street = preg_replace('/\(.+\)/', '', $street);
                        $street = TextUtil::trimOrNull(str_replace($city, '', $street));

                        $date = $crawler->getXPathText('//div[contains(text(),"Основана:")]/following-sibling::p/text()');

                        $companyData = sprintf(self::RESULT_PATTERN, $originName, $name, $nip, $status, $date,
                            $street, $city, $postalCode);
                        $resultWriter->write($companyData);
                    } catch (\Throwable $ex){
                        $profileErrorWriter->write(
                            sprintf(self::ERROR_PATTERN, $profileLink, $ex->getMessage())
                        );
                    }
                });
            } catch (\Throwable $ex){
                $errorWriter->write(sprintf(self::ERROR_PATTERN, $searchUrl, $ex->getMessage()));
                return Command::SUCCESS;
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @param string|null $address
     *
     * @return string|null
     */
    private function extractPostalCode(?string $address): ?string
    {
        $postalCodeNew = preg_match('/(\d{4})/', $address, $matches);

        return $matches[0] ?? null;
    }
}