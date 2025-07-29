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

use StoreEngine\Symfony\Component\CssSelector\Node\ElementNode;
use StoreEngine\Symfony\Component\CssSelector\Node\HashNode;

class HashNodeTest extends AbstractNodeTestCase
{
    public static function getToStringConversionTestData()
    {
        return [
            [new HashNode(new ElementNode(), 'id'), 'Hash[Element[*]#id]'],
        ];
    }

    public static function getSpecificityValueTestData()
    {
        return [
            [new HashNode(new ElementNode(), 'id'), 100],
            [new HashNode(new ElementNode(null, 'id'), 'class'), 101],
        ];
    }
}
