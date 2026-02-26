<?php

namespace App\Enum;

enum TaskNoteType: string
{
    case PROGRESS = 'progress';
    case BLOCKER = 'blocker';
    case DECISION = 'decision';
    case SYSTEM = 'system';
}
