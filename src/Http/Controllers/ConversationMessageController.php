<?php

namespace Musonza\Chat\Http\Controllers;

use Chat;
use Musonza\Chat\Http\Requests\ClearConversation;
use Musonza\Chat\Http\Requests\DeleteMessage;
use Musonza\Chat\Http\Requests\GetParticipantMessages;
use Musonza\Chat\Http\Requests\StoreMessage;

class ConversationMessageController extends Controller
{
    protected $messageTransformer;

    public function __construct()
    {
        $this->setUp();
    }

    private function setUp()
    {
        if ($messageTransformer = config('musonza_chat.transformers.message')) {
            $this->messageTransformer = app($messageTransformer);
        }
    }

    private function itemResponse($message)
    {
        if ($this->messageTransformer) {
            return fractal($message, $this->messageTransformer)->respond();
        }

        return response($message);
    }

    public function index(GetParticipantMessages $request, $conversationId)
    {
        $conversation = Chat::conversations()->getById($conversationId);
        $message = Chat::conversation($conversation)
            ->setParticipant($request->getParticipant())
            ->setParticipant($request->user())
            ->setPaginationParams($request->getPaginationParams())
            ->getMessages();

        if ($message) {
            Chat::conversation($conversation)->setParticipant($request->user())->readAll();
        }

        if ($this->messageTransformer) {
            return fractal($message, $this->messageTransformer)->respond();
        }

        return response($message);
    }

    public function show(GetParticipantMessages $request, $conversationId, $messageId)
    {
        $message = Chat::messages()->getById($messageId);

        return $this->itemResponse($message);
    }

    public function store(StoreMessage $request, $conversationId)
    {
        if ($request->participant_id !== $request->user()->id) {
            abort(403, 'Access denied');
        }

        $conversation = Chat::conversations()->getById($conversationId);
        $message = Chat::message($request->getMessageBody())
            ->from($request->getParticipant())
            ->to($conversation)
            ->send();

        return $this->itemResponse($message);
    }

    public function deleteAll(ClearConversation $request, $conversationId)
    {
        $conversation = Chat::conversations()->getById($conversationId);
        Chat::conversation($conversation)
            ->setParticipant($request->getParticipant())
            ->clear();

        return response('');
    }

    public function destroy(DeleteMessage $request, $conversationId, $messageId)
    {
        $message = Chat::messages()->getById($messageId);
        Chat::message($message)
            ->setParticipant($request->getParticipant())
            ->delete();

        return response('');
    }
}
