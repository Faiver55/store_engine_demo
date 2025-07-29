<?php

namespace StoreEngine\Addons\Invoice\PDF;

use StoreEngine\Addons\Invoice\HelperAddon;
use StoreEngine\Mpdf\Output\Destination;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Mpdf\HTMLParserMode;
use StoreEngine\Mpdf\Config\ConfigVariables;
use StoreEngine\Mpdf\Config\FontVariables;
use StoreEngine\Mpdf\Mpdf;
use StoreEngine\Mpdf\MpdfException;
use StoreEngine\Utils\Helper;
use WP_Error;

class Generator {

	public Mpdf $mpdf;
	protected string $template;
	protected string $styles;
	protected string $page_size;
	protected string $page_orientation;

	public function __construct( string $template, string $styles, array $args = [] ) {
		$ars = wp_parse_args( $args, [
			'page_size'        => HelperAddon::get_setting( 'invoice_paper_size', 'A4' ),
			'page_orientation' => 'P',
		] );

		$this->template         = $template;
		$this->styles           = $styles;
		$this->page_size        = $ars['page_size'];
		$this->page_orientation = $ars['page_orientation'];
	}

	/**
	 * @throws StoreEngineException
	 */
	public function init_mpdf() {
		if ( isset( $this->mpdf ) ) {
			return;
		}

//		$font_dirs   = ( new ConfigVariables() )->getDefaults()['fontDir'];
		$font_dirs = [ HelperAddon::get_fonts_dir() . '/ttfonts' ];

		$default_font_config = ( new FontVariables() )->getDefaults();
		$fontdata            = $default_font_config['fontdata'];

		try {
			$mpdf = new Mpdf(
				[
					'mode'             => 'utf-8',
					'tempDir'          => Helper::get_upload_dir() . '/invoice',
					'fontDir'          => $font_dirs,
					'format'           => $this->page_size,
					'orientation'      => $this->page_orientation,
					'margin_left'      => 0,
					'margin_right'     => 0,
					'margin_top'       => 0,
					'margin_bottom'    => 0,
					'default_font'     => 'dejavusans',
					'autoScriptToLang' => true,
					'autoLangToFont'   => true,
					'fontdata'         => $fontdata,
				]
			);
		} catch ( MpdfException $e ) {
			throw new StoreEngineException( 'Failed to initialize mpdf', 'failed_mpdf_init' );
		}

		$this->mpdf = $mpdf;
		$this->mpdf->setMBencoding( 'UTF-8' );
	}

	public function prepare_pdf() {
		try {
			$this->init_mpdf();
		} catch ( StoreEngineException $e ) {
			return $e->toWpError();
		}

		$template = $this->template;

		try {
			$this->mpdf->WriteHTML( $this->styles, HTMLParserMode::HEADER_CSS );
			$this->mpdf->WriteHTML( $this->custom_default_css(), HTMLParserMode::HEADER_CSS );
			$this->mpdf->WriteHTML( $template );
		} catch ( MpdfException $e ) {
			return new WP_Error( 'failed_mpdf_write', 'Invalid HTML!' );
		}
	}

	public function custom_default_css() {
		$file_path = STOREENGINE_INVOICE_DIR_PATH . '/assets/css/gutenberg-styles.css';
		if ( file_exists( $file_path ) && is_readable( $file_path ) ) {
			$css = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		} else {
			$css = esc_html__( "The File doesn't Exist", 'academy' );
		}

		return $css;
	}

	public function preview( $title, bool $download = false ) {
		$result = $this->prepare_pdf();
		if ( is_wp_error( $result ) ) {
			echo esc_html( $result->get_error_message() );
			exit;
		}
		$file_name = sanitize_file_name( wp_strip_all_tags( $title ) );
		try {
			$this->mpdf->Output( $file_name . '.pdf', $download ? Destination::DOWNLOAD : Destination::INLINE );
		} catch ( MpdfException $e ) {
			echo esc_html( $e->getMessage() );
		}
		exit;
	}

	/**
	 * @param string $filepath
	 *
	 * @return true|WP_Error
	 */
	public function save( string $filepath ) {
		$result = $this->prepare_pdf();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		try {
			$this->mpdf->Output( $filepath, Destination::FILE );
		} catch ( MpdfException $e ) {
			return new WP_Error( 'failed_mpdf_write', $e->getMessage() );
		}

		return true;
	}

}
