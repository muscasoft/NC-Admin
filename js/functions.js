// v2 version check included
// 06/11/2025 : Content Type in header set to 'application/json', so json.encode should not be called anymore
// 06/11/2025 : Spaces removed or added
// 26/11/2025 : Ajax calls replace by fetch in doFetch; json() and text() calls included in doFetch
// 26/11/2025 : Minor changes in names and texts
// 26/11/2025 : Centralize code for modal windows in createModal and disable/enable buttons to prevent double clicking
// 26/11/2025 : Move JSON.stringify from addToLogData to calling codes

const ncVersion = document.getElementById('ncVersion');
const updateRunning = document.getElementById('updateRunning');
const diskStatistics = document.getElementById('diskStatistics');
const backups  = document.getElementById('backups');
const setupChecks = document.getElementById('setupChecks');
const logData = document.getElementById('logData');
const logNC = document.getElementById('logNC');
updateNCVersion();
updateUpdateRunning();
updateDiskStatistics();
updateBackups();
updateSetupChecksStart();
updateLogNC();

async function doFetch(action, data = {}) {
  try {
    const params = new URLSearchParams({ action, ...data });

    const response = await fetch('php/main.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: params
    });

    const contentType = response.headers.get("Content-Type")?.toLowerCase() || "";

    if (!response.ok) {
      console.log(action, '--> Error:', response.status);
      throw {
        ok: false,
        status: response.status,
        data: response.statusText || `HTTP error ${response.status}`,
        raw: response
      }
    }

    if (contentType.includes("application/json")) {
      result = await response.json();
      console.log(action, '--> success (json):', result);
    } else

    if (contentType.includes("text/html")) {
      result = await response.text();
      console.log(action, '--> success (text):', result);
    } else

    if (response.status === 204) {
      result = null;
      console.log(action, '--> success (null):', result);
    }

    // Create error also when response code for example is 300 or 500 
    if (!response.ok) {
      console.log(action, '--> Error:', response.status);
      throw {
        ok: false,
        status: response.status,
        data: response.statusText || `HTTP error ${response.status}`,
        raw: response
      }
    }

    return {
      ok: true,
      status: response.status,
      data: result,
      raw: response
    }

  } catch (error) {
    if (error instanceof TypeError) {
      // fetch() netwerk error (bv CORS, offline, DNS)
      throw {
        ok: false,
        status: 0,
        data: error.message || "Network error",
        raw: error
      }
    };

    if (typeof error === "object" && error.ok === false) {
      throw error; // our own uniform error
    };
    
    // --- Unknown error ---
    throw {
      ok: false,
      status: 0,
      data: error?.message || "Unknown error",
      raw: error
    }; 
  }
}

async function updateNCVersion() {
  try {
    const returnValue = await doFetch('GetNCVersion');
    ncVersion.innerText = returnValue.data;

  } catch (error) {
      ncVersion.innerText = 'Version not available.\n' + error.data;
      alert('Version not available.\n' + error.data);
  }
}

async function updateUpdateRunning() {
  try {
    const returnValue = await doFetch('IsUpdateRunning');
      if (returnValue.data === 1) {
        updateRunning.innerText = 'Update running          ';
        const resetUpdateRunning = parent.document.createElement('button');
        resetUpdateRunning.innerText = 'Reset update';
        resetUpdateRunning.addEventListener('click', function(){ resetRunningUpdate() } );
        updateRunning.appendChild(resetUpdateRunning);
      }
      else {
        updateRunning.innerText = 'No update running';     
      }
  } catch (error) {
      updateRunning.innerText = 'Update information not available\n' + error.data;     
      alert('Update information not available.\n' + error.data);
  }
}

async function updateDiskStatistics() {
  try {
    const returnValue = await doFetch('GetDiskStatistics');
    diskStatistics.innerText = (returnValue.data / 1000000).toLocaleString('nl-nl', {maximumFractionDigits: 1}) + ' MB';

  } catch (error) {
      diskStatistics.innerText = 'Error while getting disk statistics.\n' + error.data;
      alert('Error while getting disk statistics.\n' + error.data);
  }
}

