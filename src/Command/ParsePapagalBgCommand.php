<?php

namespace App\Command;

use App\Util\CrawlerWrapper;
use App\Util\ProxyRandomizer;
use App\Util\RequestAttempt;
use App\Util\TextUtil;
use App\Util\Writer;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ParsePapagalBgCommand extends Command
{
    use ProxyRandomizer, RequestAttempt;

    private const ALPHABET = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r',
        's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'a', 'б', 'в', 'г', 'д', 'е', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н',
        'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ь', 'ю', 'я', '1', '2', '3', '4', '5', '6',
        '7', '8', '9'
    ];

    private const BASE_URI = 'https://papagal.bg';
    private const SEARCH_URI = self::BASE_URI.'/search_results/%s?type=company&page=%s';
    private const RESULT_PATTERN = "%s}##{%s}##{%s}##{%s}##{%s}##{%s}##{%s}##{%s\n";
    private const ERROR_PATTERN = "%s}##{%s\n";

    protected static $defaultName = 'parse:papagal_bg';
    protected static $defaultDescription = 'Parsing website papagal.bg';

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
            ],
        ]);

        $this->proxies = file('proxies.txt', FILE_IGNORE_NEW_LINES);
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $resultWriter = new Writer('result/bg/papagal/result.csv');
        $errorWriter = new Writer('result/bg/papagal/error.csv');
        $profileErrorWriter = new Writer('result/bg/papagal/profile_error.csv');

        foreach (self::ALPHABET as $symbol) {
            for ($page = 1; $page < 30000; $page++) {
                $url = sprintf(self::SEARCH_URI, $symbol, $page);

                try {
                    $response = $this->sendGETRequest($url, [
                        'proxy' => $this->getRandomProxy(),
                    ]);

                    if ($response->getStatusCode() !== 200) {
                        $errorWriter->write(sprintf(self::ERROR_PATTERN, $url, $response->getStatusCode()));
                        continue;
                    }

                    $crawler = new CrawlerWrapper((string)$response->getBody());

                    if (!$crawler->getTotalNodes('table tbody tr')) {
                        continue 2;
                    }

                    $rows = $crawler->filter('table tbody tr');
                    $rows->each(function (CrawlerWrapper $row) use ($resultWriter, $profileErrorWriter) {
                        if (!$profileRef = $row->getNodeAttr('th + td > a', 'href')) {
                            return;
                        }

                        $nameString = $row->getNodeHtml('th + td a');
                        $address = $row->getNodeText('td +td + td');

                        $nip = TextUtil::clearString($row->getNodeText('th'));
                        $name = $this->extractName($nameString);
                        $originName = $this->extractOriginName($nameString);
                        $date = $row->getNodeText('td + td');
                        $postalCode = $this->extractPostalCode($address);
                        $city = $this->extractCity($address);

                        $profileUrl = self::BASE_URI . $profileRef;
                        try {
                            $profileResponse = $this->sendGETRequest($profileUrl);

                            if ($profileResponse->getStatusCode() !== 200) {
                                $profileErrorWriter->write(sprintf(self::ERROR_PATTERN, $profileUrl));
                                return;
                            }

                            $crawler = new CrawlerWrapper((string)$profileResponse->getBody());
                            $profileAddress = $crawler->getXPathText('//dt[contains(.,"Седалище адрес")]//following-sibling::dd[1]');

                            $status = $crawler->getXPathText('//dt[contains(.,"Статус")]//following-sibling::dd[1]');
                            $street = $this->extractStreet($profileAddress, $city);

                            $companyData = sprintf(self::RESULT_PATTERN, $originName, $name, $nip, $status, $date,
                                $street, $city, $postalCode);
                            $resultWriter->write($companyData);
                        } catch (\Throwable $ex){
                            $profileErrorWriter->write(
                                sprintf(self::ERROR_PATTERN, $profileUrl, $ex->getMessage())
                            );
                        }
                    });
                } catch (\Throwable $ex){
                    $errorWriter->write(sprintf(self::ERROR_PATTERN, $url, $ex->getMessage()));
                }
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @param string|null $string
     *
     * @return string|null
     */
    private function extractOriginName(?string $string): ?string
    {
        $parts = explode('<br>', $string);

        return TextUtil::clearString($parts[0] ?? null);
    }

    /**
     * @param string|null $string
     *
     * @return string|null
     */
    private function extractName(?string $string): ?string
    {
        $parts = explode('<br>', $string);

        return TextUtil::clearString($parts[1] ?? null);
    }

    /**
     * @param string|null $address
     *
     * @return string|null
     */
    private function extractPostalCode(?string $address): ?string
    {
        preg_match('/\(\d{4}\)/', $address, $match);

        return TextUtil::removeChars($match[0] ?? null);
    }

    /**
     * @param string|null $address
     *
     * @return string|null
     */
    private function extractCity(?string $address): ?string
    {
        $city = preg_replace('/(\(.+\))|БЪЛГАРИЯ,/', '', $address);

        return TextUtil::clearString($city);
    }

    /**
     * @param string|null $profileAddress
     * @param string|null $city
     *
     * @return string|null
     */
    private function extractStreet(?string $profileAddress, ?string $city): ?string
    {
        $cityAndComma = sprintf('%s,', $city);
        $street = str_replace(['БЪЛГАРИЯ,', $cityAndComma, $city], '', $profileAddress);
        $street = preg_replace('/(Виж на картата.+)|(Виж на картата)/', '', $street);

        return TextUtil::clearString($street);
    }
}