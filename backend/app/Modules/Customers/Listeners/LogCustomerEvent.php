<?php

declare(strict_types=1);

namespace App\Modules\Customers\Listeners;

use App\Modules\Customers\Events\AddressAdded;
use App\Modules\Customers\Events\AddressDeleted;
use App\Modules\Customers\Events\AddressUpdated;
use App\Modules\Customers\Events\CustomerProfileUpdated;
use App\Modules\Customers\Events\CustomerRegistered;
use App\Modules\Customers\Events\PasswordReset;
use App\Modules\Customers\Events\PasswordResetRequested;
use Illuminate\Support\Facades\Log;

/**
 * Placeholder listener that logs customer domain events.
 * Replace with Notifications module integration in Phase 2.
 */
class LogCustomerEvent
{
    public function handleCustomerRegistered(CustomerRegistered $event): void
    {
        Log::info('Customer registered', ['user_id' => $event->user->id, 'email' => $event->user->email]);
    }

    public function handleCustomerProfileUpdated(CustomerProfileUpdated $event): void
    {
        Log::info('Customer profile updated', ['user_id' => $event->user->id, 'changed' => $event->changedFields]);
    }

    public function handlePasswordResetRequested(PasswordResetRequested $event): void
    {
        Log::info('Password reset requested', ['email' => $event->email]);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        Log::info('Password reset completed', ['user_id' => $event->user->id]);
    }

    public function handleAddressAdded(AddressAdded $event): void
    {
        Log::info('Address added', ['user_id' => $event->address->user_id, 'address_id' => $event->address->id]);
    }

    public function handleAddressUpdated(AddressUpdated $event): void
    {
        Log::info('Address updated', ['address_id' => $event->address->id, 'changed' => $event->changedFields]);
    }

    public function handleAddressDeleted(AddressDeleted $event): void
    {
        Log::info('Address deleted', ['user_id' => $event->userId, 'address_id' => $event->addressId]);
    }
}
