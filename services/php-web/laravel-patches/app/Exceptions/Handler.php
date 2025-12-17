<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Throwable;
use Illuminate\Http\Response;
use Symfony\Component\Console\Output\OutputInterface;

class Handler implements ExceptionHandlerContract
{
    public function report(Throwable $e): void
    {
        // Ничего не логируем для облегчённой заглушки
    }

    public function render($request, Throwable $e)
    {
        $message = env('APP_DEBUG', false) ? $e->getMessage() : 'Server Error';
        return new Response($message, 500);
    }

    public function renderForConsole($output, Throwable $e): void
    {
        if ($output instanceof OutputInterface) {
            $output->writeln($e->getMessage());
        }
    }

    public function shouldReport(Throwable $e): bool
    {
        return false;
    }
}