function updateBackups() {
  const backupButtonGroup = document.createElement('div');
  backupButtonGroup.className = 'backupButtonGroup';

  const latestBackup = parent.document.createElement('div');
  latestBackup.id = 'latestBackup';
  latestBackup.innerText = 'Please wait';
  latestBackup.addEventListener('click', makeBackup);
  backupButtonGroup.appendChild(latestBackup);

  const makeBackupButton = parent.document.createElement('button');
  makeBackupButton.id = 'makeBackup';
  makeBackupButton.innerText = 'Make backup';
  makeBackupButton.style.marginRight = '10px';
  makeBackupButton.addEventListener('click', makeBackup);
  backupButtonGroup.appendChild(makeBackupButton);

  const listBackupsButton = parent.document.createElement('button');
  listBackupsButton.id = 'listBackups';
  listBackupsButton.innerText = 'List backups';
  listBackupsButton.style.marginRight = '10px';
  listBackupsButton.addEventListener('click', listBackups);
  backupButtonGroup.appendChild(listBackupsButton);

  const deleteBackupsButton = parent.document.createElement('button');
  deleteBackupsButton.id = 'deleteBackups';
  deleteBackupsButton.innerText = 'Delete backups';
  deleteBackupsButton.addEventListener('click', selectAndDeleteBackups);
  backupButtonGroup.appendChild(deleteBackupsButton);

  backups.innerText = '';
  backups.append(backupButtonGroup);
  
  updateLastBackupTime();
}

async function updateLastBackupTime() {
  try {
    const returnValue = await doFetch('GetLatestBackupFile');;
    const latestBackup = document.getElementById('latestBackup');
    latestBackup.innerText = 'Last back-up date is ' + returnValue.data.last_modified;
  } catch (error) {
      const latestBackup = document.getElementById('latestBackup');
      latestBackup.innerText = 'No last back-up date known.\n' + error.data;
      alert('No last back-up date known.\n' + error.data);
  }
}

async function makeBackup() {
  try {
    const returnValue = await doFetch('MakeBackupDatabase');
    alert('Back-up successfull');
    updateLastBackupTime();
  } catch (error) {
      alert('Back-up not successfull.\n' + error.data);
  }
}

async function listBackups() {
  try {
    const returnValue = await doFetch('ListBackupFiles');
    listBackupFilesInPopupWindows(returnValue.data);
  } catch (error) {
      alert('No back-up files found.\n' + error.data);
  }
}

function createModal() {
  const modal = document.createElement('div');
  Object.assign(modal.style, {
      position: 'fixed',
      top: 0, left: 0, width: '100%', height: '100%',
      backgroundColor: 'rgba(0,0,0,0.5)',
      display: 'flex', justifyContent: 'center', alignItems: 'center',
      zIndex: 1,
  });

  const content = document.createElement('div');
  Object.assign(content.style, {
      backgroundColor: '#fff',
      padding: '20px',
      borderRadius: '8px',
      width: '50%',
      maxWidth: '80%',
      minWidth: '200px',
      maxHeight: '80vh',
      overflowY: 'auto',
      resize: 'horizontal',
      overflow: 'auto',
      boxShadow: '0 4px 10px rgba(0,0,0,0.3)',
      display: 'flex',
      flexDirection: 'column'
  });

  modal.tabIndex = -1;

  const okBtn = document.createElement('button');
  okBtn.textContent = 'OK';
  Object.assign(okBtn.style, {marginTop: '20px', padding: '6px 12px', alignSelf: 'flex-end'});

  content.appendChild(okBtn);
  modal.appendChild(content);

  window.addEventListener('keydown', function tabListener(e) {
    if (e.key === 'Tab') {
        e.preventDefault(); // // keep focus in modal
    }
  });
  return { modal, content, okBtn };
}

