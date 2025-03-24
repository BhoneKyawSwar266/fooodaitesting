<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class HomeController extends Controller
{
    public function index()
    {
        return view('home');
    }

    public function predict(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            $response = Http::attach(
                'image', file_get_contents($request->file('image')), 'image.jpg'
            )->post('http://24.144.117.151:5000/predict');

            if ($response->failed()) {
                return response()->json(['error' => 'Prediction API request failed'], 500);
            }

            $prediction = $response->json();
            // Extract the predicted label from the nested structure
            $foodLabel = strtolower($prediction['predicted_label'] ?? 'unknown');

            // Get nutritional data based on the label
            $nutritionData = $this->getNutritionData($foodLabel);

            return response()->json([
                'prediction' => $prediction, // Return full prediction including probabilities
                'nutrition' => $nutritionData,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    private function getNutritionData($label)
    {
        $nutrition = [
            'fried_potatos' => [
                'food_name' => 'Fried Potatoes',
                'serving_size' => '4 oz',
                'calories' => 150,
                'calorie_breakdown' => '40% fat, 55% carbs, 5% protein',
                'total_fat' => ['value' => '7.00g', 'dv' => '9%'],
                'saturated_fat' => ['value' => '3.50g', 'dv' => '18%'],
                'cholesterol' => ['value' => '0mg', 'dv' => '0%'],
                'sodium' => ['value' => '180mg', 'dv' => '8%'],
                'total_carbohydrate' => ['value' => '22.00g', 'dv' => '8%'],
                'protein' => '2.00g',
            ],
            'fried_rice' => [
                'food_name' => 'Fried Rice',
                'serving_size' => '1 cup (200g)',
                'calories' => 205,
                'calorie_breakdown' => '20% fat, 70% carbs, 10% protein',
                'total_fat' => ['value' => '4.50g', 'dv' => '6%'],
                'saturated_fat' => ['value' => '1.00g', 'dv' => '5%'],
                'cholesterol' => ['value' => '35mg', 'dv' => '12%'],
                'sodium' => ['value' => '600mg', 'dv' => '26%'],
                'total_carbohydrate' => ['value' => '36.00g', 'dv' => '13%'],
                'protein' => '5.00g',
            ],
            'pizza' => [
                'food_name' => 'Pizza',
                'serving_size' => '1 slice (100g)',
                'calories' => 266,
                'calorie_breakdown' => '35% fat, 50% carbs, 15% protein',
                'total_fat' => ['value' => '10.00g', 'dv' => '13%'],
                'saturated_fat' => ['value' => '4.50g', 'dv' => '23%'],
                'cholesterol' => ['value' => '20mg', 'dv' => '7%'],
                'sodium' => ['value' => '650mg', 'dv' => '28%'],
                'total_carbohydrate' => ['value' => '33.00g', 'dv' => '12%'],
                'protein' => '11.00g',
            ],
            'ice_cream' => [
                'food_name' => 'Ice Cream',
                'serving_size' => '1/2 cup (66g)',
                'calories' => 137,
                'calorie_breakdown' => '50% fat, 45% carbs, 5% protein',
                'total_fat' => ['value' => '7.00g', 'dv' => '9%'],
                'saturated_fat' => ['value' => '4.50g', 'dv' => '23%'],
                'cholesterol' => ['value' => '30mg', 'dv' => '10%'],
                'sodium' => ['value' => '50mg', 'dv' => '2%'],
                'total_carbohydrate' => ['value' => '16.00g', 'dv' => '6%'],
                'protein' => '2.00g',
            ],
        ];

        return $nutrition[$label] ?? ['error' => 'No nutritional data available for this food'];
    }
}
