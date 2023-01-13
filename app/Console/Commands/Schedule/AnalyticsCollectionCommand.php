<?php

namespace Pterodactyl\Console\Commands\Schedule;

use Pterodactyl\Models\Server;
use Illuminate\Console\Command;
use Pterodactyl\Models\AnalyticsData;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;

class AnalyticsCollectionCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'p:schedule:analytics';

    /**
     * @var string
     */
    protected $description = 'Collect analytics on server performance.';

    /**
     * AnalyticsCollectionCommand constructor.
     */
    public function __construct(private DaemonServerRepository $repository)
    {
        parent::__construct();
    }

    /**
     * Handle command execution.
     */
    public function handle()
    {
        foreach (Server::all() as $server) {
            $stats = $this->repository->setServer($server)->getDetails();
            $usage = $stats['utilization'];

            if ($stats['state'] === 'offline') {
                $this->line($server->id . ' is offline, skipping');
                continue;
            }

            $this->line($server->id . ' is being processed');

            if (AnalyticsData::where('server_id', $server->id)->count() >= 12) {
                $this->line($server->id . ' exceeds 12 entries, deleting oldest');
                AnalyticsData::where('server_id', $server->id)->orderBy('id', 'asc')->first()->delete();
            }

            try {
                AnalyticsData::create([
                    'server_id' => $server->id,
                    'cpu' => $usage['cpu_absolute'] / ($server->cpu / 100),
                    'memory' => ($usage['memory_bytes'] / 1024) / $server->memory / 10,
                    'disk' => ($usage['disk_bytes'] / 1024) / $server->disk / 10,
                ]);
            } catch (\Exception $ex) {
                $this->error($server->id . ' failed to write stats: ' . $ex->getMessage());
            }
        }
    }
}
