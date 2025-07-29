<?php

/**
 * This file is part of FPDI
 *
 * @package   setasign\Fpdi
 * @copyright Copyright (c) 2024 Setasign GmbH & Co. KG (https://www.setasign.com)
 * @license   http://opensource.org/licenses/mit-license The MIT License
 *
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace StoreEngine\setasign\Fpdi\Tfpdf;

use StoreEngine\setasign\Fpdi\FpdfTrait;
use StoreEngine\setasign\Fpdi\FpdiTrait;

/**
 * Class Fpdi
 *
 * This class let you import pages of existing PDF documents into a reusable structure for tFPDF.
 */
class Fpdi extends FpdfTpl
{
    use FpdiTrait;
    use FpdfTrait;

    /**
     * FPDI version
     *
     * @string
     */
    const VERSION = '2.6.3';
}
