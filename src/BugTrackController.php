<?php

namespace Ciplnew\BugTracking;

use Throwable;

/**
 * Kept for existing apps that call BugTrackController::bugTrack($e)
 * from app/Exceptions/Handler.php. New installs do not need this —
 * the service provider reports exceptions automatically.
 */
class BugTrackController
{
    public static function bugTrack($e, $linesBefore = 5, $linesAfter = 5)
    {
        if ($e instanceof Throwable) {
            BugTrackReporter::report($e);
        }

        return 0;
    }
}
