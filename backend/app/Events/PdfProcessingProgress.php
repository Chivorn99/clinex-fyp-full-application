<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PdfProcessingProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $sessionId,
        public string $message,
        public int $progress,
        public bool $completed = false,
        public ?array $data = null,
        public ?string $error = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('pdf-processing.' . $this->sessionId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PdfProcessingProgress';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'message' => $this->message,
            'progress' => $this->progress,
            'completed' => $this->completed,
            'data' => $this->data,
            'error' => $this->error,
        ];
    }
}
