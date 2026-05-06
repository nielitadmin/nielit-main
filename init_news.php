<?php
/**
 * Initialize Sample News Data
 * Run this script once to populate sample news
 */
require_once __DIR__ . '/config/config.php';

// Create news table
$create_table_sql = "CREATE TABLE IF NOT EXISTS news (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    category VARCHAR(100),
    image_url VARCHAR(500),
    is_featured TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_by VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($create_table_sql)) {
    echo "✓ News table created successfully<br>";
} else {
    echo "✗ Error creating news table: " . $conn->error . "<br>";
}

// Check if sample news already exists
$check_sql = "SELECT COUNT(*) as count FROM news";
$result = $conn->query($check_sql);
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Insert sample news
    $sample_news = [
        [
            'title' => 'NIELIT Bhubaneswar Launches New NSQF-Aligned Courses',
            'content' => 'NIELIT Bhubaneswar is excited to announce the launch of new NSQF-aligned courses designed to meet industry demands. These courses focus on emerging technologies including AI, Machine Learning, Cloud Computing, and Web Development. With hands-on training and industry partnerships, students will gain practical skills for immediate employment. Enroll now and transform your career!',
            'category' => 'Announcement',
            'image_url' => 'https://via.placeholder.com/400x300?text=New+Courses',
            'is_featured' => 1,
            'is_active' => 1,
            'created_by' => 'admin'
        ],
        [
            'title' => 'Students Achieve 95% Placement Rate',
            'content' => 'Celebrating success! NIELIT Bhubaneswar students have achieved a remarkable 95% placement rate in 2024. Our graduates are now working with leading tech companies including TCS, Infosys, and Wipro. The success is attributed to our comprehensive curriculum, expert faculty, and industry partnerships. Congratulations to all our achieving students!',
            'category' => 'Achievement',
            'image_url' => 'https://via.placeholder.com/400x300?text=Placements',
            'is_featured' => 1,
            'is_active' => 1,
            'created_by' => 'admin'
        ],
        [
            'title' => 'Campus Internship Fair 2025 - Register Now',
            'content' => 'Join us for the NIELIT Bhubaneswar Campus Internship Fair! Network with 50+ leading companies hiring interns. This is a great opportunity to gain real-world experience and explore career paths. The fair will be held on May 15-16, 2025 at OCAC Tower. All registered students are welcome. Spots are limited, so register early!',
            'category' => 'Event',
            'image_url' => 'https://via.placeholder.com/400x300?text=Internship+Fair',
            'is_featured' => 0,
            'is_active' => 1,
            'created_by' => 'admin'
        ],
        [
            'title' => 'Balasore Extension Center Now Operational',
            'content' => 'Great news! Our new training center in Balasore is now fully operational. This expansion allows us to reach more students across eastern Odisha. The Balasore center features modern labs, experienced faculty, and the same quality education as our main center. Admissions are open for all our popular courses. Visit our Balasore center or contact us for more information.',
            'category' => 'Update',
            'image_url' => 'https://via.placeholder.com/400x300?text=Balasore+Center',
            'is_featured' => 0,
            'is_active' => 1,
            'created_by' => 'admin'
        ],
        [
            'title' => 'Faculty Workshop on Latest Technologies',
            'content' => 'Our faculty team recently completed an intensive workshop on latest technologies including Artificial Intelligence, Blockchain, and IoT. This continuous professional development ensures our courses remain cutting-edge and relevant to industry needs. Our instructors bring the latest knowledge directly to the classroom.',
            'category' => 'Update',
            'image_url' => 'https://via.placeholder.com/400x300?text=Faculty+Workshop',
            'is_featured' => 0,
            'is_active' => 1,
            'created_by' => 'admin'
        ],
        [
            'title' => 'Government Recognition and Accreditation Update',
            'content' => 'NIELIT Bhubaneswar has received fresh accreditation and recognition from the Ministry of Electronics & IT. Our commitment to quality education and student welfare continues to be recognized at national levels. This accreditation validates our efforts in providing NSQF-aligned training and industry-ready skills to our students.',
            'category' => 'Achievement',
            'image_url' => 'https://via.placeholder.com/400x300?text=Accreditation',
            'is_featured' => 0,
            'is_active' => 1,
            'created_by' => 'admin'
        ]
    ];

    foreach ($sample_news as $news) {
        $sql = "INSERT INTO news (title, content, category, image_url, is_featured, is_active, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssiis", 
            $news['title'], 
            $news['content'], 
            $news['category'], 
            $news['image_url'], 
            $news['is_featured'], 
            $news['is_active'], 
            $news['created_by']
        );
        
        if ($stmt->execute()) {
            echo "✓ Added: " . $news['title'] . "<br>";
        } else {
            echo "✗ Error adding news: " . $stmt->error . "<br>";
        }
    }
    
    echo "<hr><p style='color: green; font-weight: bold;'>✓ Sample news data initialized successfully!</p>";
    echo "<p><a href='index.php'>← Back to Home</a> | <a href='admin/manage_news.php'>Go to News Management →</a></p>";
} else {
    echo "<p style='color: orange;'>ℹ News table already contains data. Skipping sample data insertion.</p>";
    echo "<p><a href='index.php'>← Back to Home</a> | <a href='admin/manage_news.php'>Go to News Management →</a></p>";
}

$conn->close();
?>
