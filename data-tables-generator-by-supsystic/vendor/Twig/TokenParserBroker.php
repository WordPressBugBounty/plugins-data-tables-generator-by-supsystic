<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 * (c) Arnaud Le Blanc
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Default implementation of a token parser broker.
 *
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 *
 * @deprecated since 1.12 (to be removed in 2.0)
 */
class Twig_SupTwgDtgs_TokenParserBroker implements Twig_SupTwgDtgs_TokenParserBrokerInterface
{
    protected $parser;
    protected $parsers = array();
    protected $brokers = array();

    /**
     * @param array|Traversable $parsers                 A Traversable of Twig_SupTwgDtgs_TokenParserInterface instances
     * @param array|Traversable $brokers                 A Traversable of Twig_SupTwgDtgs_TokenParserBrokerInterface instances
     * @param bool              $triggerDeprecationError
     */
    public function __construct($parsers = array(), $brokers = array(), $triggerDeprecationError = true)
    {
        if ($triggerDeprecationError) {
            //@trigger_error('The '.__CLASS__.' class is deprecated since version 1.12 and will be removed in 2.0.', E_USER_DEPRECATED);
        }

        foreach ($parsers as $parser) {
            if (!$parser instanceof Twig_SupTwgDtgs_TokenParserInterface) {
                throw new LogicException('$parsers must a an array of Twig_SupTwgDtgs_TokenParserInterface.');
            }
            $this->parsers[$parser->getTag()] = $parser;
        }
        foreach ($brokers as $broker) {
            if (!$broker instanceof Twig_SupTwgDtgs_TokenParserBrokerInterface) {
                throw new LogicException('$brokers must a an array of Twig_SupTwgDtgs_TokenParserBrokerInterface.');
            }
            $this->brokers[] = $broker;
        }
    }

    public function addTokenParser(Twig_SupTwgDtgs_TokenParserInterface $parser)
    {
        $this->parsers[$parser->getTag()] = $parser;
    }

    public function removeTokenParser(Twig_SupTwgDtgs_TokenParserInterface $parser)
    {
        $name = $parser->getTag();
        if (isset($this->parsers[$name]) && $parser === $this->parsers[$name]) {
            unset($this->parsers[$name]);
        }
    }

    public function addTokenParserBroker(Twig_SupTwgDtgs_TokenParserBroker $broker)
    {
        $this->brokers[] = $broker;
    }

    public function removeTokenParserBroker(Twig_SupTwgDtgs_TokenParserBroker $broker)
    {
        if (false !== $pos = array_search($broker, $this->brokers)) {
            unset($this->brokers[$pos]);
        }
    }

    /**
     * Gets a suitable TokenParser for a tag.
     *
     * First looks in parsers, then in brokers.
     *
     * @param string $tag A tag name
     *
     * @return null|Twig_SupTwgDtgs_TokenParserInterface A Twig_SupTwgDtgs_TokenParserInterface or null if no suitable TokenParser was found
     */
    public function getTokenParser($tag)
    {
        if (isset($this->parsers[$tag])) {
            return $this->parsers[$tag];
        }
        $broker = end($this->brokers);
        while (false !== $broker) {
            $parser = $broker->getTokenParser($tag);
            if (null !== $parser) {
                return $parser;
            }
            $broker = prev($this->brokers);
        }
    }

    public function getParsers()
    {
        return $this->parsers;
    }

    public function getParser()
    {
        return $this->parser;
    }

    public function setParser(Twig_SupTwgDtgs_ParserInterface $parser)
    {
        $this->parser = $parser;
        foreach ($this->parsers as $tokenParser) {
            $tokenParser->setParser($parser);
        }
        foreach ($this->brokers as $broker) {
            $broker->setParser($parser);
        }
    }
}
