<?php

namespace App\Http\Controllers;

use App\Services\OllamaPrompt;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerateController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        set_time_limit(0);
        ini_set('max_execution_time', '0');

        $prompt = $request->input('prompt');

        $response = new StreamedResponse(function () use ($prompt) {
            if (ob_get_level()) {
                ob_end_clean();
            }

            $ch = curl_init('http://127.0.0.1:11434/api/generate');

            $payload = json_encode([
                'model' => 'gemma4:e4b',
                'system' => OllamaPrompt::system(),
                'prompt' => $prompt,
                'stream' => true,
                'options' => [
                    'num_ctx' => 4096,
                    'num_predict' => 2048,
                    'temperature' => 0.7,
                ]
            ]);

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

            // Буфер для склейки раззорванных JSON-чанков
            $buffer = '';

            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$buffer) {
                $buffer .= $data;
                $lines = explode("\n", $buffer);

                // Оставляем незавершенный хвост строки в буфере
                $buffer = array_pop($lines);

                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if (empty($trimmed)) continue;

                    $json = json_decode($trimmed, true);
                    if (isset($json['response'])) {
                        echo "data: " . json_encode(['text' => $json['response']]) . "\n\n";
                        @ob_flush();
                        flush();
                    }
                }

                return strlen($data);
            });

            curl_exec($ch);
            curl_close($ch);
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
