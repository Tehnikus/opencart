<?php
class ControllerExtensionModuleFacetFilter extends Controller {

	public function __construct($registry) {
		parent::__construct($registry);
	}

	public function index() {

		$this->load->model('catalog/product');
		$data['sortOrders'] = array_keys($this->model_catalog_product->getSortOrders());

		$this->load->model('extension/module/facet_filter');
		$this->load->language('extension/module/facet_filter');
		$settings = $this->config->get('module_facet_filter_settings');

		foreach ($data['sortOrders'] as $key => $sortOrder) {
			$data['sortOrders'][$key] = [
				'name'  => $this->language->get('sort_' . $sortOrder),
				'value' => $sortOrder,
			];
		}

		$route 				= (string) $this->request->get['route'];
		$path 				= $this->request->get['category_id'] ?? $this->request->get['path'] ?? '';
		$category_id 	= explode('_', (string) $path);
		$category_id 	= end($category_id) ?? null;
		
		// Interface data
		$settings['cache'] = 0;
		if (isset($settings['cache']) && $settings['cache'] === '1' && $route !== 'product/search') {
			// Cache except search page
			$store_id 		= (int) $this->config->get('config_store_id');
			$language_id 	= (int) $this->config->get('config_language_id');
			$cachePrefix = explode('/', $route)[1] ?? $route;
			$cachePostfix = "filters";
			if ($category_id) {
				$cachePostfix = (floor($category_id / 100)) . "00.filters_{$category_id}";
			}
			$cacheName 	= "{$cachePrefix}.store_{$store_id}.language_{$language_id}.{$cachePostfix}";
			$data['filter_sets'] 	= $this->cache->get($cacheName);
			if (!$data['filter_sets']) {
				$data['filter_sets'] = $this->getFilterSets();
				$this->cache->set($cacheName, $data['filter_sets']);
			}
		} else {
			// No cache 
			$data['filter_sets'] = $this->getFilterSets();
		}
		
		// Request data to check applied filters
		$data['requests'] = [
			'filter' 						=> explode(',', $this->request->get['filter'] ?? '') 						?? null,
			'option' 						=> explode(',', $this->request->get['option'] ?? '') 						?? null,
			'attribute' 				=> explode(',', $this->request->get['attribute'] ?? '') 				?? null,
			'manufacturer_id' 	=> explode(',', $this->request->get['manufacturer_id'] ?? '') 	?? null,
			'category_id' 			=> explode(',', $this->request->get['category_id'] ?? '') 			?? null,
			'is_available' 			=> explode(',', $this->request->get['is_available'] ?? '') 			?? null,
			'is_featured' 			=> explode(',', $this->request->get['is_featured'] ?? '') 			?? null,
			'has_discount' 			=> explode(',', $this->request->get['has_discount'] ?? '') 			?? null,
		];
		
		// Create SEO URL for each filter
		foreach ($data['filter_sets'] as $filter_type_key => &$filter_type) {
			foreach ($filter_type as &$filter_group) {
				foreach ($filter_group['filters'] as &$filter_item) {
					$query = $this->request->get;
					unset($query['route']);
					
					$current = [];
					
					if (!empty($query[$filter_type_key])) {
						$current = array_filter(
							array_map('intval', explode(',', $query[$filter_type_key]))
						);
					}
					
					$id = (int) $filter_item['filter_id'];
					
					if (in_array($id, $current, true)) {
						// remove
						$current = array_diff($current, [$id]);
					} else {
						// add
						$current[] = $id;
					}
					
					$current = array_values(array_unique($current));
					
					sort($current);

					if ($current) {
						$query[$filter_type_key] = implode(',', $current);
					} else {
						unset($query[$filter_type_key]);
					}
					
					$filter_item['href'] = $this->url->link($route, http_build_query($query));
				}
			}
		}

		return $this->load->view('extension/module/facet_filter', $data);
	}

