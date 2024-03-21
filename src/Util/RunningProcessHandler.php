<?php

namespace App\Util;

use Symfony\Component\Process\Process;

trait RunningProcessHandler
{
    /**
     * Removes already finished processes.
     *
     * @param Process[]|array $runningProcesses
     *
     * @return Process[]|array
     */
    private function removeFinishedProcesses(array &$runningProcesses): array
    {
        foreach($runningProcesses as $procNum => $runningProcess){
            if(!$runningProcess->isRunning()){
                unset($runningProcesses[$procNum]);
            }
        }

        return $runningProcesses;
    }
}