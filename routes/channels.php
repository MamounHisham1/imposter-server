<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('room.{roomId}', function () {
    return true;
});
