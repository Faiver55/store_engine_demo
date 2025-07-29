<?php

namespace StoreEngine\Classes;

class PriceCollection extends AbstractCollection {
	protected string $table = 'storeengine_product_price';

	protected string $object_type = 'price';

	protected string $primary_key = 'id';

	protected string $orderBy = 'order'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

	protected string $order      = 'DESC';
	protected string $returnType = Price::class; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
}
