<?php

namespace App\Models;

use Spatie\Url\Url;
use Visus\Cuid2\Cuid2;

class ApplicationPreview extends BaseModel
{
    protected $guarded = [];

    protected static function booted()
    {
        static::deleting(function ($preview) {
            if (data_get($preview, 'application.build_pack') === 'dockercompose') {
                $server = $preview->application->destination->server;
                $composeFile = $preview->application->parse(pull_request_id: $preview->pull_request_id);
                $volumes = data_get($composeFile, 'volumes');
                $networks = data_get($composeFile, 'networks');
                $networkKeys = collect($networks)->keys();
                $volumeKeys = collect($volumes)->keys();
                $volumeKeys->each(function ($key) use ($server) {
                    instant_remote_process(["docker volume rm -f $key"], $server, false);
                });
                $networkKeys->each(function ($key) use ($server) {
                    instant_remote_process(["docker network disconnect $key coolify-proxy"], $server, false);
                    instant_remote_process(["docker network rm $key"], $server, false);
                });
            }
        });
    }

    public static function findPreviewByApplicationAndPullId(int $application_id, int $pull_request_id)
    {
        return self::where('application_id', $application_id)->where('pull_request_id', $pull_request_id)->firstOrFail();
    }

    public function isRunning()
    {
        return (bool) str($this->status)->startsWith('running');
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function generate_preview_fqdn_compose()
    {
        $domains = collect(json_decode($this->application->docker_compose_domains)) ?? collect();
        $template = $this->application->preview_url_template;
        $fqdns = $domains->map(function ($domain) {
            $domain = data_get($domain, 'domain');
            return Url::fromString($domain);
        });
        $preview_fqdns = get_preview_fqdns($template, $this->pull_request_id, $fqdns);
        foreach ($preview_fqdns as $service_name => $preview_fqdn) {
            $docker_compose_domains = data_get($this, 'docker_compose_domains');
            $docker_compose_domains = json_decode($docker_compose_domains, true);
            $docker_compose_domains[$service_name]['domain'] = $preview_fqdn;
            $docker_compose_domains = json_encode($docker_compose_domains);
            $this->docker_compose_domains = $docker_compose_domains;
            $this->save();
        }
    }
}
