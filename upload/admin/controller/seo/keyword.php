<?php 
class ControllerSeoKeyword extends Controller {
  public function index() {
    $this->load->language('seo/keyword');
    $this->load->model('seo/keyword');
    $this->document->setTitle($this->language->get('heading_title'));

    $this->getList();
  }

  public function getList() {
    $this->document->addScript('view/javascript/nimbleTable.js');
    $this->document->addScript('view/javascript/batchloader.js');

    $this->load->model('setting/store');
    $this->load->model('localisation/language');

    $data = [
      'column_left'            => $this->load->controller('common/column_left'),
      'footer'                 => $this->load->controller('common/footer'),
      'header'                 => $this->load->controller('common/header'),
      'breadcrumbs'            => $this->displayBreadcrumbs(),
      'user_token'             => $this->session->data['user_token'],
    ];

    $this->response->setOutput($this->load->view('seo/keyword', $data));
  }

  public function displayBreadcrumbs() {
    $breadcrumbs = [];
    $breadcrumbs[] = [
      'text' => $this->language->get('text_home'),
      'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
    ];
    $breadcrumbs[] = [
      'text' => $this->language->get('heading_title'),
      'href' => $this->url->link('seo/keyword', 'user_token=' . $this->session->data['user_token'], true)
    ];
    return $breadcrumbs;
  }

  public function fetchGetInterface() : void {
    // Load new Language
    $lang = new Language();
    // Firs load admin translation 
    $lang->load($this->config->get('config_admin_language'));
    // Then load current controller translation to overwrite same named entries with current controller translation
    $lang->load('seo/keyword');
    // Get languages and stores
    $this->load->model('localisation/language');
    $this->load->model('setting/store');
    $stores    = $this->model_setting_store->getMultistores();
    $languages = $this->model_localisation_language->getLanguages();

    // Return JSON to fetch
    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(
      json_encode(
        [
          'lang'      => $lang->data,
          'stores'    => $stores,
          'languages' => $languages,
        ]
      )
    );
  }

  public function fetchGetKeywordGroups() : void {
    $this->load->model('seo/keyword');
    $groups = $this->model_seo_keyword->getKeywordGroups();
    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(
      json_encode(
        $groups
      )
    );
  }

  public function fetchSaveKeywordGroups() : void {
    $response = [];
    $groups   = $this->request->post['seo_keyword_groups'];

    if (empty($groups)) {
      $response['seo_keyword_groups'] = 0;
    } else {
      $this->load->model('seo/keywords');
      $response['seo_keyword_groups'] = $this->model_seo_keywords->saveKeywordGroups($groups);
    }

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(
      json_encode(
        $response,
        JSON_UNESCAPED_UNICODE
      )
    );
  }

  public function fetchGetKeywords() : void {
    $this->load->model('seo/keyword');
    $keywords = $this->model_seo_keyword->getKeywords();
    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(
      json_encode(
        $keywords
      )
    );
  }

  public function fetchSaveKeywords() : void {
    $response = [];
    $keywords = $this->request->post['keywords'];

    if (empty($keywords)) {
      $response['seo_keyword_groups'] = 0;
    } else {
      $this->load->model('seo/keywords');
      $response['seo_keyword_groups'] = $this->model_seo_keywords->saveKeywords($keywords);
    }

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(
      json_encode(
        $response
      )
    );
  }
}