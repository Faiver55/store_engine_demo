<?php
/**
 * @license GPL-2.0-only
 *
 * Modified by kodezen on 22-July-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace StoreEngine\Mpdf\Tag;

class TocPageBreak extends FormFeed
{
	public function open($attr, &$ahtml, &$ihtml)
	{
		list($isbreak, $toc_id) = $this->tableOfContents->openTagTOCPAGEBREAK($attr);
		$this->toc_id = $toc_id;
		if ($isbreak) {
			return;
		}
		parent::open($attr, $ahtml, $ihtml);
	}
}
