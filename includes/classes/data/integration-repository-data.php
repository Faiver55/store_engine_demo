<?php

namespace StoreEngine\Classes\Data;

use StoreEngine\Classes\Integration;
use StoreEngine\Classes\Price;

class IntegrationRepositoryData {

	public Price $price;
	public Integration $integration;

	public function __construct( Integration $integration, Price $price ) {
		$this->integration = $integration;
		$this->price       = $price;
	}

}
