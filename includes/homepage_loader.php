<?php
/**
 * Homepage content loader for index.php and admin/manage_homepage.php
 */

if (!function_exists('ensureHomepageContentSchema')) {
    function ensureHomepageContentSchema($conn): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }

        $sql = "CREATE TABLE IF NOT EXISTS homepage_content (
            id INT(11) NOT NULL AUTO_INCREMENT,
            section_key VARCHAR(50) NOT NULL,
            section_title VARCHAR(255) NOT NULL,
            section_content TEXT,
            section_type ENUM('banner', 'announcement', 'featured_course', 'text_block', 'image_block') NOT NULL,
            display_order INT(11) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_homepage_section_key (section_key),
            KEY idx_active (is_active),
            KEY idx_order (display_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($sql)) {
            error_log('ensureHomepageContentSchema failed: ' . $conn->error);
            return false;
        }

        $ready = true;
        return true;
    }
}

if (!function_exists('getIndexHomepageSectionDefinitions')) {
    function getIndexHomepageSectionDefinitions(): array
    {
        return [
            'notice_primary' => [
                'group' => 'Notice Ticker',
                'label' => 'Primary notice (after NOTICE label)',
                'type' => 'text_block',
                'order' => 10,
                'default_title' => 'Primary Notice',
                'default_content' => 'Admissions Open! NIELIT Bhubaneswar offers NSQF-aligned courses with modern facilities. Visit our Baleshwar Extension Center today.',
            ],
            'notice_secondary' => [
                'group' => 'Notice Ticker',
                'label' => 'Secondary notice (after NEW label)',
                'type' => 'text_block',
                'order' => 11,
                'default_title' => 'Secondary Notice',
                'default_content' => 'Online Registrations are now live — Apply before the deadline!',
            ],
            'hero_eyebrow' => [
                'group' => 'Hero Banner',
                'label' => 'Hero eyebrow text',
                'type' => 'text_block',
                'order' => 20,
                'default_title' => 'Hero Eyebrow',
                'default_content' => 'Ministry of Electronics & IT · Est. 2021',
            ],
            'hero_subtitle' => [
                'group' => 'Hero Banner',
                'label' => 'Hero subtitle paragraph',
                'type' => 'text_block',
                'order' => 21,
                'default_title' => 'Hero Subtitle',
                'default_content' => 'NIELIT Bhubaneswar — your gateway to NSQF-aligned technology education across Odisha and Chhattisgarh. Skills that power India\'s future.',
            ],
            'hero_typing_lines' => [
                'group' => 'Hero Banner',
                'label' => 'Hero typing lines (JSON array)',
                'type' => 'text_block',
                'order' => 22,
                'default_title' => 'Hero Typing Lines',
                'default_content' => '[{"line1":"Code Tomorrow.","line2":"Transform Today."},{"line1":"Learn Today.","line2":"Lead Tomorrow."},{"line1":"Skills Today.","line2":"Success Tomorrow."}]',
            ],
            'hero_stat_1' => [
                'group' => 'Hero Stats',
                'label' => 'Stat 1 (title=number, content=label)',
                'type' => 'text_block',
                'order' => 30,
                'default_title' => '15+',
                'default_content' => 'Courses Offered',
            ],
            'hero_stat_2' => [
                'group' => 'Hero Stats',
                'label' => 'Stat 2',
                'type' => 'text_block',
                'order' => 31,
                'default_title' => '2',
                'default_content' => 'Centers Active',
            ],
            'hero_stat_3' => [
                'group' => 'Hero Stats',
                'label' => 'Stat 3',
                'type' => 'text_block',
                'order' => 32,
                'default_title' => '5000+',
                'default_content' => 'Students Trained',
            ],
            'hero_stat_4' => [
                'group' => 'Hero Stats',
                'label' => 'Stat 4',
                'type' => 'text_block',
                'order' => 33,
                'default_title' => '100%',
                'default_content' => 'Govt. Certified',
            ],
            'welcome_eyebrow' => [
                'group' => 'Welcome Strip',
                'label' => 'Eyebrow',
                'type' => 'text_block',
                'order' => 40,
                'default_title' => 'Welcome Eyebrow',
                'default_content' => 'Welcome to NIELIT Bhubaneswar',
            ],
            'welcome_title' => [
                'group' => 'Welcome Strip',
                'label' => 'Heading',
                'type' => 'text_block',
                'order' => 41,
                'default_title' => 'Welcome Title',
                'default_content' => 'Excellence in Technology Education Since 2021.',
            ],
            'welcome_text' => [
                'group' => 'Welcome Strip',
                'label' => 'Description',
                'type' => 'text_block',
                'order' => 42,
                'default_title' => 'Welcome Text',
                'default_content' => 'We are a premier autonomous scientific society under MeitY, Government of India — dedicated to developing human resources in Information, Electronics, and Communication Technology (IECT) through industry-aligned programs.',
            ],
            'jobfair_eyebrow' => [
                'group' => 'Job Fair Portal',
                'label' => 'Eyebrow',
                'type' => 'text_block',
                'order' => 50,
                'default_title' => 'Job Fair Eyebrow',
                'default_content' => 'National Job Fair Initiative',
            ],
            'jobfair_title' => [
                'group' => 'Job Fair Portal',
                'label' => 'Title',
                'type' => 'text_block',
                'order' => 51,
                'default_title' => 'Job Fair Title',
                'default_content' => 'NIELIT Bhubaneswar Job Fair Portal',
            ],
            'jobfair_lead' => [
                'group' => 'Job Fair Portal',
                'label' => 'Lead paragraph',
                'type' => 'text_block',
                'order' => 52,
                'default_title' => 'Job Fair Lead',
                'default_content' => 'A centralized government platform for transparent, seamless, and large-scale recruitment drives across NIELIT regional centres. Empowering youth and connecting employers with skilled talent.',
            ],
            'jobfair_alert' => [
                'group' => 'Job Fair Portal',
                'label' => 'Alert box text',
                'type' => 'text_block',
                'order' => 53,
                'default_title' => 'Job Fair Alert',
                'default_content' => 'Registration is open for upcoming Mega Job Fairs. Candidates can complete profiles and check in at the venue. Recruiters can upload offer letters directly through the portal.',
            ],
            'mocktest_eyebrow' => [
                'group' => 'Mock Test Portal',
                'label' => 'Eyebrow',
                'type' => 'text_block',
                'order' => 60,
                'default_title' => 'Mock Test Eyebrow',
                'default_content' => 'Exam Preparation',
            ],
            'mocktest_title' => [
                'group' => 'Mock Test Portal',
                'label' => 'Title',
                'type' => 'text_block',
                'order' => 61,
                'default_title' => 'Mock Test Title',
                'default_content' => 'NIELIT Mock Assessment Platform',
            ],
            'mocktest_lead' => [
                'group' => 'Mock Test Portal',
                'label' => 'Lead paragraph',
                'type' => 'text_block',
                'order' => 62,
                'default_title' => 'Mock Test Lead',
                'default_content' => 'A secure mock test platform for NIELIT Bhubaneswar candidates and authorized training partners. Practice NSQF-aligned assessments, download admit cards, and build exam readiness before official certification tests.',
            ],
            'features_eyebrow' => [
                'group' => 'Features Section',
                'label' => 'Section eyebrow',
                'type' => 'text_block',
                'order' => 70,
                'default_title' => 'Features Eyebrow',
                'default_content' => 'What We Offer',
            ],
            'features_title' => [
                'group' => 'Features Section',
                'label' => 'Section heading',
                'type' => 'text_block',
                'order' => 71,
                'default_title' => 'Features Title',
                'default_content' => 'Built for the Future of Work.',
            ],
            'feature_1' => [
                'group' => 'Features Section',
                'label' => 'Feature card 1 (title + description)',
                'type' => 'text_block',
                'order' => 72,
                'default_title' => 'Skill Development',
                'default_content' => 'NSQF-aligned courses to boost employability in the rapidly evolving technology sector.',
            ],
            'feature_2' => [
                'group' => 'Features Section',
                'label' => 'Feature card 2',
                'type' => 'text_block',
                'order' => 73,
                'default_title' => 'Regional Scope',
                'default_content' => 'Operating extensively across Odisha and Chhattisgarh to reach every aspiring student.',
            ],
            'feature_3' => [
                'group' => 'Features Section',
                'label' => 'Feature card 3',
                'type' => 'text_block',
                'order' => 74,
                'default_title' => 'Modern Facilities',
                'default_content' => 'State-of-the-art labs, smart classrooms, and conference halls at OCAC Tower.',
            ],
            'feature_4' => [
                'group' => 'Features Section',
                'label' => 'Feature card 4',
                'type' => 'text_block',
                'order' => 75,
                'default_title' => 'Baleshwar Extension',
                'default_content' => 'Expanding our footprint to deliver quality education across the Baleshwar region.',
            ],
            'info_eyebrow' => [
                'group' => 'Info Section',
                'label' => 'Section eyebrow',
                'type' => 'text_block',
                'order' => 80,
                'default_title' => 'Info Eyebrow',
                'default_content' => 'Learn More',
            ],
            'info_title' => [
                'group' => 'Info Section',
                'label' => 'Section heading',
                'type' => 'text_block',
                'order' => 81,
                'default_title' => 'Info Title',
                'default_content' => 'Everything You Need to Know.',
            ],
            'about_title' => [
                'group' => 'Info Section',
                'label' => 'About card (title + text)',
                'type' => 'text_block',
                'order' => 82,
                'default_title' => 'About NIELIT',
                'default_content' => 'An autonomous scientific society under MeitY, Govt. of India — focused on human resource development in IECT through quality education and practical training programs.',
            ],
            'mission_title' => [
                'group' => 'Info Section',
                'label' => 'Mission card (title + text)',
                'type' => 'text_block',
                'order' => 83,
                'default_title' => 'Our Mission',
                'default_content' => 'To empower youth with cutting-edge technology skills, making them industry-ready and contributing to India\'s digital transformation through quality education and practical training.',
            ],
            'about_checklist' => [
                'group' => 'Info Section',
                'label' => 'About card bullet points (JSON array of strings)',
                'type' => 'text_block',
                'order' => 84,
                'default_title' => 'About Checklist',
                'default_content' => '["Government of India Initiative","NSQF Aligned Programs","Industry-Ready Training"]',
                'json' => true,
            ],
            'mission_checklist' => [
                'group' => 'Info Section',
                'label' => 'Mission card bullet points (JSON array of strings)',
                'type' => 'text_block',
                'order' => 85,
                'default_title' => 'Mission Checklist',
                'default_content' => '["Skill Enhancement & Certification","Employment Generation","Digital India Mission Support"]',
                'json' => true,
            ],
            'quickaccess_title' => [
                'group' => 'Info Section',
                'label' => 'Quick Access card title',
                'type' => 'text_block',
                'order' => 86,
                'default_title' => 'Quick Access',
                'default_content' => 'Quick Access',
            ],
            'quickaccess_text' => [
                'group' => 'Info Section',
                'label' => 'Quick Access card description',
                'type' => 'text_block',
                'order' => 87,
                'default_title' => 'Quick Access Text',
                'default_content' => 'Explore our offerings and start your learning journey. Access courses, register online, and connect with us for queries.',
            ],
            'quickaccess_links' => [
                'group' => 'Info Section',
                'label' => 'Quick Access links (JSON: label, url, icon, external)',
                'type' => 'text_block',
                'order' => 88,
                'default_title' => 'Quick Access Links',
                'default_content' => '[{"label":"View Courses","url":"/public/courses","icon":"fa-book","external":false},{"label":"Student Portal","url":"/student/login","icon":"fa-sign-in-alt","external":false},{"label":"Contact Us","url":"/public/contact","icon":"fa-envelope","external":false},{"label":"News & Events","url":"/public/news","icon":"fa-newspaper","external":false},{"label":"Job Fair Portal","url":"__JOB_FAIR__","icon":"fa-briefcase","external":true},{"label":"Mock Test Portal","url":"__MOCK_TEST__","icon":"fa-laptop-code","external":true}]',
                'json' => true,
            ],
            'page_title' => [
                'group' => 'Site Meta',
                'label' => 'Browser page title',
                'type' => 'text_block',
                'order' => 5,
                'default_title' => 'Page Title',
                'default_content' => 'NIELIT Bhubaneswar | Ministry of Electronics & IT',
            ],
            'navbar_brand' => [
                'group' => 'Site Meta',
                'label' => 'Navbar brand text',
                'type' => 'text_block',
                'order' => 6,
                'default_title' => 'Navbar Brand',
                'default_content' => 'NIELIT Bhubaneswar',
            ],
            'hero_btn_1' => [
                'group' => 'Hero Buttons',
                'label' => 'Button 1 (title=label, content=URL path or full URL)',
                'type' => 'text_block',
                'order' => 23,
                'default_title' => 'Explore Courses',
                'default_content' => '/public/courses',
            ],
            'hero_btn_2' => [
                'group' => 'Hero Buttons',
                'label' => 'Button 2',
                'type' => 'text_block',
                'order' => 24,
                'default_title' => 'Student Portal',
                'default_content' => '/student/login',
            ],
            'hero_btn_3' => [
                'group' => 'Hero Buttons',
                'label' => 'Button 3',
                'type' => 'text_block',
                'order' => 25,
                'default_title' => 'Job Fair Portal',
                'default_content' => '__JOB_FAIR__',
            ],
            'hero_btn_4' => [
                'group' => 'Hero Buttons',
                'label' => 'Button 4',
                'type' => 'text_block',
                'order' => 26,
                'default_title' => 'Main Website',
                'default_content' => '__MAIN_WEBSITE__',
            ],
            'welcome_pills' => [
                'group' => 'Welcome Strip',
                'label' => 'Contact/location pills (JSON: label, url, icon, link)',
                'type' => 'text_block',
                'order' => 43,
                'default_title' => 'Welcome Pills',
                'default_content' => '[{"label":"Go to Main Website","url":"__MAIN_WEBSITE__","icon":"fa-globe","link":true},{"label":"OCAC Tower, Bhubaneswar","url":"https://www.google.com/maps/search/?api=1&query=OCAC+Tower+Bhubaneswar","icon":"fa-map-marker-alt","link":true},{"label":"Baleshwar Extension Center","url":"https://www.google.com/maps/search/?api=1&query=NIELIT+Baleshwar+Extension+Center+Baleshwar","icon":"fa-map-marker-alt","link":true},{"label":"Mon–Fri: 09:00 AM – 5:30 PM","url":"","icon":"fa-clock","link":false},{"label":"0674-2960354","url":"","icon":"fa-phone-alt","link":false},{"label":"dir-bbsr@nielit.gov.in","url":"mailto:dir-bbsr@nielit.gov.in","icon":"fa-envelope","link":true},{"label":"NSQF Aligned Programs","url":"https://www.nielit.gov.in/content/nsqf-it","icon":"fa-shield-alt","link":true}]',
                'json' => true,
            ],
            'jobfair_btn_primary' => [
                'group' => 'Job Fair Portal',
                'label' => 'Primary button (title=label, content=URL)',
                'type' => 'text_block',
                'order' => 54,
                'default_title' => 'Visit Job Fair Portal',
                'default_content' => '__JOB_FAIR__',
            ],
            'jobfair_btn_secondary' => [
                'group' => 'Job Fair Portal',
                'label' => 'Secondary button',
                'type' => 'text_block',
                'order' => 55,
                'default_title' => 'Login to Portal',
                'default_content' => '__JOB_FAIR__',
            ],
            'jobfair_stat_1' => [
                'group' => 'Job Fair Portal',
                'label' => 'Stat 1 (title=number, content=label)',
                'type' => 'text_block',
                'order' => 56,
                'default_title' => '1+',
                'default_content' => 'Centers',
            ],
            'jobfair_stat_2' => [
                'group' => 'Job Fair Portal',
                'label' => 'Stat 2',
                'type' => 'text_block',
                'order' => 57,
                'default_title' => '10+',
                'default_content' => 'Youth Enrolled',
            ],
            'jobfair_stat_3' => [
                'group' => 'Job Fair Portal',
                'label' => 'Stat 3',
                'type' => 'text_block',
                'order' => 58,
                'default_title' => '4+',
                'default_content' => 'Corporates',
            ],
            'jobfair_stat_4' => [
                'group' => 'Job Fair Portal',
                'label' => 'Stat 4',
                'type' => 'text_block',
                'order' => 59,
                'default_title' => 'Live',
                'default_content' => 'Active Drives',
            ],
            'mocktest_feature_1' => [
                'group' => 'Mock Test Portal',
                'label' => 'Feature bullet 1 (title=icon class, content=text)',
                'type' => 'text_block',
                'order' => 63,
                'default_title' => 'fa-user-graduate',
                'default_content' => 'Candidate portal for mock exams, admit cards, and digital scorecards',
            ],
            'mocktest_feature_2' => [
                'group' => 'Mock Test Portal',
                'label' => 'Feature bullet 2',
                'type' => 'text_block',
                'order' => 64,
                'default_title' => 'fa-school',
                'default_content' => 'Training partner dashboard for batch scheduling and exam slots',
            ],
            'mocktest_feature_3' => [
                'group' => 'Mock Test Portal',
                'label' => 'Feature bullet 3',
                'type' => 'text_block',
                'order' => 65,
                'default_title' => 'fa-shield-alt',
                'default_content' => 'Secure examination environment with transparent evaluation',
            ],
            'mocktest_feature_4' => [
                'group' => 'Mock Test Portal',
                'label' => 'Feature bullet 4',
                'type' => 'text_block',
                'order' => 66,
                'default_title' => 'fa-chart-line',
                'default_content' => 'Performance analytics to identify weak areas before the real exam',
            ],
            'mocktest_stat_1' => [
                'group' => 'Mock Test Portal',
                'label' => 'Stat 1',
                'type' => 'text_block',
                'order' => 67,
                'default_title' => '50K+',
                'default_content' => 'Candidates',
            ],
            'mocktest_stat_2' => [
                'group' => 'Mock Test Portal',
                'label' => 'Stat 2',
                'type' => 'text_block',
                'order' => 68,
                'default_title' => '200+',
                'default_content' => 'Exam Sessions',
            ],
            'mocktest_stat_3' => [
                'group' => 'Mock Test Portal',
                'label' => 'Stat 3',
                'type' => 'text_block',
                'order' => 69,
                'default_title' => '99.9%',
                'default_content' => 'System Uptime',
            ],
            'mocktest_btn_primary' => [
                'group' => 'Mock Test Portal',
                'label' => 'Primary button',
                'type' => 'text_block',
                'order' => 70,
                'default_title' => 'Open Mock Test Portal',
                'default_content' => '__MOCK_TEST__',
            ],
            'mocktest_btn_secondary' => [
                'group' => 'Mock Test Portal',
                'label' => 'Secondary button',
                'type' => 'text_block',
                'order' => 71,
                'default_title' => 'Candidate Login',
                'default_content' => '__MOCK_TEST__',
            ],
            'feature_1_icon' => [
                'group' => 'Features Section',
                'label' => 'Feature card 1 icon (Font Awesome class)',
                'type' => 'text_block',
                'order' => 76,
                'default_title' => 'Icon',
                'default_content' => 'fa-laptop-code',
            ],
            'feature_2_icon' => [
                'group' => 'Features Section',
                'label' => 'Feature card 2 icon',
                'type' => 'text_block',
                'order' => 77,
                'default_title' => 'Icon',
                'default_content' => 'fa-map-marked-alt',
            ],
            'feature_3_icon' => [
                'group' => 'Features Section',
                'label' => 'Feature card 3 icon',
                'type' => 'text_block',
                'order' => 78,
                'default_title' => 'Icon',
                'default_content' => 'fa-building',
            ],
            'feature_4_icon' => [
                'group' => 'Features Section',
                'label' => 'Feature card 4 icon',
                'type' => 'text_block',
                'order' => 79,
                'default_title' => 'Icon',
                'default_content' => 'fa-network-wired',
            ],
            'news_eyebrow' => [
                'group' => 'News Section',
                'label' => 'Section eyebrow',
                'type' => 'text_block',
                'order' => 90,
                'default_title' => 'News Eyebrow',
                'default_content' => 'Stay Informed',
            ],
            'news_title' => [
                'group' => 'News Section',
                'label' => 'Section heading',
                'type' => 'text_block',
                'order' => 91,
                'default_title' => 'News Title',
                'default_content' => 'Latest News & Updates.',
            ],
            'announcements_eyebrow' => [
                'group' => 'Announcements Section',
                'label' => 'Section eyebrow',
                'type' => 'text_block',
                'order' => 92,
                'default_title' => 'Announcements Eyebrow',
                'default_content' => 'Latest Updates',
            ],
            'announcements_title' => [
                'group' => 'Announcements Section',
                'label' => 'Section heading',
                'type' => 'text_block',
                'order' => 93,
                'default_title' => 'Announcements Title',
                'default_content' => 'Announcements.',
            ],
            'featured_courses_eyebrow' => [
                'group' => 'Featured Courses',
                'label' => 'Section eyebrow',
                'type' => 'text_block',
                'order' => 94,
                'default_title' => 'Courses Eyebrow',
                'default_content' => 'Courses',
            ],
            'featured_courses_title' => [
                'group' => 'Featured Courses',
                'label' => 'Section heading',
                'type' => 'text_block',
                'order' => 95,
                'default_title' => 'Courses Title',
                'default_content' => 'Featured Courses.',
            ],
            'portal_jobfair_url' => [
                'group' => 'Portal URLs',
                'label' => 'Job Fair portal URL',
                'type' => 'text_block',
                'order' => 100,
                'default_title' => 'Job Fair URL',
                'default_content' => 'https://jobfair.nielitbhubaneswar.in/',
            ],
            'portal_mocktest_url' => [
                'group' => 'Portal URLs',
                'label' => 'Mock Test portal URL',
                'type' => 'text_block',
                'order' => 101,
                'default_title' => 'Mock Test URL',
                'default_content' => 'https://mocktest.nielitbhubaneswar.in/',
            ],
            'portal_main_website_url' => [
                'group' => 'Portal URLs',
                'label' => 'NIELIT main website URL',
                'type' => 'text_block',
                'order' => 102,
                'default_title' => 'Main Website URL',
                'default_content' => 'https://www.nielit.gov.in/NielitMain/BBS',
            ],
            'footer_tagline' => [
                'group' => 'Footer',
                'label' => 'Footer description',
                'type' => 'text_block',
                'order' => 110,
                'default_title' => 'Footer Tagline',
                'default_content' => 'An autonomous scientific society dedicated to technology education and skill development.',
            ],
            'footer_address' => [
                'group' => 'Footer',
                'label' => 'Address',
                'type' => 'text_block',
                'order' => 111,
                'default_title' => 'Address',
                'default_content' => 'OCAC Tower, Acharya Vihar, Bhubaneswar, Odisha',
            ],
            'footer_phone' => [
                'group' => 'Footer',
                'label' => 'Phone',
                'type' => 'text_block',
                'order' => 112,
                'default_title' => 'Phone',
                'default_content' => '0674-2960354',
            ],
            'footer_email' => [
                'group' => 'Footer',
                'label' => 'Email',
                'type' => 'text_block',
                'order' => 113,
                'default_title' => 'Email',
                'default_content' => 'dir-bbsr@nielit.gov.in',
            ],
            'footer_hours' => [
                'group' => 'Footer',
                'label' => 'Office hours',
                'type' => 'text_block',
                'order' => 114,
                'default_title' => 'Hours',
                'default_content' => 'Mon–Fri: 09:00 AM – 5:30 PM',
            ],
            'footer_badge' => [
                'group' => 'Footer',
                'label' => 'Footer badge text',
                'type' => 'text_block',
                'order' => 115,
                'default_title' => 'Badge',
                'default_content' => 'Baleshwar Extension Active',
            ],
            'footer_credits' => [
                'group' => 'Footer',
                'label' => 'Footer credits line',
                'type' => 'text_block',
                'order' => 116,
                'default_title' => 'Credits',
                'default_content' => 'Designed & Developed by NIELIT Bhubaneswar IT Team',
            ],
            'footer_links_important' => [
                'group' => 'Footer',
                'label' => 'Important Links column (JSON: label, url, external)',
                'type' => 'text_block',
                'order' => 117,
                'default_title' => 'Important Links',
                'default_content' => '[{"label":"National Portal","url":"https://india.gov.in/","external":true},{"label":"MyGov","url":"https://www.mygov.in/","external":true},{"label":"RTI Online","url":"https://rtionline.gov.in/","external":true},{"label":"MeitY","url":"http://meity.gov.in/","external":true},{"label":"NIELIT HQ","url":"https://www.nielit.gov.in/","external":true}]',
                'json' => true,
            ],
            'footer_links_quick' => [
                'group' => 'Footer',
                'label' => 'Quick Links column (JSON)',
                'type' => 'text_block',
                'order' => 118,
                'default_title' => 'Quick Links',
                'default_content' => '[{"label":"About Us","url":"#","external":false},{"label":"Courses","url":"/public/courses","external":false},{"label":"News & Events","url":"/public/news","external":false},{"label":"Contact Us","url":"/public/contact","external":false},{"label":"Job Fair Portal","url":"__JOB_FAIR__","external":true},{"label":"Mock Test Portal","url":"__MOCK_TEST__","external":true},{"label":"Privacy Policy","url":"#","external":false},{"label":"Terms & Conditions","url":"#","external":false}]',
                'json' => true,
            ],
            'footer_links_student' => [
                'group' => 'Footer',
                'label' => 'Student Access column (JSON)',
                'type' => 'text_block',
                'order' => 119,
                'default_title' => 'Student Links',
                'default_content' => '[{"label":"Student Login","url":"/student/login","external":false},{"label":"Mock Test Portal","url":"__MOCK_TEST__","external":true},{"label":"Admit Card","url":"https://student.nielit.gov.in/","external":true},{"label":"Results","url":"https://student.nielit.gov.in/","external":true},{"label":"Certificate Verification","url":"#","external":false}]',
                'json' => true,
            ],
        ];
    }
}

