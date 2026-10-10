<?php

namespace App\Http\Controllers;

use App\Models\SiteContent;
use Illuminate\Http\Request;

class SiteContentController extends Controller
{
    public function show()
    {
        return response()->json(SiteContent::current());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'contact_email' => 'sometimes|email|max:255',

            'hero' => 'sometimes|array',
            'hero.title' => 'required_with:hero|string|max:150',
            'hero.subtitle' => 'required_with:hero|string|max:500',

            'features' => 'sometimes|array|max:24',
            'features.*.icon' => 'nullable|string|max:8',
            'features.*.title' => 'required|string|max:100',
            'features.*.text' => 'required|string|max:400',

            'platforms' => 'sometimes|array|max:24',
            'platforms.*' => 'required|string|max:60',

            'plans' => 'sometimes|array|max:6',
            'plans.*.name' => 'required|string|max:80',
            'plans.*.price' => 'required|string|max:30',
            'plans.*.featured' => 'boolean',
            'plans.*.features' => 'array|max:20',
            'plans.*.features.*' => 'required|string|max:200',

            'payment_methods' => 'sometimes|array|max:12',
            'payment_methods.*.name' => 'required|string|max:80',
            'payment_methods.*.details' => 'array|max:10',
            'payment_methods.*.details.*.label' => 'required|string|max:60',
            'payment_methods.*.details.*.value' => 'required|string|max:200',
        ]);

        foreach ($data as $key => $value) {
            SiteContent::store($key, $value);
        }

        return response()->json(SiteContent::current());
    }
}
