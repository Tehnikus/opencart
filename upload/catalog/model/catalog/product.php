<?php
class ModelCatalogProduct extends Model {
	public function updateViewed($product_id) {
		$this->db->query("UPDATE " . DB_PREFIX . "product SET viewed = (viewed + 1) WHERE product_id = '" . (int)$product_id . "'");

		$this->db->query("
			INSERT INTO " . DB_PREFIX . "product_stats (product_id, store_id, viewed)
			VALUES ('" . (int) $product_id . "', '" . $this->config->get('config_store_id') . "', 1)
			ON DUPLICATE KEY UPDATE 
				viewed = (viewed + 1) 
		");
	}

	public function getProduct($product_id) : array|bool {

		$language_id = (int) $this->config->get('config_language_id');
		$store_id = (int) $this->config->get('config_store_id');
		$customer_group_id = (int) $this->config->get('config_customer_group_id');

		$sql = "
			SELECT
				
				p.`product_id`,
				p.`model`,
				p.`sku`,
				p.`upc`,
				p.`ean`,
				p.`jan`,
				p.`isbn`,
				p.`mpn`,
				p.`location`,
				p.`quantity`,
				p.`stock_status_id`,
				p.`manufacturer_id`,
				p.`shipping`,
				p.`points`,
				p.`tax_class_id`,
				p.`date_available`,
				p.`weight`,
				p.`weight_class_id`,
				p.`length`,
				p.`width`,
				p.`height`,
				p.`length_class_id`,
				p.`subtract`,
				p.`minimum`,
				p.`viewed`,
				p.`date_added`,
				
				p2s.`sort_order`,
				p2s.`parent_id`,
				p2s.`status`,				
				p2s.`date_modified`,

				COALESCE(p2s.`image`, p.`image`) AS image,
				COALESCE(NULLIF(p2s.price, 0), p.price) AS price,

				pd.`name`,
				pd.`meta_title`,
				pd.`meta_description`,
				pd.`meta_keyword`,
				pd.`tag`,
				pd.`description`,
				pd.`seo_keywords`,
				pd.`seo_description`,
				pd.`faq`,
				pd.`how_to`,
				pd.`footer`,
				pd.`date_modified` AS description_date_modified,

				pst.`viewed`,
				pst.`sales`,
				pst.`returns`,
				pst.`review_count` AS reviews, 
				pst.`rating_avg` AS rating,

				(
					SELECT 
						md.name 
					FROM " . DB_PREFIX . "manufacturer_description md 
					WHERE md.manufacturer_id = p.manufacturer_id 
						AND md.language_id 	= {$language_id}
						AND md.store_id 		= {$store_id}
				) AS manufacturer,

				(
					SELECT JSON_OBJECTAGG(
						pi.product_image_id, JSON_OBJECT(
							'image', 			pi.image,
							'sort_order', pi.sort_order
						)
					)
					FROM " . DB_PREFIX . "product_image pi
					WHERE pi.product_id = p2s.product_id
						AND pi.store_id   = p2s.store_id
				) AS images,

				(
					SELECT JSON_OBJECTAGG(
						ps.product_special_id, JSON_OBJECT(
							'product_id',         ps.`product_id`,
							'store_id',           ps.`store_id`,
							'customer_group_id',  ps.`customer_group_id`,
							'priority',           ps.`priority`,
							'price',              ps.`price`,
							'date_start',         ps.`date_start`,
							'date_end',           ps.`date_end`
						)
					) FROM " . DB_PREFIX . "product_special ps
					 WHERE ps.product_id = p2s.product_id
					 AND ps.store_id = p2s.store_id
				) AS product_specials,

				(
					SELECT JSON_OBJECTAGG(
						pd.product_discount_id, JSON_OBJECT(
							'product_id',          pd.`product_id`,
							'store_id',            pd.`store_id`,
							'customer_group_id',   pd.`customer_group_id`,
							'quantity',            pd.`quantity`,
							'priority',            pd.`priority`,
							'price',               pd.`price`,
							'date_start',          pd.`date_start`,
							'date_end',            pd.`date_end`
						)
					) FROM " . DB_PREFIX . "product_discount pd
					 WHERE pd.product_id = p2s.product_id
					 AND pd.store_id = p2s.store_id
				) AS product_discounts,

				(
					SELECT JSON_OBJECTAGG(
						po.product_option_id, JSON_OBJECT(

							'product_option_id', 		po.`product_option_id`,
							'product_id', 					po.`product_id`,
							'store_id', 						po.`store_id`,
							'option_id', 						po.`option_id`,
							'value', 								po.`value`,
							'required', 						po.`required`,
							'type', 								o.`type`,
							'sort_order', 					(SELECT o2s.sort_order FROM " . DB_PREFIX . "option_to_store o2s WHERE o2s.option_id = po.option_id AND o2s.store_id = po.store_id LIMIT 1),
							'language_id', 					od.`language_id`,
							'name', 								od.`name`,
						
							'values', (
								SELECT JSON_ARRAYAGG(
									JSON_OBJECT(
										'product_option_value_id',	pov.`product_option_value_id`,
										'product_option_id',				pov.`product_option_id`,
										'product_id',								pov.`product_id`,
										'store_id',									pov.`store_id`,
										'option_id',								pov.`option_id`,
										'option_value_id',					pov.`option_value_id`,
										'quantity',									pov.`quantity`,
										'subtract',									pov.`subtract`,
										'price',										pov.`price`,
										'price_prefix',							pov.`price_prefix`,
										'points',										pov.`points`,
										'points_prefix',						pov.`points_prefix`,
										'weight',										pov.`weight`,
										'weight_prefix',						pov.`weight_prefix`,
										'image',										ov.`image`,
										'sort_order',								ov.`sort_order`,
										'language_id',							ovd.`language_id`,
										'name',											ovd.`name`
									)
								)
								FROM " . DB_PREFIX . "product_option_value pov
								JOIN " . DB_PREFIX . "option_value ov
									ON ov.option_value_id 		= pov.option_value_id
									AND ov.store_id 					= p2s.store_id
								JOIN " . DB_PREFIX . "option_value_description ovd
									ON ovd.option_value_id 		= pov.option_value_id
									AND ovd.language_id 			= pd.language_id
									AND ovd.store_id 					= p2s.store_id
								WHERE pov.product_id 				= p2s.product_id
									AND pov.product_option_id = po.product_option_id
									AND pov.store_id 					= p2s.store_id
							)
						)
					)
					FROM " . DB_PREFIX . "product_option po
					JOIN " . DB_PREFIX . "option o
						ON o.option_id 			= po.option_id
					JOIN " . DB_PREFIX . "option_to_store o2s
						ON 	o2s.option_id 	= po.option_id
						AND o2s.store_id 		= p2s.store_id
					JOIN " . DB_PREFIX . "option_description od
						ON 	od.option_id 		= po.option_id
						AND od.language_id	= pd.language_id
						AND od.store_id 		= p2s.store_id
					WHERE po.product_id 	= p.product_id
						AND po.store_id 		= p2s.store_id
				) AS product_options,

				(
					SELECT 
						points 
					FROM " . DB_PREFIX . "product_reward pr 
					WHERE pr.product_id = p.product_id 
						AND pr.customer_group_id = {$customer_group_id}
						AND pr.store_id = p2s.store_id
				) AS reward, 

				(
					SELECT 
						ss.name 
					FROM " . DB_PREFIX . "stock_status ss 
					WHERE ss.stock_status_id = p.stock_status_id 
						AND ss.language_id = {$language_id}
				) AS stock_status, 
				(
					SELECT 
						wcd.unit 
					FROM " . DB_PREFIX . "weight_class_description wcd 
					WHERE p.weight_class_id = wcd.weight_class_id 
						AND wcd.language_id = {$language_id}
				) AS weight_class, 
				(
					SELECT 
						lcd.unit 
					FROM " . DB_PREFIX . "length_class_description lcd 
					WHERE p.length_class_id = lcd.length_class_id 
						AND lcd.language_id = {$language_id}
				) AS length_class

			FROM " . DB_PREFIX . "product_to_store p2s
			LEFT JOIN " . DB_PREFIX . "product_stats pst
				ON pst.product_id = p2s.product_id
				AND pst.store_id = p2s.store_id
			JOIN " . DB_PREFIX . "product p
				ON p.product_id = p2s.product_id
			JOIN " . DB_PREFIX . "product_description pd
				ON 	pd.product_id  	= p2s.product_id
				AND pd.language_id 	= {$language_id}
				AND pd.store_id 		= p2s.store_id
			WHERE p2s.product_id 	= '" . (int) $product_id . "'
				AND p2s.store_id 		= {$store_id}
				AND p2s.status 			= 1
			LIMIT 1
		";

		$product = $this->db->query($sql);

		return !empty($product->row) ? $product->row : false ;
		
	}

	public function getProducts($data = array()) {
		$sql = "SELECT p.product_id, (SELECT AVG(rating) AS total FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = p.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating, (SELECT price FROM " . DB_PREFIX . "product_discount pd2 WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND pd2.quantity = '1' AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())) ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount, (SELECT price FROM " . DB_PREFIX . "product_special ps WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special";

		if (!empty($data['filter_category_id'])) {
			if (!empty($data['filter_sub_category'])) {
				$sql .= " FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (cp.category_id = p2c.category_id)";
			} else {
				$sql .= " FROM " . DB_PREFIX . "product_to_category p2c";
			}

			if (!empty($data['filter_filter'])) {
				$sql .= " LEFT JOIN " . DB_PREFIX . "product_filter pf ON (p2c.product_id = pf.product_id) LEFT JOIN " . DB_PREFIX . "product p ON (pf.product_id = p.product_id)";
			} else {
				$sql .= " LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)";
			}
		} else {
			$sql .= " FROM " . DB_PREFIX . "product p";
		}

		$sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";

		if (!empty($data['filter_category_id'])) {
			if (!empty($data['filter_sub_category'])) {
				$sql .= " AND cp.path_id = '" . (int)$data['filter_category_id'] . "'";
			} else {
				$sql .= " AND p2c.category_id = '" . (int)$data['filter_category_id'] . "'";
			}

			if (!empty($data['filter_filter'])) {
				$implode = array();

				$filters = explode(',', $data['filter_filter']);

				foreach ($filters as $filter_id) {
					$implode[] = (int)$filter_id;
				}

				$sql .= " AND pf.filter_id IN (" . implode(',', $implode) . ")";
			}
		}

		if (!empty($data['filter_name']) || !empty($data['filter_tag'])) {
			$sql .= " AND (";

			if (!empty($data['filter_name'])) {
				$implode = array();

				$words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_name'])));

				foreach ($words as $word) {
					$implode[] = "pd.name LIKE '%" . $this->db->escape($word) . "%'";
				}

				if ($implode) {
					$sql .= " " . implode(" AND ", $implode) . "";
				}

				if (!empty($data['filter_description'])) {
					$sql .= " OR pd.description LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
				}
			}

			if (!empty($data['filter_name']) && !empty($data['filter_tag'])) {
				$sql .= " OR ";
			}

			if (!empty($data['filter_tag'])) {
				$implode = array();

				$words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_tag'])));

				foreach ($words as $word) {
					$implode[] = "pd.tag LIKE '%" . $this->db->escape($word) . "%'";
				}

				if ($implode) {
					$sql .= " " . implode(" AND ", $implode) . "";
				}
			}

			if (!empty($data['filter_name'])) {
				$sql .= " OR LCASE(p.model) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.sku) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.upc) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.ean) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.jan) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.isbn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.mpn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
			}

			$sql .= ")";
		}

		if (!empty($data['filter_manufacturer_id'])) {
			$sql .= " AND p.manufacturer_id = '" . (int)$data['filter_manufacturer_id'] . "'";
		}

		$sql .= " GROUP BY p.product_id";

		$sort_data = array(
			'pd.name',
			'p.model',
			'p.quantity',
			'p.price',
			'rating',
			'p.sort_order',
			'p.date_added'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			if ($data['sort'] == 'pd.name' || $data['sort'] == 'p.model') {
				$sql .= " ORDER BY LCASE(" . $data['sort'] . ")";
			} elseif ($data['sort'] == 'p.price') {
				$sql .= " ORDER BY (CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p.price END)";
			} else {
				$sql .= " ORDER BY " . $data['sort'];
			}
		} else {
			$sql .= " ORDER BY p.sort_order";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC, LCASE(pd.name) DESC";
		} else {
			$sql .= " ASC, LCASE(pd.name) ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$product_data = array();

		$query = $this->db->query($sql);

		foreach ($query->rows as $result) {
			$product_data[$result['product_id']] = $this->getProduct($result['product_id']);
		}

		return $product_data;
	}

	public function getProductSpecials($data = array()) {
		$sql = "SELECT DISTINCT ps.product_id, (SELECT AVG(rating) FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = ps.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating FROM " . DB_PREFIX . "product_special ps LEFT JOIN " . DB_PREFIX . "product p ON (ps.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) GROUP BY ps.product_id";

		$sort_data = array(
			'pd.name',
			'p.model',
			'ps.price',
			'rating',
			'p.sort_order'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			if ($data['sort'] == 'pd.name' || $data['sort'] == 'p.model') {
				$sql .= " ORDER BY LCASE(" . $data['sort'] . ")";
			} else {
				$sql .= " ORDER BY " . $data['sort'];
			}
		} else {
			$sql .= " ORDER BY p.sort_order";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC, LCASE(pd.name) DESC";
		} else {
			$sql .= " ASC, LCASE(pd.name) ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$product_data = array();

		$query = $this->db->query($sql);

		foreach ($query->rows as $result) {
			$product_data[$result['product_id']] = $this->getProduct($result['product_id']);
		}

		return $product_data;
	}

	public function getLatestProducts($limit) {
		$product_data = $this->cache->get('product.latest.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit);

		if (!$product_data) {
			$product_data = array();
			$query = $this->db->query("SELECT p.product_id FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' ORDER BY p.date_added DESC LIMIT " . (int)$limit);

			foreach ($query->rows as $result) {
				$product_data[$result['product_id']] = $this->getProduct($result['product_id']);
			}

			$this->cache->set('product.latest.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit, $product_data);
		}

		return $product_data;
	}

	public function getPopularProducts($limit) {
		$product_data = $this->cache->get('product.popular.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit);
	
		if (!$product_data) {
			$product_data = array();
			$query = $this->db->query("SELECT p.product_id FROM " . DB_PREFIX . "product p LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' ORDER BY p.viewed DESC, p.date_added DESC LIMIT " . (int)$limit);
	
			foreach ($query->rows as $result) {
				$product_data[$result['product_id']] = $this->getProduct($result['product_id']);
			}
			
			$this->cache->set('product.popular.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit, $product_data);
		}
		
		return $product_data;
	}

	public function getBestSellerProducts($limit) {
		$product_data = $this->cache->get('product.bestseller.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit);

		if (!$product_data) {
			$product_data = array();

			$query = $this->db->query("SELECT op.product_id, SUM(op.quantity) AS total FROM " . DB_PREFIX . "order_product op LEFT JOIN `" . DB_PREFIX . "order` o ON (op.order_id = o.order_id) LEFT JOIN `" . DB_PREFIX . "product` p ON (op.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE o.order_status_id > '0' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' GROUP BY op.product_id ORDER BY total DESC LIMIT " . (int)$limit);

			foreach ($query->rows as $result) {
				$product_data[$result['product_id']] = $this->getProduct($result['product_id']);
			}

			$this->cache->set('product.bestseller.' . (int)$this->config->get('config_language_id') . '.' . (int)$this->config->get('config_store_id') . '.' . $this->config->get('config_customer_group_id') . '.' . (int)$limit, $product_data);
		}

		return $product_data;
	}

	// TODO Rewrite in a single query
	// DONE Select columns explicitly
	public function getProductAttributes($product_id) {
		$product_attribute_group_data = array();

		$product_attribute_group_query = $this->db->query("
			SELECT 
				ag2s.`attribute_group_id`, 
				agd.`name` 
			FROM " . DB_PREFIX . "product_attribute pa 
			JOIN " . DB_PREFIX . "attribute a 
				ON pa.`attribute_id` = a.`attribute_id`
			JOIN " . DB_PREFIX . "attribute_to_store a2s 
				ON  a2s.`attribute_id` = pa.`attribute_id`
				AND a2s.`store_id` 		 = pa.`store_id`
			JOIN " . DB_PREFIX . "attribute_group ag 
				ON ag.`attribute_group_id` =  a2s.`attribute_group_id`
			JOIN " . DB_PREFIX . "attribute_group_to_store ag2s 
				ON ag2s.`attribute_group_id` 	=  a2s.`attribute_group_id`
				AND ag2s.`store_id` 					= pa.`store_id`
			JOIN " . DB_PREFIX . "attribute_group_description agd 
				ON agd.`attribute_group_id` = ag2s.`attribute_group_id` 
				AND agd.`language_id` 			= '" . (int) $this->config->get('config_language_id') . "'
				AND agd.`store_id` = pa.`store_id`
			WHERE pa.`product_id` = '" . (int) $product_id . "' 
				AND pa.`store_id` 	= '" . (int) $this->config->get('config_store_id') . "'
			GROUP BY ag.`attribute_group_id` 
			ORDER BY ag.`sort_order`, agd.`name`
		");

		foreach ($product_attribute_group_query->rows as $product_attribute_group) {
			$product_attribute_data = array();

			$product_attribute_query = $this->db->query("
				SELECT 
					a.`attribute_id`, 
					ad.`name`, 
					pa.`text` 
				FROM " . DB_PREFIX . "product_attribute pa 
				JOIN " . DB_PREFIX . "attribute a 
					ON a.`attribute_id` 			 = pa.`attribute_id`
					AND a.`attribute_group_id` = '" . (int) $product_attribute_group['attribute_group_id'] . "' 
				JOIN " . DB_PREFIX . "attribute_description ad 
					ON a.`attribute_id`  = ad.`attribute_id`
					AND ad.`language_id` = pa.`language_id`
					AND ad.`store_id` 	 = pa.`store_id`
				WHERE pa.`product_id` 	= '" . (int) $product_id . "' 
					AND pa.`language_id` 	= '" . (int) $this->config->get('config_language_id') . "' 
					AND pa.`store_id` 		= '" . (int) $this->config->get('config_store_id') . "' 
				ORDER BY a.`sort_order`, ad.`name`
			");

			foreach ($product_attribute_query->rows as $product_attribute) {
				$product_attribute_data[] = array(
					'attribute_id' => $product_attribute['attribute_id'],
					'name'         => $product_attribute['name'],
					'text'         => $product_attribute['text']
				);
			}

			$product_attribute_group_data[] = array(
				'attribute_group_id' => $product_attribute_group['attribute_group_id'],
				'name'               => $product_attribute_group['name'],
				'attribute'          => $product_attribute_data
			);
		}

		return $product_attribute_group_data;
	}

	// TODO Rewrite in a single query
	// DONE Select columns explicitly
	public function getProductOptions($product_id) {
		$product_option_data = [];

		$product_option_query = $this->db->query("
			SELECT 

				po.`product_option_id`,
				po.`product_id`,
				po.`store_id`,
				po.`option_id`,
				po.`value`,
				po.`required`,
				o.`type`,
				o2s.`sort_order`,
				od.`language_id`,
				od.`name`

			FROM `" . DB_PREFIX . "product_option` po 
			JOIN `" . DB_PREFIX . "option` o 
				ON po.`option_id` = o.`option_id`
			JOIN " . DB_PREFIX . "option_to_store o2s 
				ON o2s.`option_id` = po.`option_id`
				AND o2s.`store_id` = po.`store_id`
			JOIN " . DB_PREFIX . "option_description od 
				ON  od.`option_id` 	 = o2s.`option_id`
				AND od.`language_id` = '" . (int) $this->config->get('config_language_id') . "'
				AND od.`store_id`    = po.`store_id`
			WHERE po.`product_id` = '" . (int) $product_id . "' 
				AND po.`store_id` 	= '" . (int) $this->config->get('config_store_id') . "'
			ORDER BY o2s.`sort_order`
		");

		
		
		foreach ($product_option_query->rows as $product_option) {
			$product_option_value_data = [];
			
			$product_option_value_query = $this->db->query("
				SELECT 

					pov.`product_option_value_id`,
					pov.`product_option_id`,
					pov.`product_id`,
					pov.`store_id`,
					pov.`option_id`,
					pov.`option_value_id`,
					pov.`quantity`,
					pov.`subtract`,
					pov.`price`,
					pov.`price_prefix`,
					pov.`points`,
					pov.`points_prefix`,
					pov.`weight`,
					pov.`weight_prefix`,
					ov.`image`,
					ov.`sort_order`,
					ovd.`language_id`,
					ovd.`name`

				FROM " . DB_PREFIX . "product_option_value pov 
				JOIN " . DB_PREFIX . "option_value ov 
					ON  ov.`option_value_id` = pov.`option_value_id`
					AND ov.`store_id` 			 = pov.`store_id`
				JOIN " . DB_PREFIX . "option_value_description ovd 
					ON  ovd.`option_value_id` = ov.`option_value_id`
					AND ovd.`language_id` = '" . (int) $this->config->get('config_language_id') . "'
					AND ovd.`store_id` = pov.`store_id`
				WHERE pov.`product_id` = '" . (int) $product_id . "' 
					AND pov.`store_id` = '" . $this->config->get('config_store_id') . "'
					AND pov.`product_option_id` = '" . (int)$product_option['product_option_id'] . "' 
				ORDER BY ov.sort_order
			");
			
			
			foreach ($product_option_value_query->rows as $product_option_value) {
				$product_option_value_data[] = array(
					'product_option_value_id' => $product_option_value['product_option_value_id'],
					'option_value_id'         => $product_option_value['option_value_id'],
					'name'                    => $product_option_value['name'],
					'image'                   => $product_option_value['image'],
					'quantity'                => $product_option_value['quantity'],
					'subtract'                => $product_option_value['subtract'],
					'price'                   => $product_option_value['price'],
					'price_prefix'            => $product_option_value['price_prefix'],
					'weight'                  => $product_option_value['weight'],
					'weight_prefix'           => $product_option_value['weight_prefix']
				);
			}
			
			$product_option_data[] = array(
				'product_option_id'    => $product_option['product_option_id'],
				'product_option_value' => $product_option_value_data,
				'option_id'            => $product_option['option_id'],
				'name'                 => $product_option['name'],
				'type'                 => $product_option['type'],
				'value'                => $product_option['value'],
				'required'             => $product_option['required']
			);
		}

		return $product_option_data;
	}

	// Product bulk discounts
	// TODO Should be also displayed in product list?
	public function getProductDiscounts($product_id) {
		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "product_discount 
			WHERE product_id = '" . (int) $product_id . "' 
				AND customer_group_id = '" . (int) $this->config->get('config_customer_group_id') . "' 
				AND quantity > 1 
				AND ((date_start = '0000-00-00' OR date_start < NOW()) AND (date_end = '0000-00-00' OR date_end > NOW())) 
				AND store_id = '" . (int) $this->config->get('config_store_id') . "'
			ORDER BY quantity ASC, priority ASC, price ASC
		");

		return $query->rows;
	}

	// Additional product images
	public function getProductImages($product_id) {
		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "product_image 
			WHERE product_id = '" . (int) $product_id . "' 
				AND store_id   = '" . (int) $this->config->get('config_store_id') . "'
			ORDER BY sort_order ASC
		");

		return $query->rows;
	}

	// Related products list in the bottom of product page
	public function getProductRelated($product_id) {
		$product_data = array();

		// Get product related ids
		$query = $this->db->query("
			SELECT 
				pr.related_id 
			FROM " . DB_PREFIX . "product_related pr 
			JOIN " . DB_PREFIX . "product p 
				ON p.product_id = pr.related_id
				AND p.status 		= 1
			JOIN " . DB_PREFIX . "product_to_store p2s 
				ON p2s.product_id = pr.product_id
				AND p2s.store_id  = pr.store_id
				AND p2s.status 		= 1
			WHERE pr.product_id = '" . (int) $product_id . "' 
				AND pr.store_id 	= '" . (int) $this->config->get('config_store_id') . "'
		");

		// Get products data
		foreach ($query->rows as $result) {
			$product_data[$result['related_id']] = $this->getProduct($result['related_id']);
		}

		return $product_data;
	}

	public function getProductLayoutId($product_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_layout WHERE product_id = '" . (int)$product_id . "' AND store_id = '" . (int)$this->config->get('config_store_id') . "'");

		if ($query->num_rows) {
			return (int)$query->row['layout_id'];
		} else {
			return 0;
		}
	}

	// Only used in google_base.php
	public function getCategories($product_id) {
		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "product_to_category 
			WHERE product_id = '" . (int) $product_id . "'
				AND store_id = '" . (int) $this->config->get('config_store_id') . "'
		");

		return $query->rows;
	}

	public function getTotalProducts($data = array()) {
		$sql = "SELECT COUNT(DISTINCT p.product_id) AS total";

		if (!empty($data['filter_category_id'])) {
			if (!empty($data['filter_sub_category'])) {
				$sql .= " FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "product_to_category p2c ON (cp.category_id = p2c.category_id)";
			} else {
				$sql .= " FROM " . DB_PREFIX . "product_to_category p2c";
			}

			if (!empty($data['filter_filter'])) {
				$sql .= " LEFT JOIN " . DB_PREFIX . "product_filter pf ON (p2c.product_id = pf.product_id) LEFT JOIN " . DB_PREFIX . "product p ON (pf.product_id = p.product_id)";
			} else {
				$sql .= " LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)";
			}
		} else {
			$sql .= " FROM " . DB_PREFIX . "product p";
		}

		$sql .= " LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";

		if (!empty($data['filter_category_id'])) {
			if (!empty($data['filter_sub_category'])) {
				$sql .= " AND cp.path_id = '" . (int)$data['filter_category_id'] . "'";
			} else {
				$sql .= " AND p2c.category_id = '" . (int)$data['filter_category_id'] . "'";
			}

			if (!empty($data['filter_filter'])) {
				$implode = array();

				$filters = explode(',', $data['filter_filter']);

				foreach ($filters as $filter_id) {
					$implode[] = (int)$filter_id;
				}

				$sql .= " AND pf.filter_id IN (" . implode(',', $implode) . ")";
			}
		}

		if (!empty($data['filter_name']) || !empty($data['filter_tag'])) {
			$sql .= " AND (";

			if (!empty($data['filter_name'])) {
				$implode = array();

				$words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_name'])));

				foreach ($words as $word) {
					$implode[] = "pd.name LIKE '%" . $this->db->escape($word) . "%'";
				}

				if ($implode) {
					$sql .= " " . implode(" AND ", $implode) . "";
				}

				if (!empty($data['filter_description'])) {
					$sql .= " OR pd.description LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
				}
			}

			if (!empty($data['filter_name']) && !empty($data['filter_tag'])) {
				$sql .= " OR ";
			}

			if (!empty($data['filter_tag'])) {
				$implode = array();

				$words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_tag'])));

				foreach ($words as $word) {
					$implode[] = "pd.tag LIKE '%" . $this->db->escape($word) . "%'";
				}

				if ($implode) {
					$sql .= " " . implode(" AND ", $implode) . "";
				}
			}

			if (!empty($data['filter_name'])) {
				$sql .= " OR LCASE(p.model) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.sku) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.upc) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.ean) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.jan) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.isbn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
				$sql .= " OR LCASE(p.mpn) = '" . $this->db->escape(utf8_strtolower($data['filter_name'])) . "'";
			}

			$sql .= ")";
		}

		if (!empty($data['filter_manufacturer_id'])) {
			$sql .= " AND p.manufacturer_id = '" . (int)$data['filter_manufacturer_id'] . "'";
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}

	public function getProfile($product_id, $recurring_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "recurring r JOIN " . DB_PREFIX . "product_recurring pr ON (pr.recurring_id = r.recurring_id AND pr.product_id = '" . (int)$product_id . "') WHERE pr.recurring_id = '" . (int)$recurring_id . "' AND status = '1' AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'");

		return $query->row;
	}

	public function getProfiles($product_id) {
		$query = $this->db->query("SELECT rd.* FROM " . DB_PREFIX . "product_recurring pr JOIN " . DB_PREFIX . "recurring_description rd ON (rd.language_id = " . (int)$this->config->get('config_language_id') . " AND rd.recurring_id = pr.recurring_id) JOIN " . DB_PREFIX . "recurring r ON r.recurring_id = rd.recurring_id WHERE pr.product_id = " . (int)$product_id . " AND status = '1' AND pr.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' ORDER BY sort_order ASC");

		return $query->rows;
	}

	public function getTotalProductSpecials() {
		$query = $this->db->query("SELECT COUNT(DISTINCT ps.product_id) AS total FROM " . DB_PREFIX . "product_special ps LEFT JOIN " . DB_PREFIX . "product p ON (ps.product_id = p.product_id) LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) WHERE p.status = '1' AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()))");

		if (isset($query->row['total'])) {
			return $query->row['total'];
		} else {
			return 0;
		}
	}

	// Some strange check that product is associated to category in case if SEO URLs is turned off?
	public function checkProductCategory($product_id, $category_ids) {
		
		$implode = array();

		foreach ($category_ids as $category_id) {
			$implode[] = (int)$category_id;
		}
		
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "' AND category_id IN(" . implode(',', $implode) . ")");
  	    return $query->row;
	}
}
