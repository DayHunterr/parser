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
use Symfony\Component\Console\Style\SymfonyStyle;

class ParseCeginfoSitemaps extends Command
{

    use ProxyRandomizer, RequestAttempt;

    private const SITES = 'https://ceginfo.hu/sitemap.xml';
    public const COMMAND_NAME = "parse:ceginfo.hu";
    private const BASE_URI = 'https://ceginfo.hu/';

    private const RESULT_PATTERN = "%s}##{%s}##{%s}##{%s}##{%s}##{%s}##{%s\n";
    private const ERROR_PATTERN = "%s}##{%s\n";
    private $client;

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

    protected function configure(): void
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('This command runs parsing parse:ceginfo.hu')
            ->setHelp('Run this command to execute your custom tasks in the execute function.')
            ->addOption('profiles','pr', InputOption::VALUE_REQUIRED, 'Reading from file');
    }



    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $resultWriter = new Writer('result/hu/ceginfo/result.csv');
        $errorWriter = new Writer('result/hu/ceginfo/error.csv');
        $closedCoWriter = new Writer('result/hu/ceginfo/closed.csv');

        $io = new SymfonyStyle($input, $output);


//        $sitemaps = ["sitemap_01_1.xml","sitemap_01_2.xml","sitemap_01_3.xml","sitemap_01_4.xml","sitemap_01_5.xml","sitemap_02_1.xml","sitemap_03_1.xml",
//        "sitemap_04_1.xml","sitemap_05_1.xml","sitemap_06_1.xml","sitemap_07_1.xml","sitemap_08_1.xml","sitemap_09_1.xml","sitemap_10_1.xml","sitemap_11_1.xml",
//        "sitemap_12_1.xml","sitemap_13_1.xml","sitemap_13_2.xml","sitemap_14_1.xml","sitemap_15_1.xml","sitemap_16_1.xml","sitemap_17_1.xml","sitemap_18_1.xml",
//            "sitemap_19_1.xml","sitemap_20_1.xml"];
//
//
//    foreach ($sitemaps as $key => $link){
//            $io->success($key);
//            $stream = fopen("/var/www/html/parser/CeginfoSitemaps/sitemap_01_$key.csv", 'a+');
//            $res = (string)$this->client->get(self::BASE_URI.$link)->getBody();
//            $crawler = new CrawlerWrapper($res);
//
//            $profileRefs = $crawler->filter('url loc')->each(function (CrawlerWrapper $ref) use ($stream){
//                fwrite($stream, $ref->text() . PHP_EOL);
//            });
//
//            fclose($stream);
//    }

                $line = $input->getOption('profiles');
                $content = $this->client->get($line)->getBody()->getContents();

                try {
                    $response = $this->sendGETRequest($line, [
                        'proxy' => $this->getRandomProxy(),
                    ]);

                    if ($response->getStatusCode() !== 200) {
                        $errorWriter->write(sprintf(self::ERROR_PATTERN, $line, $response->getStatusCode()));
                        return Command::SUCCESS;
                    }

                    $crawler = new CrawlerWrapper($content);

                    $year = $crawler->getNodeText('h2.company-title > .small');
                    $name = str_replace($year, '', $crawler->getNodeText('h2.company-title'));
                    $address = $crawler->getNodeText('h2.company-title + p');

                    if (!$address || $address === '-' || !$name || $name === '-') {
                        $errorWriter->write(sprintf(self::ERROR_PATTERN,$line, "Ivalid address"));
                        $io->success(sprintf('No result %s', $line));
                    }

                    $postalCode = trim($this->extractPostalCode($address));
                    $city = trim($this->extractCity($address));
                    $street = trim($this->extractStreet($address));
                    $nip = $crawler->getEqNodeText('h2.company-title + p + div > p > span > .text-capitalize', 0);
                    $regN = $crawler->getEqNodeText('h2.company-title + p + div > p > span > .text-capitalize', 1);
                    $companyData = sprintf(self::RESULT_PATTERN,$name,$nip,$year,$street,$city,$postalCode,$regN);

                    $crawler->getNodeText('div.status.stategreen')
                        ? $resultWriter->write($companyData)
                        : $closedCoWriter->write($companyData);

                } catch (\Throwable $ex) {
                    $errorWriter->write(sprintf(self::ERROR_PATTERN, $line, $ex->getMessage()));
                    return Command::SUCCESS;
                }
        return Command::SUCCESS;
    }

    /**
     * @param string|null $address
     *
     * @return string|null
     */
    private function extractStreet(?string $address): ?string
    {
        $addrParts = explode(',', $address);

        return $addrParts[1] ?? null;
    }

    /**
     * @param string|null $address
     *
     * @return string|null
     */
    private function extractCity(?string $address): ?string
    {
        $addrParts = explode(' ', $address);
        $city = $addrParts[1] ?? null;

        return str_replace(',', '', $city);
    }

    /**
     * @param string|null $address
     *
     * @return string|null
     */
    private function extractPostalCode(?string $address): ?string
    {
        $addrParts = explode(' ', $address);

        return $addrParts[0] ?? null;
    }

//    private function getArchives(): array
//    {
//        $content = (string)$this->client->request('GET', self::SITES)->getBody();
//        $crawler = new CrawlerWrapper($content);
//
//        return $crawler->filter('sitemapindex sitemap loc')->each(function (CrawlerWrapper $item) {
//            return substr($item->text(), 0, -3);
//        });
//    }
//        return self::SUCCESS;
//    }

}
