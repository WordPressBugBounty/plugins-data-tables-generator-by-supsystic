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
 * Flushes the output to the client.
 *
 * @see flush()
 *
 * @final
 */
class Twig_SupTwgDtgs_TokenParser_Flush extends Twig_SupTwgDtgs_TokenParser
{
    public function parse(Twig_SupTwgDtgs_Token $token)
    {
        $this->parser->getStream()->expect(Twig_SupTwgDtgs_Token::BLOCK_END_TYPE);

        return new Twig_SupTwgDtgs_Node_Flush($token->getLine(), $this->getTag());
    }

    public function getTag()
    {
        return 'flush';
    }
}
