<?php

namespace App\Http\Controllers;

use App\Services\OllamaPrompt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerateController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $prompt = $request->input('prompt');

        return response()->stream(function () use ($prompt) {
            $response = Http::withOptions(['stream' => true])
            ->post('http://127.0.0.1:11434/api/generate', [
                'model' => 'qwen2.5-coder:7b',
                'system' => OllamaPrompt::system(),
                'prompt' => $prompt,
                'stream' => true,
            ]);

            $body = $response->getCurlHandle() ?$response->toPsrResponse()->getBody() : null;

            while ($body && !$body->eof()) {
                $line = $body->read(1024);
                $json = json_decode($line, true);

                if (isset($json['response'])) {
                    echo "data: " . json_encode($json['response']) . "\n\n";
                    ob.flush();
                    flush();
                }
            }
        }, 200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ]);
    }
}
