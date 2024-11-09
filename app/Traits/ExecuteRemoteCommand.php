<?php

namespace App\Traits;

use App\Enums\ApplicationDeploymentStatus;
use App\Helpers\SshMultiplexingHelper;
use App\Models\Server;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;

trait ExecuteRemoteCommand
{
    public ?string $save = null;

    public static int $batch_counter = 0;

    public function execute_remote_command(...$commands)
    {
        static::$batch_counter++;
        if ($commands instanceof Collection) {
            $commandsText = $commands;
        } else {
            $commandsText = collect($commands);
        }
        if ($this->server instanceof Server === false) {
            throw new \RuntimeException('Server is not set or is not an instance of Server model');
        }
        $commandsText->each(function ($single_command) {
            $command = data_get($single_command, 'command') ?? $single_command[0] ?? null;
            if ($command === null) {
                throw new \RuntimeException('Command is not set');
            }
            $hidden = data_get($single_command, 'hidden', false);
            $customType = data_get($single_command, 'type');
            $ignore_errors = data_get($single_command, 'ignore_errors', false);
            $append = data_get($single_command, 'append', true);
            $this->save = data_get($single_command, 'save');
            $secrets = data_get($single_command, 'secrets', []);  // Secrets for interpolation and masking
            if (count($secrets) > 0) {
                $command = $this->interpolateCommand($command, $secrets);
            }
            if ($this->server->isNonRoot()) {
                if (str($command)->startsWith('docker exec')) {
                    $command = str($command)->replace('docker exec', 'sudo docker exec');
                } else {
                    $command = parseLineForSudo($command, $this->server);
                }
            }
            $remote_command = SshMultiplexingHelper::generateSshCommand($this->server, $command);
            $process = Process::timeout(3600)->idleTimeout(3600)->start($remote_command, function (string $type, string $output) use ($command, $secrets, $hidden, $customType, $append) {
                $output = str($output)->trim();
                if (count($secrets) > 0) {
                    $output = $this->maskSecrets($output, $secrets);
                    $command = $this->maskSecrets($command, $secrets);
                }
                if (str($output)->startsWith('╔')) {
                    $output = "\n" . $output;
                }
                $new_log_entry = [
                    'command' => remove_iip($command),
                    'output' => remove_iip($output),
                    'type' => $customType ?? $type === 'err' ? 'stderr' : 'stdout',
                    'timestamp' => Carbon::now('UTC'),
                    'hidden' => $hidden,
                    'batch' => static::$batch_counter,
                ];
                if (! $this->application_deployment_queue->logs) {
                    $new_log_entry['order'] = 1;
                } else {
                    $previous_logs = json_decode($this->application_deployment_queue->logs, associative: true, flags: JSON_THROW_ON_ERROR);
                    $new_log_entry['order'] = count($previous_logs) + 1;
                }
                $previous_logs[] = $new_log_entry;
                $this->application_deployment_queue->logs = json_encode($previous_logs, flags: JSON_THROW_ON_ERROR);
                $this->application_deployment_queue->save();

                if ($this->save) {
                    if (data_get($this->saved_outputs, $this->save, null) === null) {
                        data_set($this->saved_outputs, $this->save, str());
                    }
                    if ($append) {
                        $this->saved_outputs[$this->save] .= str($output)->trim();
                        $this->saved_outputs[$this->save] = str($this->saved_outputs[$this->save]);
                    } else {
                        $this->saved_outputs[$this->save] = str($output)->trim();
                    }
                }
            });
            $this->application_deployment_queue->update([
                'current_process_id' => $process->id(),
            ]);

            $process_result = $process->wait();
            if ($process_result->exitCode() !== 0) {
                if (! $ignore_errors) {
                    $this->application_deployment_queue->status = ApplicationDeploymentStatus::FAILED->value;
                    $this->application_deployment_queue->save();
                    throw new \RuntimeException($process_result->errorOutput());
                }
            }
        });
    }

    private function interpolateCommand(string $command, array $secrets): string
    {
        foreach ($secrets as $key => $value) {
            // Define the placeholder format
            $placeholder = "{{secrets.$key}}";
            // Replace placeholder with actual value
            $command = str_replace($placeholder, $value, $command);
        }
        return $command;
    }

    private function maskSecrets(string $text, array $secrets): string
    {
        // Sort secrets by length descending to prevent partial masking
        usort($secrets, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        foreach ($secrets as $value) {
            // Replace each secret value with '*****'
            $text = str_replace($value, '*****', $text);
        }
        return $text;
    }
}
