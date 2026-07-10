<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TeamApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_lists_teams()
    {
        Team::factory()->create(['team_name' => 'Caribeños', 'slug' => 'caribenos']);
        $response = $this->getJson('/api/teams');
        $response->assertStatus(200)->assertJsonStructure(['data','status']);
    }

    /** @test */
    public function it_creates_team()
    {
        $payload = ['team_name' => 'Leones del Caribe', 'slug' => 'leones-del-caribe'];
        $response = $this->postJson('/api/teams', $payload);
        $response->assertStatus(201)->assertJsonPath('data.slug', 'leones-del-caribe');
    }

    /** @test */
    public function it_updates_team()
    {
        Team::factory()->create(['team_name' => 'Tmp', 'slug' => 'tmp']);
        $response = $this->putJson('/api/teams/tmp', ['team_name' => 'Nuevo Nombre']);
        $response->assertOk()->assertJsonPath('data.team_name', 'Nuevo Nombre');
    }

    /** @test */
    public function it_deletes_team()
    {
        Team::factory()->create(['team_name' => 'ToDelete', 'slug' => 'to-delete']);
        $response = $this->deleteJson('/api/teams/to-delete');
        $response->assertOk()->assertJson(['status' => 'deleted']);
    }
}
