<?php
// =============================================================================
// home.php - Fixed Homepage with Corrected Image Paths
// =============================================================================
session_start();
require __DIR__ . '/../vendor/autoload.php';
use Lourdian\MonbelaHotel\Model\Room;
use Lourdian\MonbelaHotel\Model\Guest;

// Initialize models - only Room is needed
$roomModel = new Room();

// Get date parameters for availability checking
$checkin = $_GET['checkin'] ?? date('Y-m-d');
$checkout = $_GET['checkout'] ?? date('Y-m-d', strtotime('+1 day'));
$guests = $_GET['guests'] ?? 2;

// Get all available rooms using the getAvailableRoomsByDate method
$allRooms = $roomModel->getAvailableRoomsByDate($checkin, $checkout);

// If no rooms found with date filtering, fallback to all non-maintenance rooms
if (empty($allRooms)) {
    $allRooms = $roomModel->getAvailableRooms();
    
    // Filter out maintenance rooms
    $allRooms = array_filter($allRooms, function($room) {
        $status = $room['ROOM_STATUS'] ?? 'Available';
        return strtolower($status) !== 'maintenance';
    });
}

// Get all accommodation types using Room model's method
$accommodations = $roomModel->getAllAccommodations();

// Get featured rooms (first 6 rooms)
$featuredRooms = array_slice($allRooms, 0, 6);

// Mock testimonials data (since there's no testimonials table)
$testimonials = [
    [
        'name' => 'Sarah Johnson',
        'title' => 'Business Executive',
        'rating' => 5,
        'comment' => 'Outstanding service and beautiful accommodations. The staff went above and beyond to make our stay memorable.',
        'avatar' => 'https://i.pravatar.cc/150?img=1'
    ],
    [
        'name' => 'Michael Chen',
        'title' => 'Travel Blogger',
        'rating' => 5,
        'comment' => 'One of the finest hotels I have ever stayed at. The attention to detail and luxury amenities are exceptional.',
        'avatar' => 'https://i.pravatar.cc/150?img=2'
    ],
    [
        'name' => 'Emily Rodriguez',
        'title' => 'Wedding Planner',
        'rating' => 5,
        'comment' => 'Perfect venue for our destination wedding. The team made everything seamless and stress-free.',
        'avatar' => 'https://i.pravatar.cc/150?img=3'
    ]
];

// FIXED: Helper function to get image URL
function getRoomImageUrl($roomImage) {
    // Handle empty or null images
    if (empty($roomImage) || $roomImage === 'NULL' || strtolower($roomImage) === 'null') {
        return 'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=600&h=400&fit=crop';
    }
    
    // Check if it's already a full URL
    if (strpos($roomImage, 'http://') === 0 || strpos($roomImage, 'https://') === 0) {
        return $roomImage;
    }
    
    // Check if it's a base64 encoded image
    if (strpos($roomImage, 'data:image') === 0) {
        return $roomImage;
    }
    
    // Clean the filename - remove any path separators that might be in the database
    $filename = basename($roomImage);
    
    // Define possible web paths to check (in order of preference)
    $webPaths = [
        '/MonbelaHotel/uploads/rooms/' . $filename,
        '/uploads/rooms/' . $filename,
        '../uploads/rooms/' . $filename,
        '../../uploads/rooms/' . $filename,
    ];
    
    // Check each path
    foreach ($webPaths as $webPath) {
        // Convert web path to filesystem path
        if (strpos($webPath, '/') === 0) {
            // Absolute web path
            $fsPath = $_SERVER['DOCUMENT_ROOT'] . $webPath;
        } else {
            // Relative path
            $fsPath = __DIR__ . '/' . $webPath;
        }
        
        // Normalize path separators for Windows
        $fsPath = str_replace('/', DIRECTORY_SEPARATOR, $fsPath);
        
        // Check if file exists
        if (file_exists($fsPath) && is_file($fsPath)) {
            // Return the web path (always use forward slashes for URLs)
            return str_replace('\\', '/', $webPath);
        }
    }
    
    // Additional check: Try direct path from document root
    $directPath = $_SERVER['DOCUMENT_ROOT'] . '/MonbelaHotel/uploads/rooms/' . $filename;
    $directPath = str_replace('/', DIRECTORY_SEPARATOR, $directPath);
    
    if (file_exists($directPath) && is_file($directPath)) {
        return '/MonbelaHotel/uploads/rooms/' . $filename;
    }
    
    // Final fallback: check if file exists in any subdirectory
    $uploadsDir = $_SERVER['DOCUMENT_ROOT'] . '/MonbelaHotel/uploads/rooms/';
    $uploadsDir = str_replace('/', DIRECTORY_SEPARATOR, $uploadsDir);
    
    if (is_dir($uploadsDir)) {
        $files = scandir($uploadsDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && strtolower($file) === strtolower($filename)) {
                return '/MonbelaHotel/uploads/rooms/' . $file;
            }
        }
    }
    
    // If nothing found, return Unsplash fallback
    return 'https://images.unsplash.com/photo-1590490360182-c33d57733427?w=600&h=400&fit=crop';
}