function listBackupFilesInPopupWindows(files)
{
  if (files.length == 0) {
    alert('No back-up files found');
    return;
  }
  
  const listBackupsButton = document.getElementById('listBackups');
  listBackupsButton.disabled = true;

  const { modal, content, okBtn } = createModal();

  const ul = document.createElement('ul');
  files.forEach(f => {
    const li = document.createElement('li');
    li.textContent = f.name;
    ul.appendChild(li);
  });
  content.insertBefore(ul, okBtn);

  document.body.appendChild(modal);

  const cleanup = () => {
    document.body.removeChild(modal);
    listBackupsButton.disabled = false;
    listBackupsButton.focus();
    window.removeEventListener('keydown', escListener);
  };
  
  const escListener = (e) => {
    if (e.key === 'Escape') {
      cleanup();
    }
  };

  okBtn.addEventListener('click', () => {
    cleanup();
  });

  window.addEventListener('keydown', escListener);
}

async function selectAndDeleteBackups() {
  try {
    const returnValue = await doFetch('ListBackupFiles');
    const filenamesWithHashes = returnValue.data;

    const selectedNames = await selectBackupFilesInPopupWindows(filenamesWithHashes);

    const filteredFilenamesWithHashes = await filenamesWithHashes.filter(file => selectedNames.includes(file.name));

    if (filteredFilenamesWithHashes.length == 0) {
      alert('No back-up files deleted');
    } else {
      deleteBackups(filteredFilenamesWithHashes);
      updateLastBackupTime();
    }
  } catch (error) {
      alert('No back-up files found.\n' + error.data);
  }
}

async function selectBackupFilesInPopupWindows(filenamesWithHashes)
{
  if (filenamesWithHashes.length == 0) {
    alert('No back-up files found');
    return;
  }
  
  const deleteBackupsButton = document.getElementById('deleteBackups');
  deleteBackupsButton.disabled = true;

  const { modal, content, okBtn } = createModal();

  const form = document.createElement('form');
  filenamesWithHashes.forEach((fileWithHash, idx) => {
      const label = document.createElement('label');
      label.style.display = 'block';
      const cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.value = fileWithHash.name;
      cb.id = 'checkbox_${idx}';
      label.appendChild(cb);
      label.appendChild(document.createTextNode(' ' + fileWithHash.name));
      form.appendChild(label);
  });
  content.insertBefore(form, okBtn);

  document.body.appendChild(modal);
  
  const result = await new Promise((resolve) => {
    const cleanup = (result = '') => {
      document.body.removeChild(modal);
      deleteBackupsButton.disabled = false;
      deleteBackupsButton.focus();
      window.removeEventListener('keydown', escListener);
      resolve(result);
    };

    okBtn.addEventListener('click', (e) => {
        e.preventDefault(); // // keep focus in modal
        const checked = Array.from(form.querySelectorAll('input[type=checkbox]:checked')).map(cb => cb.value);
        cleanup(JSON.stringify(checked));
    });

    const escListener = (e) => {
      if (e.key === 'Escape') {
        cleanup();
      }
    };
    window.addEventListener('keydown', escListener);
  });
  return result;
}

async function deleteBackups(filteredFilenamesWithHashes) {
  try {
      const returnValue = await doFetch('DeleteBackupFiles', {FilenamesWithHashes: JSON.stringify(filteredFilenamesWithHashes)});
      alert('Selected back-up files successfully deleted.');
    } catch (error) {
    alert('Error deletion back-up files.\n' + error.data);
  }
}

async function resetRunningUpdate(id) {
  try {
    const returnValue = await doFetch('ResetUpdateRunning');
    alert ('Reset successfull\n');
    updateRunning.innerText = 'No update running';     
  } catch (error) {
    alert('Error while resetting running update.\n' + error.data);
  }
}

