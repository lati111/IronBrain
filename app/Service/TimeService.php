<?php

namespace App\Service;

use DateTime;

class TimeService {
    public static function time_elapsed_string(string $datetime): string {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        //| in years
        if ($diff->y > 0) {
            if ($diff->y > 1) {
                return sprintf("%s years ago", $diff->y);
            } else {
                return "1 year ago";
            }
        }

        //| in months
        if ($diff->m > 0) {
            if ($diff->m > 1) {
                return sprintf("%s months ago", $diff->m);
            } else {
                return "1 month ago";
            }
        }

        //| in weeks
        $weeks = floor($diff->d / 7);
        if ($weeks > 0) {
            if ($weeks > 1) {
                return sprintf("%s weeks ago", $weeks);
            } else {
                return "1 week ago";
            }
        }

        //| in days
        if ($diff->d > 0) {
            if ($diff->d > 1) {
                return sprintf("%s days ago", $diff->d);
            } else {
                return "1 day ago";
            }
        }

        //| in hours
        if ($diff->h > 0) {
            if ($diff->h > 1) {
                return sprintf("%s hours ago", $diff->h);
            } else {
                return "1 hour ago";
            }
        }

        //| in minutes
        if ($diff->i > 0) {
            if ($diff->i > 1) {
                return sprintf("%s minutes ago", $diff->i);
            } else {
                return "1 minute ago";
            }
        }

        return "just now";
    }
}
