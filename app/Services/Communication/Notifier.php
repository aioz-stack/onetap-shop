<?php

namespace App\Services\Communication;

use App\Enums\RoleName;
use App\Enums\ShipmentStatus;
use App\Enums\UserStatus;
use App\Models\LogisticsPartner;
use App\Models\Product;
use App\Models\Refund;
use App\Models\RiderRemittance;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\Alert;
use App\Services\Administration\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Every cross-panel event notification lives here, so the services that change state only say
 * what happened. Alerts are sent after the surrounding transaction commits, and a failing mail
 * transport never breaks the business action.
 *
 * Admin alerts follow the Notifications settings (toggle per event + optional team email).
 * User alerts always go to the in-app inbox; "Email users" also mails them.
 */
class Notifier
{
    public function __construct(private SettingsService $settings) {}

    private function money(mixed $amount): string
    {
        return '₱'.number_format((float) $amount, 2);
    }

    // ---------------------------------------------------------------- admin team

    public function sellerApplied(SellerProfile $profile): void
    {
        $this->admins('notify_seller_applications', new Alert('seller.applied', 'New seller application', "{$profile->business_name} applied to sell on Onetap.", "/admin/sellers/{$profile->id}"));
    }

    public function refundRequested(Refund $refund): void
    {
        $order = $refund->sellerOrder;
        $this->admins('notify_refund_requests', new Alert('refund.requested', 'New refund request', "{$this->money($refund->amount)} requested on order {$order->reference}.", '/admin/refunds'));
        $this->user($order->shop?->sellerProfile?->user, new Alert('refund.requested', 'Return / refund requested', "The customer asked for a refund of {$this->money($refund->amount)} on order {$order->reference}.", "/seller/orders/{$order->id}"));
    }

    public function withdrawalRequested(Withdrawal $withdrawal): void
    {
        $this->admins('notify_withdrawal_requests', new Alert('withdrawal.requested', 'New withdrawal request', "{$withdrawal->shop?->name} asked to withdraw {$this->money($withdrawal->amount)}.", '/admin/finance/withdrawals'));
    }

    public function shipmentUnrouted(Shipment $shipment): void
    {
        $this->admins('notify_unrouted_shipments', new Alert('shipment.unrouted', 'Shipment needs a logistics partner', "No partner covers the address for {$shipment->tracking_number}. Route it manually.", '/admin/logistics/shipments?unassigned=1'));
    }

    // ---------------------------------------------------------------- sellers

    public function sellerReviewed(SellerProfile $profile, bool $approved, ?string $reason = null): void
    {
        $this->user($profile->user, $approved
            ? new Alert('seller.approved', 'Your shop is approved', 'Welcome to Onetap! Your Seller Center is ready.', '/seller')
            : new Alert('seller.rejected', 'Seller application not approved', 'Reason: '.($reason ?: 'not given').'. You can update your details and apply again.', '/account/sell'));
    }

    public function productReviewed(Product $product, string $outcome, ?string $reason = null): void
    {
        [$title, $body] = match ($outcome) {
            'approved' => ['Product approved', "“{$product->name}” is now live."],
            'rejected' => ['Product not approved', "“{$product->name}” was rejected. Reason: {$reason}"],
            'violation' => ['Product violation', "“{$product->name}” was flagged and hidden. Reason: {$reason}"],
            default => ['Product unpublished', "“{$product->name}” was unpublished by Onetap.".($reason ? " Reason: {$reason}" : '')],
        };

        $this->user($product->shop?->sellerProfile?->user, new Alert("product.{$outcome}", $title, $body, "/seller/products/{$product->id}/edit"));
    }

    public function orderCancelledByCustomer(SellerOrder $order, string $reason): void
    {
        $this->user($order->shop?->sellerProfile?->user, new Alert('order.cancelled', 'Order cancelled by the customer', "Order {$order->reference} was cancelled. Reason: {$reason}", "/seller/orders/{$order->id}"));
    }

    public function orderCompleted(SellerOrder $order): void
    {
        $this->user($order->shop?->sellerProfile?->user, new Alert('order.completed', 'Order completed', "The customer confirmed order {$order->reference}. {$this->money($order->seller_earning)} was added to your pending balance.", "/seller/orders/{$order->id}"));
    }

    public function withdrawalUpdated(Withdrawal $withdrawal): void
    {
        $status = $withdrawal->status->value;
        $body = match ($status) {
            'approved' => "Your withdrawal of {$this->money($withdrawal->amount)} was approved and is being paid out.",
            'paid' => "{$this->money($withdrawal->amount)} was sent to your bank account.".($withdrawal->reference ? " Reference: {$withdrawal->reference}." : ''),
            'rejected' => "Your withdrawal of {$this->money($withdrawal->amount)} was rejected and the amount is back in your balance.".($withdrawal->notes ? " Reason: {$withdrawal->notes}" : ''),
            default => null,
        };

        if ($body) {
            $this->user($withdrawal->shop?->sellerProfile?->user, new Alert("withdrawal.{$status}", 'Withdrawal '.$status, $body, '/seller/finance'));
        }
    }

