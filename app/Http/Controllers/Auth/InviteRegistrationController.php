<?php

namespace App\Http\Controllers\Auth;

use App\Actions\RegisterInvitedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\InviteRegistrationRequest;
use App\Models\Invite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InviteRegistrationController extends Controller
{
    public function create(string $token): Response
    {
        $invite = $this->findValidInvite($token);

        return Inertia::render('auth/invite-register', [
            'token' => $invite->token,
            'email' => $invite->email,
        ]);
    }

    public function store(
        InviteRegistrationRequest $request,
        string $token,
        RegisterInvitedUser $registerInvitedUser,
    ): RedirectResponse {
        $invite = $this->findValidInvite($token);

        $user = $registerInvitedUser->handle($invite, $request->validated());

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }

    private function findValidInvite(string $token): Invite
    {
        $invite = Invite::query()->where('token', $token)->first();

        if ($invite === null || ! $invite->isValid()) {
            throw new NotFoundHttpException(__('This invite link is invalid or has already been used.'));
        }

        return $invite;
    }
}
