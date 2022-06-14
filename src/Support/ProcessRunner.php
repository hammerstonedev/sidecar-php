<?php
/**
 * @author Aaron Francis <aarondfrancis@gmail.com|https://twitter.com/aarondfrancis>
 */

namespace Hammerstone\Sidecar\PHP\Support;

use Exception;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class ProcessRunner
{
    /**
     * @var false|string
     */
    protected $binary;

    /**
     * @var CustomLambdaRuntime
     */
    protected $runtime;

    /**
     * @param CustomLambdaRuntime $runtime
     * @throws Exception
     */
    public function __construct(CustomLambdaRuntime $runtime)
    {
        $this->runtime = $runtime;
        $this->binary = (new PhpExecutableFinder)->find();

        if ($this->binary === false) {
            throw new Exception('Unable to find PHP');
        }
    }

    /**
     * @param CustomLambdaRuntime $runtime
     * @return static
     * @throws Exception
     */
    public static function make(CustomLambdaRuntime $runtime)
    {
        return new static($runtime);
    }

    /**
     * @param $id
     * @param $event
     * @return mixed|void
     * @throws Exception
     */
    public function handle($id, $event)
    {
        $output = $this->runProcess($event)->getOutput();

        if (Str::contains($output, '__START_EXCEPTION__')) {
            return $this->runtime->handleClosureException($id, $output);
        }

        $output = Str::between($output, '__START_FUNCTION_OUTPUT__', '__END_FUNCTION_OUTPUT__');
        $output = json_decode($output, JSON_OBJECT_AS_ARRAY);

        return $output['output'];
    }

    /**
     * @param $event
     * @return Process
     */
    protected function runProcess($event)
    {
        $command = $this->binary . ' ./handler.php --closure=' . escapeshellarg($event['closure']);

        $timeout = $event['timeout'] ?? 0;

        $process = Process::fromShellCommandline($command)->setTimeout($timeout);

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            fwrite(STDERR, "Task timed out after {$timeout}.00 seconds" . PHP_EOL);

            throw new Exception("Task timed out after {$timeout}.00 seconds");
        } catch (Throwable $e) {
            throw new Exception('Unhandled process exception: ' . $e->getMessage());
        }

        return $process;
    }
}
