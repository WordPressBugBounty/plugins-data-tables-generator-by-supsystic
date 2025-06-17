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
 * Marks a section of a template as untrusted code that must be evaluated in the sandbox mode.
 *
 * <pre>
 * {% sandbox %}
 *     {% include 'user.html' %}
 * {% endsandbox %}
 * </pre>
 *
 * @see http://www.twig-project.org/doc/api.html#sandbox-extension for details
 *
 * @final
 */
class Twig_SupTwgDtgs_TokenParser_Sandbox extends Twig_SupTwgDtgs_TokenParser
{
    public function parse(Twig_SupTwgDtgs_Token $token)
    {
        $stream = $this->parser->getStream();
        $stream->expect(Twig_SupTwgDtgs_Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(array($this, 'decideBlockEnd'), true);
        $stream->expect(Twig_SupTwgDtgs_Token::BLOCK_END_TYPE);

        // in a sandbox tag, only include tags are allowed
        if (!$body instanceof Twig_SupTwgDtgs_Node_Include) {
            foreach ($body as $node) {
                if ($node instanceof Twig_SupTwgDtgs_Node_Text && ctype_space($node->getAttribute('data'))) {
                    continue;
                }

                if (!$node instanceof Twig_SupTwgDtgs_Node_Include) {
                    throw new Twig_SupTwgDtgs_Error_Syntax('Only "include" tags are allowed within a "sandbox" section.', $node->getTemplateLine(), $stream->getSourceContext());
                }
            }
        }

        return new Twig_SupTwgDtgs_Node_Sandbox($body, $token->getLine(), $this->getTag());
    }

    public function decideBlockEnd(Twig_SupTwgDtgs_Token $token)
    {
        return $token->test('endsandbox');
    }

    public function getTag()
    {
        return 'sandbox';
    }
}
