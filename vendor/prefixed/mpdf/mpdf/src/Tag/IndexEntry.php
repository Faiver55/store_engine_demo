<?php
/**
 * @license GPL-2.0-only
 *
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace StoreEngine\Mpdf\Tag;

use StoreEngine\Mpdf\Mpdf;

class IndexEntry extends Tag
{

	public function open($attr, &$ahtml, &$ihtml)
	{
		if (!empty($attr['CONTENT'])) {
			if (!empty($attr['XREF'])) {
				$this->mpdf->IndexEntry(htmlspecialchars_decode($attr['CONTENT'], ENT_QUOTES), $attr['XREF']);
				return;
			}
			$objattr = [];
			$objattr['CONTENT'] = htmlspecialchars_decode($attr['CONTENT'], ENT_QUOTES);
			$objattr['type'] = 'indexentry';
			$objattr['vertical-align'] = 'T';
			$e = Mpdf::OBJECT_IDENTIFIER . "type=indexentry,objattr=" . serialize($objattr) . Mpdf::OBJECT_IDENTIFIER;
			if ($this->mpdf->tableLevel) {
				$this->mpdf->cell[$this->mpdf->row][$this->mpdf->col]['textbuffer'][] = [$e];
			} // *TABLES*
			else { // *TABLES*
				$this->mpdf->textbuffer[] = [$e];
			} // *TABLES*
		}
	}

	public function close(&$ahtml, &$ihtml)
	{
	}
}
