# NITBFreshers Study Portal 

A full-stack, highly scalable academic resource management system designed specifically for MANIT Bhopal students. Built to deliver study materials efficiently, the portal has a proven track record of sustaining 1000+ concurrent users during peak examination periods through smart traffic management and dynamic frontend rendering. 

**NITBFreshers Study Portal:** https://nitbfreshers.42web.io/

## Key Features

* **High Concurrency & Load Management:** Proven to handle massive traffic spikes (1000+ concurrent users). Includes automated traffic redirection to lightweight backup servers during peak load times.
* **Secure Authentication & Account Management:** Features a robust login system that validates specific Scholar Number formats, protected by http-only cookies and securely generated session tokens. Includes self-service password recovery logs and secure password updating functionality.
* **Dynamic Frontend & State Preservation:** Utilizes Vanilla JS and the Fetch API to load study groups, subjects, and nested folders asynchronously without page reloads. Preserves user navigation state across sessions using `sessionStorage` and includes a Dark/Light mode toggle for better UX.
* **Advanced Resource Viewer:** Features a custom document viewer supporting native PDF rendering (via `pdf.js`), Google Drive viewer integration, and automated conversion of Dropbox URLs for direct, embedded access.
* **Comprehensive Security & Logging:** Implements strict access control by checking user credentials against a dynamically read banned users list. Logs all critical events (logins, logouts, resource views, and password resets) along with client IP addresses and OS details to the database. 
* **Anti-Scraping Measures:** Deploys custom JavaScript across the application to disable right-click, drag-and-drop, specific keyboard shortcuts (like Ctrl+S), and automatically closes the window if Developer Tools are opened.

## 💻 Tech Stack
* **Frontend:** HTML5, CSS3, JavaScript (Vanilla JS, Fetch API).
* **Backend:** PHP (Procedural with Prepared Statements for SQL injection prevention).
* **Database:** MySQL.
* **Infrastructure:** Hosted on Free Web Hosting (InfinityFree) with optimized routing.

## 📂 Core Project Structure
* **Authentication:** `index.php` (Login gateway), `logout.php` (Session destruction), `forget_password.php`, `change_password.php` (Credential management).
* **Dashboard & UI:** `dashboard.php`, `dashboard.js`, `style.css` (Core user interface and state management).
* **APIs:** `fetch_subjects.php`, `fetch_resources.php` (JSON endpoints for dynamic file system traversal).
* **File Handling:** `resource_viewer.php` (Document rendering engine).
* **Load Management:** `dashboard_NITBFRESHERS3.php`, `index_NITBFRESHERS3.php` (Traffic redirection scripts).
* **Access Control:** `bannedusers.txt`, `index_banned.html` (Restriction protocols).

**Note for Developers/Recruiters:** The db_connection.php, bannedusers.txt, and ChatBot API files have been intentionally excluded from this public repository for security and privacy reasons. The codebase relies on these files to function locally.