	public function getFilterSets() : array {
		$requestFilters = []; // Data from $this->request->get
		$filterSets 		= []; // Result to be returned
		$facetTypes 		= ['category_id', 'filter', 'option', 'attribute', 'manufacturer_id', 'tag_id', 'supplier_id', 'is_available', 'has_discount', 'is_featured'];
		$route 					= explode('/', $this->request->get['route'] ?? []); // Route to apply page settings 
		$route 					= end($route) ?? null;
		$path 					= $this->request->get['category_id'] ?? $this->request->get['path'] ?? ''; // Path to fallback to current category id
		$category_id 		= explode('_', (string) $path); // Current category id
		$category_id 		= end($category_id) ?? null;
		$settings 			= $this->config->get('module_facet_filter_settings');
		$settings				= $settings['distinct_categories'][$category_id] ?? $settings[$route] ?? null; // Settings by page type, default settings for categories and individual category settings
		
		// Only show filters on allowed page types where 
		if ($settings === null || $route === null || !in_array($route, ['category', 'manufacturer', 'special', 'latest', 'search', 'bestseller'])) {
			return [];
		}

		$this->load->language('extension/module/facet_filter');

		// Get requested filters
		foreach ($this->request->get as $filterKey => $filterData) {
			if (in_array($filterKey, $facetTypes)) {
				$requestFilters['filter_'.$filterKey] = $filterData;
			}
		}

		// Add category id to requested filters
		if ($category_id !== null) {
			$requestFilters['filter_category_id'] = $category_id;
		}

		// Get facets and product count for requested filters
		$this->load->model('catalog/product');
		$facets = $this->model_catalog_product->getFilters($requestFilters);

		// Create array hierarhical from facet list
		foreach ($facets as $row) {

			// Skip current category in facets list
			if ($row['facet_type'] === 'category_id' && $row['facet_value_id'] === $requestFilters['filter_category_id']) {
				continue;
			}

			if (!isset($settings[$row['facet_type']]) || $settings[$row['facet_type']] !== '1') {
				continue;
			}

			$type  			= $row['facet_type'];
			$group 			= $row['facet_group_id'];
			$group_name = $row['facet_group_name'];
			$facet_name = $row['facet_name'];

		
			// Add missing facets and groups names
			if ($group_name === null) {
				if ($type === 'category') {
					// Categories have own name but don't have parent group 
					$group_name = $this->language->get('group_category');
				}
				if ($type === 'manufacturer') {
					// Manufacturers have own name but don't have parent group 
					$group_name = $this->language->get('group_manufacturer');
				}
				if ($type === 'is_available') {
					$group_name = $this->language->get('group_is_available');
					$facet_name = $this->language->get('facet_is_available');
				}
				if ($type === 'has_discount') {
					$group_name = $this->language->get('group_has_discount');
					$facet_name = $this->language->get('facet_has_discount');
				}
				if ($type === 'is_featured') {
					$group_name = $this->language->get('group_is_featured');
					$facet_name = $this->language->get('facet_is_featured');
				}
			}
	
			// Create group if not exists
			if (!isset($filterSets[$type][$group])) {
				$filterSets[$type][$group] = [
					'group_name' => $group_name,
					'filter_group_id' => $group,
					'filters' => []
				];
			}
	
			// Add filters
			$filterSets[$type][$group]['filters'][] = [
				'filter_id' => $row['facet_value_id'],
				'name'      => $facet_name
			];
		}
		
		foreach ($filterSets as $type => $groups) {
			$filterSets[$type] = array_values($groups);
		}

		return $filterSets;
	}


	// private function getFilterSets2() : array {
	// 	$filterSets = [];

	// 	$this->load->model('extension/module/facet_filter');
	// 	$this->load->language('extension/module/facet_filter');
	// 	$settings = $this->config->get('module_facet_filter_settings');


	// 	$route = (string) $this->request->get['route'];
	// 	$path = $this->request->get['category_id'] ?? $this->request->get['path'] ?? '';
	// 	$category_id = explode('_', (string) $path);
	// 	$category_id = end($category_id) ?? null;

	// 	// Switch case for different page types
	// 	switch ($route) {
	// 		case 'product/category':
	// 			// Check if category exists

	// 			$category_exists = $this->model_extension_module_facet_filter->categoryExists($category_id);
	// 			if (!$category_exists) {
	// 				return [];
	// 			}
	// 			// Get category products
	// 			$products = $this->model_extension_module_facet_filter->getCategoryProducts($category_id);
	// 			// Get category filters
	// 			$filters = $this->model_extension_module_facet_filter->getCategoryFilters($category_id);
	// 		break;

	// 		case 'product/special':
	// 			// Get special products
	// 			$products = $this->model_extension_module_facet_filter->getSpecialProducts();
	// 			// Get filters
	// 			$filters = $this->model_extension_module_facet_filter->getFiltersByProductSet($products);
	// 		break;

