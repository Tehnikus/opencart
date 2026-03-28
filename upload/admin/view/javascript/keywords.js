import('./nimbleTable.js');

document.addEventListener('DOMContentLoaded', async ()=> {
  const interface     = await fetch(`index.php?route=seo/keyword/fetchGetInterface&user_token=${user_token}`).then(r => r.json());
  const keywords      = await fetch(`index.php?route=seo/keyword/fetchGetKeywords&user_token=${user_token}`).then(r => r.json());
  const addGroupBtn   = document.getElementById('addKeywordGroup');
  const groupList     = document.getElementById('addKeywordGroupInput');

  // Render keyword groups
  for (const el of interface.keywordGroups) {
    const groupElement = renderKeywordGroup(el.keyword_group_id, el.keyword_group_name);
    appendKeywordGroup(groupElement, groupList);
  }
  
  // Add event listener on group add button
  addGroupBtn?.addEventListener('click', e => {
    const groupName = e.target.closest('button').previousElementSibling.value;
    if (!groupName) {return}
    saveKeywordGroup(groupName, groupList);
  });

  interface.languageSelect = renderSelect([
    ...Object.values(interface.languages).map(l => ({
        value: l.language_id,
        label: l.name
      })
    )
  ]);
  interface.storeSelect = renderSelect([
    ...Object.values(interface.stores).map(l => ({
        value: l.store_id,
        label: l.name
      })
    )
  ]);
  interface.groupSelect = renderSelect([
    ...Object.values(interface.keywordGroups).map(l => ({
        value: l.keyword_group_id,
        label: l.keyword_group_name
      })
    )
  ]);


  // Render nimbleTable keywords list
  renderKeywords(interface, keywords);
});
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

function renderKeywords(interface, keywords) {
  const keywordTable = new nimbleTable({
    table: document.getElementById('keywordTable'),
    idField:  'keyword_id',
    pagination: {perPage: 200},
    template: (row) => renderRow(interface, row),
    addEventListeners: (table) => {
      table.addEventListener('click', ()=> {
        // console.log('Table callback fired');
      })
    },
    onFilterEnd: (filteredMap) => {
      // console.log(filteredMap);
    }
  });

  keywords.forEach(row => {
    row.rowType = 'existing';
  });
  const tableHeaderElement = renderHeader(interface);
  keywordTable.renderHeader(tableHeaderElement);
  keywordTable.setData(keywords);

  // Copy row
  keywordTable.tbody.addEventListener('click', e => {
    if (e.target.closest('[data-copy-row]')) {
      const id = Number(e.target.closest('[data-id]').dataset.id);
      const rowData = {...keywordTable.rowMap.get(id)}; // Copy row instead of reusing it, because in JavaScript objects are reference types (assignments copy references, not the actual object)
      delete rowData.keyword_id; // Delete values that are treated as row identifier. If not deleted, Map() will skip duplicate ids
      delete rowData.id; // Delete values that are treated as row identifier. If not deleted, Map() will skip duplicate ids
      rowData.rowType = 'newRow';
      keywordTable.setData([rowData]);
    }
  });

  // Add new row event listener
  tableHeaderElement.querySelector('.addRow').addEventListener('click', (e) => {
    const newRow = e.target.closest('tr');
    addRow(keywordTable, newRow);
  })
}

function renderRow(interface, row) {
  const tr = document.createElement('tr');
  const rowTypeOptions  = {updatedRow: interface.lang.option_updated, newRow: interface.lang.option_new, importedRow: interface.lang.option_imported, existing: interface.lang.option_existing};
  let   rowTypeLabel = '';

  tr.dataset.id = row.keyword_id || '';

  if (row.rowType) {
    tr.classList.add(row.rowType);
    rowTypeLabel = rowTypeOptions[row.rowType];
  }

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

  tr.innerHTML = `
    <td><input data-column="keyword_text" name="keyword_text" value="${row.keyword_text}" class="form-control"></td>
    <td><input data-column="keyword_url"  name="keyword_url"  value="${row.keyword_url}"  class="form-control"></td>
    <td>${renderSelect(languageOptions, {column: 'language_id'}).outerHTML}</td>
    <td>${renderSelect(storeOptions, {column: 'store_id'}).outerHTML}</td>
    <td class="text-center">${rowTypeLabel}</td>
    <td class="text-center">
      <div class="btn-group">
        <button type="button" class="btn btn-default" data-copy-row=""><i class="fa fa-copy"></i></button>
        <button type="button" class="btn btn-danger"  data-remove-row=""><i class="fa fa-times"></i></button>
      </div>
    </td>
  `;
  // Set select value
  const langSelect = tr.querySelector('select[data-column="language_id"]');
  langSelect.value = row.language_id || langSelect.options[0].value;
  langSelect.name = 'language_id';
  // Set select value
  const storeSelect = tr.querySelector('select[data-column="store_id"]');
  storeSelect.value = row.store_id || storeSelect.options[0].value;
  storeSelect.name = 'store_id';

  return tr;
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

function addRow(keywordTable, newRow) {
  const newData = {};
  newRow.querySelectorAll('input, select').forEach(element => {
    console.log(element.value);
    newData[element.dataset.addRowColumn] = element.value || '';
  });
  newData.rowType = 'newRow';
  const table = keywordTable.setData([newData], true);
  keywordTable.setPage(keywordTable.getTotalPages());
  table.lastChild.scrollIntoView({block: "nearest", inline: "nearest"});
  const data = new FormData();
  for (const key in newData) {
    data.append(key, newData[key]);
  }
  fetch(`index.php?route=seo/keyword/fetchSaveKeywords&user_token=${user_token}`, {method: "POST", body: data})
}