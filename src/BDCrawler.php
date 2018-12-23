<?php
/*
 * MIT License
 * 
 */
namespace BDSpider;

use Symfony\Component\DomCrawler\Crawler;
use BDSpider\Exceptions\BDCrawlerException;

class BDCrawler
{
    /**
     * Symfony Dom Crawler Instance
     *
     * @var \Symfony\Component\DomCrawler\Crawler
     */
    protected $crawler = null;

    /**
     * construct
     *
     * @param string $html
     */
    public function __construct($html)
    {
        $this->crawler = new Crawler($html);
    }
    
    
    
    public static function html($html)
    {
        return new static($html);
    }
    
    
    
    public function crawling(array $rules, string $range = null, Crawler $crawler = null)
    {
        if (!is_null($range)) {
            return $this->crawlingForRange($rules, $range);
        }
        $result = null;
        $self = $this;
        $result = [];

        foreach($rules as $name => $rule) {
            $selector = ($range) ? $range . ' ' . $rule[0] : $rule[0];
            $attribute = $rule[1];
            $asRmv = $rule[2] ?? null;
            $callback = $rule[3] ?? null;
            // dump($name);
            // dump($attribute);
            $items = $this->crawler->filter($selector);
            if ($items->count() > 1) {
                foreach ($items as $key => $item) {
                    if (strpos($name, ':') !== false) {
                        list($firstName, $lastName) = explode(':', $name, 2);
                        $result[$firstName][$key][$lastName] = $this->getAttribute($item, $attribute, $asRmv, $callback, $name);
                    } else {
                        $result[$key][$name] = $this->getAttribute($item, $attribute, $asRmv, $callback, $name);
                    }
                }
            } else if($items->count()==1) {
                $result[$name] = $this->getAttribute($items->getNode(0), $attribute, $asRmv, $callback, $name);
            } else {
                $result[$name] = null;
            }
        }

        return collect($result);
    }


    public function crawlingForRange(array $rules, string $range)
    {
        $result = null;
        $self = $this;
        $result = [];

        $rangeElem = $this->crawler->filter($range);
        foreach($rangeElem as $i => $one) {
            $c = new Crawler($one);
            foreach($rules as $name => $rule) {
                $selector = $rule[0];
                $attribute = $rule[1];
                $asRmv = $rule[2] ?? null;
                $callback = $rule[3] ?? null;
                $items = $c->filter($selector);
                if ($items->count() > 1) {
                    foreach ($items as $key => $item) {
                        if (strpos($name, ':') !== false) {
                            list($firstName, $lastName) = explode(':', $name, 2);
                            $result[$i][$firstName][$key][$lastName] = $this->getAttribute($item, $attribute, $asRmv, $callback, $name);
                        } else {
                            $result[$i][$name][$key] = $this->getAttribute($item, $attribute, $asRmv, $callback, $name);
                        }
                    }
                } else if($items->count()==1) {
                    $result[$i][$name] = $this->getAttribute($items->getNode(0), $attribute, $asRmv, $callback, $name);
                } else {
                    $result[$i][$name] = null;
                }
            }

        }

        return collect($result);
    }

    

    public function getAttribute(\DOMElement $elem, $attr, $asRmv, $callback, $name = null)
    {   
        if (is_array($attr)) {
            return $this->getAttributes($elem, $attr, $asRmv, $callback, $name);
        }

        $me = new Crawler($elem);

        $result = null;
        if ($attr == 'text') {
            $result = $elem->textContent;
        } else if($checkAttr = $this->isIs($attr)) {
            $result = $this->attrIs($me, $checkAttr);
        } else if($attr == '#text') {
            $result = '';
            foreach ($me->getNode(0)->childNodes as $child) {
                if ($child->nodeName == '#text'){
                    $result .= trim($child->textContent);
                }
            }
        } else {
            $result = $elem->getAttribute($attr);
        }
        if ($callback instanceof \Closure) {
            $result = \Closure::fromCallable($callback)->call($this, $result, $name);
        }
        return $result;
    }

    private function isIs($attr)
    {
        $pos = strpos($attr, ':');
        if ($pos === false) {
            return false;
        }
        return substr($attr, 0, $pos) == 'is' ? substr($attr,$pos+1) : false;
    }
    
    private function attrIs(Crawler $crawler, $attr)
    {
        return $crawler->filter($attr)->count() > 0;
    }



    /**
     * Undocumented function
     *
     * @param \DOMElement $elem
     * @param array $attrs
     * @param array $asRmv
     * @param \Closure $callback
     * @param string $name
     * @return array
     */
    public function getAttributes(\DOMElement $elem, $attrs, $asRmv, $callback, $name = null)
    {
        return collect($attrs)->map(function($v, $k) use ($elem, $asRmv, $callback, $name){
            return $this->getAttribute($elem, $v, $asRmv, $callback, $name);
        })->toArray();
    }
}