    // ---------------------------------------------------------------- customers

    public function refundReviewed(Refund $refund, ?string $reason = null): void
    {
        $order = $refund->sellerOrder;
        $approved = $refund->status->value !== 'rejected';

        $this->user($order->order?->customer, new Alert(
            $approved ? 'refund.approved' : 'refund.rejected',
            $approved ? 'Refund approved' : 'Refund request declined',
            $approved ? "Your refund of {$this->money($refund->amount)} for order {$order->reference} was approved." : "Your refund request for order {$order->reference} was declined.".($reason ? " Reason: {$reason}" : ''),
            '/account/refunds',
        ));
    }

    /**
     * Delivery moments the customer cares about, plus the partner's view of rider problems.
     */
    public function shipmentChanged(Shipment $shipment, ShipmentStatus $status, ?string $note): void
    {
        $order = $shipment->sellerOrder;
        $customer = $order?->order?->customer;

        match ($status) {
            ShipmentStatus::OutForDelivery => $this->user($customer, new Alert('delivery.out', 'Your rider is on the way', "Order {$order->reference} is out for delivery.".(bccomp((string) $shipment->cod_amount, '0', 2) > 0 ? " Please prepare {$this->money($shipment->cod_amount)}." : ''), "/account/orders/{$order->id}")),
            ShipmentStatus::FailedDelivery, ShipmentStatus::CustomerUnavailable => $this->deliveryMissed($shipment, $customer, $note),
            default => null,
        };
    }

    private function deliveryMissed(Shipment $shipment, ?User $customer, ?string $note): void
    {
        $order = $shipment->sellerOrder;
        $this->user($customer, new Alert('delivery.failed', 'We missed you', "The rider could not deliver order {$order->reference}".($note ? ": {$note}" : '.').' They will try again or contact you.', "/account/orders/{$order->id}"));
        $this->user($shipment->logisticsPartner?->owner, new Alert('delivery.failed', 'Delivery attempt failed', "{$shipment->tracking_number}: ".($note ?: $shipment->status->label()), "/logistics/shipments/{$shipment->id}"));
    }

    // ---------------------------------------------------------------- logistics and riders

    public function shipmentRouted(Shipment $shipment, LogisticsPartner $partner): void
    {
        $this->user($partner->owner, new Alert('shipment.new', 'New pickup request', "{$shipment->tracking_number} is ready for pickup at ".($shipment->pickup_address['name'] ?? 'the shop').'. Assign a rider.', "/logistics/shipments/{$shipment->id}"));
    }

    public function riderDeclined(Shipment $shipment, User $rider, string $reason): void
    {
        $this->user($shipment->logisticsPartner?->owner, new Alert('job.declined', 'Rider declined a job', "{$rider->name} declined {$shipment->tracking_number}: {$reason}. It is back in your pickup queue.", "/logistics/shipments/{$shipment->id}"));
    }

    public function riderUnassigned(Shipment $shipment, ?User $rider): void
    {
        $this->user($rider, new Alert('job.removed', 'Job reassigned', "{$shipment->tracking_number} was taken off your list by your dispatcher.", '/rider/deliveries'));
    }

    public function remittanceSubmitted(RiderRemittance $remittance): void
    {
        $this->user($remittance->logisticsPartner?->owner, new Alert('remittance.submitted', 'Cash remittance to count', "{$remittance->rider?->user?->name} remitted {$this->money($remittance->remitted_amount)}.", '/logistics/remittances'));
    }

    public function remittanceReviewed(RiderRemittance $remittance): void
    {
        $confirmed = $remittance->status->value === 'confirmed';
        $this->user($remittance->rider?->user, new Alert(
            $confirmed ? 'remittance.confirmed' : 'remittance.disputed',
            $confirmed ? 'Remittance confirmed' : 'Remittance disputed',
            $confirmed ? "Your remittance of {$this->money($remittance->remitted_amount)} was confirmed." : "The cash counted differs by {$this->money(abs((float) $remittance->discrepancy))}. Talk to your logistics office.",
            '/rider/cod',
        ));
    }

    // ---------------------------------------------------------------- delivery

    private function user(?User $user, Alert $alert): void
    {
        if (! $user || $user->status !== UserStatus::Active) {
            return;
        }

        $alert->mail = (bool) $this->settings->get('notifications', 'email_users', false);
        $this->dispatch(fn () => $user->notify($alert));
    }

    private function admins(string $toggle, Alert $alert): void
    {
        if (! (bool) $this->settings->get('notifications', $toggle, '1')) {
            return;
        }

        $this->dispatch(function () use ($alert) {
            $admins = User::query()->where('status', UserStatus::Active)->whereHas('roles', fn ($roles) => $roles->where('name', RoleName::Admin->value))->get();
            Notification::send($admins, $alert);

            if ($email = $this->settings->get('notifications', 'admin_email')) {
                Notification::route('mail', $email)->notify(new Alert($alert->type, $alert->title, $alert->body, $alert->url, true));
            }
        });
    }

    private function dispatch(callable $send): void
    {
        DB::afterCommit(function () use ($send) {
            try {
                $send();
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
