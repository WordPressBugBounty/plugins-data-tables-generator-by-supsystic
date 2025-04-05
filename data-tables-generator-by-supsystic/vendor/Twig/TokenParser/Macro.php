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
 * Defines a macro.
 *
 * <pre>
 * {% macro input(name, value, type, size) %}
 *    <input type="{{ type|default('text') }}" name="{{ name }}" value="{{ value|e }}" size="{{ size|default(20) }}" />
 * {% endmacro %}
 * </pre>
 *
 * @final
 */
class Twig_SupTwgDtgs_TokenParser_Macro extends Twig_SupTwgDtgs_TokenParser
{
    public function parse(Twig_SupTwgDtgs_Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();
        $name = $stream->expect(Twig_SupTwgDtgs_Token::NAME_TYPE)->getValue();

        $arguments = $this->parser->getExpressionParser()->parseArguments(true, true);

        $stream->expect(Twig_SupTwgDtgs_Token::BLOCK_END_TYPE);
        $this->parser->pushLocalScope();
        $body = $this->parser->subparse(array($this, 'decideBlockEnd'), true);
        if ($token = $stream->nextIf(Twig_SupTwgDtgs_Token::NAME_TYPE)) {
            $value = $token->getValue();

            if ($value != $name) {
                throw new Twig_SupTwgDtgs_Error_Syntax(sprintf('Expected endmacro for macro "%s" (but "%s" given).', $name, $value), $stream->getCurrent()->getLine(), $stream->getSourceContext());
            }
        }
        $this->parser->popLocalScope();
        $stream->expect(Twig_SupTwgDtgs_Token::BLOCK_END_TYPE);

        $this->parser->setMacro($name, new Twig_SupTwgDtgs_Node_Macro($name, new Twig_SupTwgDtgs_Node_Body(array($body)), $arguments, $lineno, $this->getTag()));
    }

    public function decideBlockEnd(Twig_SupTwgDtgs_Token $token)
    {
        return $token->test('endmacro');
    }

    public function getTag()
    {
        return 'macro';
    }
}
