<?php

namespace App\Command;

use App\Util\CrawlerWrapper;
use App\Util\ProxyRandomizer;
use App\Util\RequestAttempt;
use App\Util\Writer;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ParseRejstrikCommandAZ extends Command
{
    use ProxyRandomizer, RequestAttempt;

    public const COMMAND_NAME = 'parse:rejstrikAZ.cz';
    private const BASE_URI = 'https://rejstrik-firem.kurzy.cz';
    private const SEARCH_URL = self::BASE_URI . '/hledej-firmy/?s=%s&r=True&page=%s';
    private const RESULT_PATTERN = "%s}##{%s}##{%s}##{%s}##{%s}##{%s\n";
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
            ->setDescription('This command runs parsing rejstrikAZ.cz')
            ->setHelp('Run this command to parse data from rejstrik-firem.kurzy.cz')
            ->addOption('combination', 'c', InputOption::VALUE_REQUIRED, 'Chars combination.');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $combination = $input->getOption('combination');

        $resultWriter = new Writer('result/cz/rejstrik/result.csv');
        $errorWriter = new Writer('result/cz/rejstrik/error.csv');

        $count = 1;

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
                if ($crawler->getXPathText('//*[@id="leftcolumn"]/div[3]/div[2]/ul[1]/li') == 'Subjekt nenalezen') {
                    return Command::SUCCESS;
                }


                $elementCount = $crawler->filter('.or_mainl_div')->text();
                if (preg_match('/nalezeno:\s*(\d+)/', $elementCount, $matches)) {
                    $elementCount = $matches[1];
                }
                if ($count > $elementCount){
                    return Command::SUCCESS;
                }

                $rows = $crawler->filterXPath('//*[@id="leftcolumn"]/div[3]/div[2]/ul[1]/li');

                $rows->each(function (CrawlerWrapper $row) use (&$count, $searchUrl, $resultWriter, $errorWriter) {
                    try {
                        $name = $this->extractName($row);

                        $companyInfoString = $row->getNodeText('li');

                        $nip = $this->extractNip($companyInfoString);
                        $address = $this->extractAddress($companyInfoString);
                        $postalCode = $this->extractPostalCode($address);
                        $city = trim($this->extractCity($address));
                        $street = trim($this->extractStreet($address));
                        $date = $this->extractDate($companyInfoString);

                        $companyData = sprintf(self::RESULT_PATTERN, $name, $nip, $date,
                            $street, $city, $postalCode);
                        $resultWriter->write($companyData);
                        $count++;
                    } catch (\Throwable $ex) {
                        $errorWriter->write(
                            sprintf(self::ERROR_PATTERN, $searchUrl, $ex->getMessage())
                        );
                    }
                });
            } catch (\Throwable $ex) {
                $errorWriter->write(sprintf(self::ERROR_PATTERN, $searchUrl, $ex->getMessage()));
                return Command::SUCCESS;
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @param CrawlerWrapper $row
     * @return array|string|string[]|null
     */
    function extractName(CrawlerWrapper $row)
    {
        $name = $row->getNodeText('li > a');
        $name = preg_replace('/\xA0/u', ' ', $name);
        return preg_replace('/\s+/', ' ', $name);
    }

    /**
     * @param string|null $string
     * @return string|null
     */
    private function extractNip(?string $string): ?string
    {
        $nip = preg_match('/IČO:\s*(\d+)/u', $string, $matches);

        return $matches[1] ?? null;
    }

    /**
     * @param string|null $string
     * @return string|null
     */
    private function extractAddress(?string $string): ?string
    {
        $address = preg_match('/Adresa:\s*(.*?)(?=\s*Den vzniku:)/u', $string, $matches);
        return $matches[1] ?? null;
    }
    /**
     * @param string|null $address
     *
     * @return string|null
     */
    private function extractPostalCode(?string $address): ?string
    {
        $lastCommaPosition = strrpos($address, ',');

        if ($lastCommaPosition !== false) {
            $textAfterLastComma = substr($address, $lastCommaPosition + 1);

            return preg_replace('/\D/', '', $textAfterLastComma);
        }
        return null;
    }

    /**
     * @param string|null $address
     *
     * @return string|null
     */
    private function extractCity(?string $address): ?string
    {
        $lastCommaPosition = strrpos($address, ',');

        if ($lastCommaPosition !== false) {
            $textAfterLastComma = substr($address, $lastCommaPosition + 1);
            $deletePSC = str_replace('PSČ', '', $textAfterLastComma);
            return preg_replace('/\d/', '', $deletePSC);
        }
        return null;
    }

    /**
     * @param string|null $address
     *
     * @return string|null
     */
    private function extractStreet(?string $address): ?string
    {
        $lastCommaPosition = strrpos($address, ',');
        if ($lastCommaPosition !== false) {
            return substr($address, 0, $lastCommaPosition);
        }

        return $address;
    }

    /**
     * @param string|null $string
     *
     * @return string|null
     */
    private function extractDate(string $string): ?string
    {
        if (preg_match('/Den vzniku:\s*([^,]+)/u', $string, $matches)) {
            return trim($matches[1]) ?? null;
        }

        return null;
    }
}