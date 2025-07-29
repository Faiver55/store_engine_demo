<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Modified by kodezen on 13-May-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace StoreEngine\Symfony\Component\CssSelector\Tests\Node;

use StoreEngine\Symfony\Component\CssSelector\Node\ClassNode;
use StoreEngine\Symfony\Component\CssSelector\Node\ElementNode;

class ClassNodeTest extends AbstractNodeTestCase
{
    public static function getToStringConversionTestData()
    {
        return [
            [new ClassNode(new ElementNode(), 'class'), 'Class[Element[*].class]'],
        ];
    }

    public static function getSpecificityValueTestData()
    {
        return [
            [new ClassNode(new ElementNode(), 'class'), 10],
            [new ClassNode(new ElementNode(null, 'element'), 'class'), 11],
        ];
    }
}
