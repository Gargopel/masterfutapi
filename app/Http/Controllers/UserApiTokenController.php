<?php

namespace App\Http\Controllers;

use App\Models\UserApiToken;
use Illuminate\Http\Request;

class UserApiTokenController extends Controller
{
    public function index(Request $request)
    {
        return view('user-api-keys', [
            'user' => $request->user(),
            'tokens' => $request->user()->apiTokens()->latest()->get(),
            'plainTextToken' => session('plain_text_api_token'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        [, $plainTextToken] = UserApiToken::issueFor($request->user(), $data['name']);

        return redirect('/api-keys')->with('plain_text_api_token', $plainTextToken);
    }

    public function destroy(Request $request, UserApiToken $token)
    {
        abort_unless($token->user_id === $request->user()->id, 404);

        $token->update(['revoked_at' => now()]);

        return back()->with('status', 'Chave revogada com sucesso.');
    }
}
