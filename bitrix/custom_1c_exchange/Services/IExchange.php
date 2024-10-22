<?php

namespace Exchange\Services;

use Carbon\Traits\ObjectInitialisation;
use Psr\Http\Message\ServerRequestInterface;

interface IExchange
{
    public function export(): array;
    public function import(ServerRequestInterface $request): array;

}