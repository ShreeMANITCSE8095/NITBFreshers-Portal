# NITBFreshers Study Portal 

The **NITBFreshers Study Portal** is an independently developed, student-run web platform designed to supplement academic resources for B.Tech students at Maulana Azad National Institute of Technology (MANIT), Bhopal. Built to solve the absence of a centralised academic hub, it provides authenticated access to previous year question papers (PYQs), lecture notes, subject-wise study materials, and a custom attendance tracking system.

## Key Features

* **Secure Authentication:** Scholar Number-based login with dual-layer security (PHP native sessions + secure HttpOnly persistent cookies for a 7-day "Keep me logged in" feature).
* **Dynamic Study Material Browser:** A responsive, three-tier navigation system (Group → Subject → Folder → File) backed entirely by the server filesystem, requiring no database updates to add new materials.
* **Smart Attendance Tracker:** Allows students to self-record daily class attendance on a per-subject basis. It automatically calculates current percentages and advises on the exact number of classes a student needs to attend (or can afford to miss) to maintain MANIT's mandatory 75% threshold.
* **Student Contribution System:** Authenticated students can submit study materials (PDFs, PPTs, images) through the dashboard, which enter a pending queue for admin review and approval.
* **Load Balancing & Traffic Routing:** A built-in traffic redirection system routes students to different mirrored server instances based on their Roll Number group (MT or ST) to manage server load on shared hosting.
* **Comprehensive Portal Manager (Admin Panel):** An independent, authenticated admin dashboard to manage announcements, process student uploads, handle password resets, review access logs, and trigger emergency maintenance mode.

## Technology Stack

* **Backend:** PHP (Procedural, no Frameworks).
* **Database:** MySQL.
* **Frontend:** Tailwind CSS, Lucide Icons, Vanilla JavaScript.
* **Storage Strategy:** Server filesystem for study materials (`/study_material/`) via FileZilla FTP.
* **File Rendering:** PDF.js (desktop) and Google Docs Viewer (mobile/PPTs).

## System Architecture

### Multi-Instance Hosting
Due to free shared hosting constraints, the portal is architected across three instances:
* **Primary:** Main portal handling login and routing.
* **Mirror 1:** Overflow portal for the MT group (Sections A-E).
* **Mirror 2:** Overflow portal for the ST group (Sections F-J).

### Content Management
The portal relies on the filesystem as the content database. The PHP API endpoints (`fetch_subjects.php` and `fetch_resources.php`) dynamically read directory structures in real-time, meaning admins can add new notes simply by dropping files into the correct folder via FTP. Configuration states (maintenance mode, global notices, allowed users) are managed via flat files (`.json` and `.txt`) to eliminate unnecessary database calls.

## Security Measures

* **Session Management:** Uses `session_regenerate_id(true)` to prevent fixation attacks.
* **Database Queries:** All queries utilize `mysqli` prepared statements to prevent SQL Injection.
* **Path Traversal Protection:** API endpoints use `basename()` and `realpath()` to sanitize paths and lock file access strictly to the `study_material` directory.
* **Rate Limiting:** Student file contributions are strictly limited to 10 uploads per day per Scholar Number.

## Documentation

Dive deep into the System Architecture, Database Schema, and Internal Workflows. 
[NITBFreshers Portal Functional Documentation](./NITBFreshers%20Portal%20Documentation.pdf)

## Team & Contributors

This project was developed by:
* **Devansh Soni** (3rd Year, ECE)
* **Tanay Sharma** (2nd Year, CSE)
* **Shree Pandit** (2nd Year, CSE)

## Legal & Disclaimer

This is a student-run, unofficial initiative. Scholar Numbers are used solely for portal functionality and personalization. The attendance tracker relies on self-reported data and is a personal utility, not an official academic record.
