<?php

namespace App\Util;

use Symfony\Component\DomCrawler\Crawler;

final class CrawlerWrapper extends Crawler
{
    /**
     * @param string $selector
     *
     * @return string|null
     */
    public function getNodeText(string $selector): ?string
    {
        $element = $this->filter($selector);

        return $element->count() > 0 ? $element->text() : null;
    }

    /**
     * @param string $selector
     *
     * @return string|null
     */
    public function getNodeHtml(string $selector): ?string
    {
        $element = $this->filter($selector);

        return $element->count() > 0 ? $element->html() : null;
    }

    /**
     * @param string $selector
     *
     * @return string|null
     */
    public function getXPathText(string $selector): ?string
    {
        $element = $this->filterXPath($selector);

        return $element->count() > 0 ? $element->text() : null;
    }

    /**
     * @param string $selector
     *
     * @return string|null
     */
    public function getXPathHtml(string $selector): ?string
    {
        $element = $this->filterXPath($selector);

        return $element->count() > 0 ? $element->html() : null;
    }

    /**
     * @param string $selector
     * @param string $attr
     *
     * @return string|null
     */
    public function getXPathAttr(string $selector, string $attr): ?string
    {
        $element = $this->filterXPath($selector);

        return $element->count() > 0 ? $element->attr($attr) : null;
    }

    /**
     * @param string $selector
     *
     * @return string|null
     */
    public function getLastNodeText(string $selector): ?string
    {
        $element = $this->filter($selector);

        return $element->count() === 0 ? null : $element->last()->text();
    }

    /**
     * @param string $selector
     *
     * @return string|null
     */
    public function getFirstNodeText(string $selector): ?string
    {
        $element = $this->filter($selector);

        return $element->count() === 0 ? null : $element->first()->text();
    }

    /**
     * @param string $selector
     * @param string $attr
     *
     * @return string|null
     */
    public function getNodeAttr(string $selector, string $attr): ?string
    {
        $element = $this->filter($selector);

        return $element->count() > 0 ? $element->attr($attr) : null;
    }

    /**
     * @param string $selector
     * @param int $eq
     *
     * @return string|null
     */
    public function getEqNodeText(string $selector, int $eq): ?string
    {
        $element = $this->filter($selector)->eq($eq);

        return $element->count() > 0 ? $element->text() : null;
    }

    /**
     * @param string $selector
     * @param string $attr
     * @param int $eq
     *
     * @return string|null
     */
    public function getEqNodeAttr(string $selector, string $attr, int $eq): ?string
    {
        $element = $this->filter($selector);

        return $element->count() > $eq ? $element->eq($eq)->attr($attr) : null;
    }

    /**
     * @param string $selector
     * @param string $attr
     * @param int $eq
     *
     * @return string|null
     */
    public function getEqXPathAttr(string $selector, string $attr, int $eq): ?string
    {
        $element = $this->filterXPath($selector);

        return $element->count() > $eq ? $element->eq($eq)->attr($attr) : null;
    }

    /**
     * @param string $selector
     *
     * @return bool
     */
    public function hasNode(string $selector): bool
    {
        $element = $this->filter($selector);

        return $element->count() > 0;
    }

    /**
     * @param string $selector
     *
     * @return int
     */
    public function getTotalNodes(string $selector): int
    {
        return $this->filter($selector)->count();
    }
}