// --- ATTENDANCE TRACKER LOGIC ---

// 1. Handle Date Picker Change
function changeAttendanceDate(date) {
    // Reload page with the selected date as a GET parameter
    const url = new URL(window.location.href);
    url.searchParams.set('date', date);
    window.location.href = url.toString();
}

// 2. Mark Attendance
function markAttendance(subject, status, safeId, dateOverride) {
    // Prepare Data
    const formData = new FormData();
    formData.append('action', 'mark_attendance');
    formData.append('subject', subject);
    formData.append('status', status);

    // Pass the specific date being modified
    if (dateOverride) {
        formData.append('date', dateOverride);
    }

    // UI Feedback (Immediate)
    const statusDiv = document.getElementById('status-' + safeId);
    if(statusDiv) {
        // Show spinner
        statusDiv.innerHTML = '<span class="text-xs text-blue-500 italic flex items-center gap-1"><i data-lucide="loader-2" class="w-3 h-3 animate-spin"></i> Updating...</span>';
        if(typeof lucide !== 'undefined') lucide.createIcons();
    }

    // Send Request
    fetch('attendance_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            // Update Status Text
            if(statusDiv) {
                let colorClass = 'text-slate-500';
                if(data.percent < 75) colorClass = 'text-red-500';
                else colorClass = 'text-green-600';

                // Display new percentage and status
                statusDiv.innerHTML = `
                    <span class="font-bold ${colorClass}">${data.percent}%</span> 
                    <span class="text-slate-500 dark:text-slate-400 text-xs ml-1">• ${data.message}</span>
                    <div class="block text-[10px] text-blue-600 mt-1 font-semibold">Recorded: ${status}</div>
                `;
            }
            // Note: We do NOT disable buttons anymore, allowing students to change their mind.
        } else {
            if(statusDiv) statusDiv.innerHTML = '<span class="text-red-500 text-xs font-semibold">Error: ' + (data.message || 'Failed') + '</span>';
            alert("Error: " + data.message);
        }
    })
    .catch(err => {
        console.error("Attendance Error:", err);
        if(statusDiv) statusDiv.innerHTML = '<span class="text-red-500 text-xs font-semibold">Network Error</span>';
    });
}

