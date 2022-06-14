<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace Hammerstone\Sidecar\PHP\Support;

use Illuminate\Support\Str;
use Laravel\Vapor\Runtime\LambdaRuntime;

class CustomLambdaRuntime extends LambdaRuntime
{
    /**
     * @param string $invocationId
     * @param string $output
     */
    public function handleClosureException(string $invocationId, string $output)
    {
        $output = Str::between($output, '__START_EXCEPTION__', '__END_EXCEPTION__');
        $data = json_decode($output);

        fwrite(STDERR, sprintf(
            "Fatal error: %s in %s:%d\nStack trace:\n%s" . PHP_EOL,
            $data->message,
            $data->file,
            $data->line,
            $data->traceAsString
        ));

        $this->notifyLambdaOfError($invocationId, [
            'errorMessage' => $data->message,
            'errorType' => $data->type,
            'stackTrace' => explode(PHP_EOL, $data->traceAsString),
        ]);
    }
}
