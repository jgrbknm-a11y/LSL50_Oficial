<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Http\Requests\Team\StoreTeamRequest;
use App\Http\Requests\Team\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    // GET /api/teams?q=...
    public function index(Request $request)
    {
        $q = $request->query('q');
        $query = Team::query();

        if ($q) {
            $query->where(function ($x) use ($q) {
                $x->where('team_name', 'like', "%{$q}%")
                  ->orWhere('team_name_short', 'like', "%{$q}%")
                  ->orWhere('team_abbr', 'like', "%{$q}%")
                  ->orWhere('slug', 'like', "%{$q}%")
                  ->orWhere('home_city', 'like', "%{$q}%");
            });
        }

        $teams = $query->orderBy('team_name')->paginate(20);

        return TeamResource::collection($teams)->additional([
            'status' => 'ok'
        ]);
    }

    // GET /api/teams/{slug}
    public function show(string $slug)
    {
        $team = Team::where('slug', $slug)->firstOrFail();
        return (new TeamResource($team))->additional(['status' => 'ok']);
    }

    // POST /api/teams
    public function store(StoreTeamRequest $request)
    {
        $data = $request->validated();

        // Generate slug if missing
        if (empty($data['slug']) && !empty($data['team_name'])) {
            $data['slug'] = Str::slug($data['team_name']);
        }

        $team = Team::create($data);

        return (new TeamResource($team))
            ->additional(['status' => 'created'])
            ->response()
            ->setStatusCode(201);
    }

    // PUT/PATCH /api/teams/{slug}
    public function update(UpdateTeamRequest $request, string $slug)
    {
        $team = Team::where('slug', $slug)->firstOrFail();
        $data = $request->validated();

        // if slug is changing, ensure uniqueness
        if (isset($data['slug']) && $data['slug'] !== $team->slug) {
            $exists = Team::where('slug', $data['slug'])->exists();
            if ($exists) {
                return response()->json(['status' => 'error', 'message' => 'Slug already in use'], 422);
            }
        }

        $team->fill($data);
        $team->save();

        return (new TeamResource($team))->additional(['status' => 'updated']);
    }

    // DELETE /api/teams/{slug}
    public function destroy(string $slug)
    {
        $team = Team::where('slug', $slug)->firstOrFail();
        $team->delete();

        return response()->json(['status' => 'deleted']);
    }
}
