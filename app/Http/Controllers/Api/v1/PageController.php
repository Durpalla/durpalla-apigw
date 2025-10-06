<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index()
    {
        $faqs = [
            [
                'id' => 1,
                'question' => 'How can I reset my password?',
                'answer' => "Go to the login screen, click on 'Forgot Password', and follow the instructions sent to your email.",
                'category' => 'Account',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'question' => 'What payment methods are supported?',
                'answer' => 'We currently support bKash, Nagad, Rocket, and all major credit/debit cards.',
                'category' => 'Payments',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'question' => 'Can I cancel a booking?',
                'answer' => "Yes, bookings can be canceled from your profile under 'My Trips' within 24 hours before departure.",
                'category' => 'Booking',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        return response()->json([
            'status' => true,
            'message' => 'FAQ list fetched successfully',
            'data' => $faqs,
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $page = Page::where('slug', $slug)->first();

        if (!$page) {
            abort(404);
        }

        return response()->success(
            $page->format()
        );
    }
}