// --- STUDY RESOURCE LOGIC (Existing) ---
function updateSubjects() {
    const groupSelect = document.getElementById('group');
    if (!groupSelect) return; 
    
    const group = groupSelect.value;
    const subjectsContainer = document.getElementById('subjectsContainer');
    const subjectsSection = document.querySelector('.subjects-section');
    const emptyState = document.getElementById('emptyState');
    
    if (subjectsContainer) subjectsContainer.innerHTML = ''; 

    if (group && group !== 'None') {
        if (subjectsSection) subjectsSection.style.display = 'block'; 
        if (emptyState) emptyState.style.display = 'none';

        fetch(`fetch_subjects.php?group=${group}`)
            .then(response => response.json())
            .then(data => {
                if (subjectsContainer) {
                    if (data.length > 0) {
                        data.forEach(subject => {
                            const tile = document.createElement('div');
                            tile.className = 'group bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-5 hover:border-blue-300 dark:hover:border-blue-500 hover:shadow-md transition-all cursor-pointer flex items-center gap-4';

                            const img = document.createElement('img');
                            img.src = `./images/${subject.toLowerCase()}.png`;
                            img.alt = `${subject}`;
                            img.className = 'w-12 h-12 object-contain group-hover:scale-110 transition-transform';
                            img.onerror = function() { this.style.display='none'; };

                            const textDiv = document.createElement('div');
                            const name = document.createElement('h3');
                            name.textContent = subject;
                            name.className = 'font-bold text-slate-800 dark:text-slate-100 group-hover:text-blue-700 dark:group-hover:text-blue-400 transition-colors';
                            
                            const subText = document.createElement('p');
                            subText.textContent = "View Resources";
                            subText.className = 'text-xs text-slate-500 dark:text-slate-400 mt-1';

                            textDiv.appendChild(name);
                            textDiv.appendChild(subText);
                            tile.appendChild(img);
                            tile.appendChild(textDiv);

                            tile.onclick = () => showResourceSelection(subject);
                            subjectsContainer.appendChild(tile);
                        });
                    } else {
                        subjectsContainer.innerHTML = '<p class="text-slate-500 dark:text-slate-400 col-span-full text-center py-4">No subjects available for this group.</p>';
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching subjects:', error);
                if (subjectsContainer) subjectsContainer.innerHTML = '<p class="text-red-500 col-span-full text-center">Error loading subjects.</p>';
            });
    } else {
        if (subjectsSection) subjectsSection.style.display = 'none'; 
        if (emptyState) emptyState.style.display = 'block';
    }
}

function showResourceSelection(subject) {
    const subjectsSection = document.querySelector('.subjects-section');
    if (subjectsSection) subjectsSection.style.display = 'none';
    
    const emptyState = document.getElementById('emptyState');
    if (emptyState) emptyState.style.display = 'none';
    
    const resourcePanel = document.getElementById('resourceSelectionPanel');
    if (resourcePanel) {
        resourcePanel.style.display = 'block';
        const titleEl = document.getElementById('resourceTitle');
        if(titleEl) titleEl.textContent = `${subject}`;
        resourcePanel.setAttribute('data-subject', subject); 
        fetchFolders(subject);
    }
}

function fetchFolders(subject) {
    const resourceDisplayContainer = document.getElementById('resourceDisplayContainer');
    if (!resourceDisplayContainer) return;

    resourceDisplayContainer.innerHTML = '<p class="text-slate-400 text-sm">Loading folders...</p>'; 

    const groupEl = document.getElementById('group');
    const group = groupEl ? groupEl.value : '';

    fetch(`fetch_resources.php?group=${group}&subject=${subject}`)
        .then(response => response.json())
        .then(data => {
            resourceDisplayContainer.innerHTML = ''; 
            if (data.folders && data.folders.length > 0) {
                data.folders.forEach(folder => {
                    const folderButton = document.createElement('button');
                    folderButton.className = 'flex items-center gap-3 w-full p-4 bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:border-blue-200 dark:hover:border-blue-500 hover:text-blue-700 dark:hover:text-blue-400 transition-all group text-left';
                    const iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-500 group-hover:scale-110 transition-transform pointer-events-none"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>`;
                    const nameSpan = document.createElement('span');
                    nameSpan.textContent = folder;
                    nameSpan.className = 'font-semibold text-slate-700 dark:text-slate-200 group-hover:text-blue-700 dark:group-hover:text-blue-400 pointer-events-none';
                    folderButton.innerHTML = iconSvg;
                    folderButton.appendChild(nameSpan);
                    folderButton.onclick = () => updateResourceContent(folder);
                    resourceDisplayContainer.appendChild(folderButton);
                });
            } else {
                resourceDisplayContainer.innerHTML = '<p class="text-slate-500 dark:text-slate-400 col-span-full">No folders available.</p>';
            }
        })
        .catch(error => {
            console.error('Error fetching folders:', error);
            resourceDisplayContainer.innerHTML = '<p class="text-red-500">Error loading folders.</p>';
        });
}

function updateResourceContent(selectedFolder) {
    const resPanel = document.getElementById('resourceSelectionPanel');
    const folderPanel = document.getElementById('folderDisplayPanel');
    if(resPanel) resPanel.style.display = 'none';
    if(folderPanel) folderPanel.style.display = 'block';

    const subject = resPanel ? resPanel.getAttribute('data-subject') : '';
    const groupEl = document.getElementById('group');
    const group = groupEl ? groupEl.value : '';
    const url = `fetch_resources.php?group=${encodeURIComponent(group)}&subject=${encodeURIComponent(subject)}&folder=${encodeURIComponent(selectedFolder)}`;
    const folderContent = document.getElementById('folderContent');
    if (!folderContent) return;
    folderContent.innerHTML = '<p class="text-slate-400 text-sm">Loading files...</p>';

    fetch(url)
    .then(response => response.json()) 
    .then(data => {
        folderContent.innerHTML = ''; 
        if (data.files && data.files.length > 0) {
            data.files.forEach(file => {
                const button = document.createElement('button');
                button.className = 'flex items-center justify-between w-full p-3 bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700 rounded-lg hover:border-blue-300 dark:hover:border-blue-500 hover:shadow-sm transition-all text-left group';
                const leftDiv = document.createElement('div');
                leftDiv.className = 'flex items-center gap-3 pointer-events-none';
                leftDiv.innerHTML = `<div class="p-2 bg-slate-50 dark:bg-slate-700 rounded-md group-hover:bg-blue-50 dark:group-hover:bg-blue-900/30"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-slate-400 dark:text-slate-500 group-hover:text-blue-500 dark:group-hover:text-blue-400"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg></div><span class="text-sm font-medium text-slate-700 dark:text-slate-200 group-hover:text-blue-700 dark:group-hover:text-blue-400 truncate max-w-[200px] sm:max-w-md">${file}</span>`;
                const rightDiv = document.createElement('div');
                rightDiv.className = 'pointer-events-none';
                rightDiv.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-slate-300 dark:text-slate-600 group-hover:text-blue-500 dark:group-hover:text-blue-400"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>`;
                button.appendChild(leftDiv);
                button.appendChild(rightDiv);
                button.onclick = () => openFile(file, subject, group, selectedFolder);
                folderContent.appendChild(button);
            });
        }
        if (data.folders && data.folders.length > 0) {
            data.folders.forEach(folder => {
                const button = document.createElement('button');
                button.textContent = folder;
                button.className = 'w-full text-left p-3 bg-slate-50 dark:bg-slate-700 rounded-lg text-slate-700 dark:text-slate-200 font-medium hover:bg-slate-100 dark:hover:bg-slate-600';
                button.onclick = () => updateResourceContent(folder); 
                folderContent.appendChild(button);
            });
        }
        if ((!data.files || data.files.length === 0) && (!data.folders || data.folders.length === 0)) {
            folderContent.innerHTML = '<p class="text-slate-500 dark:text-slate-400 text-center py-4">No files available.</p>';
        }
    })
    .catch(error => {
        console.error('Error fetching folder contents:', error);
        folderContent.innerHTML = '<p class="text-red-500">Error loading content.</p>';
    });
}

function openFile(fileName, subject, group, folder) {
    sessionStorage.setItem('currentGroup', group);
    sessionStorage.setItem('currentSubject', subject);
    sessionStorage.setItem('currentFolder', folder);
    const url = `resource_viewer.php?file=${encodeURIComponent(fileName)}&subject=${encodeURIComponent(subject)}&group=${encodeURIComponent(group)}&folder=${encodeURIComponent(folder)}`;
    window.location.href = url;
}

function goBackToSubjectPanel() {
    const resPanel = document.getElementById('resourceSelectionPanel');
    const folderPanel = document.getElementById('folderDisplayPanel');
    if(resPanel) resPanel.style.display = 'none';
    if(folderPanel) folderPanel.style.display = 'none';
    const groupEl = document.getElementById('group');
    const group = groupEl ? groupEl.value : 'None';
    const subjectsSection = document.querySelector('.subjects-section');
    const emptyState = document.getElementById('emptyState');
    if (group === 'None') {
         if(emptyState) emptyState.style.display = 'block';
         if(subjectsSection) subjectsSection.style.display = 'none';
    } else {
         if(emptyState) emptyState.style.display = 'none';
         if(subjectsSection) subjectsSection.style.display = 'block';
    }
}

function goBackToResourceSelectionPanel() {
    const folderPanel = document.getElementById('folderDisplayPanel');
    const resPanel = document.getElementById('resourceSelectionPanel');
    if(folderPanel) folderPanel.style.display = 'none';
    if(resPanel) resPanel.style.display = 'block';
}

function restorePreviousState() {
    let group = sessionStorage.getItem('currentGroup');
    const subject = sessionStorage.getItem('currentSubject');
    const folder = sessionStorage.getItem('currentFolder');
    if ((!group || group === 'None') && typeof USER_ROLL_NO !== 'undefined' && USER_ROLL_NO && USER_ROLL_NO !== 'Loading...') {
        const cleanRoll = USER_ROLL_NO.trim();
        if (cleanRoll.length >= 3) {
            const sectionChar = cleanRoll.charAt(2).toUpperCase();
            if (['A', 'B', 'C', 'D', 'E'].includes(sectionChar) || cleanRoll.toUpperCase().includes('E')) {
                group = 'ST';
            } else if (['F', 'G', 'H', 'I', 'J'].includes(sectionChar)) {
                group = 'MT';
            }
        }
    }
    if (group && group !== 'None') {
        const groupSelect = document.getElementById('group');
        if(groupSelect) {
            groupSelect.value = group;
            updateSubjects();
        }
        if (subject) {
            setTimeout(() => {
                showResourceSelection(subject);
                if (folder) {
                    setTimeout(() => {
                        updateResourceContent(folder);
                    }, 300);
                }
            }, 300);
        }
    }
}

function initTheme() {
    const themeToggleBtn = document.getElementById('themeToggle');
    if (!themeToggleBtn) return;
    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
    themeToggleBtn.onclick = () => {
        if (document.documentElement.classList.contains('dark')) {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        } else {
            document.documentElement.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        }
    };
}

// --- INITIALIZATION ---
document.addEventListener('DOMContentLoaded', () => {
    restorePreviousState();
    initTheme();
    // Chat & Dropdown Logic
    const chatPopup = document.getElementById('chatPopup');
    const chatContainer = document.getElementById('chatContainer');
    const chatForm = document.getElementById('chatForm');
    const profileButton = document.getElementById('profileButton');
    const profileDropdown = document.getElementById('profileDropdown');

    window.toggleChat = function() {
        if(chatPopup.classList.contains('hidden-chat')) {
            chatPopup.classList.remove('hidden-chat');
            chatPopup.classList.add('visible-chat');
            if(chatContainer) chatContainer.scrollTop = chatContainer.scrollHeight;
        } else {
            chatPopup.classList.remove('visible-chat');
            chatPopup.classList.add('hidden-chat');
        }
    };

    window.openChatFromDropdown = function() {
        toggleChat();
        if(profileDropdown) {
            profileDropdown.classList.remove('visible-menu');
            profileDropdown.classList.add('hidden-menu');
        }
    };

    if(chatForm) {
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const input = this.querySelector('input[name="message_content"]');
            const msg = input.value.trim();
            if(!msg) return;
            const formData = new FormData();
            formData.append('send_message', '1');
            formData.append('is_ajax', '1');
            formData.append('message_content', msg);
            input.disabled = true;
            fetch('dashboard.php', {method:'POST', body:formData})
            .then(r=>r.json()).then(d=>{
                if(d.status==='success') {
                    const div = document.createElement('div');
                    div.className = 'flex flex-col items-end mb-3';
                    div.innerHTML = `<div class="max-w-[85%] px-3 py-2 rounded-xl text-xs bg-blue-600 text-white">${d.message}</div>`;
                    chatContainer.appendChild(div);
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                    input.value = '';
                }
            }).finally(()=>input.disabled=false);
        });
    }

    if(profileButton && profileDropdown) {
        profileButton.addEventListener('click', (e) => {
            e.stopPropagation();
            if(profileDropdown.classList.contains('hidden-menu')) {
                profileDropdown.classList.remove('hidden-menu');
                profileDropdown.classList.add('visible-menu');
            } else {
                profileDropdown.classList.remove('visible-menu');
                profileDropdown.classList.add('hidden-menu');
            }
        });
        document.addEventListener('click', () => {
             profileDropdown.classList.remove('visible-menu');
             profileDropdown.classList.add('hidden-menu');
        });
    }

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('chat') === 'open') {
        toggleChat();
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});