// Function to get room status for display
function getRoomStatusDisplay($room, $checkin = null, $checkout = null) {
    $status = $room['ROOM_STATUS'] ?? 'Available';
    
    // If room is under maintenance, show maintenance status
    if (strtolower($status) === 'maintenance') {
        return [
            'status' => 'Maintenance',
            'color' => 'bg-red-500',
            'text' => 'text-white',
            'icon' => 'fas fa-tools',
            'available' => false
        ];
    }
    
    // If we have dates, check if room is available for those dates
    if ($checkin && $checkout) {
        // Room passed date filtering, so it's available
        return [
            'status' => 'Available',
            'color' => 'bg-green-500',
            'text' => 'text-white',
            'icon' => 'fas fa-check-circle',
            'available' => true
        ];
    }
    
    // Default: assume available
    return [
        'status' => 'Available',
        'color' => 'bg-green-500',
        'text' => 'text-white',
        'icon' => 'fas fa-check-circle',
        'available' => true
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monbela Hotel - Luxury Redefined</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/vanta/0.5.24/vanta.birds.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-gold: #D4AF37;
            --dark-gold: #B8941F;
            --deep-navy: #1a1f36;
            --soft-navy: #2d3561;
            --cream: #FAF7F0;
            --light-gray: #F5F5F5;
            --medium-gray: #6B7280;
        }
        
        .font-serif { font-family: 'Playfair Display', serif; }
        .font-sans { font-family: 'Inter', sans-serif; }
        
        /* Enhanced gradient backgrounds */
        .hero-gradient {
            background: linear-gradient(135deg, 
                rgba(26, 31, 54, 0.95) 0%, 
                rgba(45, 53, 97, 0.9) 50%, 
                rgba(212, 175, 55, 0.8) 100%);
        }
        
        .luxury-gradient {
            background: linear-gradient(135deg, var(--primary-gold) 0%, var(--dark-gold) 100%);
        }
        
        .navy-gradient {
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--soft-navy) 100%);
        }
        
        /* Enhanced text gradient */
        .text-luxury-gradient {
            background: linear-gradient(135deg, var(--primary-gold), #F4E4BC, var(--primary-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: shimmer 3s ease-in-out infinite;
            background-size: 200% 200%;
        }
        
        @keyframes shimmer {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        /* Modern card design */
        .luxury-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(212, 175, 55, 0.1);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        .luxury-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            border-color: rgba(212, 175, 55, 0.3);
        }
        
        /* Premium button styles */
        .btn-luxury {
            background: var(--primary-gold);
            color: var(--deep-navy);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        .btn-luxury::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.6s ease;
        }
        
        .btn-luxury:hover::before {
            left: 100%;
        }
        
        .btn-luxury:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.3);
        }
        
        /* Enhanced navigation */
        .nav-link {
            position: relative;
            color: var(--deep-navy);
            transition: all 0.3s ease;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary-gold);
            transition: width 0.3s ease;
        }
        
        .nav-link:hover::after {
            width: 100%;
        }
        
        /* Floating animation enhanced */
        @keyframes luxuryFloat {
            0%, 100% { 
                transform: translateY(0px) rotate(0deg); 
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            }
            50% { 
                transform: translateY(-20px) rotate(1deg); 
                box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
            }
        }
        
        .luxury-float {
            animation: luxuryFloat 6s ease-in-out infinite;
        }
        
        /* Enhanced room cards */
        .room-card-modern {
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            background: white;
        }
        
        .room-card-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(212, 175, 55, 0) 0%, rgba(212, 175, 55, 0.1) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .room-card-modern:hover::before {
            opacity: 1;
        }
        
        .room-image-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, 
                transparent 0%, 
                rgba(0, 0, 0, 0.3) 50%,
                rgba(26, 31, 54, 0.9) 100%);
            opacity: 0;
            transition: opacity 0.5s ease;
        }
        
        .room-card-modern:hover .room-image-overlay {
            opacity: 1;
        }
        
        /* Image loading state */
        .room-image-loading {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        .room-image-error {
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
        }
        
        /* Testimonial cards enhanced */
        .testimonial-luxury {
            background: white;
            border: 1px solid rgba(212, 175, 55, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .testimonial-luxury::before {
            content: '"';
            position: absolute;
            top: -20px;
            left: 20px;
            font-size: 100px;
            color: rgba(212, 175, 55, 0.1);
            font-family: 'Playfair Display', serif;
        }
        
        /* Feature cards */
        .feature-card {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary-gold);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--cream);
            border-radius: 20px;
            transition: all 0.3s ease;
        }
        
        .feature-card:hover .feature-icon {
            background: var(--primary-gold);
            transform: scale(1.1);
        }
        
        /* Search box enhanced */
        .luxury-search {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 60px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }
        
        .luxury-search:focus-within {
            border-color: var(--primary-gold);
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.1);
        }
        
        /* Scroll animations enhanced */
        .reveal {
            opacity: 0;
            transform: translateY(50px);
            transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        
        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Premium scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--cream);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-gold);
            border-radius: 5px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--dark-gold);
        }
        
        /* Mobile optimizations */
        @media (max-width: 768px) {
            .hero-gradient h1 {
                font-size: 2.5rem;
            }
            
            .luxury-card {
                margin-bottom: 1.5rem;
            }
        }
        
        /* Added missing styles for gold colors */
        .bg-gold\/10 { background-color: rgba(212, 175, 55, 0.1); }
        .border-gold\/20 { border-color: rgba(212, 175, 55, 0.2); }
        .border-gold\/30 { border-color: rgba(212, 175, 55, 0.3); }
        .text-primary-gold { color: var(--primary-gold); }
        .text-deep-navy { color: var(--deep-navy); }
        .bg-cream { background-color: var(--cream); }
        .bg-deep-navy { background-color: var(--deep-navy); }
        .bg-white\/90 { background-color: rgba(255, 255, 255, 0.9); }
        .backdrop-blur-lg { backdrop-filter: blur(16px); }
        .shadow-xl { box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
        .bg-green-500 { background-color: #10b981; }
        .bg-red-500 { background-color: #ef4444; }
    </style>
</head>
<body class="font-sans text-gray-800 bg-cream">
    <!-- Premium Navigation -->
    <nav class="fixed top-0 w-full z-50 bg-white/95 backdrop-blur-lg border-b border-gold/20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center">
                    <a href="home.php" class="font-serif text-3xl font-bold text-luxury-gradient">Monbela</a>
                </div>
                <div class="hidden md:flex items-center space-x-8">
                    <a href="home.php" class="nav-link font-medium">Home</a>


                    <?php if (isset($_SESSION['id'])): ?>
                        <a href="homepage.php" class="nav-link font-medium">Dashboard</a>
                        <a href="guest_profile.php?logout=1" class="nav-link font-medium">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="nav-link font-medium">Login</a>
                        <a href="register.php" class="nav-link font-medium">Register</a>
                    <?php endif; ?>
                    <a href="<?php echo isset($_SESSION['id']) ? 'booking.php' : 'login.php'; ?>" class="btn-luxury px-8 py-3 rounded-full">
                        Reserve Now
                    </a>
                </div>
                <div class="md:hidden">
                    <button id="mobile-menu-btn" class="text-deep-navy">
                        <i class="fas fa-bars text-2xl"></i>
                    </button>
                </div>
            </div>
        </div>
        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden bg-white border-t border-gray-200">
            <div class="px-4 py-2 space-y-2">
                <a href="home.php" class="block py-3 text-deep-navy font-medium">Home</a>
                <?php if (isset($_SESSION['id'])): ?>
                    <a href="homepage.php" class="block py-3 text-deep-navy font-medium">Dashboard</a>
                    <a href="guest_profile.php?logout=1" class="block py-3 text-deep-navy font-medium">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="block py-3 text-deep-navy font-medium">Login</a>
                    <a href="register.php" class="block py-3 text-deep-navy font-medium">Register</a>
                <?php endif; ?>
                <a href="<?php echo isset($_SESSION['id']) ? 'booking.php' : 'login.php'; ?>" class="block py-3">
                    <span class="btn-luxury px-6 py-2 rounded-full inline-block">Reserve Now</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section Enhanced -->
    <section id="home" class="hero-gradient min-h-screen flex items-center relative overflow-hidden">
        <div id="vanta-bg" class="absolute inset-0 z-0"></div>
        <div class="absolute inset-0 bg-gradient-to-br from-deep-navy/80 to-transparent z-5"></div>
        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <div class="text-white reveal">
                    <div class="mb-8">
                        <span class="inline-block px-4 py-2 bg-white/10 backdrop-blur-md rounded-full text-sm font-medium mb-6">
                            ⭐ Awarded Best Luxury Hotel 2024
                        </span>
                    </div>
                    <h1 class="font-serif text-5xl lg:text-7xl font-bold mb-6 leading-tight">
                        Where <span class="text-luxury-gradient">Luxury</span><br>
                        Meets Perfection
                    </h1>
                    <p class="text-xl lg:text-2xl mb-8 text-gray-200 leading-relaxed">
                        Immerse yourself in unparalleled elegance at Monbela Hotel. 
                        Every moment crafted to exceed your expectations.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="#rooms" class="btn-luxury px-10 py-4 rounded-full text-lg font-semibold inline-flex items-center justify-center">
                            <i class="fas fa-crown mr-3"></i>
                            Explore Suites
                        </a>
                        <a href="<?php echo isset($_SESSION['id']) ? 'booking.php' : 'login.php'; ?>" class="border-2 border-white/30 backdrop-blur-sm text-white px-10 py-4 rounded-full text-lg font-semibold hover:bg-white/10 transition-all duration-300 inline-flex items-center justify-center">
                            <i class="fas fa-calendar-check mr-3"></i>
                            Check Availability
                        </a>
                    </div>
                    <div class="mt-12 flex items-center gap-8">
                        <div>
                            <div class="text-3xl font-bold text-luxury-gradient">98%</div>
                            <div class="text-gray-300">Guest Satisfaction</div>
                        </div>
                        <div class="h-12 w-px bg-white/20"></div>
                        <div>
                            <div class="text-3xl font-bold text-luxury-gradient">5★</div>
                            <div class="text-gray-300">Hotel Rating</div>
                        </div>
                    </div>
                </div>
                <div class="relative reveal">
                    <div class="luxury-float">
                        <img src="https://images.unsplash.com/photo-1564501049412-61c2a3083791?w=600&h=700&fit=crop" 
                             alt="Luxury Hotel Suite" 
                             class="rounded-3xl shadow-2xl w-full h-auto">
                        <div class="absolute -bottom-6 -right-6 bg-white rounded-2xl p-6 shadow-xl">
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 bg-gold/10 rounded-full flex items-center justify-center">
                                    <i class="fas fa-award text-2xl text-primary-gold"></i>
                                </div>
                                <div>
                                    <div class="font-serif text-2xl font-bold text-deep-navy">25+</div>
                                    <div class="text-gray-600">Years of Excellence</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Premium Booking Widget -->
    <section class="bg-white py-6 shadow-xl sticky top-20 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <form action="home.php" method="GET" class="flex flex-col lg:flex-row items-center justify-between gap-4">
                <div class="flex flex-col sm:flex-row gap-4 flex-1">
                    <div class="flex-1">
                        <label class="block text-sm font-semibold text-deep-navy mb-2">Check-in</label>
                        <div class="relative">
                            <input type="date" name="checkin" id="checkin" value="<?= htmlspecialchars($checkin) ?>" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary-gold/50 focus:border-primary-gold transition-all" required>
                            <i class="fas fa-calendar-alt absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-semibold text-deep-navy mb-2">Check-out</label>
                        <div class="relative">
                            <input type="date" name="checkout" id="checkout" value="<?= htmlspecialchars($checkout) ?>" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary-gold/50 focus:border-primary-gold transition-all" required>
                            <i class="fas fa-calendar-alt absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-semibold text-deep-navy mb-2">Guests</label>
                        <div class="relative">
                            <select name="guests" id="guests" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-primary-gold/50 focus:border-primary-gold transition-all appearance-none">
                                <option value="1" <?= $guests == 1 ? 'selected' : '' ?>>1 Guest</option>
                                <option value="2" <?= $guests == 2 ? 'selected' : '' ?>>2 Guests</option>
                                <option value="3" <?= $guests == 3 ? 'selected' : '' ?>>3 Guests</option>
                                <option value="4" <?= $guests == 4 ? 'selected' : '' ?>>4 Guests</option>
                                <option value="5" <?= $guests >= 5 ? 'selected' : '' ?>>5+ Guests</option>
                            </select>
                            <i class="fas fa-users absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn-luxury px-10 py-3.5 rounded-full font-semibold whitespace-nowrap">
                    <i class="fas fa-search mr-2"></i>
                    Check Availability
                </button>
            </form>
        </div>
    </section>

    <!-- Luxury Rooms Section -->
    <section id="rooms" class="py-24 bg-gradient-to-b from-cream to-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16 reveal">
                <span class="inline-block px-6 py-2 bg-gold/10 backdrop-blur-md rounded-full text-sm font-semibold text-primary-gold mb-4">
                    EXCLUSIVE COLLECTION
                </span>
                <h2 class="font-serif text-5xl lg:text-6xl font-bold text-deep-navy mb-6">Signature Suites</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Discover our meticulously designed accommodations, where every detail speaks of luxury and comfort
                </p>
                <?php if ($checkin && $checkout): ?>
                <div class="mt-4 text-lg text-gray-700">
                    Showing rooms available from <?= date('M j, Y', strtotime($checkin)) ?> to <?= date('M j, Y', strtotime($checkout)) ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Luxury Search Box -->
            <div class="max-w-2xl mx-auto mb-12">
                <div class="luxury-search relative">
                    <i class="fas fa-search absolute left-6 top-1/2 transform -translate-y-1/2 text-gray-400 text-xl"></i>
                    <input type="text" id="roomSearch" placeholder="Search by suite name, type, or amenities..." class="w-full pl-16 pr-6 py-4 bg-transparent text-lg">
                </div>
            </div>
            
            <!-- Accommodation Type Filter -->
            <div class="flex flex-wrap justify-center gap-3 mb-8">
                <button class="filter-btn px-4 py-2 rounded-full border border-gold/30 hover:bg-gold/10 transition-all bg-primary-gold text-white" data-type="all">
                    All Types
                </button>
                <?php foreach ($accommodations as $accommodation): ?>
                    <button class="filter-btn px-4 py-2 rounded-full border border-gold/30 hover:bg-gold/10 transition-all" data-type="<?= htmlspecialchars($accommodation['ACCOMID']) ?>">
                        <?= htmlspecialchars($accommodation['ACCOMODATION']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8" id="roomsGrid">
                <?php foreach ($allRooms as $room): 
                    $imageUrl = getRoomImageUrl($room['ROOMIMAGE'] ?? '');
                    
                    // Get accommodation name for this room
                    $roomAccommodation = '';
                    foreach ($accommodations as $acc) {
                        if ($acc['ACCOMID'] == $room['ACCOMID']) {
                            $roomAccommodation = $acc['ACCOMODATION'];
                            break;
                        }
                    }
                    
                    // Get room status for display
                    $roomStatus = getRoomStatusDisplay($room, $checkin, $checkout);
                ?>
                <div class="luxury-card room-card-modern reveal" data-accommodation-id="<?= htmlspecialchars($room['ACCOMID'] ?? '') ?>">
                    <div class="relative h-72 overflow-hidden">
                        <img src="<?= htmlspecialchars($imageUrl) ?>" 
                             alt="<?= htmlspecialchars($room['ROOM'] ?? 'Room') ?>" 
                             class="w-full h-full object-cover transform transition-transform duration-700 hover:scale-110"
                             onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1590490360182-c33d57733427?w=600&h=400&fit=crop'; this.parentElement.classList.add('room-image-error');">
                        <div class="room-image-overlay"></div>
                        
                        <div class="absolute top-4 left-4 flex flex-col gap-1 z-10">
                            <span class="px-3 py-1 <?= $roomStatus['color'] ?> <?= $roomStatus['text'] ?> rounded-full text-xs font-semibold">
                                <i class="<?= $roomStatus['icon'] ?> mr-1"></i> <?= htmlspecialchars($roomStatus['status']) ?>
                            </span>
                            <?php if (!empty($roomAccommodation)): ?>
                                <span class="px-3 py-1 luxury-gradient text-white rounded-full text-xs font-semibold">
                                    <i class="fas fa-tag mr-1"></i> <?= htmlspecialchars($roomAccommodation) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <button class="absolute top-4 right-4 w-12 h-12 bg-white/90 backdrop-blur-sm rounded-full flex items-center justify-center group transition-all hover:bg-white z-10" onclick="toggleFavorite(this)">
                            <i class="far fa-heart text-lg text-deep-navy group-hover:text-red-500 transition-colors"></i>
                        </button>
                        
                        <div class="absolute bottom-0 left-0 right-0 p-6 text-white opacity-0 transform translate-y-4 transition-all duration-500 group-hover:opacity-100 group-hover:translate-y-0">
                            <div class="flex gap-3">
                                <?php if ($roomStatus['available']): ?>
                                <button class="flex-1 bg-white/90 backdrop-blur-sm text-deep-navy py-2 px-4 rounded-xl font-semibold hover:bg-white transition-all" onclick="viewRoomDetails('<?= htmlspecialchars($room['ROOMID'] ?? '') ?>')">
                                    <i class="fas fa-eye mr-2"></i> View Details
                                </button>
                                <button class="flex-1 luxury-gradient text-white py-2 px-4 rounded-xl font-semibold hover:shadow-lg transition-all" onclick="bookRoom('<?= htmlspecialchars($room['ROOMID'] ?? '') ?>')">
                                    <i class="fas fa-calendar-plus mr-2"></i> Reserve
                                </button>
                                <?php else: ?>
                                <button class="flex-1 bg-gray-300 text-gray-600 py-2 px-4 rounded-xl font-semibold cursor-not-allowed" disabled>
                                    <i class="fas fa-ban mr-2"></i> Unavailable
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <h3 class="font-serif text-2xl font-bold text-deep-navy"><?= htmlspecialchars($room['ROOM'] ?? 'Suite') ?></h3>
                                <?php if (!empty($roomAccommodation)): ?>
                                    <p class="text-sm text-gray-500 mt-1">
                                        <i class="fas fa-tag mr-1"></i> <?= htmlspecialchars($roomAccommodation) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-primary-gold">₱<?= htmlspecialchars(number_format($room['PRICE'] ?? 199, 0)) ?></div>
                                <div class="text-sm text-gray-500">per night</div>
                            </div>
                        </div>
                        
                        <p class="text-gray-600 mb-4"><?= htmlspecialchars(substr($room['ROOMDESC'] ?? 'Luxurious accommodation with premium amenities', 0, 100)) ?>...</p>
                        
                        <div class="flex items-center gap-4 mb-4 text-sm text-gray-600">
                            <span class="flex items-center">
                                <i class="fas fa-users text-primary-gold mr-2"></i>
                                <?= htmlspecialchars($room['NUMPERSON'] ?? 2) ?> Guests
                            </span>
                            <span class="flex items-center">
                                <i class="fas fa-door-open text-primary-gold mr-2"></i>
                                Suite #<?= htmlspecialchars($room['ROOMNUM'] ?? 'N/A') ?>
                            </span>
                        </div>
                        
                        <div class="flex flex-wrap gap-2 mb-4">
                            <?php 
                            // Get amenities from the room model
                            $amenities = $roomModel->getRoomAmenities($room['ROOMID'] ?? 0);
                            
                            if (empty($amenities)) {
                                // Fallback amenities based on room type
                                $amenities = ['High-Speed WiFi', 'Spa Bath'];
                                if (stripos($room['ROOM'] ?? '', 'suite') !== false || stripos($room['ROOM'] ?? '', 'deluxe') !== false) {
                                    $amenities[] = 'Butler Service';
                                    $amenities[] = 'Private Balcony';
                                }
                                if ($room['PRICE'] ?? 0 > 500) {
                                    $amenities[] = 'Jacuzzi';
                                    $amenities[] = 'Premium View';
                                }
                            } else {
                                // Convert amenity keys to display names
                                $amenities = $roomModel->getFormattedAmenities($amenities);
                            }
                            
                            foreach ($amenities as $amenity): 
                            ?>
                                <span class="px-3 py-1 bg-cream rounded-lg text-xs font-medium text-deep-navy">
                                    <i class="fas 
                                        <?php 
                                        $icons = [
                                            'WiFi' => 'fa-wifi',
                                            'wifi' => 'fa-wifi',
                                            'Air Conditioning' => 'fa-snowflake',
                                            'ac' => 'fa-snowflake',
                                            'Smart TV' => 'fa-tv',
                                            'tv' => 'fa-tv',
                                            'Balcony' => 'fa-umbrella-beach',
                                            'balcony' => 'fa-umbrella-beach',
                                            'Bathtub' => 'fa-bath',
                                            'bathtub' => 'fa-bath',
                                            'Coffee Maker' => 'fa-coffee',
                                            'coffee' => 'fa-coffee',
                                            'Bath' => 'fa-bath',
                                            'Butler' => 'fa-concierge-bell',
                                            'Jacuzzi' => 'fa-hot-tub',
                                            'View' => 'fa-mountain'
                                        ];
                                        $matched = false;
                                        foreach ($icons as $key => $icon) {
                                            if (stripos($amenity, $key) !== false) {
                                                echo $icon;
                                                $matched = true;
                                                break;
                                            }
                                        }
                                        if (!$matched) echo 'fa-check';
                                        ?> 
                                        mr-1"></i> 
                                    <?= htmlspecialchars($amenity) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($roomStatus['available']): ?>
                        <button onclick="bookRoom('<?= htmlspecialchars($room['ROOMID'] ?? '') ?>')" class="w-full btn-luxury py-3 rounded-xl font-semibold">
                            Reserve This Suite
                        </button>
                        <?php else: ?>
                        <button class="w-full bg-gray-300 text-gray-600 py-3 rounded-xl font-semibold cursor-not-allowed" disabled>
                            Currently Unavailable
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($allRooms)): ?>
            <div class="text-center py-16">
                <i class="fas fa-bed text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-2xl font-serif font-semibold text-gray-600 mb-2">No Available Suites</h3>
                <p class="text-gray-500 mb-4">
                    <?php if ($checkin && $checkout): ?>
                    No suites available for the selected dates: <?= date('M j, Y', strtotime($checkin)) ?> - <?= date('M j, Y', strtotime($checkout)) ?>
                    <?php else: ?>
                    Please check back later for available accommodations.
                    <?php endif; ?>
                </p>
                <button onclick="clearDateFilters()" class="btn-luxury px-6 py-2 rounded-full">
                    Clear Date Filters
                </button>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Premium Amenities -->
    <section id="amenities" class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16 reveal">
                <span class="inline-block px-6 py-2 bg-gold/10 backdrop-blur-md rounded-full text-sm font-semibold text-primary-gold mb-4">
                    EXCLUSIVE FACILITIES
                </span>
                <h2 class="font-serif text-5xl lg:text-6xl font-bold text-deep-navy mb-6">Premium Amenities</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Experience world-class facilities designed for your ultimate comfort and pleasure
                </p>
            </div>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="feature-card reveal">
                    <div class="feature-icon">
                        <i class="fas fa-swimming-pool text-3xl text-primary-gold"></i>
                    </div>
                    <h3 class="font-serif text-xl font-bold text-deep-navy mb-3">Infinity Pool</h3>
                    <p class="text-gray-600">Rooftop pool with panoramic city views and poolside service</p>
                </div>
                
                <div class="feature-card reveal">
                    <div class="feature-icon">
                        <i class="fas fa-spa text-3xl text-primary-gold"></i>
                    </div>
                    <h3 class="font-serif text-xl font-bold text-deep-navy mb-3">Wellness Spa</h3>
                    <p class="text-gray-600">Rejuvenating treatments and therapeutic massages</p>
                </div>
                
                <div class="feature-card reveal">
                    <div class="feature-icon">
                        <i class="fas fa-utensils text-3xl text-primary-gold"></i>
                    </div>
                    <h3 class="font-serif text-xl font-bold text-deep-navy mb-3">Michelin Dining</h3>
                    <p class="text-gray-600">Award-winning restaurants with world-renowned chefs</p>
                </div>
                
                <div class="feature-card reveal">
                    <div class="feature-icon">
                        <i class="fas fa-helicopter text-3xl text-primary-gold"></i>
                    </div>
                    <h3 class="font-serif text-xl font-bold text-deep-navy mb-3">Helipad Transfer</h3>
                    <p class="text-gray-600">Exclusive helicopter service for VIP arrivals</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Enhanced -->
    <section class="py-24 navy-gradient">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16 reveal">
                <span class="inline-block px-6 py-2 bg-white/10 backdrop-blur-md rounded-full text-sm font-semibold text-primary-gold mb-4">
                    GUEST TESTIMONIALS
                </span>
                <h2 class="font-serif text-5xl lg:text-6xl font-bold text-white mb-6">Unforgettable Experiences</h2>
                <p class="text-xl text-gray-300">Hear from our distinguished guests about their stay</p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-8">
                <?php foreach ($testimonials as $testimonial): ?>
                <div class="testimonial-luxury p-8 rounded-2xl reveal">
                    <div class="flex text-primary-gold mb-4">
                        <?php for ($i = 0; $i < $testimonial['rating']; $i++): ?>
                        <i class="fas fa-star text-lg"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="text-gray-700 mb-6 italic text-lg leading-relaxed">
                        <?php echo htmlspecialchars($testimonial['comment']); ?>
                    </p>
                    <div class="flex items-center">
                        <img src="<?php echo htmlspecialchars($testimonial['avatar']); ?>" 
                             alt="<?php echo htmlspecialchars($testimonial['name']); ?>" 
                             class="w-14 h-14 rounded-full mr-4 ring-2 ring-primary-gold/30">
                        <div>
                            <h4 class="font-serif text-lg font-bold text-deep-navy"><?php echo htmlspecialchars($testimonial['name']); ?></h4>
                            <p class="text-gray-600"><?php echo htmlspecialchars($testimonial['title']); ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- About Section Enhanced -->
    <section id="about" class="py-24 bg-cream">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <div class="reveal">
                    <span class="inline-block px-6 py-2 bg-gold/10 backdrop-blur-md rounded-full text-sm font-semibold text-primary-gold mb-6">
                        THE MONBELA STORY
                    </span>
                    <h2 class="font-serif text-5xl lg:text-6xl font-bold text-deep-navy mb-6">
                        A Legacy of <span class="text-luxury-gradient">Excellence</span>
                    </h2>
                    <p class="text-lg text-gray-700 mb-6 leading-relaxed">
                        For over two decades, Monbela Hotel has been the epitome of luxury hospitality. 
                        Our dedication to perfection has made us the preferred choice for discerning travelers worldwide.
                    </p>
                    <p class="text-lg text-gray-700 mb-8 leading-relaxed">
                        Strategically located in the city's prime district, we offer seamless access to business centers 
                        and cultural landmarks while providing an oasis of tranquility.
                    </p>
                    
                    <div class="grid grid-cols-2 gap-8 p-8 bg-white rounded-2xl shadow-lg">
                        <div class="text-center">
                            <div class="text-4xl font-bold text-primary-gold mb-2"><?= count($allRooms) ?>+</div>
                            <div class="text-gray-600">Luxury Suites</div>
                        </div>
                        <div class="text-center">
                            <div class="text-4xl font-bold text-primary-gold mb-2">50K+</div>
                            <div class="text-gray-600">Happy Guests</div>
                        </div>
                        <div class="text-center">
                            <div class="text-4xl font-bold text-primary-gold mb-2">15+</div>
                            <div class="text-gray-600">Awards Won</div>
                        </div>
                        <div class="text-center">
                            <div class="text-4xl font-bold text-primary-gold mb-2">24/7</div>
                            <div class="text-gray-600">Concierge Service</div>
                        </div>
                    </div>
                </div>
                <div class="reveal">
                    <div class="relative">
                        <img src="https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=600&h=700&fit=crop" 
                             alt="Monbela Hotel Exterior" 
                             class="rounded-3xl shadow-2xl w-full h-auto">
                        <div class="absolute -top-6 -right-6 bg-primary-gold text-white p-6 rounded-2xl">
                            <div class="text-2xl font-bold mb-1">Est. 1999</div>
                            <div class="text-sm">Heritage of Luxury</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Luxury Footer -->
    <footer class="navy-gradient text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8 mb-12">
                <div>
                    <h3 class="font-serif text-3xl font-bold text-luxury-gradient mb-4">Monbela</h3>
                    <p class="text-gray-300 mb-4">Where every stay becomes an unforgettable experience.</p>
                    <div class="flex gap-4">
                        <a href="#" class="w-10 h-10 bg-white/10 rounded-full flex items-center justify-center hover:bg-primary-gold transition-all">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-white/10 rounded-full flex items-center justify-center hover:bg-primary-gold transition-all">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-white/10 rounded-full flex items-center justify-center hover:bg-primary-gold transition-all">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-white/10 rounded-full flex items-center justify-center hover:bg-primary-gold transition-all">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>
                
                
                <div>
                    <h4 class="font-semibold text-lg mb-4">Services</h4>
                    <ul class="space-y-2 text-gray-300">
                        <li><a href="#" class="hover:text-primary-gold transition-colors">Spa & Wellness</a></li>
                        <li><a href="#" class="hover:text-primary-gold transition-colors">Fine Dining</a></li>
                        <li><a href="#" class="hover:text-primary-gold transition-colors">Events & Meetings</a></li>
                        <li><a href="#" class="hover:text-primary-gold transition-colors">Concierge</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-semibold text-lg mb-4">Contact Info</h4>
                    <ul class="space-y-2 text-gray-300">
                        <li class="flex items-center gap-2">
                            <i class="fas fa-map-marker-alt text-primary-gold"></i>
                            123 Luxury Avenue, City
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fas fa-phone text-primary-gold"></i>
                            +1 (234) 567-8900
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fas fa-envelope text-primary-gold"></i>
                            info@monbelahotel.com
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-white/10 pt-8">
                <p class="text-center text-gray-300">&copy; <?php echo date('Y'); ?> Monbela Hotel. All rights reserved. | Luxury Redefined.</p>
            </div>
        </div>
    </footer>

    <script>
        // Initialize Vanta.js background
        if (typeof VANTA !== 'undefined') {
            VANTA.BIRDS({
                el: "#vanta-bg",
                mouseControls: true,
                touchControls: true,
                gyroControls: false,
                minHeight: 200.00,
                minWidth: 200.00,
                scale: 1.00,
                scaleMobile: 1.00,
                backgroundColor: 0x1a1f36,
                color1: 0xd4af37,
                color2: 0xfaf7f0,
                colorMode: "variance",
                birdSize: 1.50,
                wingSpan: 30.00,
                speedLimit: 4.00,
                separation: 25.00,
                alignment: 25.00,
                cohesion: 25.00,
                quantity: 4.00
            });
        }

        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            const mobileMenu = document.getElementById('mobile-menu');
            
            if (mobileMenuBtn && mobileMenu) {
                mobileMenuBtn.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                    const icon = mobileMenuBtn.querySelector('i');
                    if (mobileMenu.classList.contains('hidden')) {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                    } else {
                        icon.classList.remove('fa-bars');
                        icon.classList.add('fa-times');
                    }
                });
            }
            
            document.addEventListener('click', function(event) {
                if (mobileMenuBtn && mobileMenu && !mobileMenuBtn.contains(event.target) && !mobileMenu.contains(event.target)) {
                    mobileMenu.classList.add('hidden');
                    const icon = mobileMenuBtn.querySelector('i');
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            });
        });

        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                if (this.getAttribute('href') === '#') return;
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                const target = document.querySelector(targetId);
                if (target) {
                    const mobileMenu = document.getElementById('mobile-menu');
                    if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
                        mobileMenu.classList.add('hidden');
                        const icon = document.querySelector('#mobile-menu-btn i');
                        if (icon) {
                            icon.classList.remove('fa-times');
                            icon.classList.add('fa-bars');
                        }
                    }
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // Reveal animation
        const revealElements = document.querySelectorAll('.reveal');
        const revealOnScroll = () => {
            revealElements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const windowHeight = window.innerHeight;
                if (elementTop < windowHeight - 100) {
                    element.classList.add('active');
                }
            });
        };

        window.addEventListener('scroll', revealOnScroll);
        window.addEventListener('load', revealOnScroll);
        revealOnScroll();

        // Date handling
        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        
        const formatDate = (date) => date.toISOString().split('T')[0];
        
        const checkinInput = document.getElementById('checkin');
        const checkoutInput = document.getElementById('checkout');
        
        if (checkinInput) {
            checkinInput.min = formatDate(today);
            checkinInput.addEventListener('change', function() {
                const checkinDate = new Date(this.value);
                const nextDay = new Date(checkinDate);
                nextDay.setDate(nextDay.getDate() + 1);
                checkoutInput.min = formatDate(nextDay);
                const checkoutDate = new Date(checkoutInput.value);
                if (checkoutDate <= checkinDate) {
                    checkoutInput.value = formatDate(nextDay);
                }
            });
        }

        // Room search
        let searchTimeout;
        const roomSearchInput = document.getElementById('roomSearch');
        if (roomSearchInput) {
            roomSearchInput.addEventListener('input', function(e) {
                clearTimeout(searchTimeout);
                const searchTerm = e.target.value.toLowerCase().trim();
                searchTimeout = setTimeout(() => {
                    const roomCards = document.querySelectorAll('#roomsGrid .luxury-card');
                    roomCards.forEach(card => {
                        const title = card.querySelector('h3')?.textContent.toLowerCase() || '';
                        const description = card.querySelector('p.text-gray-600')?.textContent.toLowerCase() || '';
                        const typeElement = card.querySelector('p.text-sm.text-gray-500');
                        const type = typeElement?.textContent.toLowerCase() || '';
                        const amenities = Array.from(card.querySelectorAll('.bg-cream span'))
                            .map(span => span.textContent.toLowerCase()).join(' ');
                        const searchContent = title + ' ' + description + ' ' + type + ' ' + amenities;
                        if (searchTerm === '' || searchContent.includes(searchTerm)) {
                            card.style.display = 'block';
                            setTimeout(() => card.classList.add('reveal', 'active'), 10);
                        } else {
                            card.style.display = 'none';
                        }
                    });
                }, 300);
            });
        }

        // Accommodation filter
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => {
                    b.classList.remove('bg-primary-gold', 'text-white');
                    b.classList.add('border-gold/30', 'hover:bg-gold/10');
                });
                this.classList.remove('border-gold/30', 'hover:bg-gold/10');
                this.classList.add('bg-primary-gold', 'text-white');
                const type = this.dataset.type;
                const roomCards = document.querySelectorAll('#roomsGrid .luxury-card');
                roomCards.forEach(card => {
                    if (type === 'all' || card.dataset.accommodationId === type) {
                        card.style.display = 'block';
                        setTimeout(() => card.classList.add('reveal', 'active'), 10);
                    } else {
                        card.style.display = 'none';
                    }
                });
                if (roomSearchInput) roomSearchInput.value = '';
            });
        });

        // Booking functions
        function bookRoom(roomId) {
            const checkin = document.getElementById('checkin').value;
            const checkout = document.getElementById('checkout').value;
            const guests = document.getElementById('guests').value;
            <?php if (isset($_SESSION['id'])): ?>
                window.location.href = `booking.php?room_id=${roomId}&checkin=${checkin}&checkout=${checkout}&guests=${guests}`;
            <?php else: ?>
                window.location.href = `login.php?redirect=booking&room_id=${roomId}&checkin=${checkin}&checkout=${checkout}&guests=${guests}`;
            <?php endif; ?>
        }

        function viewRoomDetails(roomId) {
            <?php if (isset($_SESSION['id'])): ?>
                window.location.href = `room_details.php?id=${roomId}`;
            <?php else: ?>
                window.location.href = 'login.php';
            <?php endif; ?>
        }

        function clearDateFilters() {
            window.location.href = 'home.php';
        }

        function toggleFavorite(btn) {
            const icon = btn.querySelector('i');
            if (icon.classList.contains('far')) {
                icon.classList.remove('far');
                icon.classList.add('fas', 'text-red-500');
                btn.classList.add('shadow-lg');
                showToast('Added to favorites!', 'success');
            } else {
                icon.classList.remove('fas', 'text-red-500');
                icon.classList.add('far');
                btn.classList.remove('shadow-lg');
                showToast('Removed from favorites', 'info');
            }
        }

        function showToast(message, type = 'info') {
            const existingToasts = document.querySelectorAll('.toast-notification');
            existingToasts.forEach(toast => toast.remove());
            const toast = document.createElement('div');
            toast.className = `toast-notification fixed top-20 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transform transition-all duration-300 ${
                type === 'success' ? 'bg-green-500 text-white' : 
                type === 'error' ? 'bg-red-500 text-white' : 'bg-blue-500 text-white'
            }`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.classList.add('translate-x-0', 'opacity-100'), 10);
            setTimeout(() => {
                toast.classList.remove('translate-x-0', 'opacity-100');
                toast.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Parallax effect
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const parallaxElements = document.querySelectorAll('.luxury-float');
            parallaxElements.forEach(element => {
                const speed = 0.5;
                const yPos = -(scrolled * speed);
                element.style.transform = `translateY(${yPos}px)`;
            });
        });

        // Header effect
        window.addEventListener('scroll', () => {
            const header = document.querySelector('nav');
            if (window.scrollY > 50) {
                header.classList.add('shadow-xl');
                header.style.backgroundColor = 'rgba(255, 255, 255, 0.98)';
            } else {
                header.classList.remove('shadow-xl');
                header.style.backgroundColor = 'rgba(255, 255, 255, 0.95)';
            }
        });

        // Toast CSS
        const toastStyle = document.createElement('style');
        toastStyle.textContent = `
            .toast-notification {
                transform: translateX(100%);
                opacity: 0;
            }
            .toast-notification.translate-x-0 {
                transform: translateX(0);
                opacity: 1;
            }
        `;
        document.head.appendChild(toastStyle);
    </script>
</body>
</html>