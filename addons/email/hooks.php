<?php

namespace StoreEngine\Addons\Email;

use StoreEngine\Addons\Email\Admin\Settings;
use StoreEngine\Addons\Email\order\Confirm;
use StoreEngine\Addons\Email\order\Refund;
use StoreEngine\Addons\Email\order\Status;
use StoreEngine\Addons\Email\order\Note;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hooks {

	public function __construct() {
		new Settings();
		new Confirm();
		new Refund();
		new Status();
		new Note();
	}

}
