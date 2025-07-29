<?php
/**
 * @license GPL-2.0-only
 *
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace StoreEngine\Mpdf\Tag;

use StoreEngine\Mpdf\Strict;

use StoreEngine\Mpdf\Cache;
use StoreEngine\Mpdf\Color\ColorConverter;
use StoreEngine\Mpdf\CssManager;
use StoreEngine\Mpdf\Form;
use StoreEngine\Mpdf\Image\ImageProcessor;
use StoreEngine\Mpdf\Language\LanguageToFontInterface;
use StoreEngine\Mpdf\Mpdf;
use StoreEngine\Mpdf\Otl;
use StoreEngine\Mpdf\SizeConverter;
use StoreEngine\Mpdf\TableOfContents;

abstract class Tag
{

	use Strict;

	/**
	 * @var \StoreEngine\Mpdf\Mpdf
	 */
	protected $mpdf;

	/**
	 * @var \StoreEngine\Mpdf\Cache
	 */
	protected $cache;

	/**
	 * @var \StoreEngine\Mpdf\CssManager
	 */
	protected $cssManager;

	/**
	 * @var \StoreEngine\Mpdf\Form
	 */
	protected $form;

	/**
	 * @var \StoreEngine\Mpdf\Otl
	 */
	protected $otl;

	/**
	 * @var \StoreEngine\Mpdf\TableOfContents
	 */
	protected $tableOfContents;

	/**
	 * @var \StoreEngine\Mpdf\SizeConverter
	 */
	protected $sizeConverter;

	/**
	 * @var \StoreEngine\Mpdf\Color\ColorConverter
	 */
	protected $colorConverter;

	/**
	 * @var \StoreEngine\Mpdf\Image\ImageProcessor
	 */
	protected $imageProcessor;

	/**
	 * @var \StoreEngine\Mpdf\Language\LanguageToFontInterface
	 */
	protected $languageToFont;

	const ALIGN = [
		'left' => 'L',
		'center' => 'C',
		'right' => 'R',
		'top' => 'T',
		'text-top' => 'TT',
		'middle' => 'M',
		'baseline' => 'BS',
		'bottom' => 'B',
		'text-bottom' => 'TB',
		'justify' => 'J'
	];

	public function __construct(
		Mpdf $mpdf,
		Cache $cache,
		CssManager $cssManager,
		Form $form,
		Otl $otl,
		TableOfContents $tableOfContents,
		SizeConverter $sizeConverter,
		ColorConverter $colorConverter,
		ImageProcessor $imageProcessor,
		LanguageToFontInterface $languageToFont
	) {

		$this->mpdf = $mpdf;
		$this->cache = $cache;
		$this->cssManager = $cssManager;
		$this->form = $form;
		$this->otl = $otl;
		$this->tableOfContents = $tableOfContents;
		$this->sizeConverter = $sizeConverter;
		$this->colorConverter = $colorConverter;
		$this->imageProcessor = $imageProcessor;
		$this->languageToFont = $languageToFont;
	}

	public function getTagName()
	{
		$tag = get_class($this);
		return strtoupper(str_replace('StoreEngine\Mpdf\Tag\\', '', $tag));
	}

	protected function getAlign($property)
	{
		$property = strtolower($property);
		return array_key_exists($property, self::ALIGN) ? self::ALIGN[$property] : '';
	}

	abstract public function open($attr, &$ahtml, &$ihtml);

	abstract public function close(&$ahtml, &$ihtml);

}
