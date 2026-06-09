<?php

namespace App\Http\Controllers;

use App\Models\UserApiToken;
use Illuminate\Http\Request;

class UserApiTokenController extends Controller
{
    private const MAX_ACTIVE_TOKENS = 3;

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

        $activeTokens = $request->user()->apiTokens()->whereNull('revoked_at')->count();

        if ($activeTokens >= self::MAX_ACTIVE_TOKENS) {
            return back()->withErrors([
                'name' => 'O plano free permite no maximo 3 API keys ativas por usuario.',
            ])->withInput();
        }

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
