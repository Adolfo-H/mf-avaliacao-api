<?php

return [
    'disk' => env(
        'STUDENT_PHOTO_DISK',
        'student_photos_local'
    ),

    'max_size_kb' => (int) env(
        'STUDENT_PHOTO_MAX_SIZE_KB',
        5120
    ),
];
