<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webpush:generate-vapid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate VAPID key pair for web push notifications';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! class_exists(VAPID::class)) {
            $this->error('Web push dependency missing. Run composer install.');
            return self::FAILURE;
        }

        $keys = VAPID::createVapidKeys();

        $this->line('Use these values in your backend environment:');
        $this->newLine();
        $this->line('VAPID_SUBJECT=mailto:admin@lendamais.com');
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);

        return self::SUCCESS;
    }
}
