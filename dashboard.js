// Update subjects based on group selection
function updateSubjects() {
    const group = document.getElementById('group').value;
    const subjectsContainer = document.getElementById('subjectsContainer');
    const subjectsSection = document.querySelector('.subjects-section');
    
    subjectsContainer.innerHTML = ''; // Clear the container

    if (group) {
        subjectsSection.style.display = 'block'; // Show the subjects section

        // Fetch subjects dynamically
        fetch(`fetch_subjects.php?group=${group}`)
            .then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    data.forEach(subject => {
                        const tile = document.createElement('div');
                        tile.className = 'subject-tile';

                        // Add icon and subject name
                        const img = document.createElement('img');
                        img.src = `./images/${subject.toLowerCase()}.png`;
                        img.alt = `${subject} Icon`;
                        img.className = 'subject-icon';

                        const name = document.createElement('h3');
                        name.textContent = subject;

                        tile.appendChild(img);
                        tile.appendChild(name);

                        // Add click event to show resource selection
                        tile.onclick = () => showResourceSelection(subject);
                        subjectsContainer.appendChild(tile);
                    });
                } else {
                    subjectsContainer.innerHTML = '<p>No subjects available for this group.</p>';
                }
            })
            .catch(error => {
                console.error('Error fetching subjects:', error);
                subjectsContainer.innerHTML = '<p>Error loading subjects.</p>';
            });
    } else {
        subjectsSection.style.display = 'none'; // Hide if no group is selected
    }
}

// Show the resource selection dropdown
function showResourceSelection(subject) {
    document.getElementById('subjectPanel').style.display = 'none';
    document.querySelector('.subjects-section').style.display = 'none';
    document.getElementById('resourceSelectionPanel').style.display = 'block';

    // Update title and fetch folders
    const resourcePanel = document.getElementById('resourceSelectionPanel');
    resourcePanel.querySelector('h3').textContent = `Select a folder for ${subject}`;
    resourcePanel.setAttribute('data-subject', subject); // Store the subject for later use

    fetchFolders(subject);
}

// Fetch folders for the selected subject
function fetchFolders(subject) {
    const resourceDisplayContainer = document.getElementById('resourceDisplayContainer');
    resourceDisplayContainer.innerHTML = ''; // Clear the container

    const group = document.getElementById('group').value;

    // Fetch folders dynamically
    fetch(`fetch_resources.php?group=${group}&subject=${subject}`)
        .then(response => response.json())
        .then(data => {
            if (data.folders && data.folders.length > 0) {
                data.folders.forEach(folder => {
                    const folderButton = document.createElement('div');
                    folderButton.className = 'subject-tile'; // Use the same styling as subject tiles

                    // const img = document.createElement('img');
                    // img.src = `./images/folder.png`; // Replace with a generic folder icon
                    // img.alt = folder;
                    // img.className = 'subject-icon';
 
                    const name = document.createElement('h3');
                    name.textContent = folder;

                    // folderButton.appendChild(img);
                    folderButton.appendChild(name);

                    // Add click event to open the folder
                    folderButton.onclick = () => updateResourceContent(folder);
                    resourceDisplayContainer.appendChild(folderButton);
                });
            } else {
                resourceDisplayContainer.innerHTML = '<p>No folders available for this subject.</p>';
            }
        })
        .catch(error => {
            console.error('Error fetching folders:', error);
            resourceDisplayContainer.innerHTML = '<p>Error loading folders.</p>';
        });
}

