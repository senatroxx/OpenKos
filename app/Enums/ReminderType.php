<?php

namespace App\Enums;

enum ReminderType: string
{
    case Upcoming = 'upcoming';
    case DueToday = 'due_today';
    case Overdue = 'overdue';
}
