<?php
// Enable error reporting for debugging
ini_set('display_errors', 0);
error_reporting(E_ALL);

// --- GLOBAL MAINTENANCE CHECK ---
$MAINTENANCE_FILE = 'maintenance_config.json';
if (file_exists($MAINTENANCE_FILE)) {
    $mConfig = json_decode(file_get_contents($MAINTENANCE_FILE), true);
    if (($mConfig['maintenance_mode'] ?? 'OFF') === 'ON') {
        $mReason = $mConfig['reason'] ?? "We are performing scheduled maintenance.";
        // Simple maintenance output if this is the entry point
        echo "<div style='text-align:center; padding:50px; font-family:sans-serif;'>
                <h1>Under Maintenance</h1>
                <p>$mReason</p>
                <p>Please check back later.</p>
              </div>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MANIT Bhopal - 1st Year Study Material</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-blue: #2563eb; /* Tailwind blue-600 */
            --primary-hover: #1d4ed8; /* Tailwind blue-700 */
            --background-overlay: rgba(0, 0, 0, 0.4);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --text-primary: #1d1d1f;
            --text-secondary: #e2e8f0;
            --white: #ffffff;
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            --radius: 16px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            overflow-x: hidden;
            background: url('manit_main_building.jpg') center/cover fixed; 
            min-height: 100vh;
        }

        .background-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--background-overlay);
            backdrop-filter: blur(8px);
            z-index: -1;
        }

        /* Navigation */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            padding: 1rem 2rem;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary-blue);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo img {
            height: 35px;
            width: auto;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 1.5rem;
            align-items: center;
        }
        
        .nav-links.active {
            display: flex;
            flex-direction: column;
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
            padding: 1rem 0;
            animation: fadeInDown 0.3s ease-out;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .nav-links a {
            text-decoration: none;
            color: #334155;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-links a:hover {
            background: #eff6ff;
            color: var(--primary-blue);
            transform: translateY(-1px);
        }
        
        .nav-links a.login-btn {
            background-color: var(--primary-blue);
            color: white !important;
            box-shadow: 0 4px 14px 0 rgba(0,118,255,0.39);
        }
        .nav-links a.login-btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }

        .mobile-menu {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #334155;
            cursor: pointer;
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 8rem 2rem 4rem;
        }

        .hero-content {
            max-width: 900px;
            color: var(--white);
        }

        .hero h1 {
            font-size: clamp(2.5rem, 6vw, 4.5rem);
            font-weight: 800;
            margin-bottom: 1.5rem;
            line-height: 1.1;
            text-shadow: 0 4px 30px rgba(0, 0, 0, 0.5);
            letter-spacing: -0.02em;
        }

        .hero p {
            font-size: 1.35rem;
            margin-bottom: 2.5rem;
            opacity: 0.95;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
            font-weight: 300;
        }

        .cta-buttons {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
        }

        .cta-button {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 2.5rem;
            background: var(--primary-blue);
            color: var(--white);
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255,255,255,0.1);
        }

        .cta-button.secondary {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .cta-button:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            background: var(--primary-hover);
        }
        
        .cta-button.secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Main Content */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 4rem 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: stretch;
        }

        .glass-card {
            background: rgba(23, 23, 23, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius);
            padding: 2.5rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            color: var(--white);
            display: flex;
            flex-direction: column;
        }

        .glass-card:hover {
            transform: translateY(-5px);
            background: rgba(23, 23, 23, 0.7);
            border-color: rgba(255,255,255,0.3);
        }

        .glass-card h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--white);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 1rem;
        }

        /* Announcements */
        .announcements-container {
            position: relative;
            min-height: 400px;
            overflow: hidden;
            flex: 1;
        }

        .announcement-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            gap: 1rem;
            height: 400px;
            overflow: hidden;
        }

        .announcement-slide.active {
            opacity: 1;
            transform: translateX(0);
        }

        .announcement-slide.prev {
            transform: translateX(-100%);
        }

        .announcement-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.25rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.05);
            flex-shrink: 0;
        }

        .announcement-item:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .announcement-icon {
            width: 45px;
            height: 45px;
            background: var(--primary-blue);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            flex-shrink: 0;
            font-size: 1.2rem;
        }

        .announcement-item a {
            color: #f1f5f9;
            text-decoration: none;
            font-weight: 500;
            flex: 1;
            font-size: 1rem;
            line-height: 1.4;
        }

        .announcement-dots {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .dot.active {
            background: var(--white);
            transform: scale(1.3);
        }

        /* About Section */
        .about-section {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .about-section p {
            margin-bottom: 1.2rem;
            line-height: 1.7;
            color: #cbd5e1;
            font-size: 1.05rem;
        }

        /* Quick Links */
        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .quick-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.25rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            text-decoration: none;
            color: var(--white);
            transition: all 0.3s ease;
        }

        .quick-link:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-3px);
            border-color: var(--primary-blue);
        }

        .quick-link-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary-blue), #60a5fa);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.2rem;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(15px);
            z-index: 2000;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 1;
        }

        .modal-content {
            background: #ffffff;
            border-radius: 24px;
            padding: 2.5rem;
            max-width: 800px;
            width: 90%;
            text-align: center;
            transform: scale(0.8);
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            color: #1e293b;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            font-family: 'Inter', sans-serif; /* Explicitly set font */
        }

        .modal.show .modal-content {
            transform: scale(1);
        }

        .close {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: #f1f5f9;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
            color: #64748b;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .close:hover {
            background: #e2e8f0;
            color: #ef4444;
        }

        /* Developer & Team Styling (MUI Style) */
        .developer-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-blue), #0ea5e9);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: var(--white);
            font-size: 2.5rem;
            font-weight: bold;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.3);
        }

        .developer-info h3 {
            font-size: 1.8rem;
            color: #0f172a;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .developer-skills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
            margin: 1rem 0;
        }

        /* MUI Chip Style */
        .skill-tag {
            background: #eff6ff;
            color: var(--primary-blue);
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid #dbeafe;
            transition: all 0.2s;
        }
        
        .skill-tag:hover {
            background: #dbeafe;
        }

        /* MUI Quote/Card Style */
        .mui-quote-card {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 16px;
            margin: 1.5rem auto;
            border-left: 4px solid var(--primary-blue);
            font-style: italic;
            text-align: center; /* Centralized */
            font-size: 0.95rem;
            color: #475569;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            max-width: 90%;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
        }

        .team-member {
            background: #ffffff;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .team-member:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            border-color: #cbd5e1;
        }

        .team-member-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #a855f7);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: var(--white);
            font-size: 1.5rem;
            font-weight: bold;
        }

        .team-member h4 {
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: #334155;
            font-size: 1.1rem;
        }
        
        .team-member-bio {
            font-size: 0.9rem;
            color: #64748b;
            margin-top: 0.8rem;
            margin-bottom: 0;
            line-height: 1.5;
            text-align: center; /* Centralized */
        }
        
        .role-badge {
            background: #dbeafe; 
            color: #2563eb; 
            padding: 0.25rem 0.75rem; 
            border-radius: 12px; 
            display: inline-block; 
            font-size: 0.75rem; 
            font-weight: 600; 
            margin: 0.5rem 0;
        }

        /* Footer */
        .footer {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            color: #94a3b8;
            padding: 3rem 2rem;
            text-align: center;
            margin-top: 4rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2.5rem;
            margin-bottom: 1.5rem;
        }

        .footer-links a {
            color: #e2e8f0;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .footer-links a:hover {
            color: var(--primary-blue);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .nav-links.active { display: flex; }
            .mobile-menu { display: block; }
            .main-content { grid-template-columns: 1fr; gap: 2rem; padding: 2rem 1rem; }
            .hero { padding: 6rem 1.5rem 3rem; }
            .hero h1 { font-size: 2.5rem; }
            .cta-buttons { flex-direction: column; gap: 1rem; width: 100%; }
            .cta-button { width: 100%; justify-content: center; }
        }

        /* Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .glass-card {
            animation: fadeInUp 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
        }

        .glass-card:nth-child(2) {
            animation-delay: 0.2s;
        }
    </style>
</head>

<body>
    <div class="background-overlay"></div>

    <nav class="navbar">
        <div class="nav-container">
            <a href="#" class="logo">
                <img src="images/logo.png" alt="Logo">
                NITB Freshers Portal
            </a>
            <ul class="nav-links">
                <li><a href="#about"><i class="fas fa-info-circle"></i> About</a></li>
                <li><a href="btech-scheme/"><i class="fas fa-graduation-cap"></i> B.Tech New Scheme</a></li>
                <li><a href="announcements/btech_1styr_syllabus.pdf" target="_blank"><i class="fas fa-book"></i> Syllabus</a></li>
                <li><a href="#" id="developerBtn"><i class="fas fa-users"></i> Team</a></li>
                <li><a href="index.php" class="login-btn"><i class="fas fa-sign-in-alt"></i>Student Login</a></li>
            </ul>
            <button class="mobile-menu">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <section class="hero">
        <div class="hero-content">
            <h1>Welcome to NIT-B <br>1st Year Study Resources</h1>
            <p>Your centralized hub for academic excellence. Access notes, PYQs, and syllabus instantly.</p>
            <div class="cta-buttons">
                <a href="index.php" class="cta-button">
                    <i class="fas fa-book-open"></i>
                    Access Study Materials
                </a>
                <a href="#announcements" class="cta-button secondary">
                    <i class="fas fa-bullhorn"></i>
                    Check Announcements
                </a>
            </div>
        </div>
    </section>

    <div class="main-content">
        <div class="glass-card" id="announcements">
            <h2><i class="fas fa-bullhorn" style="color: #fbbf24;"></i> Latest Announcements</h2>
            <div class="announcements-container">
                </div>

            <div class="announcement-dots">
                </div>
        </div>

        <div class="glass-card">
            <h2 id="about"><i class="fas fa-university" style="color: #60a5fa;"></i> About Portal</h2>
            <div class="about-section">
                <p>Welcome to the unofficial academic support portal for 1st Year students of MANIT Bhopal. This platform is designed to streamline your study process by providing easy access to essential resources.</p>
                <p>From previous year question papers (PYQs) to curated notes and book recommendations, everything you need to ace your exams is just a login away.</p>
            </div>

            <div class="quick-links">
                <a href="announcements/btech_1styr_syllabus.pdf" target="_blank" class="quick-link">
                    <div class="quick-link-icon">
                        <i class="fas fa-scroll"></i>
                    </div>
                    <span>Download Syllabus</span>
                </a>
                <a href="index.php" class="quick-link">
                    <div class="quick-link-icon">
                        <i class="fas fa-laptop-code"></i>
                    </div>
                    <span>Student Login</span>
                </a>
                <a href="http://www.manit.ac.in/" target="_blank" class="quick-link">
                    <div class="quick-link-icon">
                        <i class="fas fa-globe"></i>
                    </div>
                    <span>Institute Website</span>
                </a>
            </div>
        </div>
    </div>

    <div class="modal" id="developerModal">
        <div class="modal-content">
            <button class="close" id="closeModal">&times;</button>
            
            <div class="developer-info">
                <h3><i class="fas fa-code-branch"></i> The Team Behind</h3>
                <p style="color: #64748b; margin-bottom: 2rem;">Built with ❤️ by students, for students.</p>
            </div>

            <div class="developer-avatar">DS</div>
            <h4 style="font-size:1.4rem; font-weight:700; color:#1e293b;">Devansh Soni</h4>
            <p style="color:#64748b; font-weight:500;">B.Tech ECE (3rd Year)</p>
            <div class="role-badge">Project Lead</div>

            <div class="developer-skills">
                <span class="skill-tag"><i class="fas fa-code"></i> Full-Stack Dev</span>
                <span class="skill-tag"><i class="fas fa-server"></i> System Design</span>
                <span class="skill-tag"><i class="fas fa-cloud"></i> Cloud Infrastructure</span>
                <span class="skill-tag"><i class="fas fa-users"></i> Team Leadership</span>
            </div>
            
            <p style="font-size: 0.95rem; color:#475569; margin: 1rem auto; max-width:700px; line-height:1.6; text-align: center;">
                A Pre-Final Year student of ECE with a keen interest in Software Development. In 2024, this portal was launched by him with an aim of creating a one-stop academic support platform for students at NIT-B. The portal has since grown into a resource hub by offering previous year papers and study materials designed to help students navigate their academic journey with greater ease and confidence.
            </p>
            
            <div class="mui-quote-card">
                <i class="fas fa-quote-left" style="color:var(--primary-blue); margin-right:5px;"></i>
                Passionate about creating digital solutions that bridge the gap between technology and education. Leading the development of this platform to help fellow students excel in their academic journey at MANIT.
            </div>
            
            <div class="team-section" style="border-top:1px solid #f1f5f9; margin-top:2rem; padding-top:2rem;">
                <h3 style="color:#2563eb; font-size:1.25rem; font-weight:700; margin-bottom:1.5rem;">Core Contributors</h3>
                <div class="team-grid">
                    
                    <div class="team-member">
                        <div class="team-member-avatar">TS</div>
                        <h4>Tanay Sharma</h4>
                        <p style="font-size:0.85rem; color:#64748b;">B.Tech CSE, 2nd Year</p>
                        <div class="role-badge">Technical Coordinator</div>
                        <div class="team-member-bio">
                            A Computer Science and Engineering (CSE) sophomore at NIT-B who enjoys playing chess and table tennis. He began his academic journey in Chemical Engineering and later secured a branch change to CSE owing to his consistent performance.
                        </div>
                    </div>
                    
                    <div class="team-member">
                        <div class="team-member-avatar" style="background: linear-gradient(135deg, #f59e0b, #ef4444);">SP</div>
                        <h4>Shree Pandit</h4>
                        <p style="font-size:0.85rem; color:#64748b;">B.Tech CSE, 2nd Year</p>
                        <div class="role-badge">Resource Coordinator</div>
                        <div class="team-member-bio">
                             A Computer Science and Engineering (CSE) sophomore at NIT-B who loves to explore and learn new technologies. A national-level chess player and a state-level karate player, Shree also serves as an NCC cadet.
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="footer-links">
            <a href="#about">About</a>
            <a href="index.php">Student Login</a>
            <a href="http://www.manit.ac.in/">Institute Website</a>
        </div>
        <p style="color:#cbd5e1;">Unofficial Website for B.Tech I Year Students</p>
        <p style="opacity: 0.5; font-size: 0.85rem; margin-top: 0.75rem;">
            &copy; <?php echo date("Y"); ?> NITBFreshers Portal.
        </p>
    </footer>
    
    <script>
        // Announcement Data
        function createSlides() {
            const announcements = [
                {
                    icon: 'fas fa-book-reader',
                    text: 'New: Academic Resources List (Books & YouTube Channels) for MT/ST Groups',
                    link: 'announcements/academic_resources.pdf'
                },
                {
                    icon: 'fas fa-clipboard-list',
                    text: 'Important: Exam Scheme and Syllabus Structure for 1st Year',
                    link: 'announcements/exam_scheme_and_syllabus.pdf'
                }
            ];

            const container = document.querySelector('.announcements-container');
            const dotsContainer = document.querySelector('.announcement-dots');
            container.innerHTML = '';
            dotsContainer.innerHTML = '';
            
            // Create Single Slide Wrapper
            const slide = document.createElement('div');
            slide.className = 'announcement-slide active';
            
            announcements.forEach(announcement => {
                const item = document.createElement('div');
                item.className = 'announcement-item';
                item.innerHTML = `
                    <div class="announcement-icon">
                        <i class="${announcement.icon}"></i>
                    </div>
                    <a href="${announcement.link}" target="_blank">${announcement.text}</a>
                `;
                slide.appendChild(item);
            });
            
            container.appendChild(slide);
            
            // Single Dot since we have one slide for now
            const dot = document.createElement('span');
            dot.className = 'dot active';
            dotsContainer.appendChild(dot);
        }

        // Initialize
        createSlides();

        // Modal Logic
        const developerBtn = document.getElementById('developerBtn');
        const modal = document.getElementById('developerModal');
        const closeModal = document.getElementById('closeModal');

        developerBtn.addEventListener('click', (e) => {
            e.preventDefault();
            modal.classList.add('show');
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        });

        const hideModal = () => {
            modal.classList.remove('show');
            document.body.style.overflow = 'auto'; // Restore scrolling
        };

        closeModal.addEventListener('click', hideModal);

        modal.addEventListener('click', (e) => {
            if (e.target === modal) hideModal();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('show')) hideModal();
        });

        // Navbar Scroll Effect
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.background = 'rgba(255, 255, 255, 0.98)';
                navbar.style.boxShadow = '0 4px 20px rgba(0,0,0,0.05)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.9)';
                navbar.style.boxShadow = 'none';
            }
        });

        // Smooth Scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href === '#' || href === '') return;
                
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    const headerOffset = 80;
                    const elementPosition = target.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: "smooth"
                    });
                }
            });
        });

        // Mobile Menu Logic
        const mobileMenuBtn = document.querySelector('.mobile-menu');
        const navLinks = document.querySelector('.nav-links');

        mobileMenuBtn.addEventListener('click', () => {
            navLinks.classList.toggle('active');
            // Change icon based on state
            const icon = mobileMenuBtn.querySelector('i');
            if (navLinks.classList.contains('active')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });

        // Close mobile menu on click
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.nav-container')) {
                navLinks.classList.remove('active');
                mobileMenuBtn.querySelector('i').classList.remove('fa-times');
                mobileMenuBtn.querySelector('i').classList.add('fa-bars');
            }
        });
    </script>
</body>
</html>