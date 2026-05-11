<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('classroom.{classroomId}', function () {
    return true;
});