if (!function_exists('homepageJsonSectionKeys')) {
    function homepageJsonSectionKeys(): array
    {
        $keys = ['hero_typing_lines'];
        foreach (getIndexHomepageSectionDefinitions() as $key => $def) {
            if (!empty($def['json'])) {
                $keys[] = $key;
            }
        }
        return array_values(array_unique($keys));
    }
}

if (!function_exists('homepageIsJsonSectionKey')) {
    function homepageIsJsonSectionKey(string $key): bool
    {
        return in_array($key, homepageJsonSectionKeys(), true);
    }
}

if (!function_exists('homepageResolveUrl')) {
    function homepageResolveUrl(string $url, array $portals = []): string
    {
        $url = trim($url);
        $replacements = [
            '__JOB_FAIR__' => $portals['jobfair'] ?? '',
            '__MOCK_TEST__' => $portals['mocktest'] ?? '',
            '__MAIN_WEBSITE__' => $portals['main'] ?? '',
        ];
        foreach ($replacements as $token => $value) {
            if ($url === $token && $value !== '') {
                return $value;
            }
        }
        if ($url === '' || $url === '#') {
            return $url;
        }
        if (preg_match('/^https?:\/\//i', $url) || strpos($url, 'mailto:') === 0) {
            return $url;
        }
        if (function_exists('relative_url')) {
            return relative_url(ltrim($url, '/'));
        }
        return $url;
    }
}

