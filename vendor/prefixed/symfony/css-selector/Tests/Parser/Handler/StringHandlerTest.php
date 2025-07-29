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

use StoreEngine\Symfony\Component\CssSelector\Parser\Handler\StringHandler;
use StoreEngine\Symfony\Component\CssSelector\Parser\Token;
use StoreEngine\Symfony\Component\CssSelector\Parser\Tokenizer\TokenizerEscaping;
use StoreEngine\Symfony\Component\CssSelector\Parser\Tokenizer\TokenizerPatterns;

class StringHandlerTest extends AbstractHandlerTestCase
{
    public static function getHandleValueTestData()
    {
        return [
            ['"hello"', new Token(Token::TYPE_STRING, 'hello', 1), ''],
            ['"1"', new Token(Token::TYPE_STRING, '1', 1), ''],
            ['" "', new Token(Token::TYPE_STRING, ' ', 1), ''],
            ['""', new Token(Token::TYPE_STRING, '', 1), ''],
            ["'hello'", new Token(Token::TYPE_STRING, 'hello', 1), ''],

            ["'foo'bar", new Token(Token::TYPE_STRING, 'foo', 1), 'bar'],
        ];
    }

    public static function getDontHandleValueTestData()
    {
        return [
            ['hello'],
            ['>'],
            ['1'],
            [' '],
        ];
    }

    protected function generateHandler()
    {
        $patterns = new TokenizerPatterns();

        return new StringHandler($patterns, new TokenizerEscaping($patterns));
    }
}
