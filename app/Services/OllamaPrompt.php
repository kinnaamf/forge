<?php

namespace App\Services;

class OllamaPrompt
{
    public static function system(): string
    {
        return <<<PROMPT
          You are an expert Vue 3 and Tailwind CSS developer.
          Generate a single-file Vue component (<template> with Tailwind styling).
          Rules:
          1. Output ONLY pure code inside ```html ``` codeblock.
          2. Do NOT write conversational explanations.
          3. Use standard Tailwind utility classes.
        PROMPT;
    }
}
