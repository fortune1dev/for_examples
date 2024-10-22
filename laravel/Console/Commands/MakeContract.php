<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeContract extends Command
{
    protected $signature = 'make:contract {name}';
    protected $description = 'Create a new contract interface';

    protected $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle()
    {
        $name = $this->argument('name');
        $path = $this->getPath($name);

        if ($this->files->exists($path)) {
            $this->error('Contract already exists!');
            return;
        }

        $this->makeDirectory($path);
        $this->files->put($path, $this->buildClass($name));

        $this->info('Contract created successfully.');
    }

    protected function getPath($name)
    {
        $name = str_replace('\\', '/', $name);
        return base_path('app/Contracts/') . $name . '.php';
    }

    protected function makeDirectory($path)
    {
        if (!$this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0777, true, true);
        }
    }

    protected function buildClass($name)
    {
        $stub = $this->files->get(__DIR__ . '/stubs/contract.stub');
        return $this->replaceNamespace($stub, $name)->replaceClass($stub, $name);
    }

    protected function replaceNamespace(&$stub, $name)
    {
        $stub = str_replace(
            '{{namespace}}',
            'App\\Contracts',
            $stub
        );

        return $this;
    }

    protected function replaceClass($stub, $name)
    {
        $class = str_replace($this->getNamespace($name) . '\\', '', $name);
        return str_replace('{{class}}', $class, $stub);
    }

    protected function getNamespace($name)
    {
        return 'App\\Contracts';
    }
}
