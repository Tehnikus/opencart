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