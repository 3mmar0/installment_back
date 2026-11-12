<?php

namespace App\Services\Assistant;

interface AssistantLlmClient
{
    /**
     * Run one model turn with optional tools.
     *
     * Normalized messages:
     * - role: user|assistant|tool
     * - content: ?string
     * - tool_calls: list<{id, name, args}> (assistant)
     * - tool_call_id / name (tool)
     * - provider_meta: opaque provider state (orchestrator must pass through)
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools  Gemini-style function declarations from ToolRunner
     * @return array{
     *   text: ?string,
     *   tool_calls: list<array{id: string, name: string, args: array<string, mixed>}>,
     *   provider_meta: array<string, mixed>
     * }
     */
    public function complete(array $messages, array $tools, string $systemInstruction): array;

    public function isConfigured(): bool;
}
