<?php

namespace App\Http\Controllers;

use App\Enums\PieceState;
use App\Mail\AdminNewSimilarRequest;
use App\Mail\SimilarRequestReceived;
use App\Models\Product;
use App\Models\SimilarRequest;
use App\Support\Contact;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class SimilarRequestController extends Controller
{
    public function store(Request $request, string $slug): RedirectResponse
    {
        $product = Product::query()->reachable()->where('slug', $slug)->firstOrFail();
        abort_unless($product->pieceState() === PieceState::Sold, 404);

        // Honeypot: bots fill every field; pretend it worked.
        if (filled($request->input('website'))) {
            return back()->with('similar_request_sent', true);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'contact' => ['required', 'string', 'max:190'],
            'accept_kvkk' => ['accepted'],
        ], [
            'message.required' => 'Lütfen nasıl bir parça istediğinizi yazın.',
            'accept_kvkk.accepted' => 'Devam etmek için KVKK metnini onaylayın.',
        ]);

        $contact = Contact::parse($data['contact'])
            ?? throw ValidationException::withMessages(['contact' => 'Geçerli bir cep telefonu ya da e-posta adresi yazın.']);

        $similar = SimilarRequest::query()->create([
            'product_id' => $product->id,
            'message' => trim($data['message']),
            'contact_type' => $contact[0],
            'contact' => $contact[1],
            'ip' => $request->ip(),
        ]);

        if (filled($admin = Settings::get('admin_notification_email'))) {
            Mail::to($admin)->send(new AdminNewSimilarRequest($similar));
        }

        if ($similar->isEmail()) {
            Mail::to($similar->contact)->send(new SimilarRequestReceived($similar));
        }

        return back()->with('similar_request_sent', true);
    }
}
