<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Service;

class ServiceSeeder extends Seeder
{
    public function run()
    {
        $services = [
            [
                'name' => 'Mid Fade',
                'description' => 'A clean, versatile haircut where the fade starts midway between the temples and ears, blending smoothly for a sharp yet balanced look.',
                'price' => 99.00,
                'duration' => 30,
                'category' => 'Haircut',
                'image' => 'services/mid-fade.jpg',
            ],
            [
                'name' => 'Birkin Bangs',
                'description' => 'Trendy curtain bangs that frame the face beautifully. Perfect for a fresh new look.',
                'price' => 99.00,
                'duration' => 45,
                'category' => 'Haircut',
                'image' => 'services/birkin-bangs.jpg',
            ],
            [
                'name' => 'Balayage',
                'description' => 'Hand-painted highlights for a natural, sun-kissed look. Includes toner and gloss.',
                'price' => 1500.00,
                'duration' => 120,
                'category' => 'Hair Color',
                'image' => 'services/balayage.jpg',
            ],
            [
                'name' => 'Ghost Roots',
                'description' => 'Root touch-up with a subtle shadow root effect. Perfect for low-maintenance color.',
                'price' => 1299.00,
                'duration' => 90,
                'category' => 'Hair Color',
                'image' => 'services/ghost-roots.jpg',
            ],
            [
                'name' => 'Protein Treatment',
                'description' => 'Deep conditioning treatment to strengthen and repair damaged hair.',
                'price' => 999.00,
                'duration' => 60,
                'category' => 'Treatment',
                'image' => 'services/protein-treatment.jpg',
            ],
            [
                'name' => 'Scalp Treatment',
                'description' => 'Soothing treatment for dry or itchy scalp. Promotes healthy hair growth.',
                'price' => 999.00,
                'duration' => 60,
                'category' => 'Treatment',
                'image' => 'services/scalp-treatment.jpg',
            ],
            [
                'name' => 'Long Rebonding',
                'description' => 'Chemical straightening for long hair. Leaves hair sleek and manageable.',
                'price' => 1999.00,
                'duration' => 180,
                'category' => 'Rebonding',
                'image' => 'services/long-rebonding.jpg',
            ],
            [
                'name' => 'Short Rebonding',
                'description' => 'Chemical straightening for short hair. Smooth and straight results.',
                'price' => 1888.00,
                'duration' => 150,
                'category' => 'Rebonding',
                'image' => 'services/short-rebonding.jpg',
            ],
            [
                'name' => '3D Gel Polish',
                'description' => 'Long-lasting gel polish with a 3D effect. Includes nail art options.',
                'price' => 499.00,
                'duration' => 60,
                'category' => 'Gel Polish',
                'image' => 'services/3d-gel.jpg',
            ],
            [
                'name' => 'Metallic Gel Polish',
                'description' => 'Shiny metallic gel polish that lasts for weeks. Available in various shades.',
                'price' => 399.00,
                'duration' => 60,
                'category' => 'Gel Polish',
                'image' => 'services/metallic-gel.jpg',
            ],
        ];

        foreach ($services as $service) {
            Service::create($service);
        }
    }
}
