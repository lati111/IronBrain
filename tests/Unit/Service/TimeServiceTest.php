<?php

namespace Tests\Unit\Service;

use App\Service\TimeService;
use DateTime;
use Tests\Unit\Service\AbstractServiceTester as ServiceAbstractServiceTester;

class TimeServiceTest extends ServiceAbstractServiceTester
{
    public function testTimeElapsedString(): void
    {
        // due to how modify datetime works, the modified value should always be 1 higher than the desired value

        $date = new DateTime();
        $date->modify('+30 seconds');
        $this->assertEquals('just now', TimeService::time_elapsed_string($date));

        $date = new DateTime();
        $date->modify('+2 minutes');
        $this->assertEquals(sprintf(
            "%s minute ago", '1'),
            TimeService::time_elapsed_string($date)
        );

        $date = new DateTime();
        $date->modify('+13 minutes');
        $this->assertEquals(sprintf(
            "%s minutes ago", '12'),
            TimeService::time_elapsed_string($date)
        );

        $date = new DateTime();
        $date->modify('+2 hour');
        $this->assertEquals(sprintf(
            "%s hour ago", '1'),
            TimeService::time_elapsed_string($date)
        );

        $date = new DateTime();
        $date->modify('+3 hour');
        $this->assertEquals(sprintf(
            "%s hours ago", '2'),
            TimeService::time_elapsed_string($date)
        );

        $date = new DateTime();
        $date->modify('+2 days');
        $this->assertEquals(sprintf(
            "%s day ago", '1'),
            TimeService::time_elapsed_string($date)
        );

        $date = new DateTime();
        $date->modify('+6 days');
        $this->assertEquals(sprintf(
            "%s days ago", '5'),
            TimeService::time_elapsed_string($date)
        );

        $date = new DateTime();
        $date->modify('+9 days');
        $this->assertEquals(sprintf(
            "%s week ago", '1'),
            TimeService::time_elapsed_string($date)
        );

        $date = new DateTime();
        $date->modify('+15 days');
        $this->assertEquals(sprintf(
            "%s weeks ago", '2'),
            TimeService::time_elapsed_string($date)
        );

        $date = new DateTime();
        $date->modify('+2 months');
        $this->assertEquals(sprintf(
            "%s month ago", '1'),
            TimeService::time_elapsed_string($date)
        );

        $date = new DateTime();
        $date->modify('+6 months');
        $this->assertEquals(sprintf(
            "%s months ago", '5'),
            TimeService::time_elapsed_string($date)
        );

        $date = new DateTime();
        $date->modify('+2 years');
        $this->assertEquals(sprintf(
            "%s year ago", '1'),
            TimeService::time_elapsed_string($date)
        );

        $date = new DateTime();
        $date->modify('+6 years');
        $this->assertEquals(sprintf(
            "%s years ago", '5'),
            TimeService::time_elapsed_string($date)
        );
    }
}
