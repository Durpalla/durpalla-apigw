<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Page;

class PageTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $pages = [
        	['title' => 'About us', 'slug' => 'about-us'],
            ['title' => 'Terms & conditions', 'slug' => 'terms-and-conditions'],
            ['title' => 'Contact us', 'slug' => 'contact-us'],
        	['title' => 'Privacy policy', 'slug' => 'privacy-policy'],
        	['title' => 'How to buy tickets', 'slug' => 'how-to-buy']
        ];

        foreach ($pages as $page) {
            Page::create(['title' => $page['title'], 'slug' => $page['slug'], 'readonly' => 1, 'content' => $page['title']]);
        }
    }
}
