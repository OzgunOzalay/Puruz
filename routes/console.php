<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('shop:release-reservations')->everyMinute()->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=720')->daily();