	// 		case 'product/search':
	// 			// Get search products
	// 			$this->load->model('catalog/product');
	// 			$products = [];
				
	// 			$searchProducts = $this->model_catalog_product->getProducts([
	// 				'filter_name' => $this->request->get['search'] ?? null,
	// 				'filter_description' => $this->request->get['description'] ?? false
	// 			]) ?? [];

	// 			foreach ($searchProducts as $product) {
	// 				$products[] = $product['product_id'];
	// 			}
	// 			// Get filters
	// 			$filters = $this->model_extension_module_facet_filter->getFiltersByProductSet($products);
	// 		break;
			
	// 		default:
	// 		return [];
	// 	}
	
	// 	$options 				= $this->model_extension_module_facet_filter->getOptionsByProductSet($products);
	// 	$attributes 		= $this->model_extension_module_facet_filter->getAttributesByProductSet($products);
	// 	$manufacturers 	= $this->model_extension_module_facet_filter->getManufacturersByProductSet($products);

	// 	// Interface data
	// 	// Category settings
	// 	if ($route === 'product/category' && $category_id) {
	// 		if (isset($settings['category'][$category_id])) {
	// 			// Individual category settings
	// 			$filterSets = [
	// 				'filter'						=> (isset($settings['category'][$category_id]['show_filters'])) 			? $filters : [],
	// 				'option' 						=> (isset($settings['category'][$category_id]['show_options'])) 			? $options : [], 			
	// 				'attribute' 				=> (isset($settings['category'][$category_id]['show_attributes'])) 		? $attributes : [], 	
	// 				'manufacturer_id' 	=> (isset($settings['category'][$category_id]['show_manufacturers'])) ? $manufacturers : [],
	// 			];
	// 		} else {
	// 			// Default category settings
	// 			$filterSets = [
	// 				'filter'						=> (isset($settings['default']['show_filters'])) 			 ? $filters : [],
	// 				'option' 						=> (isset($settings['default']['show_options'])) 			 ? $options : [], 			
	// 				'attribute' 				=> (isset($settings['default']['show_attributes'])) 	 ? $attributes : [], 	
	// 				'manufacturer_id' 	=> (isset($settings['default']['show_manufacturers'])) ? $manufacturers : [],
	// 			];
	// 		}
	// 	}
		
	// 	// Special/discount products
	// 	if ($route === 'product/special') {
	// 		$filterSets = [
	// 			'filter'						=> (isset($settings['special']['show_filters'])) 				? $filters : [],
	// 			'option' 						=> (isset($settings['special']['show_options'])) 				? $options : [], 			
	// 			'attribute' 				=> (isset($settings['special']['show_attributes'])) 		? $attributes : [], 	
	// 			'manufacturer_id' 	=> (isset($settings['special']['show_manufacturers'])) 	? $manufacturers : [],
	// 		];
	// 	}

	// 	// Search page
	// 	if ($route === 'product/search') {
	// 		$filterSets = [
	// 			'filter'						=> (isset($settings['search']['show_filters'])) 				? $filters : [],
	// 			'option' 						=> (isset($settings['search']['show_options'])) 				? $options : [], 			
	// 			'attribute' 				=> (isset($settings['search']['show_attributes'])) 		  ? $attributes : [], 	
	// 			'manufacturer_id' 	=> (isset($settings['search']['show_manufacturers'])) 	? $manufacturers : [],
	// 		];
	// 	}

	// 	// Manufacturer page
	// 	if ($route === 'product/manufacturer') {
	// 		$filterSets = [
	// 			'filter'						=> (isset($settings['manufacturer']['show_filters'])) 				? $filters : [],
	// 			'option' 						=> (isset($settings['manufacturer']['show_options'])) 				? $options : [], 			
	// 			'attribute' 				=> (isset($settings['manufacturer']['show_attributes'])) 		  ? $attributes : [], 	
	// 			'manufacturer_id' 	=> (isset($settings['manufacturer']['show_manufacturers'])) 	? $manufacturers : [],
	// 		];
	// 	}
		
	// 	$filterSets = array_filter($filterSets);

	// 	// echo '<pre>' . htmlspecialchars(print_r($filterSets, true)) . '</pre>';
	// 	return $filterSets;
	// }
}