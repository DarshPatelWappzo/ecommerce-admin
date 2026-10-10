<?php

namespace App\Services;

use App\Mail\OrderStatusUpdated;
use App\Models\Tenant\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class TenantOrderEmailService
{
    /** @param array<string, string> $details Customer-facing event details captured before queueing. */
    public function send(Order $order, string $title, string $message, array $details = []): void
    {
        if (blank($order->customer_email)) {
            return;
        }

        $email = $order->customer_email;
        $mail = new OrderStatusUpdated($order->order_number, $order->customer_name, $title, $message, $details);
        DB::connection('tenant')->afterCommit(function () use ($email, $mail): void {
            try {
                Mail::to($email)->send($mail);
            } catch (\Throwable $exception) {
                report($exception);
            }
        });
    }
}
