<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInviteRequest;
use App\Models\Invite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role as SpatieRole;

class InviteController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        $invites = Invite::query()
            ->with(['invitedBy:id,name', 'usedBy:id,name'])
            ->latest()
            ->get()
            ->map(fn (Invite $invite): array => [
                'id' => $invite->id,
                'email' => $invite->email,
                'role' => $invite->role,
                'registration_url' => $invite->registrationUrl(),
                'invited_by' => $invite->invitedBy?->only(['id', 'name']),
                'used_by' => $invite->usedBy?->only(['id', 'name']),
                'used_at' => $invite->used_at?->toIso8601String(),
                'expires_at' => $invite->expires_at?->toIso8601String(),
                'is_valid' => $invite->isValid(),
                'created_at' => $invite->created_at?->toIso8601String(),
            ]);

        return Inertia::render('invites/index', [
            'invites' => $invites,
            'roles' => SpatieRole::query()->orderBy('name')->pluck('name')->values()->all(),
        ]);
    }

    public function store(StoreInviteRequest $request): RedirectResponse
    {
        $invite = Invite::query()->create([
            'token' => Invite::generateToken(),
            'email' => $request->validated('email'),
            'role' => $request->string('role')->toString(),
            'invited_by' => $request->user()?->id,
            'expires_at' => $request->filled('days')
                ? now()->addDays($request->integer('days'))
                : null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Invite created: :url', ['url' => $invite->registrationUrl()]),
        ]);

        return redirect()->route('invites.index');
    }
}