if (!function_exists('homepagePortalUrls')) {
    function homepagePortalUrls(array $map): array
    {
        $jobfair = homepageValue($map, 'portal_jobfair_url');
        if ($jobfair === '' && function_exists('getJobFairPortalUrl')) {
            $jobfair = getJobFairPortalUrl();
        }
        $mocktest = homepageValue($map, 'portal_mocktest_url');
        if ($mocktest === '' && function_exists('getMockTestPortalUrl')) {
            $mocktest = getMockTestPortalUrl();
        }
        $main = homepageValue($map, 'portal_main_website_url', 'content', 'https://www.nielit.gov.in/NielitMain/BBS');

        return [
            'jobfair' => $jobfair,
            'mocktest' => $mocktest,
            'main' => $main,
        ];
    }
}

if (!function_exists('homepageJsonArray')) {
    function homepageJsonArray(array $map, string $key, array $default = []): array
    {
        $raw = homepageValue($map, $key, 'content', '');
        if ($raw === '') {
            return $default;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $default;
    }
}

if (!function_exists('homepageChecklist')) {
    function homepageChecklist(array $map, string $key, array $default = []): array
    {
        if ($default === []) {
            $definitions = getIndexHomepageSectionDefinitions();
            if (isset($definitions[$key]['default_content'])) {
                $decodedDefault = json_decode((string) $definitions[$key]['default_content'], true);
                if (is_array($decodedDefault)) {
                    $default = $decodedDefault;
                }
            }
        }

        $items = homepageJsonArray($map, $key, $default);
        $lines = [];
        foreach ($items as $item) {
            $line = trim((string) $item);
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        return $lines ?: $default;
    }
}

if (!function_exists('homepageLinkItems')) {
    function homepageLinkItems(array $map, string $key, array $default = [], array $portals = []): array
    {
        if ($default === []) {
            $definitions = getIndexHomepageSectionDefinitions();
            if (isset($definitions[$key]['default_content'])) {
                $decodedDefault = json_decode((string) $definitions[$key]['default_content'], true);
                if (is_array($decodedDefault)) {
                    $default = $decodedDefault;
                }
            }
        }

        $items = homepageJsonArray($map, $key, $default);
        $links = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $url = homepageResolveUrl((string) ($item['url'] ?? '#'), $portals);
            $links[] = [
                'label' => $label,
                'url' => $url,
                'icon' => trim((string) ($item['icon'] ?? 'fa-chevron-right')),
                'external' => !empty($item['external']),
                'link' => array_key_exists('link', $item) ? (bool) $item['link'] : true,
            ];
        }
        return $links ?: array_map(static function ($item) use ($portals) {
            if (!is_array($item)) {
                return ['label' => '', 'url' => '#', 'icon' => 'fa-chevron-right', 'external' => false, 'link' => true];
            }
            return [
                'label' => (string) ($item['label'] ?? ''),
                'url' => homepageResolveUrl((string) ($item['url'] ?? '#'), $portals),
                'icon' => (string) ($item['icon'] ?? 'fa-chevron-right'),
                'external' => !empty($item['external']),
                'link' => array_key_exists('link', $item) ? (bool) $item['link'] : true,
            ];
        }, $default);
    }
}

if (!function_exists('homepageFeatureIcon')) {
    function homepageFeatureIcon(array $map, int $number, string $fallback = 'fa-star'): string
    {
        $icon = trim(homepageValue($map, 'feature_' . $number . '_icon', 'content', $fallback));
        return $icon !== '' ? $icon : $fallback;
    }
}

if (!function_exists('homepageButton')) {
    function homepageButton(array $map, string $key, array $portals = []): array
    {
        return [
            'label' => homepageValue($map, $key, 'title'),
            'url' => homepageResolveUrl(homepageValue($map, $key), $portals),
        ];
    }
}

if (!function_exists('seedIndexHomepageSections')) {
    function seedIndexHomepageSections($conn): void
    {
        if (!ensureHomepageContentSchema($conn)) {
            return;
        }

        $stmt = $conn->prepare(
            'INSERT INTO homepage_content (section_key, section_title, section_content, section_type, display_order, is_active)
             SELECT ?, ?, ?, ?, ?, 1 FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM homepage_content WHERE section_key = ? LIMIT 1)'
        );

        if (!$stmt) {
            error_log('seedIndexHomepageSections prepare failed: ' . $conn->error);
            return;
        }

        foreach (getIndexHomepageSectionDefinitions() as $key => $def) {
            $title = $def['default_title'];
            $content = $def['default_content'];
            $type = $def['type'];
            $order = (int) $def['order'];
            $stmt->bind_param('ssssis', $key, $title, $content, $type, $order, $key);
            $stmt->execute();
        }

        $stmt->close();
    }
}

