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
    },
    onRowDelete: async (row) => {
      // Delete rows from DB
      const body = new FormData();
      body.append('keywords[]', row.keyword_id);
      await fetch(`index.php?route=seo/keyword/fetchDeleteKeywords&user_token=${user_token}`, {method: "POST", body});
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
      copyRow(keywordTable);
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

  const languageSelect  = interface.languageSelect.cloneNode(true);
  const storeSelect     = interface.storeSelect.cloneNode(true);
  const groupSelect     = interface.groupSelect.cloneNode(true);
  storeSelect.name     = storeSelect.dataset.column     = 'store_id';
  languageSelect.name  = languageSelect.dataset.column  = 'language_id';
  groupSelect.name     = groupSelect.dataset.column     = 'keyword_group_id';

  [...languageSelect.options].forEach(opt => {
    if (opt.value == row.language_id) {
      opt.setAttribute('selected', 'selected');
    }
  });
  [...storeSelect.options].forEach(opt => {
    if (opt.value == row.store_id) {
      opt.setAttribute('selected', 'selected');
    }
  });
  [...groupSelect.options].forEach(opt => {
    if (opt.value == row.keyword_group_id) {
      opt.setAttribute('selected', 'selected');
    }
  });

  tr.innerHTML = `
    <td><input data-column="keyword_text" name="keyword_text" value="${row.keyword_text}" class="form-control"></td>
    <td><input data-column="keyword_url"  name="keyword_url"  value="${row.keyword_url}"  class="form-control"></td>
    <td>${languageSelect.outerHTML}</td>
    <td>${storeSelect.outerHTML}</td>
    <td>${groupSelect.outerHTML}</td>
    <td class="text-center">${rowTypeLabel}</td>
    <td class="text-center">
      <div class="btn-group">
        <button type="button" class="btn btn-default" data-copy-row=""><i class="fa fa-copy"></i></button>
        <button type="button" class="btn btn-danger"  data-remove-row=""><i class="fa fa-times"></i></button>
      </div>
    </td>
  `;
  
  return tr;
}

function renderHeader(interface) {
  const thead           = document.createElement('thead');

  // Create filter selects
  const languageSelect  = interface.languageSelect.cloneNode(true);
  const storeSelect     = interface.storeSelect.cloneNode(true);
  const groupSelect     = interface.groupSelect.cloneNode(true);
  // Add empty values to filter selects
  languageSelect.add(Object.assign(document.createElement('option'), {value: '', textContent: interface.lang.column_language}), languageSelect.options[0]);
  storeSelect.add(Object.assign(document.createElement('option'), {value: '', textContent: interface.lang.column_store}), storeSelect.options[0]);
  groupSelect.add(Object.assign(document.createElement('option'), {value: '', textContent: interface.lang.column_group}), groupSelect.options[0]);

  // Set dataset
  languageSelect.dataset.searchColumn = 'language_id';
  storeSelect.dataset.searchColumn    = 'store_id';
  groupSelect.dataset.searchColumn    = 'keyword_group_id';

  // Row type select options 
  const rowTypeOptions  = [{value: '', label: interface.lang.option_all_types}, {value: 'existing', label: interface.lang.option_existing}, {value: 'updatedRow', label: interface.lang.option_updated}, {value: 'newRow', label: interface.lang.option_new}, {value: 'importedRow', label: interface.lang.option_imported}];
  const filterRowTypeSelect  = renderSelect(rowTypeOptions,  {searchColumn: 'rowType'});

  // Render addRow selects
  const addRowLangSelect  = interface.languageSelect.cloneNode(true);
  const addRowStoreSelect = interface.storeSelect.cloneNode(true);
  const addRowGroupSelect = interface.groupSelect.cloneNode(true);
  addRowLangSelect.dataset.addRowColumn   = 'language_id';
  addRowStoreSelect.dataset.addRowColumn  = 'store_id';
  addRowGroupSelect.dataset.addRowColumn  = 'keyword_group_id';
  

  thead.innerHTML = `
    <tr>
      <th style="width: auto"   class="text-center"><input type="text" class="form-control" data-search-column="keyword_text" placeholder="${interface.lang.text_search} ${interface.lang.column_seo_keyword}"></th>
      <th style="width: auto"   class="text-center"><input type="text" class="form-control" data-search-column="keyword_url" placeholder="${interface.lang.text_search} ${interface.lang.column_url}"></th>
      <th style="width: 180px"  class="text-center">${languageSelect.outerHTML}</th>
      <th style="width: 180px"  class="text-center">${storeSelect.outerHTML}</th>
      <th style="width: 180px"  class="text-center">${groupSelect.outerHTML}</th>
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
         ${addRowLangSelect.outerHTML}<button type="button" class="btn btn-warning replace"><i class="fa fa-random"></i></button>
        </div>
      </th>
      <th class="text-center">
        <div class="input-group">
         ${addRowStoreSelect.outerHTML}<button type="button" class="btn btn-warning replace"><i class="fa fa-random"></i></button>
        </div>
      </th>
      <th class="text-center">
        <div class="input-group">
         ${addRowGroupSelect.outerHTML}<button type="button" class="btn btn-warning replace"><i class="fa fa-random"></i></button>
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

// Add row from form
function addRow(keywordTable, newRow) {
  const newData = {};
  const returnFlag = false;
  newRow.querySelectorAll('input, select').forEach(element => {
    if (element.tagName === 'INPUT' && element.value === '') {
      element.classList.add('alert-danger');
      returnFlag = true;
    } else {
      element.classList.remove('alert-danger');
    }
    newData[element.dataset.addRowColumn] = element.value || '';
  });
  if (returnFlag) {
    return;
  }
  newData.rowType = 'newRow';
  const table = keywordTable.setData([newData], true);
  keywordTable.setPage(keywordTable.getTotalPages());
  table.lastChild.scrollIntoView({block: "nearest", inline: "nearest"});
  saveKeyword(newData);
}

// copy existing row
function copyRow(keywordTable) {
  const id = Number(e.target.closest('[data-id]').dataset.id);
  const rowData = {...keywordTable.rowMap.get(id)}; // Copy row instead of reusing it, because in JavaScript objects are reference types (assignments copy references, not the actual object)
  delete rowData.keyword_id; // Delete values that are treated as row identifier. If not deleted, Map() will skip duplicate ids
  delete rowData.id; // Delete values that are treated as row identifier. If not deleted, Map() will skip duplicate ids
  rowData.rowType = 'newRow';
  keywordTable.setData([rowData]);
  saveKeyword(rowData);
}
}