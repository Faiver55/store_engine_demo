<?php
/**
 * @license MIT
 *
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace StoreEngine\Sabberworm\CSS\Property;

class KeyframeSelector extends Selector
{
    /**
     * regexp for specificity calculations
     *
     * @var string
     *
     * @internal since 8.5.2
     */
    const SELECTOR_VALIDATION_RX = '/
    ^(
        (?:
            [a-zA-Z0-9\x{00A0}-\x{FFFF}_^$|*="\'~\[\]()\-\s\.:#+>]* # any sequence of valid unescaped characters
            (?:\\\\.)?                                              # a single escaped character
            (?:([\'"]).*?(?<!\\\\)\2)?                              # a quoted text like [id="example"]
        )*
    )|
    (\d+%)                                                          # keyframe animation progress percentage (e.g. 50%)
    $
    /ux';
}
