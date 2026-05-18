<?php

namespace App\Services;

use Illuminate\Http\Request as InternalRequest;

class PromptService
{
    public function buildSystemPrompt(?string $extraContext = null, int $entryLimit = 50, string $mode = 'query'): string
    {
        $journalContext = $this->journalContext($entryLimit);
        $factsContext = trim((string) $extraContext);
        $modeInstruction = $mode === 'crud'
            ? 'You are in CRUD Assistant mode. You may answer journal questions. Create/update/delete requests are handled by the application before this prompt reaches you, so never invent or reveal PHP code, Laravel commands, function calls, tool handler names, or internal implementation details.'
            : 'You are in Query-only mode. Answer questions about journal entries, but do not create, update, delete, or claim that any data was changed. If the user asks for changes, tell them to switch to CRUD Assistant mode.';

        return <<<PROMPT
You are the Daily Draft Assistant for a personal journal app.
{$modeInstruction}
Use the journal context below when answering questions about entries.
If the context does not contain the answer, say so instead of inventing details.
Use the previous conversation messages to resolve follow-up questions and pronouns.
Respect any user preferences listed in the session notes.
Keep responses concise, supportive, and practical.

Session notes and journal facts:
{$factsContext}

Journal entries:
{$journalContext}
PROMPT;
    }

    public function journalContext(int $limit = 50): string
    {
        $result = $this->callToolRoute('GET', route('ai-tools.entries.context', [], false), [
            'limit' => $limit,
        ]);

        return $result['context'] ?? 'No journal entries found yet.';
    }

    private function callToolRoute(string $method, string $uri, array $payload = []): array
    {
        $request = InternalRequest::create($uri, $method, $payload);
        $request->headers->set('Accept', 'application/json');

        $response = app('router')->dispatch($request);
        $decoded = json_decode($response->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }
}
