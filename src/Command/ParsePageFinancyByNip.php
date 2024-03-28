<?php

namespace App\Command;

use App\Util\CrawlerWrapper;
use App\Util\ProxyRandomizer;
use App\Util\RequestAttempt;
use App\Util\Writer;
use DateTime;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


class ParsePageFinancyByNip extends Command
{
    use ProxyRandomizer, RequestAttempt;

    private const DELIMETR = '}##{';
    private const BASEURI = 'https://finansi.bg/search?name=';
    private const SEARCH_URI = 'https://finansi.bg/search?name=%s';
    private const RESULT_PATTERN = "%s}##{%s}##{%s}##{%s}##{%s}##{%s}##{%s}##{%s}##{%s\n";
    private const ERROR_PATTERN = "%s}##{%s\n";

    protected function configure(): void
    {
        // Use in-build functions to set name, description and help
        $this->setName('parse:financyPageByNip.bg')
            ->setDescription('This command runs parsing financy.bg')
            ->setHelp('Run this command to execute your custom tasks in the execute function.');
    }

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


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $resultWriter = new Writer('result/bg/financi/result.csv');
        $errorWriter = new Writer('result/bg/financi/error.csv');




        $links = file('nipFinanci.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($links as $xmlUrl) {
            $url = self::BASEURI . $xmlUrl;

            try {

                $response = $this->sendGETRequest($url, [
                    'proxy' => $this->getRandomProxy(),
                ]);

                $xml = $response->getBody()->getContents();

                $crawler = new CrawlerWrapper($xml);

                $filter = $crawler->filterXPath('//div[@id="registry-data"]');

                $filter->each(function (CrawlerWrapper $row) use ($resultWriter, $errorWriter, $io) {

                    $date = $row->getXPathText('//div[contains(text(),
                    "ДДС регистрация:")]/following-sibling::p/text()');

                    $date = preg_replace('/^.*?(\d.*)$/', '$1', $date);
                    $date = DateTime::createFromFormat('d.m.Y', $date);

                    if ($date !== false) {
                        $formattedDate = $date->format('Y-m-d');
                    } else {
                        $formattedDate = '';
                    }

                    $name = $row->getXPathText('//div[contains(text(), 
                    "Наименование:")]/following-sibling::p/text()');

                    $additionalName = $row->getXPathText('//div[contains(text(), 
                    "Правна форма:")]/following-sibling::p/text()');

                    $origName = $row->getXPathText('//div[contains(text(), 
                    "Транслитерация:")]/following-sibling::p/text()');

                    $nip = $row->getXPathText('//div[contains(text(), 
                    "ЕИК:")]/following-sibling::p/text()');

                    $status = $row->getXPathText('//div[contains(text(),
                    "Статус:")]/following-sibling::p/text()');

                    $address = $row->getXPathText('//div[contains(text(),
                    "Адрес:")]/following-sibling::p/text()');

                    $patternStreet = '/^[^,]*,[^,]*,\s*/u';
                    $extractStreet = preg_replace($patternStreet, '', $address, 1);

                    $patternCity = '/^[^,]+,\s*(\p{L}+(?:[\s.-]\p{L}+)*)\b/u';
                    $cityNew = preg_match($patternCity, $address, $matchesCity);
                    $extractCity = $matchesCity[1];

                    $postalCode = $row->getXPathText('//div[contains(text(),
                    "Адрес:")]/following-sibling::p/text()');
                    $pattern = '(\d{4})';
                    $postalCodeNew = preg_match($pattern, $postalCode, $matches);
                    $extractCode = $matches[0];

                    $companyData = sprintf(self::RESULT_PATTERN, $name,$additionalName,$origName, $nip,
                    $status,$extractStreet,$extractCity,$formattedDate,$extractCode);
                    $resultWriter->write($companyData);
                    $io->success('CSV file has been created successfully.');

                    return Command::SUCCESS;
                });
            } catch (\Throwable $ex) {
                $errorWriter->write(
                    sprintf(self::ERROR_PATTERN, $url, $ex->getMessage())
                );
            }
        }
        return Command::SUCCESS;
    }
}