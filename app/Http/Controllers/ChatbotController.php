<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    public function handle(Request $request)
    {
        $message = strtolower($request->input('message'));

        // Basic e-commerce response rules (or you can connect the Gemini API here)
        if (str_contains($message, 'order') || str_contains($message, 'track')) {
            $reply = "To track your order, please log into your account or provide your Order ID.";
        } elseif (str_contains($message, 'shipping') || str_contains($message, 'delivery')) {
            $reply = "We offer free shipping on all orders over $50! Standard delivery takes 3-5 days.";
        } elseif (str_contains($message, 'return')) {
            $reply = "We have a 30-day hassle-free return policy.";
        } else {
            $reply = "Thanks for reaching out to ShopEase! How else can I help with your shopping today?";
        }

        return response()->json([
            'success' => true,
            'reply' => $reply
        ]);
    }
}