if (!function_exists('clearHomepageContentCache')) {
    function clearHomepageContentCache(): void
    {
        unset($_SESSION['homepage_content_cache'], $_SESSION['homepage_content_cache_time'], $_SESSION['homepage_map_cache']);
    }
}

if (!function_exists('loadHomepageContentMap')) {
    function loadHomepageContentMap($conn, bool $activeOnly = true): array
    {
        ensureHomepageContentSchema($conn);
        seedIndexHomepageSections($conn);

        $sql = 'SELECT * FROM homepage_content';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY display_order ASC';

        $map = [];
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $map[$row['section_key']] = $row;
            }
        }

        return $map;
    }
}

if (!function_exists('homepageValue')) {
    function homepageValue(array $map, string $key, string $field = 'content', string $default = ''): string
    {
        $definitions = getIndexHomepageSectionDefinitions();
        if ($default === '' && isset($definitions[$key])) {
            $default = $field === 'title'
                ? (string) $definitions[$key]['default_title']
                : (string) $definitions[$key]['default_content'];
        }

        if (!isset($map[$key])) {
            return $default;
        }

        $row = $map[$key];
        $value = $field === 'title'
            ? (string) ($row['section_title'] ?? '')
            : (string) ($row['section_content'] ?? '');

        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('homepageTypingLines')) {
    function homepageTypingLines(array $map): array
    {
        $defaults = [
            ['line1' => 'Code Tomorrow.', 'line2' => 'Transform Today.'],
            ['line1' => 'Learn Today.', 'line2' => 'Lead Tomorrow.'],
            ['line1' => 'Skills Today.', 'line2' => 'Success Tomorrow.'],
        ];

        $raw = homepageValue($map, 'hero_typing_lines', 'content', '');
        if ($raw === '') {
            return $defaults;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        $lines = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $line1 = trim((string) ($item['line1'] ?? ''));
            $line2 = trim((string) ($item['line2'] ?? ''));
            if ($line1 !== '' && $line2 !== '') {
                $lines[] = ['line1' => $line1, 'line2' => $line2];
            }
        }

        return $lines ?: $defaults;
    }
}

if (!function_exists('loadHomepagePageSections')) {
    function loadHomepagePageSections($conn): array
    {
        $cache_duration = 3600;
        $cache_key = 'homepage_content_cache';
        $cache_time_key = 'homepage_content_cache_time';
        $map_cache_key = 'homepage_map_cache';

        if (
            isset($_SESSION[$cache_key], $_SESSION[$cache_time_key], $_SESSION[$map_cache_key])
            && (time() - (int) $_SESSION[$cache_time_key] < $cache_duration)
        ) {
            return [
                'map' => $_SESSION[$map_cache_key],
                'banners' => $_SESSION[$cache_key]['banners'] ?? [],
                'announcements_content' => $_SESSION[$cache_key]['announcements_content'] ?? [],
                'featured_courses' => $_SESSION[$cache_key]['featured_courses'] ?? [],
                'text_blocks' => $_SESSION[$cache_key]['text_blocks'] ?? [],
                'image_blocks' => $_SESSION[$cache_key]['image_blocks'] ?? [],
            ];
        }

        $map = loadHomepageContentMap($conn, true);
        $indexKeys = array_keys(getIndexHomepageSectionDefinitions());

        $banners = [];
        $announcements_content = [];
        $featured_courses = [];
        $text_blocks = [];
        $image_blocks = [];

        foreach ($map as $section) {
            if (in_array($section['section_key'], $indexKeys, true)) {
                continue;
            }

            switch ($section['section_type']) {
                case 'banner':
                    $banners[] = $section;
                    break;
                case 'announcement':
                    $announcements_content[] = $section;
                    break;
                case 'featured_course':
                    $featured_courses[] = $section;
                    break;
                case 'text_block':
                    $text_blocks[] = $section;
                    break;
                case 'image_block':
                    $image_blocks[] = $section;
                    break;
            }
        }

        $_SESSION[$cache_key] = compact('banners', 'announcements_content', 'featured_courses', 'text_blocks', 'image_blocks');
        $_SESSION[$cache_time_key] = time();
        $_SESSION[$map_cache_key] = $map;

        return compact('map', 'banners', 'announcements_content', 'featured_courses', 'text_blocks', 'image_blocks');
    }
}

if (!function_exists('getIndexHomepageCategoryDefinitions')) {
    function getIndexHomepageCategoryDefinitions(): array
    {
        return [
            [
                'key' => 'site_meta',
                'order' => 1,
                'title' => 'Page Title & Navbar',
                'icon' => 'fa-window-maximize',
                'description' => 'Browser tab title and navbar brand at the very top of the homepage.',
                'groups' => ['Site Meta'],
            ],
            [
                'key' => 'notice',
                'order' => 2,
                'title' => 'Notice Ticker',
                'icon' => 'fa-bell',
                'description' => 'Scrolling notice bar directly below the navigation menu.',
                'groups' => ['Notice Ticker'],
            ],
            [
                'key' => 'hero',
                'order' => 3,
                'title' => 'Hero Section',
                'icon' => 'fa-star',
                'description' => 'Main hero overlay — headline, subtitle, buttons, and stats on top of the carousel. Carousel images are managed in the Hero Carousel Banners card above.',
                'groups' => ['Hero Banner', 'Hero Buttons', 'Hero Stats'],
            ],
            [
                'key' => 'welcome',
                'order' => 4,
                'title' => 'Welcome Strip',
                'icon' => 'fa-hand-sparkles',
                'description' => 'Welcome message with contact and location pills on the right side.',
                'groups' => ['Welcome Strip'],
            ],
            [
                'key' => 'jobfair',
                'order' => 5,
                'title' => 'Job Fair Portal Block',
                'icon' => 'fa-briefcase',
                'description' => 'Job Fair promotion section with description, alert, stats, and portal buttons.',
                'groups' => ['Job Fair Portal'],
            ],
            [
                'key' => 'mocktest',
                'order' => 6,
                'title' => 'Mock Test Portal Block',
                'icon' => 'fa-laptop-code',
                'description' => 'Mock Test section with features, stats, and portal login buttons.',
                'groups' => ['Mock Test Portal'],
            ],
            [
                'key' => 'features',
                'order' => 7,
                'title' => 'Features Section',
                'icon' => 'fa-th-large',
                'description' => 'Four feature cards with icons shown below the portal blocks.',
                'groups' => ['Features Section'],
            ],
            [
                'key' => 'info',
                'order' => 8,
                'title' => 'About / Mission / Quick Access',
                'icon' => 'fa-info-circle',
                'description' => 'Three info cards — About NIELIT, Our Mission, and Quick Access links.',
                'groups' => ['Info Section'],
            ],
            [
                'key' => 'featured_courses',
                'order' => 9,
                'title' => 'Featured Courses Heading',
                'icon' => 'fa-graduation-cap',
                'description' => 'Section heading only. Actual course cards are added in Additional Homepage Blocks at the bottom of this page.',
                'groups' => ['Featured Courses'],
                'manage_elsewhere' => [
                    'label' => 'Add course blocks',
                    'url' => 'manage_homepage.php#additional-blocks',
                ],
            ],
            [
                'key' => 'news',
                'order' => 10,
                'title' => 'Latest News & Updates',
                'icon' => 'fa-newspaper',
                'description' => 'Section heading text. Add and edit news cards in the Latest News Articles section on this page.',
                'groups' => ['News Section'],
                'manage_elsewhere' => [
                    'label' => 'Manage News Articles',
                    'url' => '#homepage-news',
                ],
            ],
            [
                'key' => 'announcements',
                'order' => 11,
                'title' => 'Announcements',
                'icon' => 'fa-bullhorn',
                'description' => 'Section heading only. Announcement cards are managed separately.',
                'groups' => ['Announcements Section'],
                'manage_elsewhere' => [
                    'label' => 'Manage Announcements',
                    'url' => 'manage_announcements.php',
                ],
            ],
            [
                'key' => 'footer',
                'order' => 12,
                'title' => 'Footer',
                'icon' => 'fa-shoe-prints',
                'description' => 'Bottom of the page — contact details, link columns, badge, and credits.',
                'groups' => ['Footer'],
            ],
            [
                'key' => 'portal_urls',
                'order' => 13,
                'title' => 'Global Portal URLs',
                'icon' => 'fa-link',
                'description' => 'Shared portal links used across buttons and footer (__JOB_FAIR__, __MOCK_TEST__, __MAIN_WEBSITE__).',
                'groups' => ['Portal URLs'],
            ],
        ];
    }
}

if (!function_exists('getIndexHomepageSectionsGroupedByGroup')) {
    function getIndexHomepageSectionsGroupedByGroup($conn): array
    {
        seedIndexHomepageSections($conn);
        $map = loadHomepageContentMap($conn, false);
        $definitions = getIndexHomepageSectionDefinitions();
        $grouped = [];

        foreach ($definitions as $key => $def) {
            $group = $def['group'];
            $row = $map[$key] ?? null;
            $grouped[$group][] = [
                'section_key' => $key,
                'label' => $def['label'],
                'id' => $row['id'] ?? null,
                'section_title' => $row['section_title'] ?? $def['default_title'],
                'section_content' => $row['section_content'] ?? $def['default_content'],
                'section_type' => $row['section_type'] ?? $def['type'],
                'display_order' => $row['display_order'] ?? $def['order'],
                'is_active' => isset($row['is_active']) ? (int) $row['is_active'] : 1,
            ];
        }

        return $grouped;
    }
}

if (!function_exists('getIndexHomepageSectionsForAdmin')) {
    function getIndexHomepageSectionsForAdmin($conn): array
    {
        $grouped = getIndexHomepageSectionsGroupedByGroup($conn);
        $categories = [];
        $assignedGroups = [];

        foreach (getIndexHomepageCategoryDefinitions() as $category) {
            $categoryGroups = [];
            foreach ($category['groups'] as $groupName) {
                if (!empty($grouped[$groupName])) {
                    $categoryGroups[$groupName] = $grouped[$groupName];
                    $assignedGroups[$groupName] = true;
                }
            }

            if ($categoryGroups === []) {
                continue;
            }

            $categories[] = [
                'key' => $category['key'],
                'order' => $category['order'],
                'title' => $category['title'],
                'icon' => $category['icon'],
                'description' => $category['description'],
                'manage_elsewhere' => $category['manage_elsewhere'] ?? null,
                'groups' => $categoryGroups,
            ];
        }

        $uncategorized = [];
        foreach ($grouped as $groupName => $items) {
            if (empty($assignedGroups[$groupName])) {
                $uncategorized[$groupName] = $items;
            }
        }

        if ($uncategorized !== []) {
            $categories[] = [
                'key' => 'other',
                'order' => 99,
                'title' => 'Other Sections',
                'icon' => 'fa-folder-open',
                'description' => 'Additional homepage fields not mapped to a main index section.',
                'manage_elsewhere' => null,
                'groups' => $uncategorized,
            ];
        }

        return $categories;
    }
}
