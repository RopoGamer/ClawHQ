<?php

namespace App\Enum;

enum TaskStatus: string
{
    case TODO = 'todo';
    case DOING = 'doing';
    case DONE = 'done';
}
