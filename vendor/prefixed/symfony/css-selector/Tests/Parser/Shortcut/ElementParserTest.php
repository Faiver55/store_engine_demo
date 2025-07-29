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

namespace StoreEngine\Symfony\Component\CssSelector\Tests\Parser\Shortcut;

use PHPUnit\Framework\TestCase;
use StoreEngine\Symfony\Component\CssSelector\Node\SelectorNode;
use StoreEngine\Symfony\Component\CssSelector\Parser\Shortcut\ElementParser;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class ElementParserTest extends TestCase
{
    /** @dataProvider getParseTestData */
    public function testParse($source, $representation)
    {
        $parser = new ElementParser();
        $selectors = $parser->parse($source);
        $this->assertCount(1, $selectors);

        /** @var SelectorNode $selector */
        $selector = $selectors[0];
        $this->assertEquals($representation, (string) $selector->getTree());
    }

    public static function getParseTestData()
    {
        return [
            ['*', 'Element[*]'],
            ['testel', 'Element[testel]'],
            ['testns|*', 'Element[testns|*]'],
            ['testns|testel', 'Element[testns|testel]'],
        ];
    }
}
