<?php

namespace App\Octane\RoadRunner;

use Laravel\Octane\RoadRunner\ServerProcessInspector as OctaneServerProcessInspector;

/**
 * Octane's RoadRunner inspector calls posix_kill() with masterProcessId even when it is null
 * (e.g. missing state file, race during octane:stop). That raises TypeError on PHP 8+.
 */
class ServerProcessInspector extends OctaneServerProcessInspector
{
    public function stopServer(): bool
    {
        ['masterProcessId' => $masterProcessId] = $this->serverStateFile->read();

        if ($masterProcessId === null || ! is_numeric($masterProcessId) || (int) $masterProcessId <= 0) {
            return false;
        }

        return (bool) $this->posix->kill((int) $masterProcessId, SIGTERM);
    }
}
