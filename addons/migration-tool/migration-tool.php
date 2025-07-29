<?php
namespace StoreEngine\Addons\MigrationTool;

use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MigrationTool extends AbstractAddon {
	use Singleton;

	protected string $addon_name = 'migration-tool';

	public function define_constants() {
		define( 'STOREENGINE_MIGRATION_TOOL_VERSION', '1.0' );
	}

	public function init_addon() {
		new Ajax();
	}
}
