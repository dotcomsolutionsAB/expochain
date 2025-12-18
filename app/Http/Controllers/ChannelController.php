<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\ChannelModel;

class ChannelController extends Controller
{
    //
    public function add(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:t_channels,name',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        // Create Channel
        $channel = ChannelModel::create(['name' => $request->name]);

        return response()->json([
            'code' => 201,
            'success' => true,
            'message' => 'Channel created successfully!',
            'data' => $channel->makeHidden(['created_at', 'updated_at'])
        ], 201);
    }

    public function retrieve(Request $request, $id = null)
    {
        // If an id is provided, fetch the channel by id
        if ($id) {
            $channel = ChannelModel::find($id);

            // Check if the channel exists
            if (!$channel) {
                return response()->json([
                    'code'    => 200,
                    'success' => true,
                    'message' => 'Channel not found!',
                    'data'    => [],
                    'count'   => 0,
                    'total_records' => 0,
                ], 200);
            }

            // Return the channel data in the required format
            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'Channel fetched successfully!',
                'data'    => [$channel->makeHidden(['created_at', 'updated_at'])],
                'count'   => 1,
                'total_records' => 1,
            ], 200);
        }

        // If no id is provided, fetch all channels
        $channels = ChannelModel::all();

        // If no channels are found
        if ($channels->isEmpty()) {
            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'No Channels available',
                'data'    => [],
                'count'   => 0,
                'total_records' => 0,
            ], 200);
        }

        // Return all channels data in the required format
        return response()->json([
            'code'    => 200,
            'success' => true,
            'message' => 'Channels fetched successfully!',
            'data'    => $channels->makeHidden(['created_at', 'updated_at']),
            'count'   => $channels->count(),
            'total_records' => $channels->count(),
        ], 200);
    }

    public function update(Request $request, $id)
    {
        // Find the channel
        $channel = ChannelModel::find($id);

        if (!$channel) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'Channel not found!'
            ], 404);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:t_channel,name,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        // Update Channel
        $channel->update(['name' => $request->name]);

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Channel updated successfully!',
            'data' => $channel
        ], 200);
    }

    public function destroy($id)
    {
        $channel = ChannelModel::find($id);

        if (!$channel) {
            return response()->json([
                'code' => 404,
                'success' => false,
                'message' => 'Channel not found!'
            ], 404);
        }

        $channel->delete();

        return response()->json([
            'code' => 200,
            'success' => true,
            'message' => 'Channel deleted successfully!'
        ], 200);
    }
}
