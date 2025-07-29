<?php
/**
 * @license GPL-2.0-only
 *
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace StoreEngine\Mpdf\Tag;

class WatermarkText extends Tag
{

	public function open($attr, &$ahtml, &$ihtml)
	{
		$txt = '';
		if (!empty($attr['CONTENT'])) {
			$txt = htmlspecialchars_decode($attr['CONTENT'], ENT_QUOTES);
		}

		$alpha = -1;
		if (isset($attr['ALPHA']) && $attr['ALPHA'] > 0) {
			$alpha = $attr['ALPHA'];
		}
		$this->mpdf->SetWatermarkText($txt, $alpha);
	}

	public function close(&$ahtml, &$ihtml)
	{
	}
}
