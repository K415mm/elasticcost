<?php

namespace App\Http\Controllers;

use App\Events\TokenRevoked;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Passport\Token;

class TokenManagementController extends Controller
{
    /**
     * Display the token management page.
     */
    public function index(Request $request)
    {
        $query = Token::with('user')->where('revoked', false);

        if ($userId = $request->get('user_id')) {
            $query->where('user_id', $userId);
        }

        $tokens = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        $users = User::orderBy('name')->get();

        return view('tokens.index', compact('tokens', 'users'));
    }

    /**
     * Revoke a specific token.
     */
    public function destroy(Request $request, $tokenId)
    {
        $token = Token::findOrFail($tokenId);
        $token->revoke();

        broadcast(new TokenRevoked($token->user))->toOthers();

        return redirect()->route('tokens.index')->with('success', 'Token revoked successfully.');
    }

    /**
     * Revoke all tokens for a specific user.
     */
    public function revokeAllForUser(Request $request, User $user)
    {
        $user->tokens()->update(['revoked' => true]);

        broadcast(new TokenRevoked($user))->toOthers();

        return redirect()->route('tokens.index')->with('success', "All tokens for {$user->name} revoked successfully.");
    }
}
