<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    public function predict(Request $request)
        {
            $request->validate([
                'image' => 'required|image|max:2048',
            ]);

            try {
                $response = Http::withOptions([
                    'verify' => false, // Disable SSL verification for self-signed cert
                    'timeout' => 30,
                ])
                ->attach(
                    'image',
                    file_get_contents($request->file('image')),
                    'image.jpg'
                )
                ->post('https://24.144.117.151:5000/predict');

                if ($response->successful()) {
                    return response()->json([
                        'success' => true,
                        'prediction' => $response->json(),
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'error' => 'API request failed',
                    'details' => $response->body(),
                ], 500);
            } catch (\Exception $e) {
                Log::error('Prediction failed: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'error' => 'Prediction service unavailable',
                    'message' => config('app.env') === 'local' ? $e->getMessage() : null,
                ], 503);
            }
        }
}
