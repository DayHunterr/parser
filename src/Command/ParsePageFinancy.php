<?php

namespace App\Command;

use DateTime;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

class ParsePageFinancy extends Command
{
    private  const DELIMETR = '}##{';
    protected function configure(): void
    {
        // Use in-build functions to set name, description and help
        $this->setName('parse:financyPage.bg')
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

        $links = file('output.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($links as $xmlUrl) {
            $response = $client->get($xmlUrl);

            $xml = $response->getBody()->getContents();


            $crawler = new Crawler($xml);

            $filter = $crawler->filterXPath('//div[@id="registry-data"]');

            $data = $filter->each(function (Crawler $row) use ($client) {

                $name = $row->filterXPath('//div[contains(text(), 
            "Наименование:")]/following-sibling::p/text()')->text();

                $additionalName = $row->filterXPath('//div[contains(text(), 
            "Правна форма:")]/following-sibling::p/text()')->text();

                $origName = $row->filterXPath('//div[contains(text(), 
            "Транслитерация:")]/following-sibling::p/text()')->text();

                $nip = $row->filterXPath('//div[contains(text(), 
            "ЕИК:")]/following-sibling::p/text()')->text();

                $status = $row->filterXPath('//div[contains(text(), 
            "Статус:")]/following-sibling::p/text()')->text();

                $address = $row->filterXPath('//div[contains(text(), 
            "Адрес:")]/following-sibling::p/text()')->text();

                $patternStreet = '/^[^,]*,[^,]*,\s*/u';
                $extractStreet = preg_replace($patternStreet, '', $address, 1);

                $patternCity = '/^[^,]+,\s*(\p{L}+(?:[\s.-]\p{L}+)*)\b/u';
                $cityNew = preg_match($patternCity, $address, $matchesCity);
                $extractCity = $matchesCity[1];

                $date = $row->filterXPath('//div[contains(text(), 
            "Основана:")]/following-sibling::p/text()')->text();
                $date = DateTime::createFromFormat('d.m.Y', $date);
                $formattedDate = $date->format('Y-m-d');

                $postalCode = $row->filterXPath('//div[contains(text(),
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

            $fp = fopen('outputPageFinancy.csv', 'a+');
            if ($fp) {
                foreach ($data as $row) {
                    fputcsv($fp, [$row]);
                }
                fclose($fp);
                $io->success('CSV file has been created successfully.');
            } else {
                $io->error('Failed to open CSV file for writing.');
            }
            sleep(1);
        }
        return Command::SUCCESS;
    }
}