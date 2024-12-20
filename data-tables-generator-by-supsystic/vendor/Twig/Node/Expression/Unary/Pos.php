<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 * (c) Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_SupTwgDtgs_Node_Expression_Unary_Pos extends Twig_SupTwgDtgs_Node_Expression_Unary
{
    public function operator(Twig_SupTwgDtgs_Compiler $compiler)
    {
        $compiler->raw('+');
    }
}
