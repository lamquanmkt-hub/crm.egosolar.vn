<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\Ai\AiConversation;
use App\Models\Ai\AiMessage;
use App\Services\Ai\AiAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class AiAssistantController extends Controller
{
    public function chat(Request $request, AiAssistantService $assistant): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $message = trim((string) $validated['message']);

        $conversation = null;
        if (! empty($validated['conversation_id'])) {
            $conversation = AiConversation::query()
                ->whereKey((int) $validated['conversation_id'])
                ->where('user_id', $user->id)
                ->first();
        }

        if (! $conversation) {
            $conversation = AiConversation::create([
                'user_id' => $user->id,
                'company_id' => session('active_company_id') ?: null,
                'title' => Str::limit(preg_replace('/\s+/u', ' ', $message), 80),
                'model' => (string) config('ai_assistant.engine', 'internal-smart-search-v1'),
                'last_message_at' => now(),
            ]);
        }

        AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $message,
        ]);

        try {
            $result = $assistant->respond($conversation, $user);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Tìm kiếm thông minh đang tạm thời không phản hồi. Vui lòng thử lại.',
                'conversation_id' => $conversation->id,
            ], 503);
        }

        $assistantMessage = AiMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $result['answer'],
            'meta' => [
                'actions' => $result['actions'],
                'model' => $result['model'],
            ],
            'input_tokens' => 0,
            'output_tokens' => 0,
        ]);

        $conversation->update([
            'model' => $result['model'],
            'company_id' => session('active_company_id') ?: $conversation->company_id,
            'last_message_at' => now(),
        ]);

        return response()->json([
            'conversation_id' => $conversation->id,
            'message_id' => $assistantMessage->id,
            'answer' => $result['answer'],
            'actions' => $result['actions'],
            'model' => $result['model'],
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $items = AiConversation::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_message_at')
            ->limit(20)
            ->get(['id', 'title', 'model', 'last_message_at', 'created_at']);

        return response()->json([
            'conversations' => $items->map(fn (AiConversation $item) => [
                'id' => $item->id,
                'title' => $item->title ?: 'Lượt tìm kiếm',
                'model' => $item->model,
                'last_message_at' => optional($item->last_message_at)->toIso8601String(),
            ])->values(),
        ]);
    }

    public function show(Request $request, AiConversation $conversation): JsonResponse
    {
        abort_unless((int) $conversation->user_id === (int) $request->user()->id, 404);

        $messages = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id')
            ->limit(100)
            ->get(['id', 'role', 'content', 'meta', 'created_at']);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'model' => $conversation->model,
            ],
            'messages' => $messages->map(fn (AiMessage $message) => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'actions' => (array) data_get($message->meta, 'actions', []),
                'created_at' => optional($message->created_at)->toIso8601String(),
            ])->values(),
        ]);
    }

    public function destroy(Request $request, AiConversation $conversation): JsonResponse
    {
        abort_unless((int) $conversation->user_id === (int) $request->user()->id, 404);
        $conversation->delete();

        return response()->json(['ok' => true]);
    }
}
