<?php

namespace Wave\Log;

class ExceptionIntrospectionProcessor
{

    /**
     * @param array $record
     * @return array
     */
    public function __invoke(array $record)
    {

        $trace = debug_backtrace();

        $first = end($trace);

        if (isset($first['args'][0]) && $first['args'][0] instanceof \Exception) {
            // the start of this trace is an exception, so get the throwing file from
            // the exception trace.
            $exception = $first['args'][0];
            $trace = $first['args'][0]->getTrace();

            $record['extra'] = array_merge(
                $record['extra'],
                array(
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'class' => isset($trace[0]['class']) ? $trace[0]['class'] : null,
                    'function' => isset($trace[0]['function']) ? $trace[0]['function'] : null,
                )
            );

        } else {
            // otherwise, reset the end pointer and look for the first non-monolog looking line
            reset($trace);

            // skip first since it's always the current method
            array_shift($trace);
            // the call_user_func call is also skipped
            array_shift($trace);

            $i = 0;
            while (isset($trace[$i]['class'])
                && (false !== strpos($trace[$i]['class'], 'Monolog\\')
                    || (false !== strpos($trace[$i]['class'], 'Wave\\Log') && 'write' === $trace[$i]['function']))) {
                $i++;
            }

            // we should have the call source now
            $record['extra'] = array_merge(
                $record['extra'],
                array(
                    'file' => isset($trace[$i - 1]['file']) ? $trace[$i - 1]['file'] : null,
                    'line' => isset($trace[$i - 1]['line']) ? $trace[$i - 1]['line'] : null,
                    'class' => isset($trace[$i]['class']) ? $trace[$i]['class'] : null,
                    'function' => isset($trace[$i]['function']) ? $trace[$i]['function'] : null,
                )
            );
        }

        return $record;
    }


}