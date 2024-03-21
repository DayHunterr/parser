<?php

namespace App\Command;

use DateTime;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

class ParseFinancyAZ extends Command
{
    private const DELIMETR = '}##{';
    private const BASE_URI = 'https://finansi.bg';
    private const SEARCH_URI = '/search?name=';
    private const PAGE = '&page=';

    protected function configure(): void
    {
        // Use in-build functions to set name, description and help
        $this->setName('parse:financyPageAZ.bg')
            ->setDescription('This command runs parsing financy.bg')
            ->setHelp('Run this command to execute your custom tasks in the execute function.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $client = new Client([
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Connection' => 'keep-alive',
                'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:123.0) Gecko/20100101 Firefox/123.0'
            ]
        ]);

        #Create all possible combination of 'abs' from a-z
        #Uncomment if need to generate file;
//        $file = fopen('characters.csv', 'w'); // Open or create the file in write mode
//        for ($i = ord('a'); $i <= ord('z'); $i++) {
//            for ($j = ord('a'); $j <= ord('z'); $j++) {
//                for ($k = ord('a'); $k <= ord('z'); $k++) {
//                    $combination = chr($i) . chr($j) . chr($k) . "\n"; // Form the combination
//                    fwrite($file, $combination); // Write the combination to the file
//                }
//            }
//        }
//        fclose($file);
        #Create all possible combination of 'abs' from a-z
        #Uncomment if need to generate file;

        $links = file('characters.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($links as $xmlUrl) {

                for ($page = 1; $page < 500000; $page++) {

                $url = self::BASE_URI . self::SEARCH_URI . $xmlUrl . self::PAGE . $page;

                $response = $client->get($url);

                $xml = $response->getBody()->getContents();

                $crawler = new Crawler($xml);

                if ($crawler->filter('table tr')->count() <= 1) {
                    continue 2;
                }

                $filter = $crawler->filterXPath('//tr[position() > 1]');

                $data = $filter->each(function (Crawler $row) use ($client) {

                    sleep(3);

                    $profileLink = $row->filter('td > div > a')->attr('href');
                    dump($profileLink);

                    $profileResponse = $client->get($profileLink);

                    $statusCodeProfile = $profileResponse->getStatusCode();
                    if ($statusCodeProfile == '200') {
                        $profileContent = $profileResponse->getBody()->getContents();

                        $profileCrawler = new Crawler($profileContent);
                    }

                    $status = $profileCrawler->filterXPath('//div[contains(text(), 
                    "Статус:")]/following-sibling::p/text()')->text();
                    $string = 'Действащ';
                    if($status !== $string) {
                        return "Shit";
                    }
                    dump($status);

                        $name = $profileCrawler->filterXPath('//div[contains(text(), 
                    "Наименование:")]/following-sibling::p/text()')->text();

                        sleep(2);
                        dump($name);

                        $additionalName = $profileCrawler->filterXPath('//div[contains(text(), 
                    "Правна форма:")]/following-sibling::p/text()')->text();

                        $origName = $profileCrawler->filterXPath('//div[contains(text(), 
                    "Транслитерация:")]/following-sibling::p/text()')->text();

                        $nip = $profileCrawler->filterXPath('//div[contains(text(), 
                    "ЕИК:")]/following-sibling::p/text()')->text();

                        $address = $profileCrawler->filterXPath('//div[contains(text(), 
                    "Адрес:")]/following-sibling::p/text()')->text();

                        $patternStreet = '/^[^,]*,[^,]*,\s*/u';
                        $extractStreet = preg_replace($patternStreet, '', $address, 1);

                        $patternCity = '/^[^,]+,\s*(\p{L}+(?:[\s.-]\p{L}+)*)\b/u';
                        $cityNew = preg_match($patternCity, $address, $matchesCity);
                        $extractCity = $matchesCity[1];

                        $date = $profileCrawler->filterXPath('//div[contains(text(), 
                    "Основана:")]/following-sibling::p/text()')->text();
                        $date = DateTime::createFromFormat('d.m.Y', $date);
                        $formattedDate = $date->format('Y-m-d');

                        $postalCode = $profileCrawler->filterXPath('//div[contains(text(),
                    "Адрес:")]/following-sibling::p/text()')->text();
                        $pattern = '(\d{4})';
                        $postalCodeNew = preg_match($pattern, $postalCode, $matches);
                        $extractCode = $matches[0];

                    return $result = $name . ' ' .
                        $additionalName . self::DELIMETR .
                        $origName . self::DELIMETR .
                        $nip . self::DELIMETR .
                        $status . self::DELIMETR .
                        $formattedDate . self::DELIMETR .
                        $extractStreet . self::DELIMETR .
                        $extractCity . self::DELIMETR .
                        $extractCode;
                });

                $fp = fopen('outputFinancyAZ.csv', 'a+');
                if ($fp) {
                    foreach ($data as $row) {
                        if (strpos($row, 'Shit') === false) {
                            fputcsv($fp, [$row]);
                        } else {
                            $io->warning('Skip');
                        }
                    }
                    fclose($fp);
                    $io->success('CSV file has been created successfully.');
                } else {
                    $io->error('Failed to open CSV file for writing.');
                }
                sleep(1);
            }
        }
        return Command::SUCCESS;
    }
}