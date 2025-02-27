<?php


namespace App\Socket\Listeners\Chat\PrivateChat;


use App\Core\Database\Connection;
use App\Core\Socket\ConnectionInterface;
use App\Core\Socket\Request;
use App\Socket\UserStorage;
use App\Socket\Listeners\Listener;
use App\Socket\UserPresence;
use Clue\React\SQLite\Result;
use Throwable;

class ChatListener extends Listener
{
    /**
     * @var ConnectionInterface[]
     */
    public static array $users = [];

    private int $typingStatusTimeout = 2000;


    public function iamOnline(Request $request)
    {
        //Add to online list
        UserStorage::add($request->auth()->userId(), $request->client());

        //Let his trackers know he's online
        UserPresence::iamOnline($request->auth()->userId());
    }

    public function monitorUsersPresence(Request $request)
    {
        $userId = $request->auth()->userId();
        $message = $request->payload()->message;
        $users = $message->users ?? [];

        foreach ($users as $userTrackingData) {
            if (isset($userTrackingData->user_id)){
                UserPresence::track(
                    $userId, $userTrackingData->user_id,
                    function ($trackedUserId, $trackedUserPresence) use ($request) {
                        $command = 'chat.private.offline';
                        if ('online' == $trackedUserPresence) {
                            $command = 'chat.private.online';
                        }

                        resp($request->client())->send($command, [
                            'user_id' => $trackedUserId
                        ]);
                    }
                );
            }
        }
    }

    public function send(Request $request)
    {
        $userId = $request->auth()->userId();
        $payload = $request->payload();
        $receiverId = $payload->receiver_id;

        if (empty(trim($payload->message))){
            return true;
        }

        $plainSql = 'SELECT conversers FROM messages WHERE (sender_id = ? AND receiver_id =?) OR (sender_id = ? AND receiver_id = ?)';
        return Connection::get()->query($plainSql, [$userId, $receiverId, $receiverId, $userId])
            ->then(function (Result $result) use ($userId, $payload, $request,$receiverId){
                if (!empty($result->rows)) {
                    $conversers = $result->rows[0]['conversers'];
                } else {
                    $conversers = "{$userId} {$payload->receiver_id}";
                }

                //Send Message
                $sql = "INSERT INTO messages(sender_id, receiver_id, message, conversers, time) VALUES (?, ?, ?, ?, ?)";
                $userId = $request->auth()->userId();
                return Connection::get()->query($sql, [$userId, $payload->receiver_id, $payload->message, $conversers, time()])
                    ->then(function (Result $result) use ($payload, $request) {
                        if (UserStorage::exists($payload->receiver_id)) {
                            $client = UserStorage::get($payload->receiver_id);
                            resp($client)->send('chat.private.send', [
                                'id' => $result->insertId,
                                'client_id' => $client->getConnectionId(),
                                'sender_id' => $request->auth()->userId(),
                                'time' => time(),
                                'message' => $payload->message,
                            ]);
                        }
                    })->otherwise(function (Throwable $throwable) use ($request) {
                        resp($request->client())->send('chat.private.error', $throwable);
                    });
            });
    }

    public function typing(Request $request)
    {
        $userId = $request->auth()->userId();
        $payload = $request->payload();
        $receiverId = $payload->receiver_id;

        if (UserStorage::exists($receiverId)) {

            $client = UserStorage::get($receiverId);

            $data = [
                'client_id' => $client->getConnectionId(),
                'sender_id' => $userId,
                'status' => 'typing',
                'timeout' => $this->typingStatusTimeout,
            ];

            //Let's see if user is typing or stopped typing
            if ($request->payload()->status !== 'typing') {
                $data['status'] = 'stopped';
            }

            resp($client)->send('chat.private.typing', $data);
        }
    }
}