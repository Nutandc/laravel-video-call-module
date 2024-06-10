<?php

namespace App\Http\Controllers;

use App\Events\StartVideoChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class VideoChatController extends Controller
{

    public function callUser(Request $request)
    {
        $data['userToCall'] = $request->user_to_call;
        $data['signalData'] = $request->signal_data;
        $data['from'] = Auth::id();
        $data['type'] = 'incomingCall';

        if ($data['signalData'] !== null) {
            broadcast(new StartVideoChat($data))->toOthers();
            return response()->json(['success' => 'Call initiated successfully']);
        } else {
            return response()->json(['error' => 'Signal data is null'], 400);
        }
    }

    public function notify(Request $request)
    {
        $data['userToCall'] = $request->user_to_call;
        $data['signalUrl'] = $request->signal_url;
        $data['signalData'] = $request->signal_data;
        $data['from'] = Auth::id();
        $data['type'] = 'incomingCall';

        if ($data['signalData'] !== null) {
            broadcast(new StartVideoChat($data))->toOthers();
            return response()->json(['success' => 'Notification sent successfully']);
        } else {
            return response()->json(['error' => 'Signal data is null'], 400);
        }
    }


    public function acceptCall(Request $request)
    {
        $data['signal'] = $request->signal;
        $data['to'] = $request->to;
        $data['type'] = 'callAccepted';
        broadcast(new StartVideoChat($data))->toOthers();
    }

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

    public function uploadSignal(Request $request)
    {
        $signalData = $request->signal_data;
        // Save signal data to storage and generate a URL
        // For simplicity, we use the file storage
        $fileName = 'signal_' . time() . '.json';
        Storage::put('signals/' . $fileName, json_encode($signalData));

        $url = Storage::url('signals/' . $fileName);

        return response()->json(['url' => $url]);
    }

//    public function notify(Request $request)
//    {
//        $data['userToCall'] = $request->user_to_call;
//        $data['signalUrl'] = $request->signal_url;
//        $data['signalData'] = $request->signal_data;
//
//        $data['from'] = Auth::id();
//        $data['type'] = 'incomingCall';
//        if ($data['signalData'] !== null) {
//            broadcast(new StartVideoChat($data))->toOthers();
//        } else {
//            // Handle the case where signal data is null
//            return response()->json(['error' => 'Signal data is null'], 400);
//        }
//    }
}
