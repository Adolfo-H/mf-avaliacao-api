<?php

namespace Tests\Unit;

use Tests\TestCase;

class MfPrivateRemotePhotoDiskTest extends TestCase
{
    public function test_disk_is_private_and_keeps_local_default_unchanged(): void
    {
        $remote = config('filesystems.disks.student_photos_s3');

        $this->assertIsArray($remote);
        $this->assertSame('s3', $remote['driver']);
        $this->assertSame('private', $remote['visibility']);
        $this->assertTrue($remote['throw']);
        $this->assertTrue($remote['use_path_style_endpoint']);
        $this->assertSame('local', config('filesystems.disks.student_photos_local.driver'));
    }
}
