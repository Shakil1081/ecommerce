<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LadiesBagsSeeder extends Seeder
{
    /**
     * Seed 100 demo ladies bags into the catalog with variants, stock, and pricing.
     */
    public function run(): void
    {
        // 1. Get or create primary warehouse
        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'DHK-CENTRAL'],
            [
                'name' => 'Dhaka Central Distribution Hub (Tejgaon)',
                'address' => 'Plot 42, Tejgaon Industrial Area, Dhaka-1208',
                'contact_number' => '+8801711000000',
                'is_primary' => true,
                'is_active' => true,
            ]
        );

        // 2. Get or create category
        $category = Category::firstOrCreate(
            ['slug' => 'ladies-bags'],
            [
                'name' => 'Ladies Bags & Handbags',
                'description' => 'Premium luxury handbags, tote bags, crossbody bags, clutches, and leather shoulder bags for women.',
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        // 3. Seed top handbag brands
        $brandsData = [
            ['name' => 'Charles & Keith', 'slug' => 'charles-and-keith', 'origin_country' => 'Singapore'],
            ['name' => 'Michael Kors', 'slug' => 'michael-kors', 'origin_country' => 'United States'],
            ['name' => 'Aldo', 'slug' => 'aldo', 'origin_country' => 'Canada'],
            ['name' => 'Coach', 'slug' => 'coach', 'origin_country' => 'United States'],
            ['name' => 'Guess', 'slug' => 'guess', 'origin_country' => 'United States'],
            ['name' => 'Zara', 'slug' => 'zara', 'origin_country' => 'Spain'],
            ['name' => 'Mango', 'slug' => 'mango', 'origin_country' => 'Spain'],
            ['name' => 'Tory Burch', 'slug' => 'tory-burch', 'origin_country' => 'United States'],
            ['name' => 'Kate Spade', 'slug' => 'kate-spade', 'origin_country' => 'United States'],
            ['name' => 'Fossil', 'slug' => 'fossil', 'origin_country' => 'United States'],
            ['name' => 'Gucci', 'slug' => 'gucci', 'origin_country' => 'Italy'],
            ['name' => 'Prada', 'slug' => 'prada', 'origin_country' => 'Italy'],
            ['name' => 'Lavie', 'slug' => 'lavie', 'origin_country' => 'India'],
            ['name' => 'Apex Nino Rossi', 'slug' => 'apex-nino-rossi', 'origin_country' => 'Bangladesh'],
            ['name' => 'Bata Red Label', 'slug' => 'bata-red-label', 'origin_country' => 'Switzerland'],
        ];

        $brands = [];
        foreach ($brandsData as $bData) {
            $brands[$bData['name']] = Brand::firstOrCreate(
                ['slug' => $bData['slug']],
                array_merge($bData, ['is_featured' => true, 'is_active' => true])
            );
        }

        $brandKeys = array_keys($brands);

        // Curated high quality ladies bag image URLs
        $bagImages = [
            'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1590874103328-eac38a683ce7?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1566150905458-1bf1fc113f0d?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1591561954557-26941169b49e?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1598532163257-ae3c6b2524b6?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1575032617751-6ddec2089882?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1544816155-12df9643f363?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1600857544200-b2f666a9a2ec?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1594223274512-ad4803739b7c?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1614179689702-355944cd0918?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?w=800&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1554342872-034a16541bee?w=800&auto=format&fit=crop&q=80',
        ];

        // 100 Distinct Ladies Bag Definitions
        $bagCatalog = [
            // 1 - 10
            ['name' => 'Jet Set Saffiano Leather Large Tote Bag', 'style' => 'Tote Bag', 'price' => 3850, 'compare' => 5200, 'cost' => 1950],
            ['name' => 'Gabine Curved Saddle Crossbody Bag with Gold Buckle', 'style' => 'Saddle Bag', 'price' => 2950, 'compare' => 4200, 'cost' => 1450],
            ['name' => 'Pillow Tabby 26 Nappa Leather Shoulder Bag', 'style' => 'Shoulder Bag', 'price' => 4500, 'compare' => 6200, 'cost' => 2300],
            ['name' => 'Legoiri Top-Handle Quilted Crossbody Bag', 'style' => 'Crossbody Bag', 'price' => 2650, 'compare' => 3800, 'cost' => 1350],
            ['name' => 'Minimalist Faux Leather City Work Tote', 'style' => 'Tote Bag', 'price' => 2150, 'compare' => 2950, 'cost' => 1050],
            ['name' => 'Cessily Quilted Convertible Flap Shoulder Bag', 'style' => 'Shoulder Bag', 'price' => 3450, 'compare' => 4800, 'cost' => 1750],
            ['name' => 'Fleming Soft Convertible Quilted Leather Bag', 'style' => 'Shoulder Bag', 'price' => 4850, 'compare' => 6500, 'cost' => 2450],
            ['name' => 'Knott Medium Pebbled Leather Satchel Bag', 'style' => 'Satchel Bag', 'price' => 3950, 'compare' => 5400, 'cost' => 2050],
            ['name' => 'Structured Baguette Retro Underarm Shoulder Bag', 'style' => 'Baguette Bag', 'price' => 1850, 'compare' => 2600, 'cost' => 920],
            ['name' => 'Rachel Zipper Leather Satchel with Detachable Strap', 'style' => 'Satchel Bag', 'price' => 3250, 'compare' => 4500, 'cost' => 1650],

            // 11 - 20
            ['name' => 'Marmont Matelasse Chevron Small Camera Bag', 'style' => 'Camera Bag', 'price' => 5200, 'compare' => 7500, 'cost' => 2600],
            ['name' => 'Re-Edition 2005 Re-Nylon Mini Shoulder Bag', 'style' => 'Shoulder Bag', 'price' => 4200, 'compare' => 5900, 'cost' => 2100],
            ['name' => 'Koa Square Push-Lock Flap Shoulder Handbag', 'style' => 'Flap Bag', 'price' => 2750, 'compare' => 3800, 'cost' => 1400],
            ['name' => 'Greenwald Chain-Strap Dual-Compartment Crossbody', 'style' => 'Crossbody Bag', 'price' => 2450, 'compare' => 3500, 'cost' => 1250],
            ['name' => 'Mercer Extra-Small Pebbled Leather Accordion Crossbody', 'style' => 'Crossbody Bag', 'price' => 3650, 'compare' => 4950, 'cost' => 1850],
            ['name' => 'Parker Leather Top-Handle Structured Flap Bag', 'style' => 'Top-Handle Bag', 'price' => 3850, 'compare' => 5300, 'cost' => 1950],
            ['name' => 'Downtown Metropolitan Multi-Pocket Office Handbag', 'style' => 'Office Bag', 'price' => 2850, 'compare' => 3900, 'cost' => 1450],
            ['name' => 'Carlyle Tweed Chain-Accent Evening Shoulder Bag', 'style' => 'Evening Bag', 'price' => 3150, 'compare' => 4400, 'cost' => 1600],
            ['name' => 'Crocodile-Embossed Golden Lock Petite Satchel', 'style' => 'Satchel Bag', 'price' => 2350, 'compare' => 3300, 'cost' => 1200],
            ['name' => 'Aura Slouchy Soft Grained Leather Hobo Bag', 'style' => 'Hobo Bag', 'price' => 2950, 'compare' => 4100, 'cost' => 1500],

            // 21 - 30
            ['name' => 'Evelyn Scalloped Edge Leather Handbag with Bow', 'style' => 'Handbag', 'price' => 2250, 'compare' => 3200, 'cost' => 1150],
            ['name' => 'Cassette Padded Woven Texture Crossbody Bag', 'style' => 'Crossbody Bag', 'price' => 3750, 'compare' => 5100, 'cost' => 1900],
            ['name' => 'Bella Drawstring Cylindrical Leather Bucket Bag', 'style' => 'Bucket Bag', 'price' => 2850, 'compare' => 3900, 'cost' => 1450],
            ['name' => 'Harper Trapezoid Structural Mini Handbag', 'style' => 'Mini Bag', 'price' => 2150, 'compare' => 2990, 'cost' => 1100],
            ['name' => 'Sutton Medium Saffiano Zipper Tote Bag', 'style' => 'Tote Bag', 'price' => 3450, 'compare' => 4700, 'cost' => 1750],
            ['name' => 'Willow Floral Print Convertible Shoulder Purse', 'style' => 'Shoulder Bag', 'price' => 2650, 'compare' => 3700, 'cost' => 1350],
            ['name' => 'Madison Ruched Handle Cloud Dumpling Clutch Bag', 'style' => 'Cloud Bag', 'price' => 1950, 'compare' => 2800, 'cost' => 980],
            ['name' => 'Voyager Gilded Studs Large Travel Tote', 'style' => 'Tote Bag', 'price' => 3950, 'compare' => 5500, 'cost' => 2000],
            ['name' => 'Amour Heart-Shaped Quilted Chain Shoulder Purse', 'style' => 'Party Purse', 'price' => 1750, 'compare' => 2500, 'cost' => 880],
            ['name' => 'Serena Envelope Clutch with Detachable Golden Chain', 'style' => 'Clutch Bag', 'price' => 1650, 'compare' => 2400, 'cost' => 820],

            // 31 - 40
            ['name' => 'Signature Monogram Canvas & Leather Tote Handbag', 'style' => 'Tote Bag', 'price' => 3350, 'compare' => 4600, 'cost' => 1700],
            ['name' => 'Celeste Crescent Moon Metallic Buckle Shoulder Bag', 'style' => 'Shoulder Bag', 'price' => 2450, 'compare' => 3500, 'cost' => 1250],
            ['name' => 'Dahlia Pearl-Embellished Velvet Evening Minaudiere', 'style' => 'Evening Bag', 'price' => 2850, 'compare' => 4100, 'cost' => 1400],
            ['name' => 'Adeline Vintage Doctor Top-Frame Leather Satchel', 'style' => 'Satchel Bag', 'price' => 3550, 'compare' => 4900, 'cost' => 1800],
            ['name' => 'Nova Chic Compact Anti-Theft Fashion Backpack', 'style' => 'Backpack', 'price' => 2750, 'compare' => 3900, 'cost' => 1400],
            ['name' => 'Camille Tri-Tone Colorblock Executive Handbag', 'style' => 'Office Bag', 'price' => 3200, 'compare' => 4400, 'cost' => 1620],
            ['name' => 'Roxanne Double-Zip Accordion Crossbody Wallet Bag', 'style' => 'Crossbody Bag', 'price' => 1950, 'compare' => 2800, 'cost' => 990],
            ['name' => 'Genevieve Embossed Ostrich Pattern Luxury Satchel', 'style' => 'Satchel Bag', 'price' => 3800, 'compare' => 5200, 'cost' => 1950],
            ['name' => 'Luxe Lattice Laser-Cut Summer Beach & City Tote', 'style' => 'Tote Bag', 'price' => 2250, 'compare' => 3100, 'cost' => 1120],
            ['name' => 'Vivienne Turn-Lock Flap French Chic Handbag', 'style' => 'Flap Bag', 'price' => 2950, 'compare' => 4200, 'cost' => 1500],

            // 41 - 50
            ['name' => 'Giselle Patent Glossy Crocodile Shoulder Bag', 'style' => 'Shoulder Bag', 'price' => 2550, 'compare' => 3600, 'cost' => 1300],
            ['name' => 'Penelope Two-Tone Woven Straw & Faux Leather Tote', 'style' => 'Tote Bag', 'price' => 2100, 'compare' => 2950, 'cost' => 1050],
            ['name' => 'Bianca Slouchy Dumpling Crossbody Bag with Scrunchie Strap', 'style' => 'Crossbody Bag', 'price' => 1850, 'compare' => 2600, 'cost' => 920],
            ['name' => 'Scarlett Classic Padlock Grain Leather Top-Handle Handbag', 'style' => 'Top-Handle Bag', 'price' => 3650, 'compare' => 4990, 'cost' => 1850],
            ['name' => 'Aria Geometric Origami Folded Leather Shoulder Purse', 'style' => 'Shoulder Bag', 'price' => 2850, 'compare' => 3950, 'cost' => 1450],
            ['name' => 'Chelsea Quilted Double-Compartment Camera Crossbody', 'style' => 'Camera Bag', 'price' => 2350, 'compare' => 3300, 'cost' => 1180],
            ['name' => 'Ophelia Velvet Floral Embroidery Gala Clutch', 'style' => 'Clutch Bag', 'price' => 2450, 'compare' => 3500, 'cost' => 1220],
            ['name' => 'Isla Canvas Shopper Bag with Leather Straps & Zipper', 'style' => 'Tote Bag', 'price' => 1550, 'compare' => 2200, 'cost' => 780],
            ['name' => 'Valentina Diamond-Quilted Flap Bag with Chunky Chain', 'style' => 'Flap Bag', 'price' => 3450, 'compare' => 4800, 'cost' => 1750],
            ['name' => 'Sienna Western Fringe Suede Crossbody Bag', 'style' => 'Crossbody Bag', 'price' => 2650, 'compare' => 3700, 'cost' => 1320],

            // 51 - 60
            ['name' => 'Maya Minimalist Dual-Strap Everyday Work Handbag', 'style' => 'Office Bag', 'price' => 2750, 'compare' => 3800, 'cost' => 1390],
            ['name' => 'Zoe Micro Vanity Case Crossbody with Card Mirror', 'style' => 'Mini Bag', 'price' => 1950, 'compare' => 2750, 'cost' => 980],
            ['name' => 'Freya Braided Handle Hobo Leather Bag', 'style' => 'Hobo Bag', 'price' => 3150, 'compare' => 4400, 'cost' => 1600],
            ['name' => 'Chloe C-Logo Magnetic Flap Saddle Crossbody', 'style' => 'Saddle Bag', 'price' => 2850, 'compare' => 3990, 'cost' => 1450],
            ['name' => 'Kensington Polished Pebble Leather Dome Satchel', 'style' => 'Satchel Bag', 'price' => 3350, 'compare' => 4700, 'cost' => 1700],
            ['name' => 'Talia Pleated Metallic Golden Party Wristlet', 'style' => 'Party Purse', 'price' => 1650, 'compare' => 2350, 'cost' => 820],
            ['name' => 'Lillian Vintage Kiss-Lock Coin & Card Purse Handbag', 'style' => 'Vintage Bag', 'price' => 1850, 'compare' => 2650, 'cost' => 920],
            ['name' => 'Paloma Waterproof Nylon Multi-Pocket Diaper & Daily Tote', 'style' => 'Tote Bag', 'price' => 2250, 'compare' => 3200, 'cost' => 1120],
            ['name' => 'Estelle Sleek Rectangular Envelope Clutch with Tassel', 'style' => 'Clutch Bag', 'price' => 1750, 'compare' => 2500, 'cost' => 880],
            ['name' => 'Arabella Regal Snake Print Embossed Top-Handle Bag', 'style' => 'Top-Handle Bag', 'price' => 3550, 'compare' => 4950, 'cost' => 1800],

            // 61 - 70
            ['name' => 'Margot Soft Faux Shearling Winter Fuzzy Shoulder Bag', 'style' => 'Shoulder Bag', 'price' => 2150, 'compare' => 3000, 'cost' => 1080],
            ['name' => 'Astrid Elegant Bow-Knot Embellished Lady Handbag', 'style' => 'Handbag', 'price' => 2650, 'compare' => 3700, 'cost' => 1350],
            ['name' => 'Daphne Minimalist Half-Flap Crossbody Phone Bag', 'style' => 'Phone Bag', 'price' => 1350, 'compare' => 1950, 'cost' => 680],
            ['name' => 'Clara Classic Two-Way Expandable Leather Business Tote', 'style' => 'Tote Bag', 'price' => 3650, 'compare' => 5100, 'cost' => 1850],
            ['name' => 'Sora Korean Style Canvas Messenger Crossbody Bag', 'style' => 'Messenger Bag', 'price' => 1450, 'compare' => 2100, 'cost' => 720],
            ['name' => 'Nadia Quilted Lambskin Look Chain Shoulder Bag', 'style' => 'Shoulder Bag', 'price' => 3250, 'compare' => 4500, 'cost' => 1650],
            ['name' => 'Mila Asymmetrical Flap Push-Lock Evening Bag', 'style' => 'Evening Bag', 'price' => 2450, 'compare' => 3500, 'cost' => 1240],
            ['name' => 'Helena Textured Grain Leather Doctor Satchel Bag', 'style' => 'Satchel Bag', 'price' => 3850, 'compare' => 5300, 'cost' => 1950],
            ['name' => 'Rhea Multi-Zip Soft Leather Compact City Backpack', 'style' => 'Backpack', 'price' => 2950, 'compare' => 4150, 'cost' => 1490],
            ['name' => 'Selene Crystal Rhinestone Sparkle Party Clutch Purse', 'style' => 'Party Purse', 'price' => 2750, 'compare' => 3950, 'cost' => 1380],

            // 71 - 80
            ['name' => 'Imogen Retro Monogram Barrel Cylindrical Duffle Bag', 'style' => 'Duffle Bag', 'price' => 2550, 'compare' => 3600, 'cost' => 1290],
            ['name' => 'Tessa Perforated Breathable Casual Shopper Tote', 'style' => 'Tote Bag', 'price' => 1950, 'compare' => 2800, 'cost' => 990],
            ['name' => 'Gemma Chunky Acrylic Chain Crossbody Bag', 'style' => 'Crossbody Bag', 'price' => 2350, 'compare' => 3350, 'cost' => 1190],
            ['name' => 'Vera Structured Boxy Trunk Handbag with Key Clasp', 'style' => 'Box Bag', 'price' => 3450, 'compare' => 4850, 'cost' => 1750],
            ['name' => 'Bree Corduroy Casual Daily Shoulder Bag with Pockets', 'style' => 'Shoulder Bag', 'price' => 1250, 'compare' => 1800, 'cost' => 620],
            ['name' => 'Aurora Iridescent Holographic Mini Party Bag', 'style' => 'Mini Bag', 'price' => 1650, 'compare' => 2400, 'cost' => 830],
            ['name' => 'Faye O-Ring Leather Saddle Bag with Suede Flap', 'style' => 'Saddle Bag', 'price' => 3150, 'compare' => 4400, 'cost' => 1590],
            ['name' => 'Colette Parisian Polished Leather Flap Bag', 'style' => 'Flap Bag', 'price' => 3350, 'compare' => 4700, 'cost' => 1690],
            ['name' => 'Layla Contrast Stitching Everyday Hobo Bag', 'style' => 'Hobo Bag', 'price' => 2750, 'compare' => 3900, 'cost' => 1390],
            ['name' => 'Amalia Woven Bamboo Handle Summer Straw Bag', 'style' => 'Handbag', 'price' => 2450, 'compare' => 3500, 'cost' => 1240],

            // 81 - 90
            ['name' => 'Sabrina Saffiano Zip-Around Laptop Tote Bag (14 Inch)', 'style' => 'Laptop Tote', 'price' => 3950, 'compare' => 5500, 'cost' => 2000],
            ['name' => 'Yvonne Golden Ball Accent Gathered Leather Clutch', 'style' => 'Clutch Bag', 'price' => 2150, 'compare' => 3100, 'cost' => 1090],
            ['name' => 'Rowan Studded Edges Rocker Chic Crossbody Bag', 'style' => 'Crossbody Bag', 'price' => 2850, 'compare' => 4000, 'cost' => 1440],
            ['name' => 'Kiara Crocodile Texture Top-Handle Kelly Style Bag', 'style' => 'Kelly Bag', 'price' => 3750, 'compare' => 5200, 'cost' => 1900],
            ['name' => 'Saffron Bohemian Embroidered Gypsy Shoulder Bag', 'style' => 'Shoulder Bag', 'price' => 1850, 'compare' => 2650, 'cost' => 930],
            ['name' => 'Lourdes Regal Brocade Fabric Festive Wedding Purse', 'style' => 'Wedding Purse', 'price' => 2950, 'compare' => 4200, 'cost' => 1490],
            ['name' => 'Pippa Clear Transparent PVC & Inner Pouch Stadium Bag', 'style' => 'Clear Bag', 'price' => 1350, 'compare' => 1900, 'cost' => 680],
            ['name' => 'Eden Pebbled Leather Sling Chest Crossbody Pack', 'style' => 'Sling Bag', 'price' => 2250, 'compare' => 3200, 'cost' => 1140],
            ['name' => 'Matilda Wide Strap Ergonomic Shoulder Handbag', 'style' => 'Shoulder Bag', 'price' => 2650, 'compare' => 3750, 'cost' => 1340],
            ['name' => 'Celina Vintage Accordion Leather Doctor Handbag', 'style' => 'Doctor Bag', 'price' => 3650, 'compare' => 5100, 'cost' => 1850],

            // 91 - 100
            ['name' => 'Kallie Two-Tone Striped Cotton Canvas Beach Shopper', 'style' => 'Tote Bag', 'price' => 1550, 'compare' => 2200, 'cost' => 780],
            ['name' => 'Marcella Genuine Suede Leather Fringe Hobo Bag', 'style' => 'Hobo Bag', 'price' => 3850, 'compare' => 5400, 'cost' => 1950],
            ['name' => 'Jolene Compact Cardholder Crossbody Pouch Bag', 'style' => 'Pouch Bag', 'price' => 1150, 'compare' => 1650, 'cost' => 580],
            ['name' => 'Renee Quilted Metallic Rose Gold Evening Bag', 'style' => 'Evening Bag', 'price' => 2550, 'compare' => 3650, 'cost' => 1290],
            ['name' => 'Althea Structured Trapezoid Business Laptop Handbag', 'style' => 'Business Bag', 'price' => 4150, 'compare' => 5800, 'cost' => 2100],
            ['name' => 'Bridget Chunky Chain Accent Slouchy Crossbody', 'style' => 'Crossbody Bag', 'price' => 2450, 'compare' => 3500, 'cost' => 1240],
            ['name' => 'Lorelei Ruched Cloud Shoulder Purse with Pearl Chain', 'style' => 'Cloud Bag', 'price' => 2250, 'compare' => 3200, 'cost' => 1140],
            ['name' => 'Thalia Glossy Patent Mini Top-Handle Party Bag', 'style' => 'Mini Bag', 'price' => 1850, 'compare' => 2650, 'cost' => 930],
            ['name' => 'Simone Classic Everyday Dual-Strap Shoulder Tote', 'style' => 'Tote Bag', 'price' => 2950, 'compare' => 4200, 'cost' => 1490],
            ['name' => 'Valerie Grand Empress Crocodile-Pattern Luxury Satchel', 'style' => 'Luxury Satchel', 'price' => 4650, 'compare' => 6500, 'cost' => 2350],
        ];

        // Color palettes for variants
        $colorPalettes = [
            ['Midnight Black', 'Maroon Burgundy', 'Nude Beige'],
            ['Classic Black', 'Emerald Green', 'Tan Brown'],
            ['Baby Pink', 'Ivory White', 'Powder Blue'],
            ['Mocha Brown', 'Rose Gold', 'Obsidian Black'],
            ['Burgundy Red', 'Beige Taupe', 'Navy Blue'],
            ['Caramel Tan', 'Soft Lilac', 'Onyx Black'],
        ];

        $materials = [
            'Premium Saffiano Leather',
            'Ultra-Soft Nappa Leather',
            'Water-Resistant Vegan PU Leather',
            'Crocodile-Embossed Vegan Leather',
            'Durable Oxford Canvas & Leather',
            'Pebbled Full-Grain Leather',
            'Quilted Velvet with Satin Lining',
        ];

        $origins = ['Vietnam', 'China', 'Singapore', 'Spain', 'Italy', 'Bangladesh', 'United States'];

        $productCount = 0;

        foreach ($bagCatalog as $index => $item) {
            $brandIndex = $index % count($brandKeys);
            $selectedBrand = $brands[$brandKeys[$brandIndex]];
            $selectedImage = $bagImages[$index % count($bagImages)];
            $gallerySecondaryImage = $bagImages[($index + 1) % count($bagImages)];

            $material = $materials[$index % count($materials)];
            $origin = $origins[$index % count($origins)];
            $colors = $colorPalettes[$index % count($colorPalettes)];

            $slug = Str::slug($selectedBrand->name.' '.$item['name']);

            // Ensure unique slug
            $existingCount = Product::where('slug', $slug)->count();
            if ($existingCount > 0) {
                $slug = "{$slug}-".($index + 1);
            }

            $isFeatured = ($index < 16) || ($index % 5 === 0);

            $specifications = [
                'Style' => $item['style'],
                'Material' => $material,
                'Closure' => ($index % 3 === 0) ? 'Smooth Metal Zipper' : (($index % 3 === 1) ? 'Magnetic Snap Lock' : 'Turn-Lock Clasp'),
                'Strap Type' => 'Adjustable & Detachable Leather Strap with Golden Chain Accents',
                'Compartments' => '1 Main Zipper Compartment, 2 Slip Pockets, 1 Secret Zipper Pocket',
                'Hardware' => 'Polished Rust-Resistant Gold-Tone Alloy',
                'Dimensions' => '28 cm (L) × 20 cm (H) × 10 cm (W)',
                'image_url' => $selectedImage,
                'gallery_urls' => [$selectedImage, $gallerySecondaryImage],
            ];

            $bengaliDesc = "প্রিমিয়াম কোয়ালিটির {$item['style']}। এতে রয়েছে চমৎকার ফিনিশিং, আরামদায়ক শোল্ডার স্ট্র্যাপ এবং প্রয়োজনীয় কসমেটিকস, ফোন ও ওয়ালেট নিরাপদে রাখার পর্যাপ্ত জায়গা। প্রতিদিনের অফিস, কলেজ কিংবা পার্টিতে ব্যবহারের জন্য অত্যন্ত আকর্ষণীয় ও টেকসই।";

            $englishDesc = "Elevate your style with the {$item['name']} by {$selectedBrand->name}. Crafted from {$material} with polished gold-tone hardware, spacious interior compartments, and an adjustable strap for comfortable carrying. Perfect for work, travel, and festive occasions.";

            $product = Product::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => "{$selectedBrand->name} {$item['name']}",
                    'category_id' => $category->id,
                    'brand_id' => $selectedBrand->id,
                    'hs_code' => '4202.21.00',
                    'origin_country' => $origin,
                    'warranty_policy' => '6 Months Repair & Stitching Warranty (With NBR Mushak 6.3)',
                    'description' => "{$englishDesc}\n\n{$bengaliDesc}",
                    'specifications' => $specifications,
                    'is_featured' => $isFeatured,
                    'is_published' => true,
                    'seo_title' => "{$selectedBrand->name} {$item['name']} — Best Price in Bangladesh",
                    'seo_description' => "Buy 100% authentic {$selectedBrand->name} {$item['name']} in Bangladesh with Cash on Delivery & fast delivery nationwide.",
                ]
            );

            // Create Variants with different colors
            $variantSellingPrice = (float) $item['price'];
            $variantComparePrice = (float) $item['compare'];
            $variantPurchaseCost = (float) $item['cost'];
            $variantWeight = 0.550 + (($index % 5) * 0.05);

            foreach ($colors as $colorIdx => $colorName) {
                $colorSlug = Str::slug($colorName);
                $sku = strtoupper("BAG-{$selectedBrand->slug}-".Str::slug($item['style'])."-{$colorSlug}-".($index + 1));
                $barcode = '890123'.str_pad((string) (($index * 3) + $colorIdx + 1), 7, '0', STR_PAD_LEFT);

                $variant = ProductVariant::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'sku' => $sku,
                    ],
                    [
                        'barcode_ean' => $barcode,
                        'attributes' => [
                            'Color' => $colorName,
                            'Material' => $material,
                        ],
                        'purchase_cost_bdt' => $variantPurchaseCost,
                        'selling_price_bdt' => $variantSellingPrice,
                        'compare_at_price_bdt' => $variantComparePrice,
                        'weight_kg' => $variantWeight,
                        'low_stock_threshold' => 5,
                        'is_active' => true,
                    ]
                );

                // Assign Initial Physical Warehouse Stock (30 to 80 units)
                $stockQty = 35 + (($index + $colorIdx) % 45);

                InventoryStock::updateOrCreate(
                    [
                        'warehouse_id' => $warehouse->id,
                        'product_variant_id' => $variant->id,
                    ],
                    [
                        'physical_quantity' => $stockQty,
                        'reserved_quantity' => 0,
                        'bin_location' => 'RACK-BAG-'.str_pad((string) (($index % 20) + 1), 2, '0', STR_PAD_LEFT).'-'.chr(65 + $colorIdx),
                    ]
                );
            }

            $productCount++;
        }

        $this->command?->info("Successfully seeded {$productCount} demo ladies bags with variants, warehouse stock, and pricing!");
    }
}
