<?php

namespace App\Api\V1\Controllers;

use Illuminate\Http\Request;
use JWTAuth;
use Dingo\Api\Routing\Helpers;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Dingo\Api\Exception\ValidationHttpException;
use App\Models\Message;
use App\Models\Message_Attachment;
use App\Models\Chat;
use App\Models\Chat_User;

class ChatController extends Controller
{
    use Helpers;


    public function index()
	{
		$currentUser = JWTAuth::parseToken()->authenticate()
	        ->chats()
	        ->get()
	        ->toArray();

        $chats = [];

        foreach ($currentUser as $key => $user) {
            array_push($chats, $user['chat']);
        }

	    return response()->json($chats);

	}

	public function show($id)
	{

    $currentUser = JWTAuth::parseToken()->authenticate();

		$response = Chat::where('id', $id)->with('messages')->first();

		if(!$response){
            $error = [
                'message' => 'Chat can\'t be found'
            ];

            return response()->json($error);
        }

		return response()->json($response);


	}


	public function create(Request $request)
	{

        $currentUser = JWTAuth::parseToken()->authenticate();

        if(!$request->has('users')){
            return $this->response->error('Users are missing', 500);
        }


        // if(!$request->has('message')){
        //     return $this->response->error('Message is missing', 500);
        // }

        // Create chat
	    $chat = Chat::create([
	    	'title'	=>	(!empty($request->get('chat')['title'])? $request->get('chat')['title'] : ''),
	    	'fk_owner_id' => $currentUser->id
	    ]);

        // Message
        if($request->has('message')){

        	$message = Message::create([
        		'fk_chat_id'	=>	$chat->id,
        		'fk_user_id'	=>	$currentUser->id,
        		'msg_text'		=>	$request->get('message')['msg_text'] // $request->message->text
        	]);

            $chat->fk_last_entry = $message->id;
            $chat->save();

        	if(isset($request->get('message')['attachments']) AND  $request->get('message')['attachments'] !== null){
        		foreach ($request->get('message')['attachments'] as $attachment) {
                    Message_Attachment::create([
        				'url' => $attachment['url'],
        				'fk_user_id' => $currentUser->id,
        				'fk_message_id' => $message->id
        			]);
        		}
        	}

        };

        $usersInChat = [];

        // Create chat users
        $newUser = Chat_User::create([
            'fk_chat_id' => $chat->id,
            'fk_user_id' => $currentUser->id,
            'fk_last_message_seen' => $message->id
        ]);

        foreach ($request->get('users') as $friend) {
            Chat_User::create([
                'fk_chat_id' => $chat->id,
                'fk_user_id' => $friend['id']
            ]);
            array_push($usersInChat, \App\User::where('id', $friend['id'])->select(['id', 'fname', 'lname'])->first() );
        }

        $response = [
        	'id'	=>	$chat->id,
          'title'   =>  $chat->title,
          'users'   =>  $usersInChat
        ];

        return response()->json($response);

	}

    public function sendMessage(Request $request, $chatId){

        $currentUser = JWTAuth::parseToken()->authenticate();

        if(!$request->has('message')){
            $error = [
                'message' => 'Message must be added'
            ];
            return response()->json($error);
        }

        $chat = Chat::findOrFail($chatId);

        $message = Message::create([
            'fk_chat_id'    =>  $chat->id,
            'fk_user_id'    =>  $currentUser->id,
            'msg_text'      =>  $request->get('message')['msg_text'] // $request->message->text
        ]);

        $chat->fk_last_entry = $message->id;
        $chat->save();

        if(isset($request->get('message')['attachments']) AND  $request->get('message')['attachments'] !== null){
            foreach ($request->get('message')['attachments'] as $attachment) {
                Message_Attachment::create([
                    'url' => $attachment['url'],
                    'fk_user_id' => $currentUser->id,
                    'fk_message_id' => $message->id
                ]);
            }
        }

        // Trigger
        Chat_User::where(['fk_chat_id' => $chatId, 'fk_user_id' => $currentUser->id])->update(['fk_last_message_seen' => $message->id]);

        return response()->json($message);
    }

    public function getUnreadMessages(Request $request, $chatId, $lastMessageSeen){
        // $currentUser = JWTAuth::parseToken()->authenticate();

        $chat = Chat::where('id', $chatId)->with('messages')->first();

        if(!$chat){
            return response()->json([
                'message'   =>  'Chat can\'t be found'
            ]);
        }

        if(!$lastMessageSeen){
            $lastMessageSeen = 0;
        }

        $response = Message::where('id', '>', $lastMessageSeen)->where('fk_chat_id', $chatId)->get();
        return response()->json($response);
    }

}
