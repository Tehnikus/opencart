import('./nimbleTable.js');

document.addEventListener('DOMContentLoaded', async ()=> {
  const interface     = await fetch(`index.php?route=seo/keyword/fetchGetInterface&user_token=${user_token}`).then(r => r.json());
  const keywordGroups = await fetch(`index.php?route=seo/keyword/fetchGetKeywordGroups&user_token=${user_token}`).then(r => r.json());
  const keywords      = await fetch(`index.php?route=seo/keyword/fetchGetKeywords&user_token=${user_token}`).then(r => r.json());
  const addGroupBtn   = document.getElementById('addKeywordGroup');
  const groupList     = document.getElementById('addKeywordGroupInput');

  for (const el of keywordGroups ?? {}) {
    const groupElement = renderKeywordGroup(el.keyword_group_id, el.keyword_group_name);
    appendKeywordGroup(groupElement, groupList);
  }

  addGroupBtn?.addEventListener('click', e => {
    const groupName = e.target.closest('button').previousElementSibling.value;
    if (!groupName) {return}
    addKeywordGroup(groupName, groupList);
  })

});

async function addKeywordGroup(groupName, groupList) {
  const data = new FormData();
  data.append('keyword_group_name', groupName.slice(0, 100));
  let newGroup = await fetch(`index.php?route=seo/keyword/fetchSaveKeywordGroup&user_token=${user_token}`, {method: "POST", body: data}).then(r => r.json());
  console.log(newGroup);
  let groupElement = renderKeywordGroup(newGroup.keyword_group_id, groupName);
  appendKeywordGroup(groupElement, groupList)
}

function renderKeywordGroup(id, name) {
  const groupElement  = document.createElement('div');
  const nameElement   = document.createElement('span');
  const deleteButton  = document.createElement('button');
  groupElement.classList.add("keywordGroup");
  nameElement.innerText = name;

  deleteButton.classList.add("btn", "btn-danger", "btn-xs", "deleteKeywordGroup");
  deleteButton.innerHTML = `<i class="fa fa-times"></i>`
  deleteButton.addEventListener('click', async () => {
    const data = new FormData();
    data.append('keyword_group_id', id);
    deleteResponse = await fetch(`index.php?route=seo/keyword/fetchDeleteKeywordGroup&user_token=${user_token}`, {method: "POST", body: data})
    .then(r => r.json())
    .then(groupElement.remove());
  });

  groupElement.appendChild(nameElement);
  groupElement.appendChild(deleteButton);
  groupElement.dataset.groupId = id;

  return groupElement;
}

function appendKeywordGroup(el, target) {
  const parent = target.parentNode;
  parent.insertBefore(el, target);
}

function renderHeader(interface) {
  const thead           = document.createElement('thead');
  const languageOptions = [
    ...Object.values(interface.languages).map(l => ({
        value: l.language_id,
        label: l.name
      })
    )
  ];

  const storeOptions = [
    ...Object.values(interface.stores).map(s => ({
      value: s.store_id,
      label: s.name
    }))
  ];

  const rowTypeOptions  = [{value: '', label: interface.lang.option_all_types}, {value: 'existing', label: interface.lang.option_existing}, {value: 'updatedRow', label: interface.lang.option_updated}, {value: 'newRow', label: interface.lang.option_new}, {value: 'importedRow', label: interface.lang.option_imported}];


  // Render addRow selects
  const addRowLanguageSelect = renderSelect(languageOptions, {addRowColumn: 'language_id'});
  const addRowStoreSelect    = renderSelect(storeOptions,    {addRowColumn: 'store_id'});
  
  // Add empty values to filter selects
  languageOptions.unshift({value: '', label: interface.lang.column_language});
  storeOptions.unshift({value: '', label: interface.lang.column_store});
  // Render filter selects
  const filterLanguageSelect = renderSelect(languageOptions, {searchColumn: 'language_id'});
  const filterStoreSelect    = renderSelect(storeOptions,    {searchColumn: 'store_id'});
  const filterRowTypeSelect  = renderSelect(rowTypeOptions,  {searchColumn: 'rowType'});

  thead.innerHTML = `
    <tr>
      <th style="width: auto"   class="text-center"><input type="text" class="form-control" data-search-column="keyword_text" placeholder="${interface.lang.text_search} ${interface.lang.column_seo_keyword}"></th>
      <th style="width: auto"   class="text-center"><input type="text" class="form-control" data-search-column="keyword_url" placeholder="${interface.lang.text_search} ${interface.lang.column_url}"></th>
      <th style="width: 180px"  class="text-center">${filterLanguageSelect.outerHTML}</th>
      <th style="width: 180px"  class="text-center">${filterStoreSelect.outerHTML}</th>
      <th style="width: 180px"  class="text-center">${filterRowTypeSelect.outerHTML}</th>
      <th style="width: 180px"  class="text-center">
        <div class="btn-group">
          <button type="button" class="btn btn-default clearFilters" title="${interface.lang.button_clear_filters}"><i class="fa fa-times"></i></button>
          <label class="btn btn-primary importCSV" title="${interface.lang.button_import}">
            <i class="fa fa-cloud-upload"></i>
            <input type="file" accept="csv" style="display: none;" name="importKeywords" />
          </label>
          <button type="button" class="btn btn-success saveAllKeywords" title="${interface.lang.button_save_all}"><i class="fa fa-save"></i></button>
        </div>
      </th>
    </tr>
    <tr>
      <th class="text-center">
        <div class="input-group">
          <input type="text" class="form-control" data-add-row-column="keyword_text" placeholder="${interface.lang.column_add_keyword}/${interface.lang.column_edit_keyword} ${interface.lang.column_seo_keyword}">
          <button type="button" class="btn btn-default addToBeginning"><i class="fa fa-fast-backward"></i></button>
          <button type="button" class="btn btn-default addToEnd"><i class="fa fa-fast-forward"></i></button>
          <button type="button" class="btn btn-warning replace"><i class="fa fa-random"></i></button>
        </div>
      </th>
      <th class="text-center">
        <div class="input-group">
          <input type="text" class="form-control" data-add-row-column="keyword_url" placeholder="${interface.lang.column_url}">
          <button type="button" class="btn btn-default addToBeginning"><i class="fa fa-fast-backward"></i></button>
          <button type="button" class="btn btn-default addToEnd"><i class="fa fa-fast-forward"></i></button>
          <button type="button" class="btn btn-warning replace"><i class="fa fa-random"></i></button>
        </div>
      </th>
      <th class="text-center">
        <div class="input-group">
        ${addRowLanguageSelect.outerHTML}<button type="button" class="btn btn-warning replace"><i class="fa fa-random"></i></button>
        </div>
      </th>
      <th class="text-center">
        <div class="input-group">
        ${addRowStoreSelect.outerHTML}<button type="button" class="btn btn-warning replace"><i class="fa fa-random"></i></button>
        </div>
      </th>
      <th class="text-center"></th>
      <th class="text-center">
        <div class="btn-group">
          <button type="button" class="btn btn-success addRow"><i class="fa fa-plus-circle"></i></button>
        </div>
      </th>
    </tr>
  `;

  return thead;
}

// Render select from options list 
function renderSelect(options, datasetAttr) {

  const select = document.createElement('select');
  select.className = 'form-control';

  if (datasetAttr) {
    Object.entries(datasetAttr).forEach(([k, v]) => {
      select.dataset[k] = v;
    });
  }

  options.forEach(opt => {
    const option = document.createElement('option');
    option.value = opt.value;
    option.textContent = opt.label;
    select.appendChild(option);
  });

  return select;
}