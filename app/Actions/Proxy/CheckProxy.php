<?php

namespace App\Actions\Proxy;

use App\Models\Server;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckProxy
{
    use AsAction;

    public function handle(Server $server, $fromUI = false)
    {
        if (!$server->isFunctional()) {
            return false;
        }
        if ($server->isBuildServer()) {
            return false;
        }
        $proxyType = $server->proxyType();
        if (is_null($proxyType) || $proxyType === 'NONE') {
            return false;
        }
        ['uptime' => $uptime, 'error' => $error] = $server->validateConnection();
        if (!$uptime) {
            throw new \Exception($error);
        }
        if (!$server->isProxyShouldRun()) {
            if ($fromUI) {
                throw new \Exception('Proxy should not run. You selected the Custom Proxy.');
            } else {
                return false;
            }
        }

        $containerName = $server->isSwarm() ? 'coolify-proxy_traefik' : 'coolify-proxy';
        $status = getContainerStatus($server, $containerName);

        $server->proxy->set('status', $status);
        $server->save();

        return $status !== 'running';
    }
}
