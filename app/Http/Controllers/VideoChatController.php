<?php

namespace App\Http\Controllers;

use App\Events\StartVideoChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class VideoChatController extends Controller
{
    public function storeSignalData(Request $request)
    {
        $signalData = $request->signal_data;
        $signalId = uniqid();
        Cache::put($signalId, $signalData, now()->addMinutes(10)); // Store signal data for 10 minutes
        return response()->json(['signal_id' => $signalId]);
    }

    public function getSignalData($id)
    {
        $signalData = Cache::get($id);
        if ($signalData) {
            return response()->json($signalData);
        }
        return response()->json(['error' => 'Signal data not found'], 404);
    }

    public function callUser(Request $request)
    {
        $data['userToCall'] = $request->user_to_call;
        $data['signalId'] = $request->signal_id; // Use signal ID
        $data['from'] = Auth::id();
        $data['type'] = 'incomingCall';
        broadcast(new StartVideoChat($data))->toOthers();
    }

    public function acceptCall(Request $request)
    {
        $data['signal'] = $request->signal;
        $data['to'] = $request->to;
        $data['type'] = 'callAccepted';
        broadcast(new StartVideoChat($data))->toOthers();
    }
}
