<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendChatMessageRequest;
use App\Models\ChatAssistantSession;
use App\Services\ChatAssistant\ChatAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatAssistantController extends Controller
{
    public function __construct(
        private readonly ChatAssistantService $assistant,
    ) {}

    public function index(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        return Inertia::render('assistant/index', [
            'session' => $this->formatSession($this->resolveSession($request)),
            'models' => $this->assistant->availableModels(),
            'engineOptions' => $this->assistant->engineOptions(),
        ]);
    }

    public function bootstrap(Request $request): JsonResponse
    {
        abort_if($request->user() === null, 403);

        return response()->json([
            'session' => $this->formatSession($this->resolveSession($request)),
            'models' => $this->assistant->availableModels(),
            'engineOptions' => $this->assistant->engineOptions(),
        ]);
    }

    public function send(SendChatMessageRequest $request): JsonResponse
    {
        $session = ChatAssistantSession::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($request->integer('session_id'));

        $session = $this->assistant->handleMessage(
            $session,
            $request->string('message')->toString(),
            $request->string('engine')->toString() ?: (string) ($session->context['engine'] ?? 'groq'),
        );

        return response()->json([
            'session' => $this->formatSession($session),
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        abort_if($request->user() === null, 403);

        $session = $this->assistant->createSession($request->user()->id);

        return response()->json([
            'session' => $this->formatSession($session),
        ]);
    }

    /**
     * @return array{
     *   id: int,
     *   title: string,
     *   messages: list<array{role: string, content: string, sent_at: string}>,
     *   context: array<string, mixed>
     * }
     */
    private function formatSession(ChatAssistantSession $session): array
    {
        return [
            'id' => $session->id,
            'title' => $session->title ?? 'New chat',
            'messages' => $session->messages ?? [],
            'context' => $session->context ?? ['engine' => 'groq'],
        ];
    }

    private function resolveSession(Request $request): ChatAssistantSession
    {
        $session = ChatAssistantSession::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->first();

        if ($session === null) {
            return $this->assistant->createSession($request->user()->id);
        }

        return $session;
    }
}
