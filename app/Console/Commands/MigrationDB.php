<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Users;

class MigrationDB extends Command
{
    
    protected $signature = 'db:migration';

    protected $description = 'Command description';

    public function handle()
    {
        $users = Users::all();
        dd( $users->toArray() );
    }
}
