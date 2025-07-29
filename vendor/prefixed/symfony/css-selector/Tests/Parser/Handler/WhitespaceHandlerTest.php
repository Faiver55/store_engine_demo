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

namespace StoreEngine\Symfony\Component\CssSelector\Tests\Parser\Handler;

use StoreEngine\Symfony\Component\CssSelector\Parser\Handler\WhitespaceHandler;
use StoreEngine\Symfony\Component\CssSelector\Parser\Token;

class WhitespaceHandlerTest extends AbstractHandlerTestCase
{
    public static function getHandleValueTestData()
    {
        return [
            [' ', new Token(Token::TYPE_WHITESPACE, ' ', 0), ''],
            ["\n", new Token(Token::TYPE_WHITESPACE, "\n", 0), ''],
            ["\t", new Token(Token::TYPE_WHITESPACE, "\t", 0), ''],

            [' foo', new Token(Token::TYPE_WHITESPACE, ' ', 0), 'foo'],
            [' .foo', new Token(Token::TYPE_WHITESPACE, ' ', 0), '.foo'],
        ];
    }

    public static function getDontHandleValueTestData()
    {
        return [
            ['>'],
            ['1'],
            ['a'],
        ];
    }

    protected function generateHandler()
    {
        return new WhitespaceHandler();
    }
}
