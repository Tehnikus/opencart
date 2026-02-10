<?php
class ModelCatalogOption extends Model {
	public function addOption($data) : int {

		$this->db->query("START TRANSACTION");
		
		try {

			$this->db->query("
				INSERT INTO `" . DB_PREFIX . "option` 
				SET 
					`type` 				= '" . $this->db->escape($data['type']) . "', 
					`sort_order` 	= '" . (int) $data['sort_order'] . "'
			");
	
			$option_id = $this->db->getLastId();
	
			foreach ($data['option_description'] as $language_id => $value) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "option_description 
					SET 
						`option_id` 		= '" . (int) $option_id . "', 
						`language_id` 	= '" . (int) $language_id . "', 
						`store_id` 			= '" . (int) $this->session->data['store_id'] . "', 
						`name` 					= '" . $this->db->escape($value['name']) . "'
				");
			}
	
			if (isset($data['option_value'])) {
				foreach ($data['option_value'] as $option_value) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "option_value 
						SET 
							`option_id` 		= '" . (int) $option_id . "', 
							`image` 				= '" . $this->db->escape(html_entity_decode($option_value['image'], ENT_QUOTES, 'UTF-8')) . "', 
							`store_id` 			= '" . (int) $this->session->data['store_id'] . "', 
							`sort_order` 		= '" . (int) $option_value['sort_order'] . "'
					");
	
					$option_value_id = $this->db->getLastId();
	
					foreach ($option_value['option_value_description'] as $language_id => $option_value_description) {
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "option_value_description 
							SET 
								`option_value_id` 	= '" . (int) $option_value_id . "', 
								`language_id` 			= '" . (int) $language_id . "', 
								`store_id` 					= '" . (int) $this->session->data['store_id'] . "', 
								`option_id` 				= '" . (int) $option_id . "', 
								`name` 							= '" . $this->db->escape($option_value_description['name']) . "'
						");
					}
				}
			}

			// Stores association
			if (isset($data['stores_association']) && !empty($data['stores_association'])) {
				foreach ($data['stores_association'] as $store_id) {
					$this->db->query("
						INSERT INTO " . DB_PREFIX . "option_to_store
						SET
							`option_id` 		= '" . (int) $option_id . "', 
							`store_id` 			= '" . (int) $store_id . "',
							`sort_order` 		= '" . (int) $data['sort_order'] . "'
					");
				}
			}
	
			$this->db->query("COMMIT");
	
			return $option_id;

		} catch (\Throwable $e) {
			
			$this->db->query("ROLLBACK");

			throw $e;

		}

	}