async function updateSetupChecksStart() {
  try {
    const [setupChecks, knownSetupChecks, skipRepairSetupChecks] = await Promise.all([
      doFetch('GetSetupChecks'),
      doFetch('DefinedActions'),
      doFetch('SkipRepairSetupChecks')
    ]);
    processSetupChecks(setupChecks.data, knownSetupChecks.data, skipRepairSetupChecks.data);

  } catch (error) {
    const errorText = await error.data();
    alert('Repair not successful.\n' + errorText);
  }
}

function processSetupChecks(mySetupChecks, knownSetupChecks, skipRepairSetupChecks) { 
  setupChecks.innerText = '';
  
  const warningsSection = parent.document.createElement('div');
  warningsSection.className = 'warnings';
  warningsSection.id = 'warnings';
  setupChecks.appendChild(warningsSection);
  
  const infoSection = parent.document.createElement('div');
  infoSection.className = 'info';
  infoSection.id = 'info';
  setupChecks.appendChild(infoSection);

  for (let i = 0; i < mySetupChecks.length - 1; i++) {
    const idArray = mySetupChecks[i].id.split('\\');
    const id = idArray[idArray.length - 1];
    const isSetupCheckDefined = (id in knownSetupChecks);
    const isRepairDefined = isSetupCheckDefined ? knownSetupChecks[id] : false;
    const skipRepairSetupCheck = skipRepairSetupChecks.includes(id);

    const setupCheckSection = parent.document.createElement('div');
    setupCheckSection.id = id;

    const setupCheckName = parent.document.createElement('h2');
    setupCheckName.innerText = mySetupChecks[i].name + '          ';
    
    if (!skipRepairSetupCheck) {
      if (isRepairDefined) {
        const setupCheckRepairButton = parent.document.createElement('button');
        setupCheckRepairButton.id = `buttonRepair${id}`;
        setupCheckRepairButton.innerText = 'repair';
        setupCheckRepairButton.addEventListener('click', function(){ startPhpFunction(id) } );
        setupCheckName.appendChild(setupCheckRepairButton);
      }

      const setupCheckDescription = parent.document.createElement('p');
      setupCheckDescription.innerText = mySetupChecks[i].description;
      
      setupCheckSection.appendChild(setupCheckName);
      setupCheckSection.appendChild(setupCheckDescription);

      const severitySection = mySetupChecks[i].severity == 'warning' ? warningsSection : infoSection;
      severitySection.appendChild(setupCheckSection);

      addToLogData(JSON.stringify(mySetupChecks[mySetupChecks.length - 1], '##', 2)); 
    }     
  }
}

async function startPhpFunction(id) {
  try {
    const setupCheckRepairButton = document.getElementById(`buttonRepair${id}`);
    setupCheckRepairButton.disabled = true;

    const returnValue = await doFetch(id);

    alert ('Repair successfull\n')
    addToLogData(returnValue.data);

    const setupCheckSection = document.getElementById(id);
    setupCheckSection.style.textDecoration = "line-through";
  } catch (error) {
    alert('Repair not successfull.\n' + error.data);
  }
}

function addToLogData(logText) {
  logData.innerText = logData.innerText == '-' ?  '' : '-----------\n' + logData.innerText;
  logData.innerText = logText + '\n' + logData.innerText;
}

function updateLogNC() {
  // Event listeners

  buildLogNCTable();
  loadNCLogs();
}

