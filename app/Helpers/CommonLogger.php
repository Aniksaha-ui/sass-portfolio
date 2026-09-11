<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log as LaravelLog;
use Throwable;

class CommonLogger
{
    public static function error($message, array $context = [])
    {
        LaravelLog::error($message, array_merge(self::callerContext(), $context));
    }

    public static function info($message, array $context = [])
    {
        LaravelLog::info($message, array_merge(self::callerContext(), $context));
    }

    public static function exception(Throwable $exception, array $context = [])
    {
        LaravelLog::error($exception->getMessage(), array_merge(self::exceptionContext($exception), $context));
    }

    private static function callerContext()
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        foreach ($trace as $frame) {
            if (!isset($frame['class']) || $frame['class'] === self::class) {
                continue;
            }

            return self::contextForFrame($frame);
        }

        return self::requestContext();
    }

    private static function exceptionContext(Throwable $exception)
    {
        $origin = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        foreach ($exception->getTrace() as $frame) {
            if (isset($frame['class']) && (strpos($frame['class'], 'App\\Http\\Controllers\\') === 0 || strpos($frame['class'], 'App\\Repository\\Services\\') === 0)) {
                $origin = $frame;
                break;
            }
        }

        return array_merge(self::contextForFrame($origin), [
            'exception' => get_class($exception),
            'exception_file' => self::relativePath($exception->getFile()),
            'exception_line' => $exception->getLine(),
        ]);
    }

    private static function contextForFrame(array $frame)
    {
        $class = isset($frame['class']) ? $frame['class'] : null;

        return array_merge([
            'component_type' => self::componentType($class),
            'component' => $class,
            'function' => isset($frame['function']) ? $frame['function'] : null,
            'file' => isset($frame['file']) ? self::relativePath($frame['file']) : null,
            'line' => isset($frame['line']) ? $frame['line'] : null,
        ], self::requestContext());
    }

    private static function componentType($class)
    {
        if (is_string($class) && strpos($class, 'App\\Http\\Controllers\\') === 0) {
            return 'controller';
        }

        if (is_string($class) && strpos($class, 'App\\Repository\\Services\\') === 0) {
            return 'service';
        }

        return 'application';
    }

    private static function requestContext()
    {
        if (!app()->bound('request')) {
            return [];
        }

        $request = app('request');

        return [
            'request_method' => $request->method(),
            'request_path' => $request->path(),
            'trace_id' => $request->header('X-Trace-Id'),
        ];
    }

    private static function relativePath($path)
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}
