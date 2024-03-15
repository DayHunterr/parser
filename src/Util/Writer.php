<?php

namespace App\Util;

final class Writer
{
    /**
     * @var string
     */
    private $fileName;

    /**
     * @var resource
     */
    private $stream;

    /**
     * @param string $fileName
     * @param string $mode
     */
    public function __construct(string $fileName, string $mode = 'a+')
    {
        $this->fileName = $fileName;
        $this->stream = fopen($this->fileName, $mode);
    }

    public function __destruct()
    {
        fclose($this->stream);
    }

    /**
     * @param string $txt
     *
     * @return void
     */
    public function write(string $txt): void
    {
         fwrite($this->stream, $txt);
    }
}