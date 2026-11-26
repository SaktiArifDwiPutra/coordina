<?php

namespace App\Notifications;

use App\Models\BorrowRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BorrowRequestWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly BorrowRequest $borrowRequest,
        private readonly string $action,
        private readonly string $title,
        private readonly string $message
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->borrowRequest->loadMissing(['facility:id,name', 'organization:id,name']);

        return [
            'borrow_request_id' => $this->borrowRequest->id,
            'workflow_type' => $this->borrowRequest->workflow_type,
            'status' => $this->borrowRequest->status,
            'action' => $this->action,
            'title' => $this->title,
            'message' => $this->message,
            'facility_name' => $this->borrowRequest->facility?->name,
            'organization_name' => $this->borrowRequest->organization?->name,
            'date' => $this->borrowRequest->date,
            'start_time' => $this->borrowRequest->start_time,
            'end_time' => $this->borrowRequest->end_time,
            'rejection_reason' => $this->borrowRequest->rejection_reason,
        ];
    }
}

