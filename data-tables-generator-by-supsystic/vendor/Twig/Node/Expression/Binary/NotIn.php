<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_SupTwgDtgs_Node_Expression_Binary_NotIn extends Twig_SupTwgDtgs_Node_Expression_Binary
{
    public function compile(Twig_SupTwgDtgs_Compiler $compiler)
    {
        $compiler
            ->raw('!Twig_SupTwgDtgs_in_filter(')
            ->subcompile($this->getNode('left'))
            ->raw(', ')
            ->subcompile($this->getNode('right'))
            ->raw(')')
        ;
    }

    public function operator(Twig_SupTwgDtgs_Compiler $compiler)
    {
        return $compiler->raw('not in');
    }
}
