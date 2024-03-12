<?php

namespace App\Command;

use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;

class ParsePapagalBgCommand extends Command
{
    private const BASE_URI = 'https://papagal.bg';
    private const SEARCH_URI = 'https://papagal.bg/search_results/';
    private const SEARCH_URI2 = '?type=company';
    private const PAGE_URI3 = '&page=';
    protected static $defaultName = 'parse:papagal_bg';
    protected static $defaultDescription = 'Parsing website papagal.bg';
    protected function configure(): void
    {

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

        $charArray = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'];

        $length = sizeof($charArray);

        for ($keyWord = 0; $keyWord < $length; $keyWord++) {

            $char = $charArray[$keyWord];

            for ($page = 1; $page < 30000; $page++) {

                $pageId = $page;

                $result = self::SEARCH_URI . $char . self::SEARCH_URI2 . self::PAGE_URI3 . $pageId;

                dump($result);

                $response = $client->get($result);

                $statusCode = $response->getStatusCode();

                if ($statusCode == '200') {

                    $content = $response->getBody()->getContents();

                    $crawler = new Crawler($content);

                    if ($crawler->filter('table tbody tr')->count() === 0) {
                        break;
                    }

                    $filter = $crawler->filter('table tbody tr');

                    $data = $filter->each(function (Crawler $row) use ($client) {

                        $result = '';

                        if ($row->filter('th')->count() === 0) {
                            return;
                        }

                        $nip = $row->filter('th')->text();

                        $name = $row->filter('th + td a')->html();

                        $name = preg_replace('/\n/', '', $name);
                        $name = trim(preg_replace('/\s+/', ' ', $name));
                        $name = str_replace('&amp;', '&', $name);
                        $parts = explode('<br>', $name);
                        $originName = trim($parts[0] ?? '');
                        $name = trim($parts[1] ?? '');

                        $result .= $originName . '}##{';
                        $result .= $name . '}##{';
                        $result .= $nip . '}##{';

                        $rawDate = $row->filter('td + td')->text();
                        $pattern = '/^(\w+) (\d{1,2}), (\d{4}) (\d{1,2}):(\d{2})$/';

                        $converted_date = '';
                        if (preg_match('/^\d-\d{4}-\d{2} \d{2}:\d{2}:\d{2}/', $rawDate)) {

                            $timestamp = strtotime($rawDate);
                            $converted_date = date('Y-m-d H:i:s', $timestamp);

                        } elseif (preg_match('/^\d-\d{4}-\d{2} \d{2}:\d{2}/', $rawDate)) {

                            $timestamp = strtotime($rawDate);
                            $converted_date = date('Y-m-d H:i', $timestamp);

                        } elseif (preg_match('/^\d-\d{4}-\d{2}/', $rawDate)) {

                            $timestamp = strtotime($rawDate);
                            $converted_date = date('Y-m-d', $timestamp);

                        } elseif (preg_match('/^(\w+) (\d{1,2}), (\d{4}) (\d{1,2}):(\d{2})$/', $rawDate)) {
                            $replacement = '${3}-${1}-${2} ${4}:${5}:${6}';
                            $converted_date = preg_replace($pattern, $replacement, $rawDate);

                        } elseif (preg_match('/\d{4}/', $rawDate)) {

                            $timestamp = strtotime($rawDate);
                            $converted_date = date('Y', $timestamp);

                        }

                        $postalCode = $row->filter('td + td + td')->text();
                        $postalCode = preg_replace('/[^\d]+/', '', $postalCode);
                        $postalCode = trim(preg_replace('/\s+/', ' ', $postalCode));

                        $city = $row->filter('td + td + td')->text();
                        $city = str_replace('БЪЛГАРИЯ,', '', $city);
                        $city = trim(preg_replace('/\(\d+\)/', '', $city));

                        $profileLink = self::BASE_URI . $row->filter('th + td > a')->attr('href');
                        $profileResponse = $client->get($profileLink);
                        $statusCodeProfile = $profileResponse->getStatusCode();
                        if ($statusCodeProfile == '200') {
                            $profileContent = $profileResponse->getBody()->getContents();

                            $profileCrawler = new Crawler($profileContent);

                            if ($profileCrawler->filterXPath('//dl[contains(.,"Статус")]')->count() !== 0) {
                                $statusProfile = $profileCrawler->filterXPath('//dt[contains(.,"Статус")]//following-sibling::dd[1]')->text('UTF-8');
                            } else {
                                $statusProfile = '';
                            }

                            $result .= $statusProfile . '}##{';
                            $result .= $converted_date . '}##{';

                            $street = '';
                            if ($profileCrawler->filterXPath('//dl[contains(.,"Статус")]')->count() !== 0) {
                                $address = $profileCrawler->filterXPath('//dt[contains(.,"Седалище адрес")]//following-sibling::dd[1]')->text();
                                $street = str_replace(['БЪЛГАРИЯ,', $city . ',', $city], '', $address);
                                $street = preg_replace('/(Виж на картата.+)|(Виж на картата)/', '', $street);

                            }

                            $result .= trim($street . '}##{');
                            $result .= $city . '}##{';
                            $result .= $postalCode . '}##{' . PHP_EOL;

                            sleep(1);
                        }
                        return $result;
                    });

                    $fp = fopen('output.csv', 'a+');
                    if ($fp) {
                        foreach ($data as $row) {
                            fputcsv($fp, [$row]);
                        }
                        fclose($fp);
                        $io->success('CSV file has been created successfully.');
                    } else {
                        $io->error('Failed to open CSV file for writing.');
                    }
                }
            }
        }
        return Command::SUCCESS;
    }
}