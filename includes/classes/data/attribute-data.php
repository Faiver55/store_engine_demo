<?php

namespace StoreEngine\Classes\Data;

class AttributeData {

	public int $term_id;
	public int $term_taxonomy_id;
	public string $name;
	public string $slug;
	public string $description;
	public string $taxonomy;
	public int $count;
	public int $term_order;

	public function set_data( object $data ): self {
		$this->term_id          = (int) $data->term_id;
		$this->term_taxonomy_id = $data->term_taxonomy_id;
		$this->name             = $data->name;
		$this->slug             = $data->slug;
		$this->description      = $data->description;
		$this->taxonomy         = $data->taxonomy;
		$this->count            = (int) $data->count;
		$this->term_order       = (int) $data->term_order;

		return $this;
	}

}
