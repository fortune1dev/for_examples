<?php

namespace App\Events;

use App\Models\Site;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Jobs\ParseSite;

class SiteAdded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $site;

    /**
     * Create a new event instance.
     *
     * @param  \App\Models\Site  $site
     * @return void
     */
    public function __construct(Site $site)
    {
        $this->site = $site;
        ParseSite::dispatch($site);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
