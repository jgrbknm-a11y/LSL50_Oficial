<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreTeamRegistrationRequest;
use App\Models\TeamRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use App\Events\TeamRegistrationApproved;

class TeamRegistrationController extends Controller
{
    // POST /api/team-registrations
    public function store(StoreTeamRegistrationRequest $request)
    {
        $reg = TeamRegistration::create($request->validated());
        return response()->json(['status'=>'ok','data'=>$reg], 201);
    }

    // GET /api/team-registrations (admin listing)
    public function index(Request $request)
    {
        $status = $request->query('status');
        $q = TeamRegistration::query();
        if ($status) $q->where('status', $status);
        return response()->json(['status'=>'ok','data'=>$q->orderBy('id','desc')->paginate(20)]);
    }

    // POST /api/team-registrations/{id}/approve
    public function approve(int $id, Request $request)
    {
        $reg = TeamRegistration::findOrFail($id);
        if ($reg->status !== 'pending') {
            return response()->json(['status'=>'error','message'=>'Registration not pending'], 422);
        }
        $reg->status = 'approved';
        $reg->approved_at = now();
        $reg->approved_by = optional($request->user())->id;
        $reg->save();

        event(new TeamRegistrationApproved($reg->id));

        return response()->json(['status'=>'ok','message'=>'Registration approved','data'=>$reg]);
    }

    // POST /api/team-registrations/{id}/reject
    public function reject(int $id, Request $request)
    {
        $reg = TeamRegistration::findOrFail($id);
        $reg->status = 'rejected';
        $reg->save();
        return response()->json(['status'=>'ok','message'=>'Registration rejected','data'=>$reg]);
    }
}
