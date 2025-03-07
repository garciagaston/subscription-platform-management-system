<?php

namespace Tests\Unit\Api\V1;

use App\Helpers\TestsHelper;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class UsersTest extends TestCase
{
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = TestsHelper::createAdmin();
    }

    public function testIndexSuccess(): void
    {
        $count = 20;
        $perPage = 10;
        $users = [];
        foreach (range(0, $count - 1) as $i) { // @phpcs:ignore
            $users[] = TestsHelper::createUser();
        }
        $usersPaginated = User::take($perPage)->get();
        $response = $this->actingAs($this->admin)->get("/api/v1/users?per_page={$perPage}");
        TestsHelper::dumpApiResponsesWithErrors($response);
        $response->assertStatus(Response::HTTP_OK);
        TestsHelper::checkPagination($response, $this, $count + 1, $perPage);
        $jsonResponse = TestsHelper::getJsonResponse($response);
        $this->assertEquals($jsonResponse['data'], UserResource::collection($usersPaginated)->toArray(new Request()));
    }

    public function testIndexFailed(): void
    {
        $count = 20;
        $perPage = 10;
        $users = [];
        foreach (range(0, $count - 1) as $i) { // @phpcs:ignore
            $users[] = TestsHelper::createUser();
        }
        $randomUser = collect($users)->random();
        $response = $this->actingAs($randomUser)->get("/api/v1/users?per_page={$perPage}");
        TestsHelper::dumpApiResponsesWithErrors($response, Response::HTTP_FORBIDDEN);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testShowSuccessWithAdmin(): void
    {
        $count = 10;
        $users = [];
        foreach (range(0, $count - 1) as $i) { // @phpcs:ignore
            $users[] = TestsHelper::createUser();
        }
        $user = collect($users)->random();
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->actingAs($this->admin)->get("/api/v1/users/{$user->id}");
        TestsHelper::dumpApiResponsesWithErrors($response);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'data' => (new UserResource($user))->toArray(new Request()),
        ]);
    }

    public function testShowSuccessWithUser(): void
    {
        $count = 10;
        $users = [];
        foreach (range(0, $count - 1) as $i) { // @phpcs:ignore
            $users[] = TestsHelper::createUser();
        }
        $user = collect($users)->random();
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->actingAs($user)->get("/api/v1/users/{$user->id}");
        TestsHelper::dumpApiResponsesWithErrors($response);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'data' => (new UserResource($user))->toArray(new Request()),
        ]);
    }

    public function testShowFailWithUser(): void
    {
        $count = 10;
        $users = [];
        foreach (range(0, $count - 1) as $i) { // @phpcs:ignore
            $users[] = TestsHelper::createUser();
        }
        $users = collect($users)->random(2);
        $user1 = $users[0];
        $user2 = $users[1];
        $this->assertFalse($user1->isAdmin());
        $this->assertFalse($user2->isAdmin());
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->actingAs($user1)->get("/api/v1/users/{$user2->id}");
        TestsHelper::dumpApiResponsesWithErrors($response, Response::HTTP_FORBIDDEN);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $response->assertJson([
            'message' => 'This action is unauthorized.',
        ]);
    }

    public function testStoreSuccess(): void
    {
        $password = $this->faker->password(8);
        $data = [
            'name' => $this->faker->name,
            'email' => $this->faker->email,
            'password' => $password,
            'password_confirmation' => $password,
        ];
        $this->assertEquals(User::count(), 1, 'only admin user');
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->actingAs($this->admin)->post('/api/v1/users', $data);
        TestsHelper::dumpApiResponsesWithErrors($response, Response::HTTP_CREATED);
        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonStructure([
            'status',
            'data',
        ]);
        $this->assertEquals(User::count(), 2, 'created new user');
        $user = User::whereNot('email', $this->admin->email)->firstOrFail();
        $this->assertTrue($this->admin->isAdmin());
        $this->assertFalse($user->isAdmin());
        $response->assertJson([
            'status' => 'success',
            'data' => (new UserResource($user))->toArray(new Request()),
        ]);
        unset($data['password_confirmation']);
        foreach ($data as $key => $value) {
            if ($key === 'password') {
                $this->assertTrue(Hash::check($value, $user->$key));
            } else {
                $this->assertEquals($user->$key, $value, "key {$key}");
            }
        }
    }

    public function testUpdateSuccess(): void
    {
        $user = User::factory()->create();
        $password = $this->faker->password(8);
        $data = [
            'name' => $this->faker->name,
            'email' => $this->faker->email,
            'password' => $password,
            'password_confirmation' => $password,
        ];
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->actingAs($this->admin)->put("/api/v1/users/{$user->id}", $data);
        TestsHelper::dumpApiResponsesWithErrors($response, Response::HTTP_OK);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'status',
            'data',
        ]);
        $user->refresh();
        $this->assertFalse($user->isAdmin());
        $response->assertJson([
            'status' => 'success',
            'data' => (new UserResource($user))->toArray(new Request()),
        ]);
        unset($data['password_confirmation']);
        foreach ($data as $key => $value) {
            if ($key === 'password') {
                $this->assertTrue(Hash::check($value, $user->$key));
            } else {
                $this->assertEquals($user->$key, $value, "key {$key}");
            }
        }

        // UPDATE: SAME NAME / EMAIL
        $password = $this->faker->password(8);
        $data = [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $password,
            'password_confirmation' => $password,
        ];
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->actingAs($this->admin)->put("/api/v1/users/{$user->id}", $data);
        TestsHelper::dumpApiResponsesWithErrors($response, Response::HTTP_OK);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'status',
            'data',
        ]);
        $user->refresh();
        $this->assertFalse($user->isAdmin());
        $response->assertJson([
            'status' => 'success',
            'data' => (new UserResource($user))->toArray(new Request()),
        ]);
        unset($data['password_confirmation']);
        foreach ($data as $key => $value) {
            if ($key === 'password') {
                $this->assertTrue(Hash::check($value, $user->$key));
            } else {
                $this->assertEquals($user->$key, $value, "key {$key}");
            }
        }
    }

    public function testDeleteSuccess(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $this->assertEquals(User::count(), 2, '2 users');
        $this->assertEquals(User::onlyTrashed()->count(), 0, 'no deleted users');
        $response = $this->actingAs($this->admin)->delete("/api/v1/users/{$user->id}");
        TestsHelper::dumpApiResponsesWithErrors($response, Response::HTTP_OK);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(['status' => 'success', 'message' => 'User deleted successfully.']);
        $deletedUser = User::where('id', $user->id)->onlyTrashed()->firstOrFail();
        $this->assertNotNull($deletedUser);
        $this->assertEquals(User::count(), 1, '1 user deleted');
        $this->assertEquals(User::onlyTrashed()->count(), 1, 'deleted users ' . User::onlyTrashed()->count());
    }
}