// Update folder contents when selected
function updateResourceContent(selectedFolder) {
    document.getElementById('resourceSelectionPanel').style.display = 'none';
    document.getElementById('folderDisplayPanel').style.display = 'block';

    const subject = document.getElementById('resourceSelectionPanel').getAttribute('data-subject');
    const group = document.getElementById('group').value;

    const url = `fetch_resources.php?group=${encodeURIComponent(group)}&subject=${encodeURIComponent(subject)}&folder=${encodeURIComponent(selectedFolder)}`;

    fetch(url)
    .then(response => response.json()) // Ensure JSON response is parsed
    .then(data => {
        const folderContent = document.getElementById('folderContent');
        folderContent.className = 'folder-display-container'; // Add grid container class
        folderContent.innerHTML = ''; // Clear previous content

        // Populate files
        if (data.files && data.files.length > 0) {
            data.files.forEach(file => {
                const button = document.createElement('button');
                button.textContent = file;
                button.className = 'file-button'; // Add class for styling
                button.onclick = () => openFile(file, subject, group, selectedFolder);
                folderContent.appendChild(button);
            });
        }

        // Populate folders
        if (data.folders && data.folders.length > 0) {
            data.folders.forEach(folder => {
                const button = document.createElement('button');
                button.textContent = folder;
                button.className = 'folder-button'; // Add class for styling
                button.onclick = () => openFolder(folder);
                folderContent.appendChild(button);
            });
        }

        if (!data.files && !data.folders) {
            folderContent.innerHTML = '<p>No files or subfolders available in this folder.</p>';
        }
    })
    .catch(error => {
        console.error('Error fetching folder contents:', error);
        folderContent.innerHTML = '<p>Error loading folder contents.</p>';
    });

}





function openFile(fileName, subject, group, folder) {
    console.log('Opening file:', fileName);

    // Save the current state in session storage
    sessionStorage.setItem('currentGroup', group);
    sessionStorage.setItem('currentSubject', subject);
    sessionStorage.setItem('currentFolder', folder);

    // Redirect to the resource viewer PHP file
    const url = `resource_viewer.php?file=${encodeURIComponent(fileName)}&subject=${encodeURIComponent(subject)}&group=${encodeURIComponent(group)}&folder=${encodeURIComponent(folder)}`;
    window.location.href = url;
}

function restorePreviousState() {
    const group = sessionStorage.getItem('currentGroup');
    const subject = sessionStorage.getItem('currentSubject');
    const folder = sessionStorage.getItem('currentFolder');

    if (group && subject && folder) {
        document.getElementById('group').value = group;
        updateSubjects();
        setTimeout(() => {
            showResourceSelection(subject);
            setTimeout(() => {
                fetchFolders(subject);
                setTimeout(() => {
                    document.getElementById('resourceDropdown').value = folder;
                    updateResourceContent();
                }, 500);
            }, 500);
        }, 500);
    }
}

// Call this function on page load
document.addEventListener('DOMContentLoaded', restorePreviousState);


// Navigate back to the subject panel
function goBackToSubjectPanel() {
    document.getElementById('resourceSelectionPanel').style.display = 'none';
    document.getElementById('folderDisplayPanel').style.display = 'none';
    document.getElementById('subjectPanel').style.display = 'block';
    document.querySelector('.subjects-section').style.display = 'block';
}

// Navigate back to resource selection panel
function goBackToResourceSelectionPanel() {
    document.getElementById('folderDisplayPanel').style.display = 'none';
    document.getElementById('resourceSelectionPanel').style.display = 'block';
}

document.addEventListener('DOMContentLoaded', function () {
    const lightModeIcon = document.getElementById('lightModeIcon');
    const darkModeIcon = document.getElementById('darkModeIcon');

    // Check if dark mode is enabled in localStorage
    if (localStorage.getItem('darkMode') === 'enabled') {
        document.body.classList.add('dark-mode');
        lightModeIcon.style.display = 'none'; // Hide light mode icon
        darkModeIcon.style.display = 'inline'; // Show dark mode icon
    } else {
        document.body.classList.remove('dark-mode');
        lightModeIcon.style.display = 'inline'; // Show light mode icon
        darkModeIcon.style.display = 'none'; // Hide dark mode icon
    }

    // Add event listener to toggle dark mode
    lightModeIcon.addEventListener('click', function () {
        document.body.classList.add('dark-mode');
        lightModeIcon.style.display = 'none';
        darkModeIcon.style.display = 'inline';
        localStorage.setItem('darkMode', 'enabled'); // Store user preference
    });

    darkModeIcon.addEventListener('click', function () {
        document.body.classList.remove('dark-mode');
        lightModeIcon.style.display = 'inline';
        darkModeIcon.style.display = 'none';
        localStorage.setItem('darkMode', 'disabled'); // Store user preference
    });
});
