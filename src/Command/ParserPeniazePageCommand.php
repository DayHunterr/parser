<?php

namespace App\Command;

use App\Util\CrawlerWrapper;
use App\Util\TextUtil;
use App\Util\Writer;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ParserPeniazePageCommand extends Command
{

    public const COMMAND_NAME = 'parse:register.peniazePage.sk';

    private const BASE_URI = 'https://register.peniaze.sk';
    private const RESULT_PATTERN = "%s}##{%s}##{%s}##{%s}##{%s}##{%s}##{%s\n";
    private const ERROR_PATTERN = "%s}##{%s\n";

    protected function configure()
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('This command runs parsing register.peniaze.sk')
            ->setHelp('Execute this command to run the parser and write the results to a file.')
            ->addOption('line', 'l', InputOption::VALUE_REQUIRED, 'File line.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $client = new Client([
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Connection' => 'keep-alive',
                'User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:123.0) Gecko/20100101 Firefox/123.0'
            ]
        ]);

        $line = $input->getOption('line');

        $resultWriter = new Writer('result/sk/register_peniaze/result.csv');
        $errorWriter = new Writer('result/sk/register_peniaze/error.csv');
        
            try {
                $response = $client->get($line);

                if ($response->getStatusCode() !== 200) {
                    $errorWriter->write(sprintf(self::ERROR_PATTERN, $line, $response->getStatusCode()));
                    return Command::SUCCESS;
                }

                $crawler = new CrawlerWrapper((string)$response->getBody());

                if ($crawler->getTotalNodes('#tabcontent_1_1') <= 0) {
                    $errorWriter->write(sprintf(self::ERROR_PATTERN, $line, 'Node is empty'));
                    return Command::SUCCESS;
                }

                $rows = $crawler->filter('#tabcontent_1_1');

                $rows->each(function (CrawlerWrapper $row) use ($line, $resultWriter, $errorWriter) {
                    if ($row->hasNode('.rejstrik__detail__label:contains("Deň výmazu")')) {
                        $errorWriter->write(sprintf(self::ERROR_PATTERN, $line, 'Node has "Deň výmazu"'));
                        return Command::SUCCESS;
                    }

                    $originName = $row->getXPathText('//*[@id="tabcontent_1_1"]/div/div[4]/div/h2/span');
                    $nip = $row->getXPathText('//*[@id="tabcontent_1_1"]/div/div[8]/div/h2/span');
                    $status = 'Active';
                    $date = TextUtil::clearString($row->getXPathText('//*[@id="tabcontent_1_1"]/div/div[2]'));
                    $date = str_replace(' ', '', $date);
                    $address = $this->extractAddress($row);

                    $city = '';

                    $city = $this->extractCity($address);
                    $street = $this->extractStreet($address);
                    $postalCode = $this->extractPostalCode($address);

                    $companyData = sprintf(self::RESULT_PATTERN, $originName, $nip, $status, $date,
                        $street, $city, $postalCode);
                    $resultWriter->write($companyData);
                });
            } catch (\Throwable $ex) {
                $errorWriter->write(sprintf(self::ERROR_PATTERN, $line, $ex->getMessage()));
                return Command::SUCCESS;
            }
        return Command::SUCCESS;
    }

    /**
     * @param CrawlerWrapper $row
     * @return string|null
     */
    private function extractAddress(CrawlerWrapper $row): ?string
    {
        return TextUtil::trimOrNull(
            preg_replace('/od\s+\d{1,2}\.\s+\d{1,2}\.\s+\d{4}\s/',
                '',
                $row->getXPathText('//*[@id="tabcontent_1_1"]/div/div[6]/div[1]')));
    }
    /**
     * @param string|null $address
     * @return string
     */
    private function extractPostalCode(?string $address): string
    {
        if (preg_match('/PSČ (\d{5})/', $address, $matches)) {
            $postalCode = $matches[1];
        } else {
            $postalCode = '';
        }
        return $postalCode;
    }

    /**
     * @param string|null $address
     * @return string
     */
    private function extractStreet(?string $address): string
    {
        $parts = explode(',', $address, 2);

        if (isset($parts[1])) {
            $newRow = trim($parts[1]);
        } else {
            $newRow = '';
        }

        $pos = strpos($newRow, 'PSČ');

        if ($pos !== false) {

            $commaPos = strrpos(substr($newRow, 0, $pos), ',');

            if ($commaPos !== false) {
                $street = trim(substr($newRow, 0, $commaPos));
            } else {
                $street = '';
            }
        } else {
            $street = $newRow;
        }
        return $street;
    }

    /**
     * @param string|null $address
     * @return false|string|null
     */
    private function extractCity(?string $address)
    {
        $firstCommaPosition = strpos($address, ',');
        if ($firstCommaPosition !== false) {
            $city = substr($address, 0, $firstCommaPosition);
        } else {
            $city = $address;
        }
        return $city;
    }
}
