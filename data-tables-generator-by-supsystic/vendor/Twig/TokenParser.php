<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Base class for all token parsers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Twig_SupTwgDtgs_TokenParser implements Twig_SupTwgDtgs_TokenParserInterface
{
    /**
     * @var Twig_SupTwgDtgs_Parser
     */
    protected $parser;

    /**
     * Sets the parser associated with this token parser.
     */
    public function setParser(Twig_SupTwgDtgs_Parser $parser)
    {
        $this->parser = $parser;
    }
}
