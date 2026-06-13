<?php

namespace App\Services\Telegram;

use App\DTOs\Moodle\MoodleAssignment;
use App\DTOs\Moodle\MoodleResource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends Moodle alerts via the Telegram Bot HTTP API.
 *
 * Uses Laravel's HTTP client because telegram-bot-sdk/laravel does not yet
 * support Laravel 13. Credentials are read from config/services.php (.env).
 */
class TelegramNotificationService
{
    /**
     * Notify the configured chat about a new Moodle assignment.
     */
    public function sendAssignmentAlert(MoodleAssignment $assignment): void
    {
        $lines = [
            '📝 <b>New Assignment</b>',
            '',
            '<b>Course:</b> ' . $this->escape($assignment->courseName),
            '<b>Title:</b> ' . $this->escape($assignment->title),
        ];

        if ($assignment->dueDate !== null) {
            $lines[] = '<b>Due:</b> ' . $this->escape($assignment->dueDate);
        }

        $lines[] = '';
        $lines[] = '<a href="' . $this->escape($assignment->url) . '">Open in Moodle</a>';

        $this->send(implode("\n", $lines), 'HTML');
    }

    /**
     * Notify the configured chat about a new Moodle resource.
     */
    public function sendResourceAlert(MoodleResource $resource): void
    {
$lines = [
    '📎 <b>New Resource Detected</b>',
    '────────────────────────',
    '📚 <b>Course:</b> ' . $this->escape($resource->courseName),
    '📌 <b>Title:</b> ' . $this->escape($resource->title),
    '📂 <b>Type:</b> ' . $this->escape($resource->resourceType),
    '────────────────────────',
    '🔗 <a href="' . $this->escape($resource->url) . '">Access Resource on Moodle</a>',
];
        $this->send(implode("\n", $lines), 'HTML');
    }

    /**
     * Send a raw message to the configured Telegram chat.
     */
    public function send(string $message, string $parseMode = 'MarkdownV2'): void
    {
        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (empty($botToken) || empty($chatId)) {
            throw new RuntimeException(
                'Telegram is not configured. Set TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID in .env.'
            );
        }

        $response = Http::timeout(15)->post(
            sprintf('https://api.telegram.org/bot%s/sendMessage', $botToken),
            [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => $parseMode,
                'disable_web_page_preview' => false,
            ]
        );

        if (! $response->successful()) {
            $responseBody = $response->body();

            Log::error('Telegram notification failed.', [
                'status' => $response->status(),
                'body' => $responseBody,
            ]);

            throw new RuntimeException(sprintf(
                'Failed to send Telegram notification: %s %s',
                $response->status(),
                $responseBody
            ));
        }
    }

    /**
     * Escape text for Telegram HTML parse mode.
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
