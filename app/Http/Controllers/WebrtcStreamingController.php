<?php

namespace App\Http\Controllers;

use App\Events\StreamAnswer;
use App\Events\StreamOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class WebrtcStreamingController extends Controller
{

    public function index()
    {
        //  The view for the broadcaster.
        return view('video-broadcast', ['type' => 'broadcaster', 'id' => Auth::id()]);
    }

    public function consumer(Request $request, $streamId)
    {
        // The view for the consumer(viewer). They join with a link that bears the streamId
        // initiated by a specific broadcaster.
        return view('video-broadcast', ['type' => 'consumer', 'streamId' => $streamId, 'id' => Auth::id()]);
    }

    public function makeStreamOffer(Request $request)
    {
        // Broadcasts an offer signal sent by broadcaster to a specific user who just joined
        $data['broadcaster'] = $request->broadcaster;
        $data['receiver'] = $request->receiver;
        $data['signalId'] = $request->signalId;
        event(new StreamOffer($data));
    }

    public function makeStreamAnswer(Request $request)
    {
        // Sends an answer signal to broadcaster to fully establish the peer connection.
        $data['broadcaster'] = $request->broadcaster;
        $data['answer'] = $request->answer;
        event(new StreamAnswer($data));
    }

    public function storeSignalData(Request $request)
    {
        $signalData = $request->offer;
        $signalId = 1111;
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


}