function buildLogNCTable() {
  const daysLabel = document.createElement('label');
  daysLabel.setAttribute('for','daysSelect');
  daysLabel.textContent = 'Number of days to select:';
  logNC.appendChild(daysLabel);

  const daysSelect = document.createElement('select');
  daysSelect.id = 'daysSelect';
  logNC.appendChild(daysSelect);

  for(let i=1; i<=30; i++){
    const day = document.createElement('option');
    day.value = i;
    day.textContent = i;
    daysSelect.appendChild(day);
  }

  daysSelect.addEventListener('change', renderNCLogs);
  daysSelect.value = 10; // start value 10 days

  const fieldset = document.createElement('fieldset');
  fieldset.style.marginTop = '10px';
  fieldset.style.marginBottom = '10px';
  fieldset.style.display = 'flex';
  fieldset.style.alignItems = 'center';
  fieldset.style.flexWrap = 'wrap';

  const legend = document.createElement('legend');
  legend.textContent = 'Filter on level:';
  fieldset.appendChild(legend);

  const levels = [
    {val:1, text:'Info'},
    {val:2, text:'Warning'},
    {val:3, text:'Error'},
    {val:4, text:'Fataal'}
  ];

  levels.forEach(level=>{
    const label = document.createElement('label');
    label.style.marginRight = '10px';
    label.style.display = 'flex';
    label.style.alignItems = 'center';
    label.style.lineHeight = '1.2'

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.className = 'levelCheckbox';
    checkbox.value = level.val;
    checkbox.checked = level.val>2;
    
    label.appendChild(checkbox);
    label.appendChild(document.createTextNode(' ' + level.text));
    
    fieldset.appendChild(label);
  });

  const levelCheckboxes = fieldset.querySelectorAll('.levelCheckbox');
  levelCheckboxes.forEach(checkbox => checkbox.addEventListener('change', renderNCLogs));

  logCounter = document.createElement('p');
  logCounter.id = 'logCounter';
  logCounter.style.marginLeft = '40px';
  logCounter.style.lineHeight = '1.2';
  logCounter.style.alignSelf = 'flex-start';

  fieldset.appendChild(logCounter);

  logNC.appendChild(fieldset);

  const logTable = document.createElement('table');
  logTable.id = 'logTable';

  const thead = document.createElement('thead');
  const trHead = document.createElement('tr');
  ['Tijd','Level','App','User','Message'].forEach(columnTitle=>{
      const th = document.createElement('th');
      th.textContent = columnTitle;
      trHead.appendChild(th);
  });
  thead.appendChild(trHead);
  logTable.appendChild(thead);

  const tbody = document.createElement('tbody');
  logTable.appendChild(tbody);

  logNC.appendChild(logTable);  
}

async function loadNCLogs() {
  try {
    const returnValue = await doFetch('GetLogData');
    // Log level must be saved as an integer
    logs = await returnValue.data.map(log => ({ ...log, level: parseInt(log.level) }));
    renderNCLogs();
  } catch (error) {
    alert('Error retrieving the logs.\n' + error.data);
  }
}

function renderNCLogs() {
  const daysSelect = document.getElementById('daysSelect');
  const days = parseInt(daysSelect.value);

  const levelCheckboxes = document.querySelectorAll('.levelCheckbox');
  const selectedLevels = Array.from(levelCheckboxes)
      .filter(checkbox => checkbox.checked)
      .map(checkbox => parseInt(checkbox.value));

  const now = new Date();
  const cutoff = new Date(now.getTime() - days*24*60*60*1000);

  const tbody = document.querySelector('#logTable tbody');
  tbody.innerHTML = '';

  const filtered = logs
      .filter(log => new Date(log.time) >= cutoff && selectedLevels.includes(log.level))
      .sort((Date1,Date2) => new Date(Date2.time) - new Date(Date1.time));

  let logCounter = document.getElementById('logCounter');
  logCounter.textContent = `Total filtered logs: ${filtered.length}`;

  filtered.forEach(log => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
          <td>${formatDate(log.time)}</td>
          <td class="level-${log.level}">${log.level}</td>
          <td>${log.app}</td>
          <td>${log.user}</td>
          <td>${log.message}</td>
      `;
      tbody.appendChild(tr);
  });
}

function formatDate(isoString) {
    const date = new Date(isoString);
    const yyyy = date.getFullYear();
    const dd = String(date.getDate()).padStart(2,'0');
    const mm = String(date.getMonth()+1).padStart(2,'0');
    const hh = String(date.getHours()).padStart(2,'0');
    const min = String(date.getMinutes()).padStart(2,'0');
    const ss = String(date.getSeconds()).padStart(2,'0');
    return `${yyyy}-${dd}-${mm} ${hh}:${min}:${ss}`;
}