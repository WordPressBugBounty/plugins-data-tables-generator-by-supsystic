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
 * Imports macros.
 *
 * <pre>
 *   {% import 'forms.html' as forms %}
 * </pre>
 *
 * @final
 */
class Twig_SupTwgDtgs_TokenParser_Import extends Twig_SupTwgDtgs_TokenParser
{
    public function parse(Twig_SupTwgDtgs_Token $token)
    {
        $macro = $this->parser->getExpressionParser()->parseExpression();
        $this->parser->getStream()->expect('as');
        $var = new Twig_SupTwgDtgs_Node_Expression_AssignName($this->parser->getStream()->expect(Twig_SupTwgDtgs_Token::NAME_TYPE)->getValue(), $token->getLine());
        $this->parser->getStream()->expect(Twig_SupTwgDtgs_Token::BLOCK_END_TYPE);

        $this->parser->addImportedSymbol('template', $var->getAttribute('name'));

        return new Twig_SupTwgDtgs_Node_Import($macro, $var, $token->getLine(), $this->getTag());
    }

    public function getTag()
    {
        return 'import';
    }
}
