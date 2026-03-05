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

	private function getValidDiscount(array $rows, int $customerGroupId): ?array {
    $now = time();

    $valid = array_filter($rows, function ($r) use ($customerGroupId, $now) : bool {

			// Remove all rows that don't match customer_group_id 
			if ((int) $r['customer_group_id'] !== $customerGroupId) {
				return false;
			}

			// Normalize null dates from non strict SQL to null
			$start 	= (!$r['date_start'] || str_starts_with($r['date_start'],	'0000-00-00')) ? null : strtotime($r['date_start']);
			$end 	= (!$r['date_end'] || str_starts_with($r['date_end'],	'0000-00-00')) ? null : strtotime($r['date_end']);;
			
			// Remove all rows where discount starts later then now
			if ($start && $start > $now) {return false;}
			// remove all rows where discount ends earlier then now 
			if ($end && $end < $now) {return false;}

			return true;
    });

		// Order rows first by priority then by price
    usort($valid, fn($a,$b) =>
			[$a['priority'], $a['price']] <=> [$b['priority'], $b['price']]
    );

		// Return first valid row
    return $valid[0] ?? null;
	}

	public function getProduct($product_id) : array|bool {
		$product_id 				= (int) $product_id;
		$language_id 				= (int) $this->config->get('config_language_id');
		$store_id 					= (int) $this->config->get('config_store_id');
		$customer_group_id 	= (int) $this->config->get('config_customer_group_id');
		
		// Cache
		$cacheName 	= "product.store_{$store_id}.language_{$language_id}." . (floor($product_id / 100)) . "00.product_{$product_id}";
		$product 		= $this->cache->get($cacheName);
		
		if ($product) {
			// Filter specials and discounts
			$now = date('Y-m-d H:i:s');
			$product['specials'] = array_filter($product['specials'], function ($var) use ($now) {
				return 
					strtotime($var['date_start']) <= strtotime($now)
					&& (
						strtotime($var['date_end']) >= strtotime($now) 
						|| $var['date_end'] === null
						|| str_contains($var['date_end'], '0000-00-00')
					)
				;
			});
			$product['discounts'] = array_filter($product['discounts'], function ($var) use ($now) {
				return 
					strtotime($var['date_start']) <= strtotime($now)
					&& (
						strtotime($var['date_end']) >= strtotime($now) 
						|| $var['date_end'] === null
						|| str_contains($var['date_end'], '0000-00-00')
					)
				;
			});

			return $product;
		}

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
				COALESCE(NULLIF(p2s.`price`, 0), p.`price`) AS price,

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
							'image', 			pi.`image`,
							'sort_order', pi.`sort_order`
						)
					)
					FROM " . DB_PREFIX . "product_image pi
					WHERE pi.`product_id` = p2s.`product_id`
						AND pi.`store_id`   = p2s.`store_id`
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
					 WHERE ps.`product_id` = p2s.`product_id`
					 AND ps.`store_id` 		 = p2s.`store_id`
				) AS specials,

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
					 WHERE pd.`product_id` = p2s.`product_id`
					 AND pd.`store_id` 		 = p2s.`store_id`
				) AS discounts,

				(
					SELECT JSON_ARRAYAGG(
						JSON_OBJECT(
							'attribute_group_id', t.`attribute_group_id`,
							'name', 							t.`group_name`,
							'attribute', 					t.`attributes_json`
						)
					)
					FROM (
						SELECT
							pa.attribute_group_id,
							agd.`name` AS `group_name`,
				
							JSON_ARRAYAGG(
								JSON_OBJECT(
									'name', 				ad.`name`,
									'attribute_id', pa.`attribute_id`,
									'text', 				pa.`text`,
									'sort_order', 	a2s.`sort_order`
								)
							) AS `attributes_json`
				
						FROM " . DB_PREFIX . "product_attribute pa
				
						LEFT JOIN " . DB_PREFIX . "attribute_description ad
							ON ad.`attribute_id` = pa.`attribute_id`
							AND ad.`language_id` = pa.`language_id`
							AND ad.`store_id` = pa.`store_id`
				
						LEFT JOIN " . DB_PREFIX . "attribute_group_description agd
							ON agd.`attribute_group_id` = pa.`attribute_group_id`
							AND agd.`language_id` = pa.`language_id`
							AND agd.`store_id` = pa.`store_id`
				
						LEFT JOIN " . DB_PREFIX . "attribute_to_store a2s
							ON a2s.`attribute_id` = pa.`attribute_id`
							AND a2s.`store_id` = pa.`store_id`
				
						WHERE pa.product_id = p2s.product_id
							AND pa.`language_id` = pd.`language_id`
							AND pa.`store_id` = p2s.`store_id`
				
						GROUP BY pa.`attribute_group_id`
					) t
				) AS attributes,

				(
					SELECT JSON_OBJECTAGG(
						po.product_option_id, JSON_OBJECT(

							'product_option_id', 		po.`product_option_id`,
							'option_id', 						po.`option_id`,
							'value', 								po.`value`,
							'required', 						po.`required`,
							'type', 								o.`type`,
							'sort_order', 					(SELECT o2s.`sort_order` FROM " . DB_PREFIX . "option_to_store o2s WHERE o2s.`option_id` = po.`option_id` AND o2s.`store_id` = po.`store_id` LIMIT 1),
							'name', 								od.`name`,
						
							'product_option_value', (
								SELECT JSON_ARRAYAGG(
									JSON_OBJECT(
										'product_option_value_id',	pov.`product_option_value_id`,
										'product_option_id',				pov.`product_option_id`,
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
										'image',										(SELECT ov.`image` FROM " . DB_PREFIX . "option_value ov WHERE ov.`option_value_id` = pov.`option_value_id` AND ov.`store_id` = p2s.`store_id`),
										'sort_order',								(SELECT ov.`sort_order` FROM " . DB_PREFIX . "option_value ov WHERE ov.`option_value_id` = pov.`option_value_id` AND ov.`store_id` = p2s.`store_id`),
										'name',											(SELECT ovd.`name` FROM " . DB_PREFIX . "option_value_description ovd WHERE ovd.`option_value_id` = pov.`option_value_id` AND ovd.`language_id` = pd.`language_id` AND ovd.`store_id` = p2s.`store_id`)
									)
								)
								FROM " . DB_PREFIX . "product_option_value pov
								WHERE pov.product_id 				= po.product_id
									AND pov.product_option_id = po.product_option_id
									AND pov.store_id 					= po.store_id
							)
						)
					)
					FROM " . DB_PREFIX . "product_option po
					JOIN `" . DB_PREFIX . "option` o
						ON o.`option_id` 			= po.`option_id`
					JOIN " . DB_PREFIX . "option_to_store o2s
						ON 	o2s.`option_id` 	= po.`option_id`
						AND o2s.store_id 		= p2s.store_id
					JOIN " . DB_PREFIX . "option_description od
						ON 	od.`option_id` 		= po.`option_id`
						AND od.`language_id`	= pd.`language_id`
						AND od.`store_id` 		= p2s.`store_id`
					WHERE po.`product_id` 	= p.`product_id`
						AND po.`store_id` 		= p2s.`store_id`
				) AS options,

				(
					SELECT JSON_OBJECTAGG(
						pr.customer_group_id, JSON_OBJECT(
							'product_reward_id', 	pr.`product_reward_id`,
							'customer_group_id', 	pr.`customer_group_id`,
							'points',            	pr.`points`
						)
					)
						FROM " . DB_PREFIX . "product_reward pr
						WHERE pr.`product_id` = p2s.`product_id`
							AND pr.`store_id` 	= p2s.`store_id`
				) AS rewards, 

				(
					SELECT 
						ss.name 
					FROM " . DB_PREFIX . "stock_status ss 
					WHERE ss.`stock_status_id` = p.`stock_status_id` 
						AND ss.`language_id` = {$language_id}
				) AS stock_status, 

				(
					SELECT 
						wcd.`unit` 
					FROM " . DB_PREFIX . "weight_class_description wcd 
					WHERE p.`weight_class_id` = wcd.`weight_class_id` 
						AND wcd.`language_id` = {$language_id}
				) AS weight_class, 

				(
					SELECT 
						lcd.`unit` 
					FROM " . DB_PREFIX . "length_class_description lcd 
					WHERE p.`length_class_id` = lcd.`length_class_id` 
						AND lcd.`language_id` = {$language_id}
				) AS length_class

			FROM " . DB_PREFIX . "product_to_store p2s
			LEFT JOIN " . DB_PREFIX . "product_stats pst
				ON pst.`product_id` = p2s.`product_id`
				AND pst.`store_id` = p2s.`store_id`
			JOIN " . DB_PREFIX . "product p
				ON p.`product_id` = p2s.`product_id`
			JOIN " . DB_PREFIX . "product_description pd
				ON 	pd.`product_id`  	= p2s.`product_id`
				AND pd.`language_id` 	= {$language_id}
				AND pd.`store_id` 		= p2s.`store_id`
			WHERE p2s.`product_id` 	= '" . (int) $product_id . "'
				AND p2s.`store_id` 		= {$store_id}
				AND p2s.`status` 			= 1
			LIMIT 1
		";

		$product = $this->db->query($sql)->row;

		if (empty($product)) {
			return false;
		}

		// Decode data
		$product['images'] 							= json_decode($product['images'] 			?? '[]', true);
		$product['specials'] 						= json_decode($product['specials'] 		?? '[]', true);
		$product['discounts'] 					= json_decode($product['discounts'] 	?? '[]', true);
		$product['options'] 						= json_decode($product['options'] 		?? '[]', true);
		$product['attributes'] 					= json_decode($product['attributes'] 	?? '[]', true);
		$product['reward'] 							= json_decode($product['rewards'] 		?? '[]', true)[$customer_group_id] ?? null;
		// Get valid discount float prices and dates in YYYY-MM-DD format
		$product['discount'] 						= $this->getValidDiscount($product['discounts'], $customer_group_id)['price'] 		?? null;
		$product['special'] 						= $this->getValidDiscount($product['specials'],  $customer_group_id)['price'] 		?? null;
		$product['discount_date_end'] 	= $this->getValidDiscount($product['discounts'], $customer_group_id)['date_end'] ?? null;
		$product['special_date_end'] 		= $this->getValidDiscount($product['specials'],  $customer_group_id)['date_end'] ?? null;
		// Sort data
		usort(array: $product['images'], 		callback: fn ($a, $b) =>  $a['sort_order'] <=> $b['sort_order']);
		usort(array: $product['options'], 		callback: fn ($a, $b) =>  $a['sort_order'] <=> $b['sort_order']);
		usort(array: $product['attributes'], callback: fn ($a, $b) =>  $a['sort_order'] <=> $b['sort_order']);
		usort(array: $product['specials'], 	callback: fn ($a, $b) =>  $a['priority'] 	<=> $b['priority']);
		array_multisort(
			$product['discounts'],
			array_column($product['discounts'], 'quantity'),  SORT_ASC,
			array_column($product['discounts'], 'priority'),  SORT_ASC,
			array_column($product['discounts'], 'price'),  SORT_ASC,
		);

		$this->cache->set($cacheName, $product);

		// Filter specials and discounts
		$now = date('Y-m-d H:i:s');
		$product['specials'] = array_filter($product['specials'], function ($var) use ($now) {
			return 
				strtotime($var['date_start']) <= strtotime($now)
				&& (
					strtotime($var['date_end']) >= strtotime($now) 
					|| $var['date_end'] === null
					|| str_contains($var['date_end'], '0000-00-00')
				)
			;
		});
		$product['discounts'] = array_filter($product['discounts'], function ($var) use ($now) {
			return 
				strtotime($var['date_start']) <= strtotime($now)
				&& (
					strtotime($var['date_end']) >= strtotime($now) 
					|| $var['date_end'] === null
					|| str_contains($var['date_end'], '0000-00-00')
				)
			;
		});
		return $product;
	}

	public function getProducts($data = []) : array {
		$products = [];
		$where 		= [];
		$join 		= [];
		$select		= [];
		$order 		= [];

		// Set mandatory WHEREs
		// Connect to external query
		$where[] = "p2s.`product_id` = p2s2.`product_id`";
		// Only available products
		$where[] = "p2s.`status` = 1";
		// Only products from current store
		$where[] = "p2s.`store_id` = '" . (int) $this->config->get('config_store_id') . "'";

		$sort_data = array(
			'sort_order'		=> 'p2s2.`sort_order` ASC',
			'name'					=> 'pd.`name` ASC',
			'sales'					=> 'pst.`sales` DESC',
			'rating'				=> 'pst.`rating` DESC',
			'views'					=> 'pst.`views` DESC',
			'date_added'		=> 'p2s2.`date_added` DESC',
			'available'			=> 'p2s2.`available` DESC',
			'quantity'			=> 'p.`quantity` > p.`minimum` DESC, p2s2.`sort_order` ASC',
			'price_asc'			=> '(CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p2s2.`price` END) ASC',
			'price_desc'		=> '(CASE WHEN special IS NOT NULL THEN special WHEN discount IS NOT NULL THEN discount ELSE p2s2.`price` END) DESC',
			'discounts'			=> '',
			'trends'				=> 'trends DESC',
		);

		// Sort
		if (isset($data['sort']) && in_array($data['sort'], array_keys($sort_data))) {
		
			// Sort by trends
			if ($data['sort'] === 'trends') {
				// Sort subquery to get column needed for sorting
				$select[] = "
					(
						SELECT
							(
								LOG(pst.sales + 1) * 4
								+ COALESCE(pst.rating_avg, 0) * LOG(pst.review_count + 1) * 2
								+ LOG(pst.viewed + 1)
							)
						FROM " . DB_PREFIX . "product_stats pst
						WHERE pst.product_id = p2s2.product_id
							AND pst.store_id = p2s2.store_id
					) AS trends
				";
			}

			// Sort by price
			if ($data['sort'] === 'price_asc' || $data['sort'] === 'price_desc' || $data['sort'] === 'discounts') {
				$select[] = "
					(
						SELECT 
							price 
						FROM " . DB_PREFIX . "product_discount pd2 
						WHERE pd2.product_id = p.product_id 
							AND pd2.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' 
							AND pd2.quantity = '1' 
							AND (
								(pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) 
								AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW())
							) 
						ORDER BY pd2.priority ASC, pd2.price ASC 
						LIMIT 1
					) AS discount
				";
				$select[] = "
					(
						SELECT 
							price 
						FROM " . DB_PREFIX . "product_special ps 
						WHERE ps.product_id = p.product_id 
							AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' 
							AND (
								(ps.date_start = '0000-00-00' OR ps.date_start < NOW()) 
								AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())
							) 
						ORDER BY ps.priority ASC, ps.price ASC 
						LIMIT 1
					) AS special
				";
			}
		}

		// Start filters
		// Conditions
		// Category
		if (!empty($data['filter_category_id'])) {
			$where[] = "
				p2c.`category_id` = " . (int) $data['filter_category_id'] . "
			";

			$join[] = "
				JOIN " . DB_PREFIX . "product_to_category p2c
					ON p2c.`product_id` = p2s.`product_id`
					AND p2c.`store_id` = p2s.`store_id`
			";
		}

		if (!empty($data['filter_sub_category'])) {
			$where[] = "
				cp.`path_id` = '" . (int) $data['filter_category_id'] . "'
			";
			$join[] = "
				JOIN " . DB_PREFIX . "product_to_category p2c
					ON p2c.`product_id` = p2s.`product_id`
					AND p2c.`store_id` 	= p2s.`store_id`
				JOIN " . DB_PREFIX . "category_path cp
					ON cp.`category_id` = p2c.`category_id`
					AND pc.`store_id` 	= p2s.`store_id`
			";
		}

		// Facet filter
		if (!empty($data['filter_filter'])) {
			// Get filter ids by filter groups
			// Logic: (
			// 	(filter_group_1 => [ filter_1 OR filter_2 OR filter_3 ]) 
			// 		AND 
			// 	(filter_group_2 => [ filter_4 OR filter_5 OR ... ])
			// 		AND 
			// 	(filter_group_3 => [ ... ])
			// )

			$filters_by_group = [];
			
			// Sanitize and unique
			$filter_ids = array_values(
				array_unique(
					array_map(
						'intval', 
						explode(',', $data['filter_filter'])
					)
				)
			);

			// Get filter groups
			$sql = "
				SELECT 
					`filter_id`, 
					`filter_group_id`
				FROM " . DB_PREFIX . "product_filter
				WHERE `store_id` = '" . (int) $this->config->get('config_store_id') . "'
					AND `filter_id` IN (" . implode(',', $filter_ids) .")
			";

			$filter_groups = $this->db->query($sql)->rows;

			// Group filter ids by filter group
			foreach ($filter_groups as $filter_group) {
				$filter_group_id	= (int) $filter_group['filter_group_id'];
				$filter_id 				= (int) $filter_group['filter_id'];

				$filters_by_group[$filter_group_id][] = $filter_id;
			}

			// Build EXISTS string
			foreach ($filters_by_group as $groupId => $filterIds) {

				$ids = implode(',', array_unique($filterIds));

				// Put EXISTS string to WHERE clause
				$where[] = "
					EXISTS (
						SELECT 1
						FROM " . DB_PREFIX . "product_filter pf
						WHERE pf.product_id = p2s.product_id
							AND pf.filter_group_id = {$groupId}
							AND pf.filter_id IN ({$ids})
							AND pf.store_id = '" . (int) $this->config->get('config_store_id') . "'
					)
				";
			}
		}

		// Options filter
		// Same as facet filter
		if (!empty($data['filter_option'])) {

			$options_by_group = [];
			
			// Sanitize and unique
			$option_ids = array_values(
				array_unique(
					array_map(
						'intval', 
						explode(',', $data['filter_option'])
					)
				)
			);

			// Get option groups
			$sql = "
				SELECT 
					`option_value_id`, 
					`option_id`
				FROM " . DB_PREFIX . "product_option_value
				WHERE `store_id` = '" . (int) $this->config->get('config_store_id') . "'
					AND `option_value_id` IN (" . implode(',', $option_ids) .")
			";

			$option_groups = $this->db->query($sql)->rows;

			// Group option ids by option group
			foreach ($option_groups as $option_group) {
				$option_group_id	= (int) $option_group['option_id'];
				$option_id 				= (int) $option_group['option_value_id'];

				$options_by_group[$option_group_id][] = $option_id;
			}

			// Build EXISTS string
			foreach ($options_by_group as $groupId => $optionIds) {

				$ids = implode(',', array_unique($optionIds));

				// Put EXISTS string to WHERE clause
				$where[] = "
					EXISTS (
						SELECT 1
						FROM " . DB_PREFIX . "product_option_value po
						WHERE po.`product_id` = p2s.`product_id`
							AND po.`option_id` = {$groupId}
							AND po.`option_value_id` IN ({$ids})
							AND po.`store_id` = '" . (int) $this->config->get('config_store_id') . "'
					)
				";
			}
		}

		// Attribute filter
		// Same as facet filter
		if (!empty($data['filter_attribute'])) {

			$attributes_by_group = [];
			
			// Sanitize and unique
			$attribute_ids = array_values(
				array_unique(
					array_map(
						'intval', 
						explode(',', $data['filter_attribute'])
					)
				)
			);

			// Get attribute groups
			$sql = "
				SELECT 
					`attribute_id`, 
					`attribute_group_id`
				FROM " . DB_PREFIX . "product_attribute
				WHERE `store_id` = '" . (int) $this->config->get('config_store_id') . "'
					AND `attribute_id` IN (" . implode(',', $attribute_ids) .")
			";

			$attribute_groups = $this->db->query($sql)->rows;

			// Group attribute ids by attribute group
			foreach ($attribute_groups as $attribute_group) {
				$attribute_group_id	= (int) $attribute_group['attribute_group_id'];
				$attribute_id 				= (int) $attribute_group['attribute_id'];

				$attributes_by_group[$attribute_group_id][] = $attribute_id;
			}

			// Build EXISTS string
			foreach ($attributes_by_group as $groupId => $attributeIds) {

				$ids = implode(',', array_unique($attributeIds));

				// Put EXISTS string to WHERE clause
				$where[] = "
					EXISTS (
						SELECT 1
						FROM " . DB_PREFIX . "product_attribute pa
						WHERE pa.`product_id` = p2s.`product_id`
							AND pa.`attribute_group_id` = {$groupId}
							AND pa.`attribute_id` IN ({$ids})
							AND pa.`store_id` = '" . (int) $this->config->get('config_store_id') . "'
					)
				";
			}
		}

		// Manufacturers
		if (!empty($data['filter_manufacturer_id'])) {
			$where[] = "
				p.`manufacturer_id` IN(" . $data['filter_manufacturer_id'] . ")
			";
		}

		// Search by name/description/model
		if (isset($data['filter_name'])) {
			$words 		= [];
			$implode 	= [];
			$orCondition = [];
			$words = explode(' ', trim(preg_replace('/\s+/', ' ', $data['filter_name'])));
			foreach ($words as $word) {
				$implode['name'][]  = "pd.`name` LIKE '%" . $this->db->escape($word) . "%'";
				$implode['model'][] = "p.`model` LIKE '%" . $this->db->escape($word) . "%'";
				if (!empty($data['filter_description'])) {
					$implode['description'][] = "pd.`description` LIKE '%" . $this->db->escape($word) . "%'";
				}
			}

			foreach ($implode as $searchColumn) {
				foreach ($searchColumn as $key => $searchTerm) {
					$andCondition[$key] = $searchTerm;
				}
				$orCondition[] = "(" . implode(' AND ', $andCondition) . ")";
			}

			$where[] = "
				(" . implode(' OR ', $orCondition) . ")
			";
		}
		// End filters

		// Main query
		$sql = "
			SELECT
				p.`product_id`
			FROM " . DB_PREFIX . "product p
			JOIN " . DB_PREFIX . "product_to_store p2s2
				ON p.`product_id` = p2s2.`product_id`
				AND p2s2.`store_id` = '" . (int) $this->config->get('config_store_id') . "'
			JOIN " . DB_PREFIX . "product_description pd
				ON pd.`product_id` = p.`product_id`
				AND pd.`language_id` 	= '" . (int) $this->config->get('config_language_id') . "'
				AND pd.`store_id` 		= '" . (int) $this->config->get('config_store_id') . "'

			-- Sort joins
			LEFT JOIN " . DB_PREFIX . "product_stats pst
				ON pst.`product_id` = p2s2.`product_id`
				AND pst.`store_id`  = p2s2.`product_id`
			-- Conditions
			WHERE EXISTS (
				SELECT 
					1
				FROM " . DB_PREFIX . "product_to_store p2s
				" . implode(" \n ", $join) . "
				WHERE
				" . implode(" \nAND ", $where) . "
			)
		";

		$productRows = $this->db->query($sql)->rows;
		foreach ($productRows as $row) {
			$products[] = $this->getProduct((int) $row['product_id']);
		}

		return $products;
	}

	public function getProductSpecials($data = array()) {

	$store_id 					= (int) $this->config->get('config_store_id');
	$language_id 				= (int) $this->config->get('config_language_id');
	$limit 							= (int) $data['limit'];
	$cacheName 					= "product.store_{$store_id}.language_{$language_id}.special.{$limit}";

		$sql = "
			SELECT 
				DISTINCT ps.product_id, 
				(SELECT AVG(rating) FROM " . DB_PREFIX . "review r1 WHERE r1.product_id = ps.product_id AND r1.status = '1' GROUP BY r1.product_id) AS rating 
			FROM " . DB_PREFIX . "product_special ps 
			LEFT JOIN " . DB_PREFIX . "product p ON (ps.product_id = p.product_id) 
			LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id) 
			LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id) 
			WHERE p.status = '1' AND p.date_available <= NOW() 
				AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' 
				AND ps.customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "' 
				AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) 
				AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW())) 
			GROUP BY ps.product_id
		";

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

		$store_id 					= (int) $this->config->get('config_store_id');
		$language_id 				= (int) $this->config->get('config_language_id');
		$limit 							= (int) $limit;
		$cacheName 					= "product.store_{$store_id}.language_{$language_id}.latest.{$limit}";

		$product_data = $this->cache->get($cacheName);

		if (!$product_data) {
			$product_data = [];
			$query = $this->db->query("
				SELECT 
					p2s.product_id 
				FROM " . DB_PREFIX . "product_to_store p2s 
				JOIN " . DB_PREFIX . "product p
					ON p.product_id = p2s.product_id
				WHERE p2s.status = 1
					AND p2s.store_id = {$store_id}
				ORDER BY p.date_added DESC 
				LIMIT {$limit}"
			);

			foreach ($query->rows as $result) {
				$product_data[$result['product_id']] = $this->getProduct($result['product_id']);
			}

			$this->cache->set($cacheName, $product_data);
		}

		return $product_data;
	}

	public function getPopularProducts($limit) {

		$store_id 					= (int) $this->config->get('config_store_id');
		$language_id 				= (int) $this->config->get('config_language_id');
		$limit 							= (int) $limit;
		$cacheName 					= "product.store_{$store_id}.language_{$language_id}.popular.{$limit}";

		$product_data = $this->cache->get($cacheName);
	
		if (!$product_data) {
			$product_data = [];

			$query = $this->db->query("
				SELECT 
					pst.product_id
				FROM " . DB_PREFIX . "product_stats pst
				JOIN " . DB_PREFIX . "product_to_store p2s
					ON p2s.store_id = pst.store_id
					AND p2s.status = 1
				WHERE pst.store_id = {$store_id}
				ORDER BY pst.viewed DESC
				LIMIT {$limit}"
			);
	
			foreach ($query->rows as $result) {
				$product_data[$result['product_id']] = $this->getProduct($result['product_id']);
			}
			
			$this->cache->set($cacheName, $product_data);
		}
		
		return $product_data;
	}

	public function getBestSellerProducts($limit) : array {

		$store_id 					= (int) $this->config->get('config_store_id');
		$language_id 				= (int) $this->config->get('config_language_id');
		$limit 							= (int) $limit;
		$cacheName 					= "product.store_{$store_id}.language_{$language_id}.bestseller.{$limit}";

		$product_data = $this->cache->get($cacheName);

		if (!$product_data) {
			$product_data = [];

			$query = $this->db->query("
				SELECT 
					pst.product_id
				FROM " . DB_PREFIX . "product_stats pst
				JOIN " . DB_PREFIX . "product_to_store p2s
					ON p2s.store_id = pst.store_id
					AND p2s.status = 1
				WHERE pst.store_id = {$store_id}
				ORDER BY pst.sales DESC
				LIMIT {$limit}"
			);

			foreach ($query->rows as $result) {
				$product_data[$result['product_id']] = $this->getProduct($result['product_id']);
			}

			$this->cache->set($cacheName, $product_data);
		}

		return $product_data;
	}

	public function getProductAttributes($product_id) : array {
		$product = $this->getProduct($product_id);
		$attributes = $product['attributes'] ?? [];
		return $attributes;
	}

	public function getProductOptions($product_id) : array {
		$product = $this->getProduct($product_id);
		$options = $product['options'] ?? [];
		return $options;
	}

	// Product bulk discounts
	public function getProductDiscounts($product_id) {
		$product = $this->getProduct($product_id);
		$discounts = $product['discounts'] ?? [];
		return $discounts;
	}

	// Additional product images
	public function getProductImages($product_id) : array {
		$product = $this->getProduct($product_id);
		$images = $product['images'] ?? [];
		return $images;
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