	public function editOption($option_id, $data) : int {

		$this->db->query("START TRANSACTION");

		try {
			
			$this->db->query("
				UPDATE `" . DB_PREFIX . "option` 
				SET 
					`type` 					= '" . $this->db->escape($data['type']) . "', 
					`sort_order` 		= '" . (int) $data['sort_order'] . "' 
				WHERE `option_id` = '" . (int) $option_id . "'
			");

			$this->db->query("
				DELETE FROM " . DB_PREFIX . "option_description 
				WHERE `option_id` = '" . (int) $option_id . "'
					AND `store_id` 	= '" . (int) $this->session->data['store_id'] . "'
			");

			foreach ($data['option_description'] as $language_id => $value) {
				$this->db->query("
					INSERT INTO " . DB_PREFIX . "option_description 
					SET 		
						`option_id` 	= '" . (int) $option_id . "', 
						`language_id` = '" . (int) $language_id . "',
						`store_id` 		= '" . (int) $this->session->data['store_id'] . "',
						`name` 				= '" . $this->db->escape($value['name']) . "'
				");
			}

			$this->db->query("
				DELETE FROM " . DB_PREFIX . "option_value 
				WHERE `option_id` = '" . (int) $option_id . "'
					AND `store_id` 	= '" . (int) $this->session->data['store_id'] . "'
			");

			$this->db->query("
				DELETE FROM " . DB_PREFIX . "option_value_description 
				WHERE `option_id` = '" . (int) $option_id . "'
					AND `store_id` 	= '" . (int) $this->session->data['store_id'] . "'
			");

			if (isset($data['option_value'])) {
				foreach ($data['option_value'] as $option_value) {
					if ($option_value['option_value_id']) {
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "option_value SET 
								`option_value_id` = '" . (int) $option_value['option_value_id'] . "', 
								`option_id` 			= '" . (int) $option_id . "', 
								`store_id`				= '" . (int) $this->session->data['store_id'] . "',
								`image` 					= '" . $this->db->escape(html_entity_decode($option_value['image'], ENT_QUOTES, 'UTF-8')) . "', 
								`sort_order` 			= '" . (int) $option_value['sort_order'] . "'
						");
					} else {
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "option_value SET 
								`option_id` 		= '" . (int) $option_id . "', 
								`store_id`			= '" . (int) $this->session->data['store_id'] . "',
								`image` 				= '" . $this->db->escape(html_entity_decode($option_value['image'], ENT_QUOTES, 'UTF-8')) . "', 
								`sort_order` 		= '" . (int) $option_value['sort_order'] . "'
						");
					}

					$option_value_id = $this->db->getLastId();

					foreach ($option_value['option_value_description'] as $language_id => $option_value_description) {
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "option_value_description 
							SET 
								`option_value_id` = '" . (int)$option_value_id . "', 
								`language_id` 		= '" . (int)$language_id . "', 
								`option_id` 			= '" . (int)$option_id . "', 
								`store_id`				= '" . (int) $this->session->data['store_id'] . "',
								`name` 						= '" . $this->db->escape($option_value_description['name']) . "'
						");
					}
				}
			}
			
			// Stores association
			// Remove all unselected stores
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "option_to_store 
				WHERE `option_id` = '" . (int) $option_id . "'
					AND `store_id` NOT IN (" . implode(',', array_map('intval', $data['stores_association'])) . ")
			");

			// Remove only current store no matter if it's selected
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "option_to_store 
				WHERE option_id = '" . (int) $option_id . "'
					AND store_id 	= '" . (int) $this->session->data['store_id'] . "'
			");
			
			// Write store association and store related data
			if (isset($data['stores_association']) && !empty($data['stores_association'])) {
				foreach ($data['stores_association'] as $store_id) {
					if (((int) $store_id) === ((int) $this->session->data['store_id'])) {
						// Set data for current store
						$this->db->query("
							INSERT INTO " . DB_PREFIX . "option_to_store
							SET
								`option_id` 		= '" . (int) $option_id . "', 
								`store_id` 			= '" . (int) $store_id . "',
								`sort_order` 		= '" . (int) $data['sort_order'] . "'
						");
					} else {
						// Skip if data for other stores already exists
						$this->db->query("
							INSERT IGNORE INTO " . DB_PREFIX . "option_to_store
							SET
								`option_id` 		= '" . (int) $option_id . "', 
								`store_id` 			= '" . (int) $store_id . "',
								`sort_order` 		= '" . (int) $data['sort_order'] . "'
						");
					}
				}
			}			

			$this->db->query("COMMIT");

			return (int) $option_id;

		} catch (\Throwable $e) {
			
			$this->db->query("ROLLBACK");
			
			throw $e;

		}
	}

	public function deleteOption($option_id) : bool {

		$this->db->query("START TRANSACTION");

		try {
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "option_description 
				WHERE option_id = '" . (int) $option_id . "'
					AND store_id  = '" . (int) $this->session->data['store_id'] . "'
			");

			// Delete product options
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "product_option
				WHERE option_id = '" . (int) $option_id . "'
					AND store_id 	= '" . (int) $this->session->data['store_id'] . "'
			");

			// Delete product option values in current store
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "product_option_value pov
				WHERE pov.option_value_id IN (
					SELECT
						option_value_id ov
					FROM " . DB_PREFIX . "option_value ov
					WHERE ov.option_id 	= '" . (int) $option_id . "'
						AND ov.store_id 	= '" . (int) $this->session->data['store_id'] . "'
				)
					AND pov.store_id = '" . (int) $this->session->data['store_id'] . "'
			");
			
			// Then delete option values
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "option_value 
				WHERE option_id = '" . (int) $option_id . "'
					AND store_id = '" . (int) $this->session->data['store_id'] . "'
			");

			$this->db->query("
				DELETE FROM " . DB_PREFIX . "option_value_description 
				WHERE option_id = '" . (int) $option_id . "'
					AND store_id  = '" . (int) $this->session->data['store_id'] . "'
			");

			$this->db->query("
				DELETE FROM " . DB_PREFIX . "option_to_store 
				WHERE option_id = '" . (int) $option_id . "'
					AND store_id  = '" . (int) $this->session->data['store_id'] . "'
			");

			// Check if option exists in other stores
			$optionsInOtherStores = $this->db->query("
				SELECT
					option_id
				FROM " . DB_PREFIX . "option_to_store
				WHERE store_id <> '" . (int) $this->session->data['store_id'] . "'
			")->num_rows;

			// If option doesn't exist in other stores then delete it from main table where autoincrement is
			if (!$optionsInOtherStores) {
				$tables = [
					'option',
					'option_value',
					'option_value_description',
					'option_description',
					'option_to_store',
					'product_option',
				];

			// Delete product option values in all stores
			$this->db->query("
				DELETE FROM " . DB_PREFIX . "product_option_value pov
				WHERE pov.option_value_id IN (
					SELECT
						option_value_id ov
					FROM " . DB_PREFIX . "option_value ov
					WHERE ov.option_id 	= '" . (int) $option_id . "'
				)
			");

				// Remove all redundant data if present 
				foreach ($tables as $table) {
					$this->db->query("
						DELETE FROM " . DB_PREFIX . $table . "
						WHERE option_id = " . (int) $option_id
					);
				}
			}
			
			$this->db->query("COMMIT");

			return true;

		} catch (\Throwable $e) {

			$this->db->query("ROLLBACK");

			throw $e;
		}
	}

	public function getOption($option_id) {
		$query = $this->db->query("
			SELECT 
				* 
			FROM `" . DB_PREFIX . "option` o 
			LEFT JOIN " . DB_PREFIX . "option_description od 
				ON (o.option_id = od.option_id) WHERE o.option_id = '" . (int)$option_id . "' 
				AND od.language_id = '" . (int)$this->config->get('config_language_id') . "'
		");

		return $query->row;
	}

	public function getOptions($data = array()) {

		$result = [];
		$where  = [];

		$where[] = "
			od.language_id = '" . (int) $this->config->get('config_language_id') . "'
		";
		// Filter by name
		if (isset($data['filter_name'])) {
			$where[] = " od.`name` LIKE '%" . $this->db->escape($data['filter_name']) . "%'";
		}
		// FIlter by store
		if (isset($data['store_id'])) {
			$where[] = " o2s.store_id = '" . (int) $data['store_id'] . "'";
		}

		if (!empty($data['has_values'])) {
			$where[] = "
				EXISTS (
					SELECT 1
					FROM " . DB_PREFIX . "option_value ov
					WHERE ov.option_id = o.option_id
						AND ov.store_id = '" . (int) $this->session->data['store_id'] . "'
				)
			";
		}

		// TODO Add wildcard search by option_value_description name
		$sql = "
			SELECT 
				o.option_id,
				o.type,
				o2s.sort_order,
				(
					SELECT 
						od.name 
					FROM " . DB_PREFIX . "option_description od
					WHERE od.option_id = o.option_id
					ORDER BY 
						FIELD(od.store_id, '" . (int) $this->session->data['store_id'] ."') DESC, 
						FIELD(od.language_id, '" . (int) $this->config->get('config_language_id') . "') DESC
					LIMIT 1
				) AS `name`,
				(SELECT COUNT(ov.option_value_id) FROM " . DB_PREFIX . "option_value ov WHERE ov.option_id = o.option_id AND ov.store_id = '" . (int) $this->session->data['store_id'] . "') AS option_count,
				(SELECT JSON_ARRAYAGG(o2s.store_id) FROM " . DB_PREFIX . "option_to_store o2s WHERE o2s.option_id = o.option_id) AS stores,
				(SELECT JSON_ARRAYAGG(ovd.name) FROM " . DB_PREFIX . "option_value_description ovd WHERE ovd.option_id = o.option_id AND ovd.language_id = '" . (int) $this->config->get('config_language_id') . "' AND ovd.store_id = '" . (int) $this->session->data['store_id'] . "') AS values_list
			FROM `" . DB_PREFIX . "option` o 
			JOIN " . DB_PREFIX . "option_to_store o2s
			 	ON o2s.option_id = o.option_id
				AND o2s.store_id = '" . (int) $this->session->data['store_id'] . "'
			WHERE EXISTS (
				SELECT 1
				FROM " . DB_PREFIX . "option_to_store o2s
				JOIN " . DB_PREFIX . "option_description od ON od.option_id = o.option_id
				WHERE " . implode(' AND ', $where) . "
			) 
		";

		$sort_data = array(
			'name',
			'o.type',
			'o2s.sort_order'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY name";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
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

		$query = $this->db->query($sql);
		
		foreach ($query->rows ?? [] as $row) {
			$row['stores'] 			= json_decode($row['stores'] ?? '[]');
			$row['values_list'] = json_decode($row['values_list'] ?? '[]');
			$result[] = $row;
		}

		return $result;
	}

	public function getOptionDescriptions($option_id) {
		$option_data = array();

		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "option_description 
			WHERE option_id = '" . (int) $option_id . "'
				AND store_id 	= '" . (int) $this->session->data['store_id'] . "'
		");

		foreach ($query->rows as $result) {
			$option_data[$result['language_id']] = array('name' => $result['name']);
		}

		return $option_data;
	}

	public function getOptionValue($option_value_id) {
		$query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "option_value ov 
			LEFT JOIN " . DB_PREFIX . "option_value_description ovd 
				ON (ov.option_value_id = ovd.option_value_id) 
			WHERE ov.option_value_id = '" . (int)$option_value_id . "' 
				AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "'
				AND ov.store_id 		= '" . (int) $this->session->data['store_id'] . "'
				AND ovd.store_id 		= '" . (int) $this->session->data['store_id'] . "'
		");

		return $query->row;
	}

	public function getOptionValues($option_id) {
		$option_value_data = array();

		$option_value_query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "option_value ov 
			LEFT JOIN " . DB_PREFIX . "option_value_description ovd 
				ON  ovd.option_value_id = ov.option_value_id
				AND ovd.store_id 				= ov.store_id
			WHERE ov.option_id 		= '" . (int) $option_id . "' 
				AND ov.store_id 		= '" . (int) $this->session->data['store_id'] . "'
				AND ovd.language_id = '" . (int) $this->config->get('config_language_id') . "' 
			ORDER BY ov.sort_order, ovd.name
		");

		foreach ($option_value_query->rows as $option_value) {
			$option_value_data[] = array(
				'option_value_id' => $option_value['option_value_id'],
				'name'            => $option_value['name'],
				'image'           => $option_value['image'],
				'sort_order'      => $option_value['sort_order']
			);
		}

		return $option_value_data;
	}

	public function getOptionValueDescriptions($option_id) {
		$option_value_data = array();

		$option_value_query = $this->db->query("
			SELECT 
				* 
			FROM " . DB_PREFIX . "option_value 
			WHERE option_id = '" . (int) $option_id . "' 
				AND store_id 	= '" . (int) $this->session->data['store_id'] . "'
			ORDER BY sort_order
		");

		foreach ($option_value_query->rows as $option_value) {
			$option_value_description_data = array();

			$option_value_description_query = $this->db->query("
				SELECT 
					* 
				FROM " . DB_PREFIX . "option_value_description 
				WHERE option_value_id = '" . (int)$option_value['option_value_id'] . "'
					AND store_id 				= '" . (int) $this->session->data['store_id']. "'
			");

			foreach ($option_value_description_query->rows as $option_value_description) {
				$option_value_description_data[$option_value_description['language_id']] = array('name' => $option_value_description['name']);
			}

			$option_value_data[] = array(
				'option_value_id'          => $option_value['option_value_id'],
				'option_value_description' => $option_value_description_data,
				'image'                    => $option_value['image'],
				'sort_order'               => $option_value['sort_order']
			);
		}

		return $option_value_data;
	}

	public function getTotalOptions() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "option`");

		return $query->row['total'];
	}

	public function getStoresAssociation($id = null) : array {
		$result = [];

		if (!$id) {
			return $result;
		}

		// Get stores association
		$storeData = $this->db->query("
			SELECT
				store_id
			FROM `" . DB_PREFIX . "option_to_store`
			WHERE option_id = '" . (int) $id . "'
		");
		foreach ($storeData->rows as $store) {
			$result[] = $store['store_id']; 
		}

		return $result;
	}
}