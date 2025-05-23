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
 * Creates a nested scope.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class Twig_SupTwgDtgs_TokenParser_With extends Twig_SupTwgDtgs_TokenParser
{
    public function parse(Twig_SupTwgDtgs_Token $token)
    {
        $stream = $this->parser->getStream();

        $variables = null;
        $only = false;
        if (!$stream->test(Twig_SupTwgDtgs_Token::BLOCK_END_TYPE)) {
            $variables = $this->parser->getExpressionParser()->parseExpression();
            $only = $stream->nextIf(Twig_SupTwgDtgs_Token::NAME_TYPE, 'only');
        }

        $stream->expect(Twig_SupTwgDtgs_Token::BLOCK_END_TYPE);

        $body = $this->parser->subparse(array($this, 'decideWithEnd'), true);

        $stream->expect(Twig_SupTwgDtgs_Token::BLOCK_END_TYPE);

        return new Twig_SupTwgDtgs_Node_With($body, $variables, $only, $token->getLine(), $this->getTag());
    }

    public function decideWithEnd(Twig_SupTwgDtgs_Token $token)
    {
        return $token->test('endwith');
    }

    public function getTag()
    {
        return 'with';
    }
}
