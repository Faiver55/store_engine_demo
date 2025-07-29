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

use StoreEngine\Symfony\Component\CssSelector\Node\CombinedSelectorNode;
use StoreEngine\Symfony\Component\CssSelector\Node\ElementNode;

class CombinedSelectorNodeTest extends AbstractNodeTestCase
{
    public static function getToStringConversionTestData()
    {
        return [
            [new CombinedSelectorNode(new ElementNode(), '>', new ElementNode()), 'CombinedSelector[Element[*] > Element[*]]'],
            [new CombinedSelectorNode(new ElementNode(), ' ', new ElementNode()), 'CombinedSelector[Element[*] <followed> Element[*]]'],
        ];
    }

    public static function getSpecificityValueTestData()
    {
        return [
            [new CombinedSelectorNode(new ElementNode(), '>', new ElementNode()), 0],
            [new CombinedSelectorNode(new ElementNode(null, 'element'), '>', new ElementNode()), 1],
            [new CombinedSelectorNode(new ElementNode(null, 'element'), '>', new ElementNode(null, 'element')), 2],
        ];
    }
}
