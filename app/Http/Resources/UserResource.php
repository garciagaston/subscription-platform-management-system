<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @OA\Schema(
 * )
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property object $roles
 * @property array $permissions
 * @property array $subscriptions
 *
 * @method subscriptions()
 *
 * @property string $created_at
 * @property string $updated_at
 * @property string $deleted_at
 */
class UserResource extends JsonResource
{
    /**
     * @OA\Property(format="int64",  property="id", title="id",description="id",default=1),
     * @OA\Property(format="string", property="name", title="name", description="user fullname", default="jon snow"),
     * @OA\Property(format="string", property="email", title="email", description="email", default="jon.snow@example.com"),
     * @OA\Property(format="array", property="roles", title="role", description="role", default="[]"),
     * @OA\Property(format="array", property="permissions", title="permissions", description="permissions", default="[]"),
     * @OA\Property(format="array", type="array", property="subscriptions", description="subscriptions", @OA\Items( ref="#/components/schemas/SubscriptionResource") ),
     * @OA\Property(format="date", property="created_at", title="created_at", description="created_at", default="2024-01-29 15:21:29"),
     * @OA\Property(format="date", property="updated_at", title="updated_at", description="updated_at", default="2024-01-29 15:21:29"),
     * @OA\Property(format="date", property="deleted_at", title="deleted_at", description="deleted_at", default="2024-01-29 15:21:29"),
     */
    public function toArray(Request $request): array
    {
        $permissions = collect($this->getAllPermissions()->toArray())->pluck('name')->toArray();
        $subscriptions = $this->subscriptions;
        $activeSubscription = $this->subscriptions()
            ->where('start_date', '<=', Carbon::now())
            ->where('end_date', '>=', Carbon::now())
            ->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => (array) optional($this->roles->first())->only('id', 'name'),
            'permissions' => count($permissions) ? $permissions : null,
            'active_subscription' => isset($activeSubscription) ? (new SubscriptionResource($activeSubscription))->toArray(new Request) : null,
            'subscriptions' => count($subscriptions) ? SubscriptionResource::collection(
                $subscriptions
            )->toArray(new Request) : null,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
            'deleted_at' => optional($this->deleted_at)->toDateTimeString(),
        ];
    }
}
