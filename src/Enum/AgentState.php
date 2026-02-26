<?php

namespace App\Enum;

enum AgentState: string
{
    case IDLE = 'idle';
    case WORKING = 'working';
    case BLOCKED = 'blocked';
}
