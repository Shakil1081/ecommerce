<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Common\Models\BangladeshThana;
use Illuminate\Database\Seeder;

class BangladeshGeoLocationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 8 Divisions
        $divisions = [
            'Dhaka' => ['bn' => 'ঢাকা', 'districts' => [
                ['en' => 'Dhaka', 'bn' => 'ঢাকা', 'zone' => BangladeshDistrict::ZONE_INSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Dhanmondi', 'bn' => 'ধানমন্ডি', 'post' => '1209'],
                    ['en' => 'Gulshan', 'bn' => 'গুলশান', 'post' => '1212'],
                    ['en' => 'Banani', 'bn' => 'বনানী', 'post' => '1213'],
                    ['en' => 'Uttara', 'bn' => 'উত্তরা', 'post' => '1230'],
                    ['en' => 'Mirpur', 'bn' => 'মিরপুর', 'post' => '1216'],
                    ['en' => 'Mohammadpur', 'bn' => 'মোহাম্মদপুর', 'post' => '1207'],
                    ['en' => 'Motijheel', 'bn' => 'মতিঝিল', 'post' => '1000'],
                    ['en' => 'Badda', 'bn' => 'বাড্ডা', 'post' => '1212'],
                    ['en' => 'Khilgaon', 'bn' => 'খিলগাঁও', 'post' => '1219'],
                    ['en' => 'Paltan', 'bn' => 'পল্টন', 'post' => '1000'],
                    ['en' => 'Ramna', 'bn' => 'রমনা', 'post' => '1000'],
                    ['en' => 'Shahbagh', 'bn' => 'শাহবাগ', 'post' => '1000'],
                    ['en' => 'Tejgaon', 'bn' => 'তেজগাঁও', 'post' => '1215'],
                    ['en' => 'Savar', 'bn' => 'সাভার', 'post' => '1340'],
                    ['en' => 'Keraniganj', 'bn' => 'কেরানীগঞ্জ', 'post' => '1310'],
                    ['en' => 'Dhamrai', 'bn' => 'ধামরাই', 'post' => '1350'],
                    ['en' => 'Dohar', 'bn' => 'দোহার', 'post' => '1330'],
                    ['en' => 'Nawabganj', 'bn' => 'নবাবগঞ্জ', 'post' => '1320'],
                ]],
                ['en' => 'Gazipur', 'bn' => 'গাজীপুর', 'zone' => BangladeshDistrict::ZONE_DHAKA_SUBURBS, 'thanas' => [
                    ['en' => 'Joydebpur', 'bn' => 'জয়দেবপুর', 'post' => '1700'],
                    ['en' => 'Tongi', 'bn' => 'টঙ্গী', 'post' => '1710'],
                    ['en' => 'Kaliakair', 'bn' => 'কালিয়াকৈর', 'post' => '1750'],
                    ['en' => 'Kapasia', 'bn' => 'কাপাসিয়া', 'post' => '1730'],
                    ['en' => 'Sreepur', 'bn' => 'শ্রীপুর', 'post' => '1740'],
                    ['en' => 'Kaliganj', 'bn' => 'কালীগঞ্জ', 'post' => '1720'],
                ]],
                ['en' => 'Narayanganj', 'bn' => 'নারায়ণগঞ্জ', 'zone' => BangladeshDistrict::ZONE_DHAKA_SUBURBS, 'thanas' => [
                    ['en' => 'Narayanganj Sadar', 'bn' => 'নারায়ণগঞ্জ সদর', 'post' => '1400'],
                    ['en' => 'Fatullah', 'bn' => 'ফতুল্লা', 'post' => '1420'],
                    ['en' => 'Siddhirganj', 'bn' => 'সিদ্ধিরগঞ্জ', 'post' => '1430'],
                    ['en' => 'Bandar', 'bn' => 'বন্দর', 'post' => '1410'],
                    ['en' => 'Rupganj', 'bn' => 'রূপগঞ্জ', 'post' => '1460'],
                    ['en' => 'Sonargaon', 'bn' => 'সোনারগাঁও', 'post' => '1440'],
                    ['en' => 'Araihazar', 'bn' => 'আড়াইহাজার', 'post' => '1450'],
                ]],
                ['en' => 'Munshiganj', 'bn' => 'মুন্সীগঞ্জ', 'zone' => BangladeshDistrict::ZONE_DHAKA_SUBURBS, 'thanas' => [
                    ['en' => 'Munshiganj Sadar', 'bn' => 'মুন্সীগঞ্জ সদর', 'post' => '1500'],
                    ['en' => 'Sreenagar', 'bn' => 'শ্রীনগর', 'post' => '1550'],
                    ['en' => 'Sirajdikhan', 'bn' => 'সিরাজদিখান', 'post' => '1540'],
                    ['en' => 'Louhajang', 'bn' => 'লৌহজং', 'post' => '1530'],
                    ['en' => 'Tongibari', 'bn' => 'টঙ্গিবাড়ী', 'post' => '1520'],
                    ['en' => 'Gazaria', 'bn' => 'গজারিয়া', 'post' => '1510'],
                ]],
                ['en' => 'Manikganj', 'bn' => 'মানিকগঞ্জ', 'zone' => BangladeshDistrict::ZONE_DHAKA_SUBURBS, 'thanas' => [
                    ['en' => 'Manikganj Sadar', 'bn' => 'মানিকগঞ্জ সদর', 'post' => '1800'],
                    ['en' => 'Singair', 'bn' => 'সিংগাইর', 'post' => '1820'],
                    ['en' => 'Saturia', 'bn' => 'সাটুরিয়া', 'post' => '1810'],
                    ['en' => 'Ghior', 'bn' => 'ঘিওর', 'post' => '1840'],
                    ['en' => 'Shibalaya', 'bn' => 'শিবালয়', 'post' => '1850'],
                ]],
                ['en' => 'Narsingdi', 'bn' => 'নরসিংদী', 'zone' => BangladeshDistrict::ZONE_DHAKA_SUBURBS, 'thanas' => [
                    ['en' => 'Narsingdi Sadar', 'bn' => 'নরসিংদী সদর', 'post' => '1600'],
                    ['en' => 'Palash', 'bn' => 'পলাশ', 'post' => '1610'],
                    ['en' => 'Shibpur', 'bn' => 'শিবপুর', 'post' => '1620'],
                    ['en' => 'Raipura', 'bn' => 'রায়পুরা', 'post' => '1630'],
                    ['en' => 'Monohardi', 'bn' => 'মনোহরদী', 'post' => '1650'],
                    ['en' => 'Belabo', 'bn' => 'বেলাবো', 'post' => '1640'],
                ]],
                ['en' => 'Faridpur', 'bn' => 'ফরিদপুর', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Faridpur Sadar', 'bn' => 'ফরিদপুর সদর', 'post' => '7800'],
                    ['en' => 'Bhanga', 'bn' => 'ভাঙ্গা', 'post' => '7830'],
                ]],
                ['en' => 'Gazipur Sadar', 'bn' => 'টঙ্গী উপশহর', 'zone' => BangladeshDistrict::ZONE_DHAKA_SUBURBS, 'thanas' => []],
                ['en' => 'Gopalganj', 'bn' => 'গোপালগঞ্জ', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Gopalganj Sadar', 'bn' => 'গোপালগঞ্জ সদর', 'post' => '8100'],
                ]],
                ['en' => 'Kishoreganj', 'bn' => 'কিশোরগঞ্জ', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Kishoreganj Sadar', 'bn' => 'কিশোরগঞ্জ সদর', 'post' => '2300'],
                    ['en' => 'Bhairab', 'bn' => 'ভৈরব', 'post' => '2350'],
                ]],
                ['en' => 'Madaripur', 'bn' => 'মাদারীপুর', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Madaripur Sadar', 'bn' => 'মাদারীপুর সদর', 'post' => '7900'],
                ]],
                ['en' => 'Rajbari', 'bn' => 'রাজবাড়ী', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Rajbari Sadar', 'bn' => 'রাজবাড়ী সদর', 'post' => '7700'],
                ]],
                ['en' => 'Shariatpur', 'bn' => 'শরীয়তপুর', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Shariatpur Sadar', 'bn' => 'শরীয়তপুর সদর', 'post' => '8000'],
                ]],
                ['en' => 'Tangail', 'bn' => 'টাঙ্গাইল', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Tangail Sadar', 'bn' => 'টাঙ্গাইল সদর', 'post' => '1900'],
                    ['en' => 'Mirzapur', 'bn' => 'মির্জাপুর', 'post' => '1940'],
                ]],
            ]],
            'Chattogram' => ['bn' => 'চট্টগ্রাম', 'districts' => [
                ['en' => 'Chattogram', 'bn' => 'চট্টগ্রাম', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Kotwali (Chattogram)', 'bn' => 'কোতোয়ালী', 'post' => '4000'],
                    ['en' => 'Panchlaish', 'bn' => 'পাঁচলাইশ', 'post' => '4203'],
                    ['en' => 'Pahartali', 'bn' => 'পাহাড়তলী', 'post' => '4202'],
                    ['en' => 'Halishahar', 'bn' => 'হালিশহর', 'post' => '4216'],
                    ['en' => 'Double Mooring', 'bn' => 'ডবল মুরিং', 'post' => '4100'],
                    ['en' => 'Patenga', 'bn' => 'পতেঙ্গা', 'post' => '4204'],
                    ['en' => 'Agrabad', 'bn' => 'আগ্রাবাদ', 'post' => '4100'],
                    ['en' => 'Hathazari', 'bn' => 'হাটহাজারী', 'post' => '4330'],
                    ['en' => 'Sitakunda', 'bn' => 'সীতাকুণ্ড', 'post' => '4310'],
                    ['en' => 'Patiya', 'bn' => 'পটিয়া', 'post' => '4370'],
                ]],
                ['en' => "Cox's Bazar", 'bn' => 'কক্সবাজার', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => "Cox's Bazar Sadar", 'bn' => 'কক্সবাজার সদর', 'post' => '4700'],
                    ['en' => 'Teknaf', 'bn' => 'টেকনাফ', 'post' => '4760'],
                ]],
                ['en' => 'Cumilla', 'bn' => 'কুমিল্লা', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Cumilla Adarsha Sadar', 'bn' => 'কুমিল্লা আদর্শ সদর', 'post' => '3500'],
                    ['en' => 'Daudkandi', 'bn' => 'দাউদকান্দি', 'post' => '3516'],
                ]],
                ['en' => 'Feni', 'bn' => 'ফেনী', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Feni Sadar', 'bn' => 'ফেনী সদর', 'post' => '3900'],
                ]],
                ['en' => 'Brahmanbaria', 'bn' => 'ব্রাহ্মণবাড়িয়া', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Brahmanbaria Sadar', 'bn' => 'ব্রাহ্মণবাড়িয়া সদর', 'post' => '3400'],
                ]],
                ['en' => 'Chandpur', 'bn' => 'চাঁদপুর', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Chandpur Sadar', 'bn' => 'চাঁদপুর সদর', 'post' => '3600'],
                ]],
                ['en' => 'Lakshmipur', 'bn' => 'লক্ষ্মীপুর', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Lakshmipur Sadar', 'bn' => 'লক্ষ্মীপুর সদর', 'post' => '3700'],
                ]],
                ['en' => 'Noakhali', 'bn' => 'নোয়াখালী', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Noakhali Sadar', 'bn' => 'নোয়াখালী সদর', 'post' => '3800'],
                    ['en' => 'Begumganj', 'bn' => 'বেগমগঞ্জ', 'post' => '3820'],
                ]],
                ['en' => 'Bandarban', 'bn' => 'বান্দরবান', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Bandarban Sadar', 'bn' => 'বান্দরবান সদর', 'post' => '4600'],
                ]],
                ['en' => 'Khagrachhari', 'bn' => 'খাগড়াছড়ি', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Khagrachhari Sadar', 'bn' => 'খাগড়াছড়ি সদর', 'post' => '4400'],
                ]],
                ['en' => 'Rangamati', 'bn' => 'রাঙ্গামাটি', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Rangamati Sadar', 'bn' => 'রাঙ্গামাটি সদর', 'post' => '4500'],
                ]],
            ]],
            'Rajshahi' => ['bn' => 'রাজশাহী', 'districts' => [
                ['en' => 'Rajshahi', 'bn' => 'রাজশাহী', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Boalia', 'bn' => 'বোয়ালিয়া', 'post' => '6000'],
                    ['en' => 'Motihar', 'bn' => 'মতিহার', 'post' => '6205'],
                    ['en' => 'Rajpara', 'bn' => 'রাজপাড়া', 'post' => '6000'],
                ]],
                ['en' => 'Bogura', 'bn' => 'বগুড়া', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Bogura Sadar', 'bn' => 'বগুড়া সদর', 'post' => '5800'],
                    ['en' => 'Sherpur (Bogura)', 'bn' => 'শেরপুর', 'post' => '5840'],
                ]],
                ['en' => 'Joypurhat', 'bn' => 'জয়পুরহাট', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Joypurhat Sadar', 'bn' => 'জয়পুরহাট সদর', 'post' => '5900'],
                ]],
                ['en' => 'Naogaon', 'bn' => 'নওগাঁ', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Naogaon Sadar', 'bn' => 'নওগাঁ সদর', 'post' => '6500'],
                ]],
                ['en' => 'Natore', 'bn' => 'নাটোর', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Natore Sadar', 'bn' => 'নাটোর সদর', 'post' => '6400'],
                ]],
                ['en' => 'Chapainawabganj', 'bn' => 'চাঁপাইনবাবগঞ্জ', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Chapainawabganj Sadar', 'bn' => 'চাঁপাইনবাবগঞ্জ সদর', 'post' => '6300'],
                ]],
                ['en' => 'Pabna', 'bn' => 'পাবনা', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Pabna Sadar', 'bn' => 'পাবনা সদর', 'post' => '6600'],
                    ['en' => 'Ishwardi', 'bn' => 'ঈশ্বরদী', 'post' => '6620'],
                ]],
                ['en' => 'Sirajganj', 'bn' => 'সিরাজগঞ্জ', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Sirajganj Sadar', 'bn' => 'সিরাজগঞ্জ সদর', 'post' => '6700'],
                ]],
            ]],
            'Khulna' => ['bn' => 'খুলনা', 'districts' => [
                ['en' => 'Khulna', 'bn' => 'খুলনা', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Khulna Sadar', 'bn' => 'খুলনা সদর', 'post' => '9100'],
                    ['en' => 'Sonadanga', 'bn' => 'সোনাডাঙ্গা', 'post' => '9000'],
                    ['en' => 'Khalishpur', 'bn' => 'খালিশপুর', 'post' => '9000'],
                ]],
                ['en' => 'Bagerhat', 'bn' => 'বাগেরহাট', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Bagerhat Sadar', 'bn' => 'বাগেরহাট সদর', 'post' => '9300'],
                ]],
                ['en' => 'Chuadanga', 'bn' => 'চুয়াডাঙ্গা', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Chuadanga Sadar', 'bn' => 'চুয়াডাঙ্গা সদর', 'post' => '7200'],
                ]],
                ['en' => 'Jashore', 'bn' => 'যশোর', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Jashore Sadar', 'bn' => 'যশোর সদর', 'post' => '7400'],
                    ['en' => 'Benapole', 'bn' => 'বেনাপোল', 'post' => '7432'],
                ]],
                ['en' => 'Jhenaidah', 'bn' => 'ঝিনাইদহ', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Jhenaidah Sadar', 'bn' => 'ঝিনাইদহ সদর', 'post' => '7300'],
                ]],
                ['en' => 'Kushtia', 'bn' => 'কুষ্টিয়া', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Kushtia Sadar', 'bn' => 'কুষ্টিয়া সদর', 'post' => '7000'],
                ]],
                ['en' => 'Magura', 'bn' => 'মাগুরা', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Magura Sadar', 'bn' => 'মাগুরা সদর', 'post' => '7600'],
                ]],
                ['en' => 'Meherpur', 'bn' => 'মেহেরপুর', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Meherpur Sadar', 'bn' => 'মেহেরপুর সদর', 'post' => '7100'],
                ]],
                ['en' => 'Narail', 'bn' => 'নড়াইল', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Narail Sadar', 'bn' => 'নড়াইল সদর', 'post' => '7500'],
                ]],
                ['en' => 'Satkhira', 'bn' => 'সাতক্ষীরা', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Satkhira Sadar', 'bn' => 'সাতক্ষীরা সদর', 'post' => '9400'],
                ]],
            ]],
            'Barishal' => ['bn' => 'বরিশাল', 'districts' => [
                ['en' => 'Barishal', 'bn' => 'বরিশাল', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Kotwali (Barishal)', 'bn' => 'কোতোয়ালী', 'post' => '8200'],
                ]],
                ['en' => 'Barguna', 'bn' => 'বরগুনা', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Barguna Sadar', 'bn' => 'বরগুনা সদর', 'post' => '8700'],
                ]],
                ['en' => 'Bhola', 'bn' => 'ভোলা', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Bhola Sadar', 'bn' => 'ভোলা সদর', 'post' => '8300'],
                ]],
                ['en' => 'Jhalokathi', 'bn' => 'ঝালকাঠি', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Jhalokathi Sadar', 'bn' => 'ঝালকাঠি সদর', 'post' => '8400'],
                ]],
                ['en' => 'Patuakhali', 'bn' => 'পটুয়াখালী', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Patuakhali Sadar', 'bn' => 'পটুয়াখালী সদর', 'post' => '8600'],
                ]],
                ['en' => 'Pirojpur', 'bn' => 'পিরোজপুর', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Pirojpur Sadar', 'bn' => 'পিরোজপুর সদর', 'post' => '8500'],
                ]],
            ]],
            'Sylhet' => ['bn' => 'সিলেট', 'districts' => [
                ['en' => 'Sylhet', 'bn' => 'সিলেট', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Kotwali (Sylhet)', 'bn' => 'কোতোয়ালী', 'post' => '3100'],
                    ['en' => 'South Surma', 'bn' => 'দক্ষিণ সুরমা', 'post' => '3100'],
                ]],
                ['en' => 'Habiganj', 'bn' => 'হবিগঞ্জ', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Habiganj Sadar', 'bn' => 'হবিগঞ্জ সদর', 'post' => '3300'],
                ]],
                ['en' => 'Moulvibazar', 'bn' => 'মৌলভীবাজার', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Moulvibazar Sadar', 'bn' => 'মৌলভীবাজার সদর', 'post' => '3200'],
                    ['en' => 'Sreemangal', 'bn' => 'শ্রীমঙ্গল', 'post' => '3210'],
                ]],
                ['en' => 'Sunamganj', 'bn' => 'সুনামগঞ্জ', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Sunamganj Sadar', 'bn' => 'সুনামগঞ্জ সদর', 'post' => '3000'],
                ]],
            ]],
            'Rangpur' => ['bn' => 'রংপুর', 'districts' => [
                ['en' => 'Rangpur', 'bn' => 'রংপুর', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Kotwali (Rangpur)', 'bn' => 'কোতোয়ালী', 'post' => '5400'],
                ]],
                ['en' => 'Dinajpur', 'bn' => 'দিনাজপুর', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Dinajpur Sadar', 'bn' => 'দিনাজপুর সদর', 'post' => '5200'],
                ]],
                ['en' => 'Gaibandha', 'bn' => 'গাইবান্ধা', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Gaibandha Sadar', 'bn' => 'গাইবান্ধা সদর', 'post' => '5700'],
                ]],
                ['en' => 'Kurigram', 'bn' => 'কুড়িগ্রাম', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Kurigram Sadar', 'bn' => 'কুড়িগ্রাম সদর', 'post' => '5600'],
                ]],
                ['en' => 'Lalmonirhat', 'bn' => 'লালমনিরহাট', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Lalmonirhat Sadar', 'bn' => 'লালমনিরহাট সদর', 'post' => '5500'],
                ]],
                ['en' => 'Nilphamari', 'bn' => 'নীলফামারী', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Nilphamari Sadar', 'bn' => 'নীলফামারী সদর', 'post' => '5300'],
                    ['en' => 'Saidpur', 'bn' => 'সৈয়দপুর', 'post' => '5310'],
                ]],
                ['en' => 'Panchagarh', 'bn' => 'পঞ্চগড়', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Panchagarh Sadar', 'bn' => 'পঞ্চগড় সদর', 'post' => '5000'],
                ]],
                ['en' => 'Thakurgaon', 'bn' => 'ঠাকুরগাঁও', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Thakurgaon Sadar', 'bn' => 'ঠাকুরগাঁও সদর', 'post' => '5100'],
                ]],
            ]],
            'Mymensingh' => ['bn' => 'ময়মনসিংহ', 'districts' => [
                ['en' => 'Mymensingh', 'bn' => 'ময়মনসিংহ', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Kotwali (Mymensingh)', 'bn' => 'কোতোয়ালী', 'post' => '2200'],
                ]],
                ['en' => 'Jamalpur', 'bn' => 'জামালপুর', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Jamalpur Sadar', 'bn' => 'জামালপুর সদর', 'post' => '2000'],
                ]],
                ['en' => 'Netrokona', 'bn' => 'নেত্রকোণা', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Netrokona Sadar', 'bn' => 'নেত্রকোণা সদর', 'post' => '2400'],
                ]],
                ['en' => 'Sherpur', 'bn' => 'শেরপুর', 'zone' => BangladeshDistrict::ZONE_OUTSIDE_DHAKA, 'thanas' => [
                    ['en' => 'Sherpur Sadar', 'bn' => 'শেরপুর সদর', 'post' => '2100'],
                ]],
            ]],
        ];

        foreach ($divisions as $divNameEn => $divData) {
            $division = BangladeshDivision::firstOrCreate(
                ['name_en' => $divNameEn],
                ['name_bn' => $divData['bn'], 'is_active' => true]
            );

            foreach ($divData['districts'] as $distData) {
                $district = BangladeshDistrict::firstOrCreate(
                    ['division_id' => $division->id, 'name_en' => $distData['en']],
                    [
                        'name_bn' => $distData['bn'],
                        'zone_type' => $distData['zone'],
                        'is_active' => true,
                    ]
                );

                foreach ($distData['thanas'] as $thanaData) {
                    BangladeshThana::firstOrCreate(
                        ['district_id' => $district->id, 'name_en' => $thanaData['en']],
                        [
                            'name_bn' => $thanaData['bn'],
                            'post_code' => $thanaData['post'] ?? null,
                            'is_active' => true,
                        ]
                    );
                }
            }
        }
    }
}
