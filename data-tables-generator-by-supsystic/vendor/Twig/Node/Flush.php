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
 * Represents a flush node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Twig_SupTwgDtgs_Node_Flush extends Twig_SupTwgDtgs_Node
{
    public function __construct($lineno, $tag)
    {
        parent::__construct(array(), array(), $lineno, $tag);
    }

    public function compile(Twig_SupTwgDtgs_Compiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write("flush();\n")
        ;
    }
}
